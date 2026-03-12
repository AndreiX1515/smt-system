<?php
/**
 *   API
 *     
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../conn.php';
require_once '../i18n_helper.php';

// POST  
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? 'getByPackage';
$packageId = $input['packageId'] ?? $input['package_id'] ?? null;
$bookingId = $input['bookingId'] ?? $input['booking_id'] ?? null;
//    (URL   POST )
$lang = $input['lang'] ?? $_GET['lang'] ?? getCurrentLanguage();

try {
    if ($action === 'getByPackage') {
        if (!$packageId) {
            echo json_encode([
                'success' => false,
                'message' => 'Package ID .'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // package_options    
        $tableCheck = $conn->query("SHOW TABLES LIKE 'package_options'");
        if ($tableCheck->num_rows === 0) {
            //     
            echo json_encode([
                'success' => true,
                'options' => [],
                'selectedOptions' => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // optionCategory    
        $columnCheck = $conn->query("SHOW COLUMNS FROM package_options LIKE 'optionCategory'");
        $hasCategoryColumn = $columnCheck->num_rows > 0;
        
        //     
        $langColumnCheck = $conn->query("SHOW COLUMNS FROM package_options LIKE 'optionNameEn'");
        $hasLangColumns = $langColumnCheck->num_rows > 0;
        
        // package_options      
        if ($hasCategoryColumn) {
            if ($hasLangColumns) {
                //       
                $sql = "SELECT optionId, optionName, optionNameEn, optionNameTl, optionDescription, optionPrice, 
                               optionCategory, isRequired, maxQuantity
                        FROM package_options 
                        WHERE packageId = ? AND isAvailable = 1
                        ORDER BY optionCategory, optionName";
            } else {
                $sql = "SELECT optionId, optionName, optionDescription, optionPrice, 
                               optionCategory, isRequired, maxQuantity
                        FROM package_options 
                        WHERE packageId = ? AND isAvailable = 1
                        ORDER BY optionCategory, optionName";
            }
        } else {
            // optionCategory   
            if ($hasLangColumns) {
                $sql = "SELECT optionId, optionName, optionNameEn, optionNameTl, optionDescription, optionPrice, 
                               isRequired, maxQuantity
                        FROM package_options 
                        WHERE packageId = ? AND isAvailable = 1
                        ORDER BY optionName";
            } else {
                $sql = "SELECT optionId, optionName, optionDescription, optionPrice, 
                               isRequired, maxQuantity
                        FROM package_options 
                        WHERE packageId = ? AND isAvailable = 1
                        ORDER BY optionName";
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('  : ' . $conn->error);
        }
        
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        error_log("Package options API - Package ID: " . $packageId);
        error_log("Package options API -   : " . $result->num_rows);
        
        $options = [];
        while ($row = $result->fetch_assoc()) {
            error_log("Package options API -  : " . json_encode($row, JSON_UNESCAPED_UNICODE));
            // category   (baggage, meal, wifi )
            $category = '';
            if ($hasCategoryColumn && isset($row['optionCategory'])) {
                $category = $row['optionCategory'];
            }
            
            if (empty($category)) {
                // optionName  
                $name = strtolower($row['optionName']);
                if (strpos($name, '') !== false || strpos($name, 'baggage') !== false || strpos($name, 'luggage') !== false || strpos($name, '') !== false) {
                    $category = 'baggage';
                } elseif (strpos($name, '') !== false || strpos($name, 'breakfast') !== false || strpos($name, '') !== false || strpos($name, 'meal') !== false) {
                    $category = 'meal';
                } elseif (strpos($name, '') !== false || strpos($name, 'wifi') !== false) {
                    $category = 'wifi';
                } else {
                    $category = 'other';
                }
            }
            
            $options[] = [
                'id' => (string)$row['optionId'],
                'category' => $category,
                'name' => getOptionNameLocalized($row, $lang),
                'description' => $row['optionDescription'] ?? '',
                'price' => floatval($row['optionPrice']),
                'isRequired' => isset($row['isRequired']) ? (bool)$row['isRequired'] : false,
                'maxQuantity' => isset($row['maxQuantity']) ? intval($row['maxQuantity']) : 1
            ];
        }
        $stmt->close();
        
        // bookingId        
        $selectedOptions = [];
        if ($bookingId) {
            $bookingSql = "SELECT selectedOptions FROM bookings WHERE bookingId = ?";
            $bookingStmt = $conn->prepare($bookingSql);
            if ($bookingStmt) {
                $bookingStmt->bind_param('s', $bookingId);
                $bookingStmt->execute();
                $bookingResult = $bookingStmt->get_result();
                if ($bookingRow = $bookingResult->fetch_assoc()) {
                    if ($bookingRow['selectedOptions']) {
                        $selectedData = json_decode($bookingRow['selectedOptions'], true);
                        if (is_array($selectedData) && isset($selectedData['selectedOptions'])) {
                            $selectedOptions = $selectedData['selectedOptions'];
                        }
                    }
                }
                $bookingStmt->close();
            }
        }
        
        error_log("Package options API -    : " . count($options));
        error_log("Package options API -  : " . json_encode($options, JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'success' => true,
            'options' => $options,
            'selectedOptions' => $selectedOptions
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => '  .'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("Package options API error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Package ID: " . ($packageId ?? 'N/A'));
    error_log("Booking ID: " . ($bookingId ?? 'N/A'));
    
    //      
    $errorMessage = $e->getMessage();
    if (strpos($errorMessage, 'Unknown column') !== false || strpos($errorMessage, 'doesn\'t exist') !== false) {
        //        
        echo json_encode([
            'success' => true,
            'options' => [],
            'selectedOptions' => [],
            'warning' => 'Table or column not found, returning empty options'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => '    : ' . $errorMessage,
            'error' => $errorMessage,
            'packageId' => $packageId,
            'bookingId' => $bookingId
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Error $e) {
    error_log("Package options API fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '     : ' . $e->getMessage(),
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

//     
function getOptionNameLocalized($row, $lang) {
    //       
    if (isset($row['optionNameEn']) && isset($row['optionNameTl'])) {
        switch ($lang) {
            case 'en':
                return !empty($row['optionNameEn']) ? $row['optionNameEn'] : $row['optionName'];
            case 'tl':
                return !empty($row['optionNameTl']) ? $row['optionNameTl'] : $row['optionName'];
            default:
                return $row['optionName'];
        }
    }
    
    // i18n_texts   
    global $conn;
    $optionId = $row['optionId'];
    $i18nKey = 'option_' . $optionId . '_name';
    
    $i18nSql = "SELECT textValue FROM i18n_texts WHERE textKey = ? AND languageCode = ?";
    $i18nStmt = $conn->prepare($i18nSql);
    if ($i18nStmt) {
        $i18nStmt->bind_param("ss", $i18nKey, $lang);
        $i18nStmt->execute();
        $i18nResult = $i18nStmt->get_result();
        if ($i18nResult->num_rows > 0) {
            $translatedName = $i18nResult->fetch_assoc()['textValue'];
            $i18nStmt->close();
            return $translatedName;
        }
        $i18nStmt->close();
    }
    
    //    optionName 
    return $row['optionName'];
}
?>

