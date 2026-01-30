# 예약 금액 DB 저장 및 표시 개선 - 실행 계획

## 작업 개요
예약 생성 시 단가(adultPrice, childPrice, infantPrice)와 부가비용(visaFee, flightOptionFee)을 DB에 저장하고, 관리자 페이지에서 DB 값을 그대로 표시하도록 수정

## Phase 1: DB 스키마 변경

```sql
ALTER TABLE bookings
ADD COLUMN IF NOT EXISTS adultPrice DECIMAL(10,2) NULL COMMENT '예약 시 적용된 성인 단가',
ADD COLUMN IF NOT EXISTS childPrice DECIMAL(10,2) NULL COMMENT '예약 시 적용된 아동 단가',
ADD COLUMN IF NOT EXISTS infantPrice DECIMAL(10,2) NULL COMMENT '예약 시 적용된 유아 단가',
ADD COLUMN IF NOT EXISTS visaFee DECIMAL(10,2) DEFAULT 0 COMMENT '비자 비용 합계',
ADD COLUMN IF NOT EXISTS flightOptionFee DECIMAL(10,2) DEFAULT 0 COMMENT '항공옵션 비용 합계';
```

## Phase 2: 데이터 마이그레이션

### 2-1. 단가 컬럼 채우기
```sql
UPDATE bookings b
LEFT JOIN package_available_dates pa ON b.packageId = pa.package_id AND DATE(b.departureDate) = pa.available_date
LEFT JOIN packages p ON b.packageId = p.packageId
SET
    b.adultPrice = COALESCE(pa.b2b_price, p.b2b_price, p.packagePrice),
    b.childPrice = COALESCE(pa.b2b_child_price, p.b2b_child_price, ROUND(COALESCE(pa.b2b_price, p.b2b_price, p.packagePrice) * 0.7, 0)),
    b.infantPrice = COALESCE(pa.b2b_infant_price, p.b2b_infant_price, 10000)
WHERE b.bookingStatus NOT IN ('cancelled', 'rejected');
```

### 2-2. 비자 비용 채우기
```sql
UPDATE bookings b
SET b.visaFee = (
    SELECT COALESCE(SUM(CASE
        WHEN LOWER(bt.visaType) = 'group' THEN 1500
        WHEN LOWER(bt.visaType) = 'individual' THEN 1900
        ELSE 0
    END), 0)
    FROM booking_travelers bt WHERE bt.transactNo = b.bookingId
)
WHERE b.bookingStatus NOT IN ('cancelled', 'rejected');
```

### 2-3. 항공옵션 비용 채우기
```sql
UPDATE bookings b
SET b.flightOptionFee = (
    SELECT COALESCE(SUM(bto.price), 0)
    FROM booking_traveler_options bto WHERE bto.booking_id = b.bookingId
)
WHERE b.bookingStatus NOT IN ('cancelled', 'rejected');
```

## Phase 3: 소스 코드 수정

### 3-1. super-api.php
파일: `/var/www/html/admin/backend/api/super-api.php`

`getB2BBookingDetail()` 함수에서 bookings 테이블 SELECT에 새 컬럼 추가:
```php
b.adultPrice, b.childPrice, b.infantPrice, b.visaFee, b.flightOptionFee
```

### 3-2. agent-api.php
파일: `/var/www/html/admin/backend/api/agent-api.php`

1. 예약 조회 시 새 컬럼 SELECT에 추가
2. 예약 생성(INSERT) 시 새 컬럼 포함:
```php
// INSERT에 추가할 컬럼
adultPrice, childPrice, infantPrice, visaFee, flightOptionFee

// VALUES에 추가 (POST 데이터에서 받음)
$adultPrice = $_POST['adultPrice'] ?? null;
$childPrice = $_POST['childPrice'] ?? null;
$infantPrice = $_POST['infantPrice'] ?? null;
$visaFee = $_POST['visaFee'] ?? 0;
$flightOptionFee = $_POST['flightOptionFee'] ?? 0;
```

### 3-3. b2b-booking-detail.html
파일: `/var/www/html/admin/super/b2b-booking-detail.html`

`renderAmountBreakdown()` 함수 수정 - 자체 계산 제거하고 DB 값 사용:
```javascript
// Before: 복잡한 계산 로직
// After: DB 값 그대로 사용
const adultPrice = parseFloat(booking.adultPrice || 0);
const childPrice = parseFloat(booking.childPrice || 0);
const infantPrice = parseFloat(booking.infantPrice || 0);
const visaFee = parseFloat(booking.visaFee || 0);
const flightOptionFee = parseFloat(booking.flightOptionFee || 0);
const totalAmount = parseFloat(booking.totalAmount || 0);

// 계산하지 않고 표시만
```

### 3-4. agent-create-reservation.js
파일: `/var/www/html/admin/js/agent-create-reservation.js`

예약 저장 시 단가 정보 전송:
```javascript
// submitReservation() 또는 API 호출 부분에 추가
{
    ...existingData,
    adultPrice: bookingInfo.adultPrice,
    childPrice: bookingInfo.childPrice,
    infantPrice: bookingInfo.infantPrice,
    visaFee: calculatedVisaFee,
    flightOptionFee: calculatedFlightOptionFee
}
```

### 3-5. reservation-detail.html (확인)
파일: `/var/www/html/admin/agent/reservation-detail.html`

DB 값을 그대로 표시하는지 확인하고, 필요시 수정

## Phase 4: 검증

### DB 스키마 확인
```bash
mysql -u root -pcloud1234 smarttravel -e "DESCRIBE bookings" | grep -E "adultPrice|childPrice|infantPrice|visaFee|flightOptionFee"
```

### 마이그레이션 확인
```bash
mysql -u root -pcloud1234 smarttravel -e "SELECT bookingId, adultPrice, childPrice, infantPrice, visaFee, flightOptionFee FROM bookings WHERE adultPrice IS NOT NULL LIMIT 5;"
```

### 기능 테스트
1. 새 예약 생성 후 DB에 단가 저장 확인
2. Super Admin 예약 상세에서 금액 표시 확인
3. Agent 예약 상세에서 금액 표시 확인

## DB 접속 정보 (테스트서버)
- Host: localhost
- User: root
- Password: cloud1234
- Database: smarttravel
