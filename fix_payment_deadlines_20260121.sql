-- 결제 Deadline 규칙 적용 SQL
-- 생성일: 2026-01-21
-- 실행 완료: 2026-01-21
--
-- ========== 결제 Deadline 규칙 ==========
-- 규칙 1: 출발일까지 30일 이내 예약 → Full Payment만, deadline 예약일+1일
-- 규칙 2: 출발일까지 44일 이내 예약 → 모든 deadline 예약일+3일
-- 규칙 3: 출발일까지 44일 초과 예약 → 일반 규칙
--         - Down Payment: 예약일 + 3일
--         - Second Payment: Down Payment deadline + 30일
--         - Balance: 출발일 - 30일
--
-- ========== 수정된 파일 ==========
-- 1. agent-api.php createReservation (라인 2570~2647) - 신규 예약 생성
-- 2. agent-api.php updatePaymentInfo (라인 9534~9608) - 결제정보 수정
-- 3. agent-create-reservation.js (라인 4710~4830) - 프론트엔드 계산

-- ============================================================
-- 1. Second Payment (advancePaymentDueDate) 업데이트
-- ============================================================

-- 44일 초과: downPaymentDueDate + 30일
UPDATE bookings
SET advancePaymentDueDate = DATE_ADD(downPaymentDueDate, INTERVAL 30 DAY)
WHERE departureDate IS NOT NULL
AND downPaymentDueDate IS NOT NULL
AND paymentType = 'staged'
AND DATEDIFF(departureDate, DATE(createdAt)) > 44;
-- 결과: 136건

-- 31-44일: 예약일 + 3일
UPDATE bookings
SET advancePaymentDueDate = DATE_ADD(DATE(createdAt), INTERVAL 3 DAY)
WHERE departureDate IS NOT NULL
AND paymentType = 'staged'
AND DATEDIFF(departureDate, DATE(createdAt)) <= 44
AND DATEDIFF(departureDate, DATE(createdAt)) > 30;
-- 결과: 22건

-- 30일 이내 (기존 staged 예약): 예약일 + 1일
UPDATE bookings
SET advancePaymentDueDate = DATE_ADD(DATE(createdAt), INTERVAL 1 DAY)
WHERE departureDate IS NOT NULL
AND paymentType = 'staged'
AND advancePaymentDueDate IS NULL
AND DATEDIFF(departureDate, DATE(createdAt)) <= 30;
-- 결과: 11건

-- ============================================================
-- 2. Balance (balanceDueDate) 업데이트
-- ============================================================

-- 44일 초과: 출발일 - 30일
UPDATE bookings
SET balanceDueDate = DATE_SUB(departureDate, INTERVAL 30 DAY)
WHERE departureDate IS NOT NULL
AND balanceDueDate IS NULL
AND paymentType = 'staged';
-- 결과: 70건 (이전 fix_balance_duedate_20260121.sql에서 실행)

-- 31-44일: 예약일 + 3일
UPDATE bookings
SET balanceDueDate = DATE_ADD(DATE(createdAt), INTERVAL 3 DAY)
WHERE departureDate IS NOT NULL
AND paymentType = 'staged'
AND DATEDIFF(departureDate, DATE(createdAt)) <= 44
AND DATEDIFF(departureDate, DATE(createdAt)) > 30;
-- 결과: 22건

-- 30일 이내 (기존 staged 예약): 예약일 + 1일
UPDATE bookings
SET balanceDueDate = DATE_ADD(DATE(createdAt), INTERVAL 1 DAY)
WHERE departureDate IS NOT NULL
AND paymentType = 'staged'
AND DATEDIFF(departureDate, DATE(createdAt)) <= 30;
-- 결과: 12건
