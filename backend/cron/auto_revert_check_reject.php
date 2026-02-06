#!/usr/bin/php
<?php
/**
 * Auto Revert check_reject Bookings Cron Script
 *
 * Automatically reverts bookings stuck in check_reject status for more than 3 days.
 * Restores original booking status and pre-deducted values if applicable.
 *
 * Crontab entry (run daily at 2:00 AM):
 * 0 2 * * * /usr/bin/php /var/www/html/backend/cron/auto_revert_check_reject.php >> /var/log/auto_revert_check_reject.log 2>&1
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log start time
$startTime = microtime(true);
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

echo $logPrefix . "Auto revert check_reject cron job started\n";

try {
    // Load database connection
    require_once __DIR__ . '/../conn.php';

    // 3일 이상 경과한 check_reject 예약 조회
    $sql = "SELECT b.bookingId, b.adults, b.children, b.infants,
                   bcr.id as changeRequestId, bcr.changeType, bcr.originalStatus, bcr.originalPaymentStatus,
                   bcr.previousData, bcr.newData, bcr.processedAt
            FROM bookings b
            INNER JOIN booking_change_requests bcr ON b.bookingId = bcr.bookingId
            WHERE b.bookingStatus = 'check_reject'
              AND bcr.status = 'rejected'
              AND bcr.processedAt <= NOW() - INTERVAL 3 DAY
            ORDER BY bcr.processedAt ASC";

    $result = $conn->query($sql);

    if (!$result) {
        echo $logPrefix . "ERROR: Query failed: " . $conn->error . "\n";
        exit(1);
    }

    $processed = 0;
    $reverted = 0;
    $failed = 0;
    $skipped = 0;

    while ($row = $result->fetch_assoc()) {
        $processed++;
        $bookingId = $row['bookingId'];
        $changeRequestId = (int)$row['changeRequestId'];

        echo $logPrefix . "Processing bookingId={$bookingId}, changeRequestId={$changeRequestId}\n";

        // 트랜잭션 내에서 복원
        $conn->begin_transaction();
        try {
            // 현재 bookingStatus가 아직 check_reject인지 확인 (동시 처리 방지)
            $checkStmt = $conn->prepare("SELECT bookingStatus FROM bookings WHERE bookingId = ? FOR UPDATE");
            $checkStmt->bind_param('s', $bookingId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$checkResult || $checkResult['bookingStatus'] !== 'check_reject') {
                $conn->rollback();
                echo $logPrefix . "  SKIP: bookingId={$bookingId} is no longer check_reject (current: " . ($checkResult['bookingStatus'] ?? 'null') . ")\n";
                $skipped++;
                continue;
            }

            // originalStatus 복원 (pending_update/check_reject 오염 방지)
            $originalStatus = $row['originalStatus'] ?? 'confirmed';
            if (in_array($originalStatus, ['pending_update', 'check_reject'])) {
                // booking_change_requests에서 유효한 원본 상태 조회
                $osStmt = $conn->prepare("SELECT originalStatus FROM booking_change_requests WHERE bookingId = ? AND originalStatus IS NOT NULL AND originalStatus NOT IN ('pending_update', 'check_reject') ORDER BY requestedAt DESC LIMIT 1");
                if ($osStmt) {
                    $osStmt->bind_param('s', $bookingId);
                    $osStmt->execute();
                    $osRow = $osStmt->get_result()->fetch_assoc();
                    $osStmt->close();
                    if ($osRow && !empty($osRow['originalStatus'])) {
                        $originalStatus = $osRow['originalStatus'];
                    } else {
                        $originalStatus = 'confirmed';
                    }
                }
            }

            $originalPaymentStatus = $row['originalPaymentStatus'] ?? null;
            $newData = json_decode($row['newData'] ?? '{}', true);
            $previousData = json_decode($row['previousData'] ?? '{}', true);
            $preDeducted = (bool)($newData['preDeducted'] ?? false);
            $changeType = $row['changeType'] ?? '';

            // 선차감 복원: preDeducted=true였으면 원본 컬럼값으로 복원
            if ($preDeducted && $previousData) {
                $origAdults = (int)($previousData['originalAdults'] ?? 0);
                $origChildren = (int)($previousData['originalChildren'] ?? 0);
                $origInfants = (int)($previousData['originalInfants'] ?? 0);
                $origTotalAmount = (float)($previousData['originalTotalAmount'] ?? 0);
                $origVisaFee = (float)($previousData['originalVisaFee'] ?? 0);
                $origFlightOptionFee = (float)($previousData['originalFlightOptionFee'] ?? 0);

                if ($originalPaymentStatus !== null) {
                    $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, adults = ?, children = ?, infants = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, updatedAt = NOW() WHERE bookingId = ?");
                    $updStmt->bind_param('ssiiiddds', $originalStatus, $originalPaymentStatus, $origAdults, $origChildren, $origInfants, $origTotalAmount, $origVisaFee, $origFlightOptionFee, $bookingId);
                } else {
                    $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = ?, adults = ?, children = ?, infants = ?, totalAmount = ?, visaFee = ?, flightOptionFee = ?, updatedAt = NOW() WHERE bookingId = ?");
                    $updStmt->bind_param('siiiddds', $originalStatus, $origAdults, $origChildren, $origInfants, $origTotalAmount, $origVisaFee, $origFlightOptionFee, $bookingId);
                }
                $updStmt->execute();
                $updStmt->close();
                echo $logPrefix . "  Restored pre-deducted values for bookingId={$bookingId}\n";
            } else {
                // 선차감 없음: 상태만 복원
                if ($originalPaymentStatus !== null) {
                    $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = ?, paymentStatus = ?, updatedAt = NOW() WHERE bookingId = ?");
                    $updStmt->bind_param('sss', $originalStatus, $originalPaymentStatus, $bookingId);
                } else {
                    $updStmt = $conn->prepare("UPDATE bookings SET bookingStatus = ?, updatedAt = NOW() WHERE bookingId = ?");
                    $updStmt->bind_param('ss', $originalStatus, $bookingId);
                }
                $updStmt->execute();
                $updStmt->close();
            }

            // travelers/travelers_add_remove 타입이면 원본 traveler 데이터 복원
            if (in_array($changeType, ['travelers', 'travelers_add_remove']) && !empty($previousData['originalTravelers'])) {
                $originalTravelers = $previousData['originalTravelers'];

                // transactNo 조회
                $tkStmt = $conn->prepare("SELECT COALESCE(NULLIF(transactNo,''), bookingId) as tk FROM bookings WHERE bookingId = ? LIMIT 1");
                $tkStmt->bind_param('s', $bookingId);
                $tkStmt->execute();
                $tkRow = $tkStmt->get_result()->fetch_assoc();
                $tkStmt->close();
                $travelerKey = $tkRow['tk'] ?? $bookingId;

                // 기존 travelers 삭제
                $delStmt = $conn->prepare("DELETE FROM booking_travelers WHERE transactNo = ?");
                $delStmt->bind_param('s', $travelerKey);
                $delStmt->execute();
                $delStmt->close();

                // 원본 travelers 복원
                foreach ($originalTravelers as $tr) {
                    $travelerType = $tr['travelerType'] ?? 'adult';
                    $title = $tr['title'] ?? null;
                    $firstName = $tr['firstName'] ?? '';
                    $lastName = $tr['lastName'] ?? '';
                    $birthDate = $tr['birthDate'] ?? null;
                    $gender = $tr['gender'] ?? null;
                    $nationality = $tr['nationality'] ?? '';
                    $passportNumber = $tr['passportNumber'] ?? '';
                    $passportIssueDate = $tr['passportIssueDate'] ?? null;
                    $passportExpiry = $tr['passportExpiry'] ?? null;
                    $passportImage = $tr['passportImage'] ?? null;
                    $visaDocument = $tr['visaDocument'] ?? null;
                    $visaStatus = $tr['visaStatus'] ?? 'not_required';
                    $visaType = $tr['visaType'] ?? null;
                    $specialRequests = $tr['specialRequests'] ?? null;
                    $isMainTraveler = (int)($tr['isMainTraveler'] ?? 0);
                    $reservationStatus = $tr['reservationStatus'] ?? null;
                    $childRoom = (int)($tr['childRoom'] ?? 0);
                    $profileSource = $tr['profile_source'] ?? $tr['profileSource'] ?? '';

                    $birthDateVal = (!empty($birthDate) && $birthDate !== '0000-00-00') ? $birthDate : null;
                    $passportIssueDateVal = (!empty($passportIssueDate) && $passportIssueDate !== '0000-00-00') ? $passportIssueDate : null;
                    $passportExpiryVal = (!empty($passportExpiry) && $passportExpiry !== '0000-00-00') ? $passportExpiry : null;

                    $insertSql = "INSERT INTO booking_travelers (transactNo, travelerType, title, firstName, lastName, birthDate, gender, nationality, passportNumber, passportIssueDate, passportExpiry, passportImage, visaDocument, visaStatus, visaType, specialRequests, isMainTraveler, reservationStatus, childRoom, profile_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if ($insertStmt) {
                        $insertStmt->bind_param('ssssssssssssssssssss',
                            $travelerKey, $travelerType, $title, $firstName, $lastName,
                            $birthDateVal, $gender, $nationality, $passportNumber,
                            $passportIssueDateVal, $passportExpiryVal, $passportImage,
                            $visaDocument, $visaStatus, $visaType, $specialRequests,
                            $isMainTraveler, $reservationStatus, $childRoom, $profileSource
                        );
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                }
                echo $logPrefix . "  Restored " . count($originalTravelers) . " travelers for bookingId={$bookingId}\n";
            }

            // booking_change_requests에 auto-acknowledged 마킹
            $ackStmt = $conn->prepare("UPDATE booking_change_requests SET status = 'auto_acknowledged', processedAt = NOW() WHERE id = ?");
            $ackStmt->bind_param('i', $changeRequestId);
            $ackStmt->execute();
            $ackStmt->close();

            // 상태 변경 히스토리 로깅
            $historyStmt = $conn->prepare("INSERT INTO booking_status_history (bookingId, fromStatus, toStatus, changedBy, reason, createdAt) VALUES (?, 'check_reject', ?, 'system_cron', 'Auto-reverted after 3 days without acknowledgement', NOW())");
            if ($historyStmt) {
                $historyStmt->bind_param('ss', $bookingId, $originalStatus);
                $historyStmt->execute();
                $historyStmt->close();
            }

            $conn->commit();
            $reverted++;
            echo $logPrefix . "  SUCCESS: Reverted bookingId={$bookingId} to status={$originalStatus}\n";

        } catch (Throwable $e) {
            $conn->rollback();
            $failed++;
            echo $logPrefix . "  ERROR: Failed to revert bookingId={$bookingId}: " . $e->getMessage() . "\n";
        }
    }

    // Log results
    $duration = round(microtime(true) - $startTime, 2);
    echo $logPrefix . "Completed in {$duration}s\n";
    echo $logPrefix . "Stats: Processed={$processed}, Reverted={$reverted}, Failed={$failed}, Skipped={$skipped}\n";

    // Close database connection
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

} catch (Exception $e) {
    echo $logPrefix . "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo $logPrefix . "Auto revert check_reject cron job finished\n";
exit(0);
