<?php
/**
 * Bulk Airline Ticket Parser
 * OCR-based PDF parsing to extract passenger names and add-ons
 */

/**
 * Parse a multi-passenger airline ticket PDF via OCR
 *
 * @param string $pdfPath Absolute path to PDF file
 * @return array ['success', 'passengers', 'bookingRef', 'flights', 'rawText', 'airline', 'bookingDate']
 */
function parseTicketPdf($pdfPath) {
    if (!file_exists($pdfPath)) {
        return ['success' => false, 'message' => 'PDF file not found'];
    }

    // Check tools
    $pdftoppm = trim(shell_exec('which pdftoppm 2>/dev/null'));
    $tesseract = trim(shell_exec('which tesseract 2>/dev/null'));
    if (!$pdftoppm || !$tesseract) {
        return ['success' => false, 'message' => 'OCR tools (pdftoppm/tesseract) not available on server'];
    }

    // Create temp directory
    $tmpDir = sys_get_temp_dir() . '/ticket_ocr_' . uniqid();
    mkdir($tmpDir, 0755, true);

    try {
        // Get page count (roughly) - convert all pages
        $cmd = escapeshellarg($pdftoppm) . ' -r 200 -png '
             . escapeshellarg($pdfPath) . ' '
             . escapeshellarg($tmpDir . '/page');
        exec($cmd . ' 2>/dev/null', $output, $retCode);

        // Find generated page images
        $pageFiles = glob($tmpDir . '/page-*.png');
        sort($pageFiles);

        if (empty($pageFiles)) {
            return ['success' => false, 'message' => 'Failed to render PDF pages'];
        }

        // OCR each page
        $fullText = '';
        $pageTexts = [];
        foreach ($pageFiles as $pageFile) {
            $cmd = escapeshellarg($tesseract) . ' ' . escapeshellarg($pageFile) . ' - 2>/dev/null';
            $pageText = shell_exec($cmd);
            $pageTexts[] = $pageText;
            $fullText .= $pageText . "\n\n";
        }

        // Extract QR code from page 1 (Cebu Pacific has QR in top-right area)
        $qrCodeBase64 = extractQrCodeFromPage($pageFiles[0] ?? '');

        // Extract airline logo from page 1 (AirAsia has logo in top-left area)
        $logoBase64 = extractLogoFromPage($pageFiles[0] ?? '');

        // Detect airline
        $airline = detectAirline($fullText);

        // Parse booking reference
        $bookingRef = parseBookingRef($fullText, $airline);

        // Parse booking date
        $bookingDate = parseBookingDate($fullText, $airline);

        // Parse flight info (airline-specific)
        $flights = ($airline === 'airasia')
            ? parseFlightInfoAirAsia($fullText)
            : parseFlightInfo($pageTexts[0] ?? '');

        // Parse passengers (airline-specific)
        $passengers = ($airline === 'airasia')
            ? parsePassengersAirAsia($fullText)
            : parsePassengers($fullText);

        return [
            'success' => true,
            'passengers' => $passengers,
            'bookingRef' => $bookingRef,
            'flights' => $flights,
            'rawText' => $fullText,
            'pageCount' => count($pageFiles),
            'airline' => $airline,
            'bookingDate' => $bookingDate,
            'qrCodeBase64' => $qrCodeBase64,
            'logoBase64' => $logoBase64
        ];

    } finally {
        // Cleanup temp files
        $files = glob($tmpDir . '/*');
        foreach ($files as $f) @unlink($f);
        @rmdir($tmpDir);
    }
}

/**
 * Detect airline from OCR text
 */
function detectAirline($text) {
    $lower = strtolower($text);

    if (strpos($lower, 'airasia') !== false || strpos($lower, 'air asia') !== false) {
        return 'airasia';
    }
    if (strpos($lower, 'cebu pacific') !== false) {
        return 'cebu_pacific';
    }

    // Detect by flight number prefix
    if (preg_match('/\bZ2\s?\d{2,4}\b/', $text)) {
        return 'airasia';
    }
    if (preg_match('/\b5J\s?\d{2,4}\b/', $text)) {
        return 'cebu_pacific';
    }

    return 'unknown';
}

/**
 * Parse booking reference
 */
function parseBookingRef($text, $airline) {
    if ($airline === 'airasia') {
        // AirAsia: "Booking no." followed by the ref on the next line
        if (preg_match('/Booking\s*no\.?\s*\n\s*([A-Z0-9]{5,8})/i', $text, $m)) {
            return trim($m[1]);
        }
    }

    // Cebu Pacific / generic
    if (preg_match('/([A-Z0-9]{5,8})\s*(?:Scan QR|$)/m', $text, $m)) {
        return $m[1];
    }
    if (preg_match('/(?:BOOKING\s*REFERENCE|Reference\s*No)[.\s:]*([A-Z0-9]{5,8})/i', $text, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * Parse booking date from OCR text
 */
function parseBookingDate($text, $airline) {
    if ($airline === 'airasia') {
        // "Booking date: 08 Aug 2025"
        if (preg_match('/Booking\s*date[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i', $text, $m)) {
            return trim($m[1]);
        }
    }

    if ($airline === 'cebu_pacific') {
        // "BOOKING DATE" header then date on next line like "May 2, 2025"
        // May be on same line as booking ref: "May 2, 2025 BFC4NS"
        if (preg_match('/BOOKING\s*DATE.*?\n\s*(\w+\s+\d{1,2},?\s+\d{4})/i', $text, $m)) {
            return trim($m[1]);
        }
    }

    // Generic fallback
    if (preg_match('/(?:Booking\s*date|BOOKING\s*DATE)[:\s]*(\d{1,2}[\s\/\-]\w+[\s\/\-]\d{4}|\w+\s+\d{1,2},?\s+\d{4})/i', $text, $m)) {
        return trim($m[1]);
    }

    return '';
}

/**
 * Parse flight details from first page text (Cebu Pacific format)
 */
function parseFlightInfo($text) {
    $flights = [];

    // Match flight number pattern: "FLIGHT NO. 5J 188" or "5J188"
    // OCR may misread "5J" as "5)" or "5j"
    if (preg_match_all('/FLIGHT\s*NO[.\s:]*(\d?[A-Z)]{1,2}\s?\d{2,4})/i', $text, $m)) {
        foreach ($m[1] as $fn) {
            $fn = preg_replace('/\s+/', '', $fn);
            // Fix OCR misreads: "5)" -> "5J"
            $fn = str_replace(')', 'J', $fn);
            $flights[] = strtoupper($fn);
        }
    }

    // Parse departure/arrival info
    $flightDetails = [];

    // MNL - ICN block
    if (preg_match('/MNL\s*[-–]\s*ICN.*?(\d{1,2}\s+\w+\s+\d{4})\s*.*?(\d{1,2}:\d{2}\s*[AP]M).*?(\d{1,2}\s+\w+\s+\d{4})\s*.*?(\d{1,2}:\d{2}\s*[AP]M)/si', $text, $m)) {
        $flightDetails['departure'] = [
            'route' => 'MNL → ICN',
            'depDate' => trim($m[1]),
            'depTime' => trim($m[2]),
            'arrDate' => trim($m[3]),
            'arrTime' => trim($m[4]),
            'flightNo' => $flights[0] ?? ''
        ];
    }

    // ICN - MNL block
    if (preg_match('/ICN\s*[-–]\s*MNL.*?(\d{1,2}\s+\w+\s+\d{4})\s*.*?(\d{1,2}:\d{2}\s*[AP]M).*?(\d{1,2}\s+\w+\s+\d{4})\s*.*?(\d{1,2}:\d{2}\s*[AP]M)/si', $text, $m)) {
        $flightDetails['return'] = [
            'route' => 'ICN → MNL',
            'depDate' => trim($m[1]),
            'depTime' => trim($m[2]),
            'arrDate' => trim($m[3]),
            'arrTime' => trim($m[4]),
            'flightNo' => $flights[1] ?? ''
        ];
    }

    return [
        'flightNumbers' => $flights,
        'details' => $flightDetails
    ];
}

/**
 * Parse AirAsia flight info
 * OCR may misread "Z2" as "72" or "22", times may be split across lines
 */
function parseFlightInfoAirAsia($text) {
    $flights = [];
    $flightDetails = [];

    // Extract flight numbers from "Philippines AirAsia, Z2/72/22 XXX" pattern
    // OCR commonly misreads "Z2" as "72" or "22"
    if (preg_match_all('/Philippines\s+AirAsia\s*[,.]?\s*(?:Z2|72|22)\s?(\d{2,4})/i', $text, $m)) {
        foreach ($m[1] as $num) {
            $flights[] = 'Z2' . $num;
        }
        $flights = array_unique($flights);
        $flights = array_values($flights);
    }
    // Fallback: direct Z2 pattern
    if (empty($flights) && preg_match_all('/\bZ2\s?(\d{2,4})\b/', $text, $m)) {
        foreach ($m[1] as $num) {
            $flights[] = 'Z2' . $num;
        }
        $flights = array_unique($flights);
        $flights = array_values($flights);
    }

    // Depart info: "Depart: Wednesday, 11 March 2026"
    if (preg_match('/Depart[:\s]+\w+day,?\s*(\d{1,2}\s+\w+\s+\d{4})/i', $text, $m)) {
        $depDate = trim($m[1]);

        // Try two-time pattern on same line: "07:00  11:55  3h 55m  Non-stop"
        $depTime = '';
        $arrTime = '';
        $duration = '';

        if (preg_match('/(\d{2}:\d{2})\s+(\d{2}:\d{2})\s+(\d+h\s*\d+m)\s+Non[- ]?stop/i', $text, $tm)) {
            $depTime = $tm[1];
            $arrTime = $tm[2];
            $duration = $tm[3];
        } else {
            // Fallback: find time near "Depart" section, and duration separately
            // OCR often puts departure time on its own line
            $depPos = strpos($text, $m[0]);
            $depSection = substr($text, $depPos, 500);
            if (preg_match('/(\d{2}:\d{2})/', $depSection, $tm)) {
                $depTime = $tm[1];
            }
            // Find "Xh Ym Non-stop" near the depart section in the first page
            if (preg_match('/(\d+h\s*\d+m)\s+Non[- ]?stop/i', $text, $tm)) {
                $duration = $tm[1];
            }
        }

        $flightDetails['departure'] = [
            'route' => 'MNL → ICN',
            'depDate' => $depDate,
            'depTime' => $depTime,
            'arrTime' => $arrTime,
            'duration' => $duration,
            'flightNo' => $flights[0] ?? '',
            'airline' => 'Philippines AirAsia'
        ];
    }

    // Return info: "Return: Sunday, 15 March 2026"
    if (preg_match('/Return[:\s]+\w+day,?\s*(\d{1,2}\s+\w+\s+\d{4})/i', $text, $m)) {
        $retDate = trim($m[1]);
        $retDepTime = '';
        $retArrTime = '';
        $retDuration = '';

        // Find return time block: "@ 13:00 16:15 4h 15m" or "13:00  16:15  4h 15m"
        // Search near the Return section
        $returnPos = strpos($text, 'Return : Philippines AirAsia');
        if ($returnPos === false) $returnPos = strpos($text, $m[0]);
        if ($returnPos !== false) {
            $returnText = substr($text, $returnPos, 500);
            if (preg_match('/@?\s*(\d{2}:\d{2})\s+(\d{2}:\d{2})\s+(\d+h\s*\d+m)/i', $returnText, $tm)) {
                $retDepTime = $tm[1];
                $retArrTime = $tm[2];
                $retDuration = $tm[3];
            }
        }

        $flightDetails['return'] = [
            'route' => 'ICN → MNL',
            'depDate' => $retDate,
            'depTime' => $retDepTime,
            'arrTime' => $retArrTime,
            'duration' => $retDuration,
            'flightNo' => $flights[1] ?? '',
            'airline' => 'Philippines AirAsia'
        ];
    }

    // Departure summary line: "Departure: Wed, Mar 11 • Return: Sun, Mar 15"
    // OCR may use * instead of •
    $departureSummary = '';
    $returnSummary = '';
    if (preg_match('/Departure:\s*(.+?)\s*[•·*]\s*Return:\s*(.+)/i', $text, $m)) {
        $departureSummary = trim($m[1]);
        $returnSummary = trim($m[2]);
    }

    return [
        'flightNumbers' => $flights,
        'details' => $flightDetails,
        'departureSummary' => $departureSummary,
        'returnSummary' => $returnSummary
    ];
}

/**
 * Parse AirAsia passengers from "Guest details" section
 * Format: "MARITES CALIBJO (adult)"
 */
function parsePassengersAirAsia($text) {
    $passengers = [];

    // Normalize text
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Extract guest details: "NAME (type)" pattern
    // Use [A-Z ] (space only, not \s) to avoid spanning across lines
    preg_match_all('/\b([A-Z][A-Z ]{2,}?)\s*\((adult|child|infant)\)/i', $text, $matches, PREG_SET_ORDER);

    // Known header words to filter out from names
    $headerWords = ['GUEST', 'DETAILS', 'FLIGHT', 'SUMMARY', 'ADD', 'ONS', 'NAME'];

    $guestNames = [];
    foreach ($matches as $m) {
        $name = strtoupper(trim(preg_replace('/\s+/', ' ', $m[1])));
        $type = ucfirst(strtolower(trim($m[2])));

        // Skip if name contains header words
        $words = explode(' ', $name);
        $isHeader = false;
        foreach ($words as $w) {
            if (in_array($w, $headerWords)) {
                // If name starts with a header word, strip it
                $name = trim(preg_replace('/^(GUEST\s+DETAILS\s+|FLIGHT\s+SUMMARY\s+|NAME\s+)/i', '', $name));
                if (empty($name) || strlen($name) < 3) {
                    $isHeader = true;
                }
                break;
            }
        }
        if ($isHeader) continue;
        if (strlen($name) < 3) continue;

        // Avoid duplicates
        $key = $name . '_' . $type;
        if (!isset($guestNames[$key])) {
            $guestNames[$key] = ['name' => $name, 'type' => $type];
        }
    }

    // Parse add-ons sections
    // Segments: "Manila to Seoul" (outbound), "Seoul - Incheon to Manila" (return)
    $knownNames = array_column($guestNames, 'name');
    $addOnsData = parseAirAsiaAddOns($text, $knownNames);

    // Build passenger list
    foreach ($guestNames as $guest) {
        $name = $guest['name'];
        $type = $guest['type'];

        $outbound = $addOnsData['outbound'][$name] ?? [];
        $returnData = $addOnsData['return'][$name] ?? [];

        $passengers[] = [
            'title' => '',
            'name' => $name,
            'type' => $type,
            'outbound' => $outbound,
            'return' => $returnData
        ];
    }

    return $passengers;
}

/**
 * Try to split a line that may contain two passenger names from a 2-column OCR layout
 * e.g., "MARITES CALIBJO EVANGELINE BELMONTE" -> ["MARITES CALIBJO", "EVANGELINE BELMONTE"]
 */
function splitTwoColumnNames($line, $knownNames = []) {
    $line = trim(preg_replace('/\s+/', ' ', $line));

    // Try matching against known names
    if (!empty($knownNames)) {
        foreach ($knownNames as $name) {
            if (strpos($line, $name) === 0 && strlen($line) > strlen($name) + 2) {
                $remainder = trim(substr($line, strlen($name)));
                if (!empty($remainder) && preg_match('/^[A-Z][A-Z ]{2,}$/', $remainder)) {
                    // Check if remainder is also a known name
                    foreach ($knownNames as $name2) {
                        if ($remainder === $name2) {
                            return [$name, $name2];
                        }
                    }
                    // Even if not exact match, return both parts
                    return [$name, $remainder];
                }
            }
        }
    }

    // Fallback: treat as single name
    return [$line];
}

/**
 * Parse AirAsia Add-ons sections for baggage and seat info
 */
function parseAirAsiaAddOns($text, $knownNames = []) {
    $result = ['outbound' => [], 'return' => []];

    // Normalize
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Find Add-ons section
    $addonsPos = strpos($text, 'Add-ons');
    if ($addonsPos === false) $addonsPos = strpos($text, 'ADD-ONS');
    if ($addonsPos === false) $addonsPos = strpos($text, 'add-ons');
    if ($addonsPos === false) return $result;

    $addonsText = substr($text, $addonsPos);

    // Split by segment headers
    // "Manila to Seoul - Incheon" or "Manila to Seoul" (outbound)
    // "Seoul - Incheon to Manila" or "Seoul to Manila" (return)
    $segments = preg_split('/(Manila\s+to\s+Seoul(?:\s*[-–]\s*Incheon)?|Seoul\s*[-–]?\s*Incheon\s+to\s+Manila)/i', $addonsText, -1, PREG_SPLIT_DELIM_CAPTURE);

    $currentSegment = null;
    for ($i = 0; $i < count($segments); $i++) {
        $seg = trim($segments[$i]);
        if (preg_match('/Manila\s+to\s+Seoul/i', $seg)) {
            $currentSegment = 'outbound';
            continue;
        }
        if (preg_match('/Seoul.*to\s+Manila/i', $seg)) {
            $currentSegment = 'return';
            continue;
        }
        if ($currentSegment === null) continue;

        // Parse passenger blocks in this segment
        // Each passenger: NAME on its own line, then baggage/seat lines
        // OCR may put two names on one line: "MARITES CALIBJO EVANGELINE BELMONTE"
        $lines = array_values(array_filter(array_map('trim', explode("\n", $seg))));

        $currentNames = []; // may be 1 or 2 names on same line (2-column layout)
        $currentAddOns = [];

        foreach ($lines as $line) {
            // Skip empty lines and noise
            if (empty($line) || strlen($line) < 3) continue;
            // Skip known noise
            if (preg_match('/^(Non[- ]?stop|GMT|MNL|ICN|Economy|@)/i', $line)) continue;

            // Detect passenger name line (all uppercase, at least 2 words)
            // May contain two names from 2-column OCR layout
            $cleanLine = preg_replace('/^[^A-Z]+/', '', $line); // strip leading OCR artifacts
            if (preg_match('/^[A-Z][A-Z ]{3,}$/', $cleanLine) && preg_match('/[A-Z]\s+[A-Z]/', $cleanLine)) {
                // Save previous passenger(s)
                foreach ($currentNames as $cn) {
                    if (!empty($currentAddOns)) {
                        $result[$currentSegment][$cn] = $currentAddOns;
                    }
                }

                // Try to split two names if this line has two known passenger names
                $currentNames = splitTwoColumnNames($cleanLine, $knownNames);
                $currentAddOns = [];
                continue;
            }

            if (empty($currentNames)) continue;

            // Checked baggage: "Checked baggage 20kg"
            if (preg_match('/Checked\s+baggage\s+(\d+)\s*kg/i', $line, $m)) {
                $currentAddOns['baggage'] = 'Checked baggage ' . $m[1] . 'kg';
            }
            // Carry-on: "7 kg Carry-on baggage"
            if (preg_match('/(\d+)\s*kg\s+Carry[- ]?on/i', $line, $m)) {
                $currentAddOns['carryon'] = $m[1] . 'kg carry-on';
            }
            // Seat: "Seat 22A" or "& Seat 22A" or "% Seat 22C" (OCR artifacts before Seat)
            if (preg_match('/Seat\s+(\d+[A-Z])/i', $line, $m)) {
                $currentAddOns['seat'] = $m[1];
            }
        }

        // Save last passenger(s) in segment
        foreach ($currentNames as $cn) {
            if (!empty($currentAddOns)) {
                $result[$currentSegment][$cn] = $currentAddOns;
            }
        }
    }

    return $result;
}

/**
 * Parse passenger blocks from OCR text (Cebu Pacific format)
 */
function parsePassengers($text) {
    $passengers = [];

    // Normalize text
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Split by NAME header to find passenger blocks
    // Pattern: "NAME" on its own line (or "NAME  FLIGHT  ADD-ONS" header)
    $blocks = preg_split('/\nNAME\b[^\n]*/i', $text);

    // Skip the first block (header area before first NAME)
    array_shift($blocks);

    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;

        $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));
        if (empty($lines)) continue;

        // First significant line should have the passenger name with title
        $name = '';
        $title = '';
        $type = 'Adult';
        $addOns = [];

        // Find the name line (starts with MR./MS./MRS./MSTR.)
        $nameLineIdx = -1;
        foreach ($lines as $idx => $line) {
            if (preg_match('/^(MR|MS|MRS|MSTR)[.\s]+(.+)/i', $line, $m)) {
                $title = strtoupper(trim($m[1]));
                // Name may be followed by route info on same line - extract just the name part
                $namePart = trim($m[2]);
                // Remove trailing route markers like "MNL ICN" or "MNL → ICN"
                $namePart = preg_replace('/\s+(MNL|ICN|CEB|DVO)\s+(ICN|MNL|CEB|DVO)\s*$/i', '', $namePart);
                $name = strtoupper(trim($namePart));
                $nameLineIdx = $idx;
                break;
            }
        }

        if (empty($name)) continue;

        // Find traveler type (Adult/Child/Infant) - usually the line right after name
        foreach ($lines as $idx => $line) {
            if ($idx <= $nameLineIdx) continue;
            if (preg_match('/^(Adult|Child|Infant)$/i', trim($line))) {
                $type = ucfirst(strtolower(trim($line)));
                break;
            }
        }

        // Extract add-ons (GO Basic, bag info, seat info)
        $outboundAddOns = [];
        $returnAddOns = [];
        $currentSegment = 'outbound';

        foreach ($lines as $idx => $line) {
            if ($idx <= $nameLineIdx) continue;

            // Detect segment switch
            if (preg_match('/ICN\s+MNL|ICN\s*→\s*MNL/i', $line)) {
                $currentSegment = 'return';
                continue;
            }
            if (preg_match('/MNL\s+ICN|MNL\s*→\s*ICN/i', $line)) {
                $currentSegment = 'outbound';
                continue;
            }

            // Parse add-on lines
            if (preg_match('/GO\s+(Basic|Lite|Plus|Flexi)/i', $line, $m)) {
                $addon = 'GO ' . ucfirst(strtolower($m[1]));
                if ($currentSegment === 'outbound') $outboundAddOns['fare'] = $addon;
                else $returnAddOns['fare'] = $addon;
            }
            if (preg_match('/(\d+)pc\s+checked\s+bag\s*\((\d+)kg\)/i', $line, $m)) {
                $addon = $m[1] . 'pc checked bag (' . $m[2] . 'kg)';
                if ($currentSegment === 'outbound') $outboundAddOns['baggage'] = $addon;
                else $returnAddOns['baggage'] = $addon;
            }
            if (preg_match('/Seat:\s*(.+)/i', $line, $m)) {
                $seat = trim($m[1]);
                if ($currentSegment === 'outbound') $outboundAddOns['seat'] = $seat;
                else $returnAddOns['seat'] = $seat;
            }
        }

        $passengers[] = [
            'title' => $title,
            'name' => $name,
            'type' => $type,
            'outbound' => $outboundAddOns,
            'return' => $returnAddOns
        ];
    }

    return $passengers;
}

/**
 * Match parsed passengers to DB booking_travelers
 *
 * @param mysqli $conn
 * @param array  $passengers  Parsed passenger array from parsePassengers()
 * @param string $departureDate
 * @param int    $packageId
 * @return array ['matched' => [bookingId => [...]], 'unmatched' => [...]]
 */
function matchPassengersToTravelers($conn, $passengers, $departureDate, $packageId) {
    // Fetch all travelers for this date + package
    $stmt = $conn->prepare("
        SELECT bt.bookingTravelerId, bt.transactNo AS bookingId,
               bt.firstName, bt.lastName, bt.travelerType,
               b.agentId,
               (SELECT a.username FROM accounts a WHERE a.accountId = b.agentId LIMIT 1) AS agentName
        FROM booking_travelers bt
        JOIN bookings b ON bt.transactNo = b.bookingId
        WHERE b.departureDate = ? AND b.packageId = ?
          AND b.bookingStatus IN ('confirmed','completed','pending')
        ORDER BY bt.transactNo, bt.bookingTravelerId
    ");
    $stmt->bind_param('si', $departureDate, $packageId);
    $stmt->execute();
    $travelers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Build normalized name map
    $travelerMap = [];
    foreach ($travelers as $t) {
        $normalized = strtoupper(trim($t['firstName'] . ' ' . $t['lastName']));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $travelerMap[] = array_merge($t, ['normalizedName' => $normalized]);
    }

    $matched = [];    // bookingId => [passengers with traveler info]
    $unmatched = [];
    $usedTravelerIds = [];

    foreach ($passengers as $pax) {
        $paxName = strtoupper(trim($pax['name']));
        $paxName = preg_replace('/\s+/', ' ', $paxName);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($travelerMap as $t) {
            if (in_array($t['bookingTravelerId'], $usedTravelerIds)) continue;

            $dbName = $t['normalizedName'];
            $score = 0;

            // Exact match
            if ($paxName === $dbName) {
                $score = 100;
            }
            // PDF name contains both firstName and lastName
            elseif (!empty(trim($t['firstName'])) && !empty(trim($t['lastName']))) {
                $fn = strtoupper(trim($t['firstName']));
                $ln = strtoupper(trim($t['lastName']));
                if (strpos($paxName, $fn) !== false && strpos($paxName, $ln) !== false) {
                    $score = 90;
                }
                // DB name contained in PDF name (handles middle names)
                elseif (strpos($paxName, $ln) !== false) {
                    // Last name match + first name partial
                    $fnParts = explode(' ', $fn);
                    if (strpos($paxName, $fnParts[0]) !== false) {
                        $score = 80;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $t;
            }
        }

        // Build display name
        $displayName = !empty($pax['title'])
            ? $pax['title'] . '. ' . $pax['name']
            : $pax['name'];

        if ($bestMatch && $bestScore >= 70) {
            $usedTravelerIds[] = $bestMatch['bookingTravelerId'];
            $bookingId = $bestMatch['bookingId'];
            if (!isset($matched[$bookingId])) {
                $matched[$bookingId] = [
                    'agentName' => $bestMatch['agentName'] ?? '',
                    'passengers' => []
                ];
            }
            $matched[$bookingId]['passengers'][] = [
                'parsedName' => $displayName,
                'parsedType' => $pax['type'],
                'outbound' => $pax['outbound'],
                'return' => $pax['return'],
                'bookingTravelerId' => $bestMatch['bookingTravelerId'],
                'dbFirstName' => $bestMatch['firstName'],
                'dbLastName' => $bestMatch['lastName'],
                'travelerType' => $bestMatch['travelerType'],
                'matchScore' => $bestScore
            ];
        } else {
            $unmatched[] = [
                'parsedName' => $displayName,
                'parsedType' => $pax['type'],
                'outbound' => $pax['outbound'],
                'return' => $pax['return']
            ];
        }
    }

    return ['matched' => $matched, 'unmatched' => $unmatched];
}

/**
 * Extract QR code from rendered PDF page image
 * Cebu Pacific places QR code in the top-right area of page 1
 *
 * @param string $pageImagePath Path to the rendered page PNG
 * @return string|null Base64-encoded PNG of the QR code, or null if extraction fails
 */
function extractQrCodeFromPage($pageImagePath) {
    if (empty($pageImagePath) || !file_exists($pageImagePath)) {
        return null;
    }

    // Check if ImageMagick convert is available
    $convert = trim(shell_exec('which convert 2>/dev/null'));
    if (!$convert) {
        // Fallback: try PHP GD
        return extractQrCodeWithGD($pageImagePath);
    }

    // Get image dimensions
    $identify = trim(shell_exec('which identify 2>/dev/null'));
    if (!$identify) return null;

    $info = shell_exec(escapeshellarg($identify) . ' -format "%wx%h" ' . escapeshellarg($pageImagePath) . ' 2>/dev/null');
    if (!preg_match('/(\d+)x(\d+)/', $info, $m)) return null;

    $imgW = (int)$m[1];
    $imgH = (int)$m[2];

    // QR code position: top-right area of Cebu Pacific itinerary
    // At 200 DPI (default render): page is ~1654x2339
    // At 300 DPI: page is ~2480x3509
    // QR is roughly at 74-85% from left, 20-28% from top
    $qrX = (int)($imgW * 0.74);
    $qrY = (int)($imgH * 0.20);
    $qrSize = (int)($imgW * 0.12);

    $tmpQr = sys_get_temp_dir() . '/qr_extract_' . uniqid() . '.png';

    // Crop QR region, resize to consistent 150x150, increase contrast
    $cmd = escapeshellarg($convert) . ' ' . escapeshellarg($pageImagePath)
         . ' -crop ' . $qrSize . 'x' . $qrSize . '+' . $qrX . '+' . $qrY
         . ' +repage -resize 150x150 -colorspace Gray -threshold 50%'
         . ' ' . escapeshellarg($tmpQr) . ' 2>/dev/null';
    exec($cmd, $out, $ret);

    if ($ret !== 0 || !file_exists($tmpQr)) {
        @unlink($tmpQr);
        return null;
    }

    $base64 = base64_encode(file_get_contents($tmpQr));
    @unlink($tmpQr);

    // Sanity check: QR images should have some data
    if (strlen($base64) < 100) {
        return null;
    }

    return $base64;
}

/**
 * Fallback: extract QR code using PHP GD
 */
function extractQrCodeWithGD($pageImagePath) {
    if (!function_exists('imagecreatefrompng')) return null;

    $src = @imagecreatefrompng($pageImagePath);
    if (!$src) return null;

    $imgW = imagesx($src);
    $imgH = imagesy($src);

    $qrX = (int)($imgW * 0.74);
    $qrY = (int)($imgH * 0.20);
    $qrSize = (int)($imgW * 0.12);

    $dst = imagecreatetruecolor(150, 150);
    imagecopyresampled($dst, $src, 0, 0, $qrX, $qrY, 150, 150, $qrSize, $qrSize);
    imagedestroy($src);

    ob_start();
    imagepng($dst);
    $pngData = ob_get_clean();
    imagedestroy($dst);

    $base64 = base64_encode($pngData);
    return strlen($base64) > 100 ? $base64 : null;
}

/**
 * Extract airline logo from rendered PDF page image
 * AirAsia places the circular red logo in the top-left area of page 1
 *
 * @param string $pageImagePath Path to the rendered page PNG
 * @return string|null Base64-encoded PNG of the logo, or null if extraction fails
 */
function extractLogoFromPage($pageImagePath) {
    if (empty($pageImagePath) || !file_exists($pageImagePath)) {
        return null;
    }

    $convert = trim(shell_exec('which convert 2>/dev/null'));
    $identify = trim(shell_exec('which identify 2>/dev/null'));
    if (!$convert || !$identify) return null;

    $info = shell_exec(escapeshellarg($identify) . ' -format "%wx%h" ' . escapeshellarg($pageImagePath) . ' 2>/dev/null');
    if (!preg_match('/(\d+)x(\d+)/', $info, $m)) return null;

    $imgW = (int)$m[1];
    $imgH = (int)$m[2];

    // AirAsia logo: top-left circular logo, roughly 0-20% from left, 0.5-14% from top
    $logoX = (int)($imgW * 0.004);
    $logoY = (int)($imgH * 0.005);
    $logoSize = (int)($imgW * 0.20);

    $tmpLogo = sys_get_temp_dir() . '/logo_extract_' . uniqid() . '.png';

    // Crop logo region, resize to 100x100 for template use
    $cmd = escapeshellarg($convert) . ' ' . escapeshellarg($pageImagePath)
         . ' -crop ' . $logoSize . 'x' . $logoSize . '+' . $logoX . '+' . $logoY
         . ' +repage -resize 100x100'
         . ' ' . escapeshellarg($tmpLogo) . ' 2>/dev/null';
    exec($cmd, $out, $ret);

    if ($ret !== 0 || !file_exists($tmpLogo)) {
        @unlink($tmpLogo);
        return null;
    }

    $base64 = base64_encode(file_get_contents($tmpLogo));
    @unlink($tmpLogo);

    return strlen($base64) > 100 ? $base64 : null;
}
