-- Balance Due Date 자동 설정 (출발일 30일 전)
-- 생성일: 2026-01-21
-- 대상: departureDate가 있고 balanceDueDate가 NULL인 예약

UPDATE bookings
SET balanceDueDate = DATE_SUB(departureDate, INTERVAL 30 DAY)
WHERE departureDate IS NOT NULL
AND balanceDueDate IS NULL;
