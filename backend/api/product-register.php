<?php
//   
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// PHP   
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '150M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '512M');
ini_set('max_file_uploads', '20');

require_once '../conn.php';

// Content-Type 설정
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

//    (   )
if (!function_exists('get_column_type')) {
    function get_column_type(mysqli $conn, string $table, string $column): ?string {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($t === '' || $c === '') return null;
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        if ($res && ($row = $res->fetch_assoc())) return $row['Type'] ?? null;
        return null;
    }
}

//     (   )
if (!function_exists('__table_has_column')) {
    function __table_has_column(mysqli $conn, string $table, string $column): bool {
        return get_column_type($conn, $table, $column) !== null;
    }
}

if (!function_exists('table_exists_safe')) {
    function table_exists_safe(mysqli $conn, string $table): bool {
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$t'");
        return ($res && $res->num_rows > 0);
    }
}

// SMT (#165): product document columns (flyer/itinerary/detail-file)
if (!function_exists('ensure_package_product_doc_columns')) {
    function ensure_package_product_doc_columns(mysqli $conn): void {
        $need = [
            'flyer_file' => "ALTER TABLE packages ADD COLUMN flyer_file VARCHAR(255) NULL",
            'flyer_name' => "ALTER TABLE packages ADD COLUMN flyer_name VARCHAR(255) NULL",
            'flyer_size' => "ALTER TABLE packages ADD COLUMN flyer_size INT NULL",
            'itinerary_file' => "ALTER TABLE packages ADD COLUMN itinerary_file VARCHAR(255) NULL",
            'itinerary_name' => "ALTER TABLE packages ADD COLUMN itinerary_name VARCHAR(255) NULL",
            'itinerary_size' => "ALTER TABLE packages ADD COLUMN itinerary_size INT NULL",
            'detail_file' => "ALTER TABLE packages ADD COLUMN detail_file VARCHAR(255) NULL",
            'detail_name' => "ALTER TABLE packages ADD COLUMN detail_name VARCHAR(255) NULL",
            'detail_size' => "ALTER TABLE packages ADD COLUMN detail_size INT NULL",
        ];
        foreach ($need as $col => $sql) {
            try {
                $check = $conn->query("SHOW COLUMNS FROM packages LIKE '" . $conn->real_escape_string($col) . "'");
                if (!$check || $check->num_rows === 0) {
                    $conn->query($sql);
                }
            } catch (Throwable $_) {
                // ignore
            }
        }
    }
}

// 공통 숙박/교통 정보 컬럼 (Common Accommodation/Transportation)
if (!function_exists('ensure_package_common_info_columns')) {
    function ensure_package_common_info_columns(mysqli $conn): void {
        $need = [
            'common_accommodation_name' => "ALTER TABLE packages ADD COLUMN common_accommodation_name VARCHAR(255) NULL",
            'common_accommodation_address' => "ALTER TABLE packages ADD COLUMN common_accommodation_address VARCHAR(500) NULL",
            'common_accommodation_description' => "ALTER TABLE packages ADD COLUMN common_accommodation_description TEXT NULL",
            'common_accommodation_image' => "ALTER TABLE packages ADD COLUMN common_accommodation_image VARCHAR(255) NULL",
            'common_transportation_description' => "ALTER TABLE packages ADD COLUMN common_transportation_description TEXT NULL",
        ];
        foreach ($need as $col => $sql) {
            try {
                $check = $conn->query("SHOW COLUMNS FROM packages LIKE '" . $conn->real_escape_string($col) . "'");
                if (!$check || $check->num_rows === 0) {
                    $conn->query($sql);
                }
            } catch (Throwable $_) {
                // ignore
            }
        }
    }
}

// 다중 숙소 테이블 (package_accommodations)
if (!function_exists('ensure_package_accommodations_table')) {
    function ensure_package_accommodations_table(mysqli $conn): void {
        if (table_exists_safe($conn, 'package_accommodations')) return;
        $sql = "CREATE TABLE package_accommodations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            package_id INT NOT NULL,
            sort_order INT DEFAULT 0,
            accommodation_name VARCHAR(255) NULL,
            accommodation_address VARCHAR(500) NULL,
            accommodation_description TEXT NULL,
            accommodation_image VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_package_id (package_id),
            FOREIGN KEY (package_id) REFERENCES packages(packageId) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            $conn->query($sql);
        } catch (Throwable $_) {
            // ignore
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => '  .'], 405);
}

try {
    //  (packageId  update)
    $packageId = isset($_POST['packageId']) ? intval($_POST['packageId']) : 0;

    // ensure doc columns exist (flyer/itinerary/detail-file)
    try { ensure_package_product_doc_columns($conn); } catch (Throwable $_) {}
    // ensure common accommodation/transportation columns exist
    try { ensure_package_common_info_columns($conn); } catch (Throwable $_) {}
    // ensure package_accommodations table exists (다중 숙소)
    try { ensure_package_accommodations_table($conn); } catch (Throwable $_) {}

    //
    // - : active( / )
    // - temporary: ( )
    $saveMode = sanitize_input($_POST['saveMode'] ?? ($_POST['status'] ?? ''));
    $isTemporarySave = in_array(strtolower((string)$saveMode), ['temporary', 'temp', 'draft', 'saved'], true);
    $statusValue = $isTemporarySave ? 'temporary' : 'active';
    //    user-facing API isActive=1   isActive=0 
    $isActiveValue = $isTemporarySave ? 0 : 1;
    // packageStatus enum('active','inactive','soldout')   inactive 
    $packageStatusValue = $isTemporarySave ? 'inactive' : 'active';

    //   
    $productName = sanitize_input($_POST['productName'] ?? '');
    $salesTarget = sanitize_input($_POST['salesTarget'] ?? '');
    $mainCategory = sanitize_input($_POST['mainCategory'] ?? '');
    $subCategory = sanitize_input($_POST['subCategory'] ?? '');

    //      
    if ($isTemporarySave) {
        if ($salesTarget === '') $salesTarget = 'B2B';
        if ($mainCategory === '') $mainCategory = 'season';
        // subCategory  NULL (  / )
        if ($subCategory === '') $subCategory = null;
    }
    $productDescription = $_POST['productDescription'] ?? '';

    // /:  off       .
    // - update   " "    (     )
    $includedProvided = array_key_exists('included_items', $_POST);
    $excludedProvided = array_key_exists('excluded_items', $_POST);
    $includedItems = $includedProvided ? (string)($_POST['included_items'] ?? '') : null;
    $excludedItems = $excludedProvided ? (string)($_POST['excluded_items'] ?? '') : null;

    //  (    )
    $adminComponents = null;
    if (array_key_exists('adminComponents', $_POST)) {
        $raw = trim((string)($_POST['adminComponents'] ?? ''));
        $adminComponents = ($raw !== '') ? $raw : null;
    }

    //  
    $salesPeriodRaw = sanitize_input($_POST['salesPeriod'] ?? '');
    $salesPeriod = null; // packages.sales_period (DATE)  ( )
    $salesStartDate = null;
    $salesEndDate = null;
    if (!empty($salesPeriodRaw)) {
        $raw = (string)$salesPeriodRaw;
        $mm = [];
        $start = '';
        $end = '';
        if (preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/', $raw, $mm) && !empty($mm[1])) {
            $start = $mm[1][0] ?? '';
            $end = $mm[1][1] ?? ($mm[1][0] ?? '');
        }

        if ($start !== '' && $end !== '') {
            // packages.sales_period
            $spType = get_column_type($conn, 'packages', 'sales_period');
            $spTypeLower = strtolower((string)$spType);
            if (str_starts_with($spTypeLower, 'date') || str_starts_with($spTypeLower, 'datetime') || str_starts_with($spTypeLower, 'timestamp')) {
                $salesPeriod = $start; //    
            } else {
                $salesPeriod = $start . ' ~ ' . $end;
            }
            $salesStartDate = $start;
            $salesEndDate = $end;
        } elseif ($start !== '') {
            $salesPeriod = $start;
            $salesStartDate = $start;
            $salesEndDate = $start;
        } else {
            $salesPeriod = null;
        }
    }
    // IMPORTANT:
    // - update  min/max/base " "    .
    //   (  PHP  0 ,   0   )
    $minProvided = array_key_exists('minParticipants', $_POST) && trim((string)($_POST['minParticipants'] ?? '')) !== '';
    $maxProvided = array_key_exists('maxParticipants', $_POST) && trim((string)($_POST['maxParticipants'] ?? '')) !== '';
    $baseProvided = array_key_exists('basePrice', $_POST) && trim((string)($_POST['basePrice'] ?? '')) !== '';

    $minParticipants = $minProvided ? intval($_POST['minParticipants']) : 0;
    $maxParticipants = $maxProvided ? intval($_POST['maxParticipants']) : 0;
    $basePrice = $baseProvided ? floatval($_POST['basePrice']) : 0;

    // / (/  )
    $singleRoomFee = null;
    if (isset($_POST['singleRoomFee'])) {
        $raw = preg_replace('/[^0-9.]/', '', (string)$_POST['singleRoomFee']);
        $singleRoomFee = ($raw !== '') ? floatval($raw) : null;
    }
    $refundDays = null;
    if (isset($_POST['refundDays'])) {
        $refundDays = intval($_POST['refundDays']);
        if ($refundDays <= 0) $refundDays = null;
    }

    //  
    $meetingHour = sanitize_input($_POST['meetingHour'] ?? '00');
    $meetingMinute = sanitize_input($_POST['meetingMinute'] ?? '00');
    $meetingTime = $meetingHour . ':' . $meetingMinute . ':00';
    $meetingLocation = sanitize_input($_POST['meetingLocation'] ?? '');
    $meetingAddress = sanitize_input($_POST['meetingAddress'] ?? '');

    //     (day1, day2, day3...)
    $daySchedules = [];
    $dayNumber = 1;
    while (isset($_POST["day{$dayNumber}_description"])) {
        $dayDescription = sanitize_input($_POST["day{$dayNumber}_description"] ?? '');
        $dayStartHour = sanitize_input($_POST["day{$dayNumber}_startHour"] ?? '00');
        $dayStartMinute = sanitize_input($_POST["day{$dayNumber}_startMinute"] ?? '00');
        $dayStartTime = $dayStartHour . ':' . $dayStartMinute . ':00';
        $dayEndHour = sanitize_input($_POST["day{$dayNumber}_endHour"] ?? '00');
        $dayEndMinute = sanitize_input($_POST["day{$dayNumber}_endMinute"] ?? '00');
        $dayEndTime = $dayEndHour . ':' . $dayEndMinute . ':00';
        $dayAirport = sanitize_input($_POST["day{$dayNumber}_airport"] ?? '');
        $dayAirportAddress = sanitize_input($_POST["day{$dayNumber}_airportAddress"] ?? '');

        // (//) - dayN_* ,      
        $dayAccommodationName = sanitize_input($_POST["day{$dayNumber}_accommodationName"] ?? ($_POST['accommodationName'] ?? ''));
        $dayAccommodationAddress = sanitize_input($_POST["day{$dayNumber}_accommodationAddress"] ?? ($_POST['accommodationAddress'] ?? ''));
        $dayAccommodationDescription = $_POST["day{$dayNumber}_accommodation_description"] ?? ($_POST['accommodation_description'] ?? '');
        $dayTransportationDescription = $_POST["day{$dayNumber}_transportation_description"] ?? ($_POST['transportation_description'] ?? '');
        $dayBreakfast = sanitize_input($_POST["day{$dayNumber}_breakfast"] ?? ($_POST['breakfast'] ?? ''));
        $dayLunch = sanitize_input($_POST["day{$dayNumber}_lunch"] ?? ($_POST['lunch'] ?? ''));
        $dayDinner = sanitize_input($_POST["day{$dayNumber}_dinner"] ?? ($_POST['dinner'] ?? ''));

        //    ( data-existing  POST )
        $dayAirportImageExisting = sanitize_input($_POST["day{$dayNumber}_airportImage_existing"] ?? '');
        $dayAccommodationImageExisting = sanitize_input($_POST["day{$dayNumber}_accommodationImage_existing"] ?? '');
        
        //   ( )
        $dayAirportDescription = '';
        //      
        
        $daySchedules[$dayNumber] = [
            'description' => $dayDescription,
            'start_time' => $dayStartTime,
            'end_time' => $dayEndTime,
            'airport_location' => $dayAirport,
            'airport_address' => $dayAirportAddress,
            'airport_description' => $dayAirportDescription,
            'airport_image_existing' => $dayAirportImageExisting,
            'accommodation_name' => $dayAccommodationName,
            'accommodation_address' => $dayAccommodationAddress,
            'accommodation_description' => $dayAccommodationDescription,
            'accommodation_image_existing' => $dayAccommodationImageExisting,
            'transportation_description' => $dayTransportationDescription,
            'breakfast' => $dayBreakfast,
            'lunch' => $dayLunch,
            'dinner' => $dayDinner
        ];
        
        $dayNumber++;
    }
    
    // 1Day   (   )
    $day1Description = sanitize_input($_POST['day1_description'] ?? '');
    $day1StartHour = sanitize_input($_POST['day1_startHour'] ?? '00');
    $day1StartMinute = sanitize_input($_POST['day1_startMinute'] ?? '00');
    $day1StartTime = $day1StartHour . ':' . $day1StartMinute . ':00';
    $day1EndHour = sanitize_input($_POST['day1_endHour'] ?? '00');
    $day1EndMinute = sanitize_input($_POST['day1_endMinute'] ?? '00');
    $day1EndTime = $day1EndHour . ':' . $day1EndMinute . ':00';
    $day1Airport = sanitize_input($_POST['day1_airport'] ?? '');
    $day1AirportAddress = sanitize_input($_POST['day1_airportAddress'] ?? '');

    //
    $accommodationName = sanitize_input($_POST['accommodationName'] ?? '');
    $accommodationAddress = sanitize_input($_POST['accommodationAddress'] ?? '');

    // 공통 숙박 정보 (Common Accommodation)
    $commonAccommodationName = sanitize_input($_POST['common_accommodationName'] ?? '');
    $commonAccommodationAddress = sanitize_input($_POST['common_accommodationAddress'] ?? '');
    $commonAccommodationDescription = $_POST['common_accommodation_description'] ?? '';
    $commonAccommodationImageExisting = sanitize_input($_POST['common_accommodationImage_existing'] ?? '');

    // 공통 교통 정보 (Common Transportation)
    $commonTransportationDescription = $_POST['common_transportation_description'] ?? '';

    //
    $breakfast = sanitize_input($_POST['breakfast'] ?? '');
    $lunch = sanitize_input($_POST['lunch'] ?? '');
    $dinner = sanitize_input($_POST['dinner'] ?? '');

    //  (   ) - package_attractions 
    //  attractionsJson(JSON ) .
    // item : { day:1, order:1, name:'', address:'', descriptionHtml:'', startHour:'09', startMinute:'00', endHour:'10', endMinute:'00', imageKey:'attractionImage_0', existingImage:'' }
    $attractionsByDay = [];
    if (!empty($_POST['attractionsJson'])) {
        $decoded = json_decode((string)$_POST['attractionsJson'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $day = isset($item['day']) ? intval($item['day']) : 0;
                if ($day <= 0) continue;
                if (!isset($attractionsByDay[$day])) $attractionsByDay[$day] = [];
                $attractionsByDay[$day][] = $item;
            }
        }
    }

    //   (availability)
    $availabilityRows = [];
    if (isset($_POST['availabilityDate']) && is_array($_POST['availabilityDate'])) {
        $dates = $_POST['availabilityDate'];
        $seats = $_POST['availabilitySeats'] ?? [];
        $prices = $_POST['availabilityPrice'] ?? [];
        $b2bPrices = $_POST['availabilityB2bPrice'] ?? [];
        for ($i = 0; $i < count($dates); $i++) {
            $d = sanitize_input($dates[$i] ?? '');
            if ($d === '') continue;
            $b2bVal = isset($b2bPrices[$i]) && $b2bPrices[$i] !== '' ? floatval($b2bPrices[$i]) : null;
            $availabilityRows[] = [
                'date' => $d,
                'seats' => intval($seats[$i] ?? 0),
                'price' => floatval($prices[$i] ?? 0),
                'b2bPrice' => $b2bVal
            ];
        }
    }

    //   -
    $departureFlightNumber = sanitize_input($_POST['departureFlightNumber'] ?? '');
    $departureFlightAirline = sanitize_input($_POST['departureFlightAirline'] ?? '');
    $departureFlightDepartureHour = sanitize_input($_POST['departureFlightDepartureHour'] ?? '');
    $departureFlightDepartureMinute = sanitize_input($_POST['departureFlightDepartureMinute'] ?? '');
    $departureFlightArrivalHour = sanitize_input($_POST['departureFlightArrivalHour'] ?? '');
    $departureFlightArrivalMinute = sanitize_input($_POST['departureFlightArrivalMinute'] ?? '');
    $departureFlightDeparturePoint = sanitize_input($_POST['departureFlightDeparturePoint'] ?? '');
    $departureFlightDestination = sanitize_input($_POST['departureFlightDestination'] ?? '');

    //   -
    $returnFlightNumber = sanitize_input($_POST['returnFlightNumber'] ?? '');
    $returnFlightAirline = sanitize_input($_POST['returnFlightAirline'] ?? '');
    $returnFlightDepartureHour = sanitize_input($_POST['returnFlightDepartureHour'] ?? '');
    $returnFlightDepartureMinute = sanitize_input($_POST['returnFlightDepartureMinute'] ?? '');
    $returnFlightArrivalHour = sanitize_input($_POST['returnFlightArrivalHour'] ?? '');
    $returnFlightArrivalMinute = sanitize_input($_POST['returnFlightArrivalMinute'] ?? '');
    $returnFlightDeparturePoint = sanitize_input($_POST['returnFlightDeparturePoint'] ?? '');
    $returnFlightDestination = sanitize_input($_POST['returnFlightDestination'] ?? '');

    // TIME  / (   ':20:00'   time  500   )
    $norm_time_part = function ($val, $max, $msg) {
        $s = trim((string)$val);
        if ($s === '') {
            send_json_response(['success' => false, 'message' => $msg], 400);
        }
        if (!ctype_digit($s)) {
            send_json_response(['success' => false, 'message' => $msg], 400);
        }
        $n = intval($s);
        if ($n < 0 || $n > $max) {
            send_json_response(['success' => false, 'message' => $msg], 400);
        }
        return str_pad((string)$n, 2, '0', STR_PAD_LEFT);
    };

    $departureFlightDepartureTime = null;
    $departureFlightArrivalTime = null;
    if (!empty($departureFlightNumber)) {
        $dh = $norm_time_part($departureFlightDepartureHour, 23, ' () .');
        $dm = $norm_time_part($departureFlightDepartureMinute, 59, ' () .');
        $ah = $norm_time_part($departureFlightArrivalHour, 23, ' () .');
        $am = $norm_time_part($departureFlightArrivalMinute, 59, ' () .');
        $departureFlightDepartureTime = $dh . ':' . $dm . ':00';
        $departureFlightArrivalTime = $ah . ':' . $am . ':00';
    }

    $returnFlightDepartureTime = null;
    $returnFlightArrivalTime = null;
    if (!empty($returnFlightNumber)) {
        $dh = $norm_time_part($returnFlightDepartureHour, 23, ' () .');
        $dm = $norm_time_part($returnFlightDepartureMinute, 59, ' () .');
        $ah = $norm_time_part($returnFlightArrivalHour, 23, ' () .');
        $am = $norm_time_part($returnFlightArrivalMinute, 59, ' () .');
        $returnFlightDepartureTime = $dh . ':' . $dm . ':00';
        $returnFlightArrivalTime = $ah . ':' . $am . ':00';
    }

    //    (      )
    $clearFlights = false;
    if (isset($_POST['clearFlights'])) {
        $clearFlights = in_array(strtolower(trim((string)$_POST['clearFlights'])), ['1', 'true', 'yes', 'y'], true);
    }

    //
    $productPricingType = sanitize_input($_POST['productPricingType'] ?? '');
    $productPricing = floatval($_POST['productPricing'] ?? 0);
    $adultPrice = floatval($_POST['adultPrice'] ?? $_POST['packagePrice'] ?? 0);
    $childPrice = !empty($_POST['childPrice']) ? floatval($_POST['childPrice']) : null;
    $infantPrice = !empty($_POST['infantPrice']) ? floatval($_POST['infantPrice']) : null;
    // B2B 가격 (에이전트/관리자용)
    // b2bPrice (기본 B2B 가격)이 있으면 우선 사용, 없으면 b2bAdultPrice 사용
    $b2bBasePrice = !empty($_POST['b2bPrice']) ? floatval($_POST['b2bPrice']) : null;
    $b2bAdultPrice = !empty($_POST['b2bAdultPrice']) ? floatval($_POST['b2bAdultPrice']) : $b2bBasePrice;
    $b2bChildPrice = !empty($_POST['b2bChildPrice']) ? floatval($_POST['b2bChildPrice']) : null;
    $b2bInfantPrice = !empty($_POST['b2bInfantPrice']) ? floatval($_POST['b2bInfantPrice']) : null;

    // 가격 텍스트 (문자열로 표시할 가격)
    $priceDisplayText = isset($_POST['priceDisplayText']) ? trim((string)$_POST['priceDisplayText']) : null;
    $priceDisplayText = ($priceDisplayText !== '') ? $priceDisplayText : null;
    $b2bPriceDisplayText = isset($_POST['b2bPriceDisplayText']) ? trim((string)$_POST['b2bPriceDisplayText']) : null;
    $b2bPriceDisplayText = ($b2bPriceDisplayText !== '') ? $b2bPriceDisplayText : null;

    //
    $airfareCost = floatval($_POST['airfareCost'] ?? 0);
    $accommodationCost = floatval($_POST['accommodationCost'] ?? 0);
    $mealCost = floatval($_POST['mealCost'] ?? 0);
    $guideCost = floatval($_POST['guideCost'] ?? 0);
    $vehicleCost = floatval($_POST['vehicleCost'] ?? 0);
    $entranceCost = floatval($_POST['entranceCost'] ?? 0);
    $otherCost = floatval($_POST['otherCost'] ?? 0);
    $totalCost = floatval($_POST['totalCost'] ?? 0);

    // 필수값 검증
    // - (temporary) "임시저장"은 최소한의 검사만.
    //   (단, packages.packageName NOT NULL 제약)
    if (empty($productName)) {
        send_json_response(['success' => false, 'message' => '상품명을 입력해주세요.'], 400);
    }
    if (!$isTemporarySave) {
        if (empty($salesTarget)) {
            send_json_response(['success' => false, 'message' => '판매대상을 선택해주세요.'], 400);
        }
        if (empty($mainCategory) || empty($subCategory)) {
            send_json_response(['success' => false, 'message' => '카테고리를 선택해주세요.'], 400);
        }
    }

    //  
    $conn->begin_transaction();

    //    
    function uploadImage($fileInputName, $uploadDir = null) {
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('    .');
        }

        $file = $_FILES[$fileInputName];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('   .');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('  5MB  .');
        }

        //  () 
        // NOTE:           __DIR__  .
        if ($uploadDir === null) {
            $uploadDir = __DIR__ . '/../../uploads/products/';
        }
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new Exception('    .');
            }
        }

        //   
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = rtrim($uploadDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('  .');
        }

        return $filename;
    }

    /**
     * existing   /    .
     * - src   basename 
     * - /uploads/...    copy
     * - http(s) URL   
     */
    function ensureExistingAssetSaved($src, $kind = 'image') {
        $src = trim((string)$src);
        if ($src === '') return '';

        $root = realpath(__DIR__ . '/../../'); // /var/www/html
        if (!$root) return basename($src);

        $isUrl = preg_match('#^https?://#i', $src) === 1;
        $isUploadsPath = str_starts_with($src, '/uploads/') || str_starts_with($src, 'uploads/');

        //   
        $targetDir = ($kind === 'pdf') ? ($root . '/uploads/usage_guides/') : ($root . '/uploads/products/');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        //  ( ):   (Blob UUID   )
        if (!$isUrl && !$isUploadsPath && strpos($src, '/') === false) {
            $base = basename($src);
            if ($kind === 'image') {
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) return '';
            }
            if ($kind === 'pdf') {
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') return '';
            }
            return $base;
        }

        //  (/uploads/...) copy
        if (!$isUrl && $isUploadsPath) {
            $rel = ltrim($src, '/');
            $abs = $root . '/' . $rel;
            if (!is_file($abs)) return basename($src);

            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if ($kind === 'pdf' && $ext !== 'pdf') return '';
            if ($kind === 'image' && !in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
                //    jpg
                $ext = 'jpg';
            }

            $newName = uniqid('tpl_', true) . '_' . time() . '.' . $ext;
            $dst = rtrim($targetDir, '/') . '/' . $newName;
            if (@copy($abs, $dst)) return $newName;
            return basename($src);
        }

        //  URL 
        if ($isUrl) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'follow_location' => 1,
                    'user_agent' => 'SmartTravel/1.0'
                ],
                'https' => [
                    'timeout' => 8,
                    'follow_location' => 1,
                    'user_agent' => 'SmartTravel/1.0'
                ]
            ]);
            $bin = @file_get_contents($src, false, $ctx);
            if ($bin === false || $bin === '') return '';

            //  
            $max = ($kind === 'pdf') ? (50 * 1024 * 1024) : (5 * 1024 * 1024);
            if (strlen($bin) > $max) return '';

            // MIME 
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($bin) ?: '';
            if ($kind === 'pdf') {
                if ($mime !== 'application/pdf') return '';
                $ext = 'pdf';
            } else {
                if (!str_starts_with($mime, 'image/')) return '';
                $extMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp'
                ];
                $ext = $extMap[$mime] ?? 'jpg';
            }

            $newName = uniqid('tpl_', true) . '_' . time() . '.' . $ext;
            $dst = rtrim($targetDir, '/') . '/' . $newName;
            if (@file_put_contents($dst, $bin) !== false) return $newName;
            return '';
        }

        return basename($src);
    }

    //  ( PDF )
    function uploadFile($fileInputName, array $allowedTypes, $maxBytes, $uploadDir) {
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            return [null, null, null];
        }
        if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
            error_log("SMT uploadFile: {$fileInputName} error code = " . $_FILES[$fileInputName]['error']);
            throw new Exception('파일 업로드 오류.');
        }
        $file = $_FILES[$fileInputName];
        // Check by extension as well (MIME types can be unreliable)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = [];
        foreach ($allowedTypes as $t) {
            if ($t === 'application/pdf') $allowedExtensions[] = 'pdf';
            if ($t === 'image/jpeg') { $allowedExtensions[] = 'jpg'; $allowedExtensions[] = 'jpeg'; }
            if ($t === 'image/png') $allowedExtensions[] = 'png';
        }
        $mimeOk = in_array($file['type'], $allowedTypes);
        $extOk = in_array($ext, $allowedExtensions);
        if (!$mimeOk && !$extOk) {
            error_log("SMT uploadFile: {$fileInputName} type={$file['type']} ext={$ext} not allowed");
            throw new Exception('허용되지 않는 파일 형식.');
        }
        if ($file['size'] > $maxBytes) {
            throw new Exception('파일 크기 초과.');
        }
        //   (   __DIR__  )
        if ($uploadDir !== '' && $uploadDir[0] !== '/') {
            $uploadDir = __DIR__ . '/' . $uploadDir;
        }
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new Exception('    .');
            }
        }
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = rtrim($uploadDir, '/') . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('  .');
        }
        return [$filename, $file['name'], intval($file['size'])];
    }

    //  
    // update    (    )
    $existingThumb = null;
    $existingDetail = null;
    $existingProductImagesJson = null;
    $existingUsageGuideFile = null;
    $existingUsageGuideName = null;
    $existingUsageGuideSize = null;
    $existingFlyerFile = null;
    $existingFlyerName = null;
    $existingFlyerSize = null;
    $existingItineraryFile = null;
    $existingItineraryName = null;
    $existingItinerarySize = null;
    $existingDetailFile = null;
    $existingDetailName = null;
    $existingDetailSize = null;
    if ($packageId > 0) {
        $cur = $conn->prepare("SELECT thumbnail_image, detail_image, product_images, usage_guide_file, usage_guide_name, usage_guide_size, flyer_file, flyer_name, flyer_size, itinerary_file, itinerary_name, itinerary_size, detail_file, detail_name, detail_size, minParticipants, maxParticipants, base_price, packagePrice, included_items, excluded_items FROM packages WHERE packageId = ? LIMIT 1");
        if (!$cur) throw new Exception('쿼리 준비 실패.');
        $cur->bind_param('i', $packageId);
        $cur->execute();
        $curRes = $cur->get_result();
        if (!$curRes || $curRes->num_rows === 0) {
            throw new Exception('해당 상품을 찾을 수 없습니다.');
        }
        $curRow = $curRes->fetch_assoc();
        $existingThumb = $curRow['thumbnail_image'] ?? null;
        $existingDetail = $curRow['detail_image'] ?? null;
        $existingProductImagesJson = $curRow['product_images'] ?? null;
        $existingUsageGuideFile = $curRow['usage_guide_file'] ?? null;
        $existingUsageGuideName = $curRow['usage_guide_name'] ?? null;
        $existingUsageGuideSize = $curRow['usage_guide_size'] ?? null;
        $existingFlyerFile = $curRow['flyer_file'] ?? null;
        $existingFlyerName = $curRow['flyer_name'] ?? null;
        $existingFlyerSize = $curRow['flyer_size'] ?? null;
        $existingItineraryFile = $curRow['itinerary_file'] ?? null;
        $existingItineraryName = $curRow['itinerary_name'] ?? null;
        $existingItinerarySize = $curRow['itinerary_size'] ?? null;
        $existingDetailFile = $curRow['detail_file'] ?? null;
        $existingDetailName = $curRow['detail_name'] ?? null;
        $existingDetailSize = $curRow['detail_size'] ?? null;
        $existingIncludedItems = $curRow['included_items'] ?? '';
        $existingExcludedItems = $curRow['excluded_items'] ?? '';
        $existingMinParticipants = isset($curRow['minParticipants']) ? intval($curRow['minParticipants']) : 0;
        $existingMaxParticipants = isset($curRow['maxParticipants']) ? intval($curRow['maxParticipants']) : 0;
        $existingBasePrice = null;
        if (isset($curRow['base_price']) && $curRow['base_price'] !== null && $curRow['base_price'] !== '') {
            $existingBasePrice = floatval($curRow['base_price']);
        } elseif (isset($curRow['packagePrice']) && $curRow['packagePrice'] !== null && $curRow['packagePrice'] !== '') {
            $existingBasePrice = floatval($curRow['packagePrice']);
        }

        //      
        if (!$minProvided) $minParticipants = $existingMinParticipants;
        if (!$maxProvided) $maxParticipants = $existingMaxParticipants;
        if (!$baseProvided) $basePrice = ($existingBasePrice !== null ? $existingBasePrice : $basePrice);
        if (!$includedProvided) $includedItems = (string)$existingIncludedItems;
        if (!$excludedProvided) $excludedItems = (string)$existingExcludedItems;
        $cur->close();
    }

    // create  /
    if ($packageId <= 0) {
        if ($includedItems === null) $includedItems = '';
        if ($excludedItems === null) $excludedItems = '';
    }

    $thumbnailImage = uploadImage('thumbnailImage');
    if ($thumbnailImage === null && !empty($_POST['thumbnailImage_existing'])) {
        $thumbnailImage = ensureExistingAssetSaved((string)$_POST['thumbnailImage_existing'], 'image');
    }
    if ($thumbnailImage === null && $packageId > 0) $thumbnailImage = $existingThumb;

    $detailImage = uploadImage('detailImage');
    if ($detailImage === null && !empty($_POST['detailImage_existing'])) {
        $detailImage = ensureExistingAssetSaved((string)$_POST['detailImage_existing'], 'image');
    }
    if ($detailImage === null && $packageId > 0) $detailImage = $existingDetail;

    // 상품 이미지 (최대 5개)
    // IMPORTANT:
    // -  /  " 5 +  5"    ,
    //     "(0..4) " .
    // -  productImages_existing  5  (  '').
    // -  (productImage_0..4)   .

    // 기존 이미지 (우선순위: POST > DB)
    $existingSlots = array_fill(0, 5, '');
    if (isset($_POST['productImages_existing'])) {
        $tmp = json_decode((string)$_POST['productImages_existing'], true);
        if (is_array($tmp)) {
            $i = 0;
            foreach ($tmp as $v) {
                if ($i >= 5) break;
                $saved = ensureExistingAssetSaved((string)$v, 'image');
                $existingSlots[$i] = (string)$saved;
                $i++;
            }
        }
    } elseif (!empty($existingProductImagesJson) && is_string($existingProductImagesJson)) {
        $tmp = json_decode((string)$existingProductImagesJson, true);
        if (is_array($tmp)) {
            for ($i = 0; $i < 5; $i++) {
                $fn = isset($tmp[$i]) ? trim((string)$tmp[$i]) : '';
                if ($fn !== '') $existingSlots[$i] = $fn;
            }
        } else {
            //        
            $single = trim((string)$existingProductImagesJson);
            if ($single !== '' && $single[0] !== '[') $existingSlots[0] = $single;
        }
    }

    //    
    $finalSlots = $existingSlots;
    for ($i = 0; $i < 5; $i++) {
        $imageName = uploadImage('productImage_' . $i);
        if ($imageName) {
            $finalSlots[$i] = $imageName;
        }
    }

    //  :    +   +  5
    $final = [];
    foreach ($finalSlots as $fn) {
        $fn = trim((string)$fn);
        if ($fn === '') continue;
        if (!in_array($fn, $final, true)) $final[] = $fn;
        if (count($final) >= 5) break;
    }
    $productImagesJson = !empty($final) ? json_encode($final) : null;

    // 안내문 (pdf, jpg, png)
    [$usageGuideFile, $usageGuideName, $usageGuideSize] = uploadFile(
        'usageGuideFile',
        ['application/pdf', 'image/jpeg', 'image/png'],
        50 * 1024 * 1024,
        __DIR__ . '/../../uploads/usage_guides/'
    );
    if ($usageGuideFile === null && !empty($_POST['usageGuideFile_existing'])) {
        $usageGuideFile = ensureExistingAssetSaved((string)$_POST['usageGuideFile_existing'], 'pdf');
        $usageGuideName = (string)($_POST['usageGuideFileName_existing'] ?? $usageGuideFile);
        $usageGuideSize = isset($_POST['usageGuideFileSize_existing']) ? intval($_POST['usageGuideFileSize_existing']) : null;
    }
    if ($usageGuideFile === null && $packageId > 0) {
        $usageGuideFile = $existingUsageGuideFile;
        $usageGuideName = $existingUsageGuideName;
        $usageGuideSize = $existingUsageGuideSize;
    }

    // product docs (flyer/detail/itinerary) stored under uploads/products/
    // Debug: log $_FILES for product docs
    error_log("SMT product-register: FILES keys = " . implode(',', array_keys($_FILES)));
    if (isset($_FILES['flyerFile'])) error_log("SMT product-register: flyerFile = " . json_encode($_FILES['flyerFile']));
    if (isset($_FILES['itineraryFile'])) error_log("SMT product-register: itineraryFile = " . json_encode($_FILES['itineraryFile']));
    if (isset($_FILES['detailFile'])) error_log("SMT product-register: detailFile = " . json_encode($_FILES['detailFile']));

    [$flyerFile, $flyerName, $flyerSize] = uploadFile(
        'flyerFile',
        ['application/pdf', 'image/jpeg', 'image/png'],
        50 * 1024 * 1024,
        __DIR__ . '/../../uploads/products/'
    );
    if ($flyerFile === null && !empty($_POST['flyerFile_existing'])) {
        $flyerFile = basename((string)$_POST['flyerFile_existing']);
        $flyerName = (string)($_POST['flyerFileName_existing'] ?? $flyerFile);
        $flyerSize = isset($_POST['flyerFileSize_existing']) ? intval($_POST['flyerFileSize_existing']) : null;
    }
    if ($flyerFile === null && $packageId > 0) {
        $flyerFile = $existingFlyerFile;
        $flyerName = $existingFlyerName;
        $flyerSize = $existingFlyerSize;
    }

    [$itineraryFile, $itineraryName, $itinerarySize] = uploadFile(
        'itineraryFile',
        ['application/pdf'],
        50 * 1024 * 1024,
        __DIR__ . '/../../uploads/products/'
    );
    if ($itineraryFile === null && !empty($_POST['itineraryFile_existing'])) {
        $itineraryFile = basename((string)$_POST['itineraryFile_existing']);
        $itineraryName = (string)($_POST['itineraryFileName_existing'] ?? $itineraryFile);
        $itinerarySize = isset($_POST['itineraryFileSize_existing']) ? intval($_POST['itineraryFileSize_existing']) : null;
    }
    if ($itineraryFile === null && $packageId > 0) {
        $itineraryFile = $existingItineraryFile;
        $itineraryName = $existingItineraryName;
        $itinerarySize = $existingItinerarySize;
    }

    [$detailFile, $detailFileName, $detailFileSize] = uploadFile(
        'detailFile',
        ['application/pdf', 'image/jpeg', 'image/png'],
        50 * 1024 * 1024,
        __DIR__ . '/../../uploads/products/'
    );
    if ($detailFile === null && !empty($_POST['detailFile_existing'])) {
        $detailFile = basename((string)$_POST['detailFile_existing']);
        $detailFileName = (string)($_POST['detailFileName_existing'] ?? $detailFile);
        $detailFileSize = isset($_POST['detailFileSize_existing']) ? intval($_POST['detailFileSize_existing']) : null;
    }
    if ($detailFile === null && $packageId > 0) {
        $detailFile = $existingDetailFile;
        $detailFileName = $existingDetailName;
        $detailFileSize = $existingDetailSize;
    }

    // 공통 숙박 이미지 업로드 처리 (UPDATE/INSERT 공통)
    $commonAccommodationImage = uploadImage('common_accommodationImage');
    if (!$commonAccommodationImage && $commonAccommodationImageExisting) {
        $commonAccommodationImage = ensureExistingAssetSaved((string)$commonAccommodationImageExisting, 'image');
    }

    // packages 테이블 신규등록/수정
    if ($packageId > 0) {
        $stmt = $conn->prepare("
            UPDATE packages SET
                packageName = ?,
                sales_target = ?,
                packageCategory = ?,
                subCategory = ?,
                packageDescription = ?,
                packagePrice = ?,
                price_display_text = ?,
                b2b_price = ?,
                b2b_price_display_text = ?,
                childPrice = ?,
                b2b_child_price = ?,
                infantPrice = ?,
                b2b_infant_price = ?,
                sales_period = ?,
                minParticipants = ?,
                maxParticipants = ?,
                base_price = ?,
                meeting_time = ?,
                meeting_location = ?,
                meeting_address = ?,
                thumbnail_image = ?,
                product_images = ?,
                detail_image = ?,
                included_items = ?,
                excluded_items = ?,
                single_room_fee = ?,
                refund_days = ?,
                usage_guide_file = ?,
                usage_guide_name = ?,
                usage_guide_size = ?,
                pricing_type = ?,
                product_pricing = ?,
                common_accommodation_name = ?,
                common_accommodation_address = ?,
                common_accommodation_description = ?,
                common_accommodation_image = ?,
                common_transportation_description = ?,
                updatedAt = NOW()
            WHERE packageId = ?
        ");

        if (!$stmt) {
            throw new Exception('상품 수정 준비 실패: ' . $conn->error);
        }

        $stmt->bind_param(
            "sssssdsdsddddsiidssssssssdissisdsssss" . "i",
            $productName,
            $salesTarget,
            $mainCategory,
            $subCategory,
            $productDescription,
            $adultPrice,
            $priceDisplayText,
            $b2bAdultPrice,
            $b2bPriceDisplayText,
            $childPrice,
            $b2bChildPrice,
            $infantPrice,
            $b2bInfantPrice,
            $salesPeriod,
            $minParticipants,
            $maxParticipants,
            $basePrice,
            $meetingTime,
            $meetingLocation,
            $meetingAddress,
            $thumbnailImage,
            $productImagesJson,
            $detailImage,
            $includedItems,
            $excludedItems,
            $singleRoomFee,
            $refundDays,
            $usageGuideFile,
            $usageGuideName,
            $usageGuideSize,
            $productPricingType,
            $productPricing,
            $commonAccommodationName,
            $commonAccommodationAddress,
            $commonAccommodationDescription,
            $commonAccommodationImage,
            $commonTransportationDescription,
            $packageId
        );
        if (!$stmt->execute()) {
            throw new Exception('상품 수정 실패: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO packages (
                packageName,
                sales_target,
                packageCategory,
                subCategory,
                packageDescription,
                packagePrice,
                price_display_text,
                b2b_price,
                b2b_price_display_text,
                childPrice,
                b2b_child_price,
                infantPrice,
                b2b_infant_price,
                sales_period,
                minParticipants,
                maxParticipants,
                base_price,
                meeting_time,
                meeting_location,
                meeting_address,
                thumbnail_image,
                product_images,
                detail_image,
                included_items,
                excluded_items,
                single_room_fee,
                refund_days,
                usage_guide_file,
                usage_guide_name,
                usage_guide_size,
                pricing_type,
                product_pricing,
                common_accommodation_name,
                common_accommodation_address,
                common_accommodation_description,
                common_accommodation_image,
                common_transportation_description,
                createdAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            throw new Exception('상품 등록 준비 실패: ' . $conn->error);
        }

        $stmt->bind_param(
            "sssssdsdsddddsiidssssssssdissisdsssss",
            $productName,
            $salesTarget,
            $mainCategory,
            $subCategory,
            $productDescription,
            $adultPrice,
            $priceDisplayText,
            $b2bAdultPrice,
            $b2bPriceDisplayText,
            $childPrice,
            $b2bChildPrice,
            $infantPrice,
            $b2bInfantPrice,
            $salesPeriod,
            $minParticipants,
            $maxParticipants,
            $basePrice,
            $meetingTime,
            $meetingLocation,
            $meetingAddress,
            $thumbnailImage,
            $productImagesJson,
            $detailImage,
            $includedItems,
            $excludedItems,
            $singleRoomFee,
            $refundDays,
            $usageGuideFile,
            $usageGuideName,
            $usageGuideSize,
            $productPricingType,
            $productPricing,
            $commonAccommodationName,
            $commonAccommodationAddress,
            $commonAccommodationDescription,
            $commonAccommodationImage,
            $commonTransportationDescription
        );

        if (!$stmt->execute()) {
            throw new Exception('상품 등록 실패: ' . $stmt->error);
        }

        $packageId = $conn->insert_id;
        $stmt->close();
    }

    //  (status/isActive/packageStatus) 
    // -  SQL    status/isActive/packageStatus      .
    $st = $conn->prepare("UPDATE packages SET status = ?, isActive = ?, packageStatus = ? WHERE packageId = ?");
    if (!$st) throw new Exception('   .');
    $st->bind_param('sisi', $statusValue, $isActiveValue, $packageStatusValue, $packageId);
    if (!$st->execute()) throw new Exception('  : ' . $st->error);
    $st->close();

    // SMT (#165): persist product documents
    try {
        $docUp = $conn->prepare("
            UPDATE packages SET
                flyer_file = ?,
                flyer_name = ?,
                flyer_size = ?,
                itinerary_file = ?,
                itinerary_name = ?,
                itinerary_size = ?,
                detail_file = ?,
                detail_name = ?,
                detail_size = ?
            WHERE packageId = ?
        ");
        if ($docUp) {
            $pid = intval($packageId);
            $docUp->bind_param(
                'ssississii',
                $flyerFile,
                $flyerName,
                $flyerSize,
                $itineraryFile,
                $itineraryName,
                $itinerarySize,
                $detailFile,
                $detailFileName,
                $detailFileSize,
                $pid
            );
            if (!$docUp->execute()) {
                error_log("SMT product-register: docUp execute failed: " . $docUp->error);
            }
            $docUp->close();
        } else {
            error_log("SMT product-register: docUp prepare failed: " . $conn->error);
        }
    } catch (Throwable $e) {
        error_log("SMT product-register: doc update exception: " . $e->getMessage());
    }

    //    (admin_components)
    // - packages         
    try {
        if ($adminComponents !== null) {
            if (!__table_has_column($conn, 'packages', 'admin_components')) {
                $conn->query("ALTER TABLE packages ADD COLUMN admin_components VARCHAR(1024) NULL");
            }
            if (__table_has_column($conn, 'packages', 'admin_components')) {
                $stc = $conn->prepare("UPDATE packages SET admin_components = ? WHERE packageId = ?");
                if ($stc) {
                    $stc->bind_param('si', $adminComponents, $packageId);
                    $stc->execute();
                    $stc->close();
                }
            }
        }
    } catch (Throwable $e) {
        // ignore (non-critical)
    }

    // update      
    if ($packageId > 0) {
        //  "  " (    )
        $hasFlightPayload = $clearFlights
            || isset($_POST['departureFlightNumber'])
            || isset($_POST['returnFlightNumber'])
            || ($departureFlightNumber !== '')
            || ($returnFlightNumber !== '');
        if ($hasFlightPayload) {
            $conn->query("DELETE FROM package_flights WHERE package_id = " . intval($packageId));
        }
        $conn->query("DELETE FROM package_usage_guide WHERE package_id = " . intval($packageId));
        $conn->query("DELETE FROM package_pricing_options WHERE package_id = " . intval($packageId));
        $conn->query("DELETE FROM package_travel_costs WHERE package_id = " . intval($packageId));
    }

    //    (sales_start_date/sales_end_date)   ( DB  )
    if (__table_has_column($conn, 'packages', 'sales_start_date') && __table_has_column($conn, 'packages', 'sales_end_date')) {
        try {
            $st = $conn->prepare("UPDATE packages SET sales_start_date = ?, sales_end_date = ? WHERE packageId = ?");
            if ($st) {
                //   NULL 
                $ssd = ($salesStartDate !== null && trim((string)$salesStartDate) !== '') ? (string)$salesStartDate : null;
                $sed = ($salesEndDate !== null && trim((string)$salesEndDate) !== '') ? (string)$salesEndDate : null;
                $st->bind_param('ssi', $ssd, $sed, $packageId);
                $st->execute();
                $st->close();
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    // /() : attractions schedules   
    $delAttr = $conn->prepare("DELETE a FROM package_attractions a JOIN package_schedules s ON a.schedule_id = s.schedule_id WHERE s.package_id = ?");
    if ($delAttr) {
        $delAttr->bind_param('i', $packageId);
        $delAttr->execute();
        $delAttr->close();
    }
    $delSch = $conn->prepare("DELETE FROM package_schedules WHERE package_id = ?");
    if ($delSch) {
        $delSch->bind_param('i', $packageId);
        $delSch->execute();
        $delSch->close();
    }

    //    -
    if (!$clearFlights && !empty($departureFlightNumber)) {
        $stmt = $conn->prepare("
            INSERT INTO package_flights (
                package_id,
                flight_type,
                flight_number,
                airline_name,
                departure_time,
                arrival_time,
                departure_point,
                destination
            ) VALUES (?, 'departure', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "issssss",
            $packageId,
            $departureFlightNumber,
            $departureFlightAirline,
            $departureFlightDepartureTime,
            $departureFlightArrivalTime,
            $departureFlightDeparturePoint,
            $departureFlightDestination
        );
        $stmt->execute();
        $stmt->close();
    }

    //    -
    if (!$clearFlights && !empty($returnFlightNumber)) {
        $stmt = $conn->prepare("
            INSERT INTO package_flights (
                package_id,
                flight_type,
                flight_number,
                airline_name,
                departure_time,
                arrival_time,
                departure_point,
                destination
            ) VALUES (?, 'return', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "issssss",
            $packageId,
            $returnFlightNumber,
            $returnFlightAirline,
            $returnFlightDepartureTime,
            $returnFlightArrivalTime,
            $returnFlightDeparturePoint,
            $returnFlightDestination
        );
        $stmt->execute();
        $stmt->close();
    }

    //    
    $stmt = $conn->prepare("
        INSERT INTO package_travel_costs (
            package_id,
            airfare_cost,
            accommodation_cost,
            meal_cost,
            guide_cost,
            vehicle_cost,
            entrance_cost,
            other_cost,
            total_cost
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "idddddddd",
        $packageId,
        $airfareCost,
        $accommodationCost,
        $mealCost,
        $guideCost,
        $vehicleCost,
        $entranceCost,
        $otherCost,
        $totalCost
    );
    $stmt->execute();
    $stmt->close();

    //   
    $accommodationDescription = $_POST['accommodation_description'] ?? '';
    $transportationDescription = $_POST['transportation_description'] ?? '';

    // package_schedules      
    $stmt = $conn->prepare("
        INSERT INTO package_schedules (
            package_id,
            day_number,
            description,
            start_time,
            end_time,
            airport_location,
            airport_address,
            airport_description,
            airport_image,
            accommodation_name,
            accommodation_address,
            accommodation_description,
            accommodation_image,
            transportation_description,
            breakfast,
            lunch,
            dinner
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    //    
    $stmtAttr = $conn->prepare("
        INSERT INTO package_attractions (
            schedule_id,
            attraction_name,
            attraction_address,
            attraction_description,
            attraction_image,
            visit_order
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($daySchedules as $dayNum => $dayData) {
        // day-level(   schedule airport_* ' ' )
        $dayAirportDescription = $_POST["day{$dayNum}_airport_description"] ?? '';

        //     ( )
        $dayAirportImage = uploadImage("day{$dayNum}_airportImage");
        if ($dayAirportImage === null) {
            $dayAirportImage = ensureExistingAssetSaved((string)($dayData['airport_image_existing'] ?? ''), 'image');
        }

        //   
        $dayAccommodationImage = uploadImage("day{$dayNum}_accommodationImage");
        if ($dayAccommodationImage === null) {
            $dayAccommodationImage = ensureExistingAssetSaved((string)($dayData['accommodation_image_existing'] ?? ''), 'image');
        }

        $stmt->bind_param(
            "iisssssssssssssss",
            $packageId,
            $dayNum,
            $dayData['description'],
            $dayData['start_time'],
            $dayData['end_time'],
            $dayData['airport_location'],
            $dayData['airport_address'],
            $dayAirportDescription,
            $dayAirportImage,
            $dayData['accommodation_name'],
            $dayData['accommodation_address'],
            $dayData['accommodation_description'],
            $dayAccommodationImage,
            $dayData['transportation_description'],
            $dayData['breakfast'],
            $dayData['lunch'],
            $dayData['dinner']
        );

        if (!$stmt->execute()) {
            throw new Exception(" {$dayNum}    : " . $stmt->error);
        }

        $scheduleId = intval($conn->insert_id);

        //   
        $items = $attractionsByDay[$dayNum] ?? [];
        if (!empty($items) && $stmtAttr) {
            $order = 1;
            foreach ($items as $it) {
                $name = sanitize_input($it['name'] ?? '');
                if ($name === '') continue;
                $addr = sanitize_input($it['address'] ?? '');
                $desc = (string)($it['descriptionHtml'] ?? '');
                $visitOrder = isset($it['order']) ? intval($it['order']) : $order;

                $img = null;
                $imageKey = (string)($it['imageKey'] ?? '');
                if ($imageKey !== '') {
                    $img = uploadImage($imageKey);
                }
                if ($img === null) {
                    $img = ensureExistingAssetSaved((string)($it['existingImage'] ?? ''), 'image');
                }

                $stmtAttr->bind_param(
                    "issssi",
                    $scheduleId,
                    $name,
                    $addr,
                    $desc,
                    $img,
                    $visitOrder
                );
                if (!$stmtAttr->execute()) {
                    throw new Exception(" {$dayNum}   : " . $stmtAttr->error);
                }
                $order++;
            }
        }
    }
    $stmt->close();
    if ($stmtAttr) $stmtAttr->close();

    //
    if (isset($_POST['optionName']) && is_array($_POST['optionName'])) {
        $optionNames = $_POST['optionName'];
        $optionPrices = $_POST['optionPrice'] ?? [];
        $optionB2bPrices = $_POST['optionB2bPrice'] ?? [];

        $stmt = $conn->prepare("
            INSERT INTO package_pricing_options (
                package_id,
                option_name,
                price,
                b2b_price
            ) VALUES (?, ?, ?, ?)
        ");

        for ($i = 0; $i < count($optionNames); $i++) {
            if (!empty($optionNames[$i])) {
                $optionName = sanitize_input($optionNames[$i]);
                $optionPrice = floatval($optionPrices[$i] ?? 0);
                $optionB2bPrice = isset($optionB2bPrices[$i]) && $optionB2bPrices[$i] !== '' ? floatval($optionB2bPrices[$i]) : null;

                $stmt->bind_param("isdd", $packageId, $optionName, $optionPrice, $optionB2bPrice);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    //   
    $usageGuide = $_POST['usage_guide'] ?? '';
    $cancellationPolicy = $_POST['cancellation_policy'] ?? '';
    $visaGuide = $_POST['visa_guide'] ?? '';

    if (!empty($usageGuide)) {
        $stmt = $conn->prepare("
            INSERT INTO package_usage_guide (package_id, guide_type, guide_description)
            VALUES (?, 'usage', ?)
        ");
        $stmt->bind_param("is", $packageId, $usageGuide);
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($cancellationPolicy)) {
        $stmt = $conn->prepare("
            INSERT INTO package_usage_guide (package_id, guide_type, guide_description)
            VALUES (?, 'cancellation', ?)
        ");
        $stmt->bind_param("is", $packageId, $cancellationPolicy);
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($visaGuide)) {
        $stmt = $conn->prepare("
            INSERT INTO package_usage_guide (package_id, guide_type, guide_description)
            VALUES (?, 'visa', ?)
        ");
        $stmt->bind_param("is", $packageId, $visaGuide);
        $stmt->execute();
        $stmt->close();
    }

    // 다중 숙소 저장 (package_accommodations)
    if (!empty($_POST['accommodationsJson'])) {
        $accommodationsData = json_decode((string)$_POST['accommodationsJson'], true);
        if (is_array($accommodationsData) && !empty($accommodationsData)) {
            // 기존 숙소 삭제
            $delAccom = $conn->prepare("DELETE FROM package_accommodations WHERE package_id = ?");
            if ($delAccom) {
                $delAccom->bind_param('i', $packageId);
                $delAccom->execute();
                $delAccom->close();
            }

            // 새 숙소 저장
            $stmtAccom = $conn->prepare("
                INSERT INTO package_accommodations (
                    package_id,
                    sort_order,
                    accommodation_name,
                    accommodation_address,
                    accommodation_description,
                    accommodation_image
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            if ($stmtAccom) {
                foreach ($accommodationsData as $idx => $accom) {
                    $sortOrder = intval($accom['sortOrder'] ?? $idx);
                    $accomName = sanitize_input($accom['name'] ?? '');
                    $accomAddress = sanitize_input($accom['address'] ?? '');
                    $accomDescription = (string)($accom['description'] ?? '');

                    // 이미지 처리
                    $accomImage = null;
                    if (!empty($accom['hasNewImage'])) {
                        $accomImage = uploadImage("accommodationImage_{$idx}");
                    }
                    if ($accomImage === null && !empty($accom['existingImage'])) {
                        $accomImage = ensureExistingAssetSaved((string)$accom['existingImage'], 'image');
                    }

                    $stmtAccom->bind_param(
                        'iissss',
                        $packageId,
                        $sortOrder,
                        $accomName,
                        $accomAddress,
                        $accomDescription,
                        $accomImage
                    );
                    if (!$stmtAccom->execute()) {
                        error_log("숙소 저장 실패: " . $stmtAccom->error);
                    }
                }
                $stmtAccom->close();
            }
        }
    }

    //   (package_available_dates)  (upsert)
    if (!empty($availabilityRows)) {
        $stmtAvail = $conn->prepare("
            INSERT INTO package_available_dates (package_id, available_date, price, b2b_price, capacity, status)
            VALUES (?, ?, ?, ?, ?, 'open')
            ON DUPLICATE KEY UPDATE
                price = VALUES(price),
                b2b_price = VALUES(b2b_price),
                capacity = VALUES(capacity),
                status = 'open'
        ");
        if (!$stmtAvail) throw new Exception('    .');
        foreach ($availabilityRows as $row) {
            $d = $row['date'];
            $seats = intval($row['seats']);
            $price = floatval($row['price']);
            $b2bPrice = $row['b2bPrice'];
            $stmtAvail->bind_param('isddi', $packageId, $d, $price, $b2bPrice, $seats);
            if (!$stmtAvail->execute()) {
                throw new Exception('   : ' . $stmtAvail->error);
            }
        }
        $stmtAvail->close();

        // UI   (  )
        $dates = array_map(fn($r) => $r['date'], $availabilityRows);
        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $types = 'i' . str_repeat('s', count($dates));
        $params = array_merge([$packageId], $dates);
        $sqlCleanup = "DELETE FROM package_available_dates
                       WHERE package_id = ?
                         AND available_date NOT IN ($placeholders)
                         AND (booked_seats IS NULL OR booked_seats = 0)";
        $stmtCl = $conn->prepare($sqlCleanup);
        if ($stmtCl) {
            $refs = [];
            $refs[] = &$types;
            foreach ($params as $k => $v) $refs[] = &$params[$k];
            call_user_func_array([$stmtCl, 'bind_param'], $refs);
            $stmtCl->execute();
            $stmtCl->close();
        }
    }

    // NOTE( ):
    // - flight  origin/flightName/flightCode/  NOT NULL  ,
    //         INSERT    .
    // -   "  " package_available_dates ,
    //    / product_availability.php package_available_dates  .
    // - ,   flight row   / (UPDATE)  .
    if (!empty($availabilityRows) && table_exists_safe($conn, 'flight')) {
        $landPrice = floatval($adultPrice ?? 0);
        $stmtFind = $conn->prepare("SELECT flightId FROM flight WHERE packageId = ? AND DATE(flightDepartureDate) = ? LIMIT 1");
        $stmtUpd = $conn->prepare("UPDATE flight SET availSeats = ?, flightPrice = ?, landPrice = ?, is_active = 1 WHERE flightId = ?");
        if ($stmtFind && $stmtUpd) {
            foreach ($availabilityRows as $row) {
                $d = (string)($row['date'] ?? '');
                if ($d === '') continue;
                $seat = intval($row['seats'] ?? 0);
                $fare = floatval($row['price'] ?? 0);
                $stmtFind->bind_param('is', $packageId, $d);
                $stmtFind->execute();
                $rs = $stmtFind->get_result();
                $found = $rs ? $rs->fetch_assoc() : null;
                $fid = intval($found['flightId'] ?? 0);
                if ($fid > 0) {
                    $stmtUpd->bind_param('iddi', $seat, $fare, $landPrice, $fid);
                    $stmtUpd->execute();
                }
            }
        }
        if ($stmtFind) $stmtFind->close();
        if ($stmtUpd) $stmtUpd->close();
    }

    // pricingOptions( 1 )  flight.landPrice (   )
    if (table_exists_safe($conn, 'flight')) {
        try {
            $landPrice = floatval($adultPrice ?? 0);
            $st = $conn->prepare("UPDATE flight SET landPrice = ? WHERE packageId = ?");
            if ($st) {
                $st->bind_param('di', $landPrice, $packageId);
                $st->execute();
                $st->close();
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    // NOTE: Upload Flyer/Detail/Itinerary   .
    //   (package_file)   ,  API     / .

    //  
    $conn->commit();

    send_json_response([
        'success' => true,
        'message' => ($packageId > 0 ? '  .' : '  .'),
        'packageId' => $packageId
    ]);

} catch (Exception $e) {
    //  
    if ($conn) {
        $conn->rollback();
    }

    error_log('Product registration error: ' . $e->getMessage());
    send_json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>
