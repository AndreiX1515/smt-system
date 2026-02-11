<?php
/**
 * 예약 관련 공통 비즈니스 로직
 */

/**
 * 패키지+날짜 조합의 잔여 좌석을 확인하고 요청 인원 수용 가능 여부를 반환한다.
 *
 * @param mysqli $db               DB 커넥션
 * @param int    $packageId        패키지 ID
 * @param string $departureDate    출발일 (YYYY-MM-DD)
 * @param int    $requestedPax     요청 인원 (adults+children+infantsWithSeat)
 * @param string|null $excludeBookingId  제외할 bookingId (UPDATE 시 자기 자신)
 * @return array {ok:bool, remainingSeats:int, maxParticipants:int, bookedSeats:int, message:string}
 */
if (!function_exists('check_capacity')) {
    function check_capacity(mysqli $db, int $packageId, string $departureDate, int $requestedPax, ?string $excludeBookingId = null): array {
        // 1) packages.maxParticipants 조회
        $st = $db->prepare("SELECT maxParticipants FROM packages WHERE packageId = ? LIMIT 1");
        if (!$st) {
            return ['ok' => false, 'remainingSeats' => 0, 'maxParticipants' => 0, 'bookedSeats' => 0, 'message' => 'DB error'];
        }
        $st->bind_param('i', $packageId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();

        if (!$row) {
            return ['ok' => false, 'remainingSeats' => 0, 'maxParticipants' => 0, 'bookedSeats' => 0, 'message' => 'Package not found'];
        }

        $packageMaxParticipants = (int)($row['maxParticipants'] ?? 0);
        $maxParticipants = $packageMaxParticipants;

        // 2) package_available_dates.capacity (per-date override)
        // dev_tasks #115: packages.maxParticipants=0 이면 per-date capacity 무시 (0 유지)
        if ($packageMaxParticipants > 0) {
            try {
                $tbl = $db->query("SHOW TABLES LIKE 'package_available_dates'");
                if ($tbl && $tbl->num_rows > 0) {
                    $st2 = $db->prepare("SELECT capacity FROM package_available_dates WHERE package_id = ? AND available_date = ? LIMIT 1");
                    if ($st2) {
                        $st2->bind_param('is', $packageId, $departureDate);
                        $st2->execute();
                        $rs2 = $st2->get_result();
                        $r2 = $rs2 ? $rs2->fetch_assoc() : null;
                        $st2->close();
                        if ($r2 && isset($r2['capacity']) && $r2['capacity'] !== null) {
                            $maxParticipants = (int)$r2['capacity'];
                        }
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        // 3) 활성 예약 합산 (cancelled/refunded 제외, rejected는 좌석 점유)
        $excludeCond = '';
        if ($excludeBookingId !== null && $excludeBookingId !== '') {
            $excludeCond = "AND b.bookingId <> ?";
        }

        $sql = "SELECT COALESCE(SUM(COALESCE(b.adults,0) + COALESCE(b.children,0) + COALESCE(b.infantsWithSeat,0)), 0) AS bookedSeats
                FROM bookings b
                WHERE b.packageId = ?
                  AND DATE(b.departureDate) = ?
                  AND b.bookingStatus NOT IN ('cancelled','draft')
                  AND b.paymentStatus <> 'refunded'
                  {$excludeCond}";
        $st3 = $db->prepare($sql);
        if (!$st3) {
            return ['ok' => false, 'remainingSeats' => 0, 'maxParticipants' => $maxParticipants, 'bookedSeats' => 0, 'message' => 'DB error'];
        }
        if ($excludeBookingId !== null && $excludeBookingId !== '') {
            $st3->bind_param('iss', $packageId, $departureDate, $excludeBookingId);
        } else {
            $st3->bind_param('is', $packageId, $departureDate);
        }
        $st3->execute();
        $rs3 = $st3->get_result();
        $r3 = $rs3 ? $rs3->fetch_assoc() : null;
        $st3->close();

        $bookedSeats = (int)($r3['bookedSeats'] ?? 0);
        $remainingSeats = max($maxParticipants - $bookedSeats, 0);
        $ok = ($remainingSeats >= $requestedPax);

        return [
            'ok' => $ok,
            'remainingSeats' => $remainingSeats,
            'maxParticipants' => $maxParticipants,
            'bookedSeats' => $bookedSeats,
            'message' => $ok ? 'OK' : 'Not enough seats (remaining: ' . $remainingSeats . ', requested: ' . $requestedPax . ')',
        ];
    }
}
