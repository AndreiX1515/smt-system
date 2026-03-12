<?php
/**
 *    
 * Internationalization Helper Functions
 */

//  
require_once 'conn.php';

//   
function getCurrentLanguage() {
    // URL   
    // NOTE:     (en), (ko)     .
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'tl'], true)) {
        $_SESSION['language'] = $_GET['lang'];
        return $_GET['lang'];
    }
    
    //   
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], ['en', 'tl'], true)) {
        return $_SESSION['language'];
    }
    
    //  
    $_SESSION['language'] = 'en';
    return 'en';
}

//   
function getI18nText($key, $lang = null) {
    global $conn;
    
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    //    
    static $textCache = [];
    $cacheKey = $lang . '_' . $key;
    
    if (isset($textCache[$cacheKey])) {
        return $textCache[$cacheKey];
    }
    
    //   
    $sql = "SELECT textValue FROM i18n_texts WHERE textKey = ? AND languageCode = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $key, $lang);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $text = $result->fetch_assoc()['textValue'];
        $textCache[$cacheKey] = $text;
        return $text;
    }
    
    //      
    if ($lang !== 'en') {
        return getI18nText($key, 'en');
    }
    
    //    
    return $key;
}

//    (HTML  )
function echoI18nText($key, $lang = null) {
    echo htmlspecialchars(getI18nText($key, $lang));
}

//    (HTML  )
function echoI18nTextHTML($key, $lang = null) {
    echo getI18nText($key, $lang);
}

//    
function getCategoryName($category, $lang = null) {
    global $conn;
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    //    
    $category = strtolower(trim($category));

    //   (product_main_categories)     
    // (:      )
    static $mainCategoryNameByCode = null;
    if ($mainCategoryNameByCode === null) {
        $mainCategoryNameByCode = [];
        try {
            $tbl = $conn->query("SHOW TABLES LIKE 'product_main_categories'");
            if ($tbl && $tbl->num_rows > 0) {
                $res = $conn->query("SELECT code, name FROM product_main_categories");
                while ($res && ($row = $res->fetch_assoc())) {
                    $code = strtolower(trim((string)($row['code'] ?? '')));
                    $name = trim((string)($row['name'] ?? ''));
                    if ($code !== '' && $name !== '') {
                        $mainCategoryNameByCode[$code] = $name;
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore and fallback to hardcoded map
        }
    }
    if ($category !== '' && isset($mainCategoryNameByCode[$category])) {
        return $mainCategoryNameByCode[$category];
    }
    
    $categoryNames = [
        'ko' => [
            'season' => '',
            'region' => '', 
            'theme' => '',
            'private' => ''
        ],
        'en' => [
            'season' => 'Seasonal',
            'region' => 'Regional', 
            'theme' => 'Themed',
            'private' => 'Private'
        ],
        'tl' => [
            'season' => 'Pana-panahon',
            'region' => 'Rehiyonal', 
            'theme' => 'May Tema',
            'private' => 'Pribado'
        ]
    ];
    
    return $categoryNames[$lang][$category] ?? $category;
}

//    
function getSubCategoryName($subCategory, $lang = null, $mainCategoryCode = null) {
    global $conn;
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    if (empty($subCategory)) {
        return '';
    }
    
    //    
    $subCategory = strtolower(trim($subCategory));

    //   (product_sub_categories)     
    // (:  code  mainCategoryId    mainCategoryCode    )
    static $subCategoryNameByMainAndCode = null; // [mainCode][subCode] => name
    static $subCategoryNameFirstMatch = null;    // [subCode] => name (fallback)
    if ($subCategoryNameByMainAndCode === null || $subCategoryNameFirstMatch === null) {
        $subCategoryNameByMainAndCode = [];
        $subCategoryNameFirstMatch = [];
        try {
            $tbl1 = $conn->query("SHOW TABLES LIKE 'product_main_categories'");
            $tbl2 = $conn->query("SHOW TABLES LIKE 'product_sub_categories'");
            $hasTables = ($tbl1 && $tbl1->num_rows > 0) && ($tbl2 && $tbl2->num_rows > 0);
            if ($hasTables) {
                $mainIdToCode = [];
                $mres = $conn->query("SELECT mainCategoryId, code FROM product_main_categories");
                while ($mres && ($mr = $mres->fetch_assoc())) {
                    $mid = (int)($mr['mainCategoryId'] ?? 0);
                    $code = strtolower(trim((string)($mr['code'] ?? '')));
                    if ($mid > 0 && $code !== '') $mainIdToCode[$mid] = $code;
                }
                $sres = $conn->query("SELECT mainCategoryId, code, name FROM product_sub_categories ORDER BY sortOrder ASC, subCategoryId ASC");
                while ($sres && ($sr = $sres->fetch_assoc())) {
                    $mid = (int)($sr['mainCategoryId'] ?? 0);
                    $mcode = $mainIdToCode[$mid] ?? '';
                    $scode = strtolower(trim((string)($sr['code'] ?? '')));
                    $name = trim((string)($sr['name'] ?? ''));
                    if ($scode === '' || $name === '') continue;
                    if ($mcode !== '') {
                        if (!isset($subCategoryNameByMainAndCode[$mcode])) $subCategoryNameByMainAndCode[$mcode] = [];
                        // sortOrder    
                        if (!isset($subCategoryNameByMainAndCode[$mcode][$scode])) {
                            $subCategoryNameByMainAndCode[$mcode][$scode] = $name;
                        }
                    }
                    if (!isset($subCategoryNameFirstMatch[$scode])) {
                        $subCategoryNameFirstMatch[$scode] = $name;
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore and fallback
        }
    }
    $mc = is_string($mainCategoryCode) ? strtolower(trim($mainCategoryCode)) : '';
    if ($mc !== '' && isset($subCategoryNameByMainAndCode[$mc]) && isset($subCategoryNameByMainAndCode[$mc][$subCategory])) {
        return $subCategoryNameByMainAndCode[$mc][$subCategory];
    }
    if (isset($subCategoryNameFirstMatch[$subCategory])) {
        return $subCategoryNameFirstMatch[$subCategory];
    }
    
    $subCategoryNames = [
        'ko' => [
            'spring' => '',
            'summer' => '',
            'autumn' => '',
            'winter' => '',
            'seoul' => '',
            'busan' => '',
            'jeju' => '',
            'gyeongju' => '',
            'gangwon' => '',
            'asia' => '',
            'europe' => '',
            'america' => '',
            'culture' => '',
            'nature' => '',
            'adventure' => '',
            'food' => '',
            'kpop' => 'K-Pop',
            'custom' => '',
            'vip' => 'VIP',
            'luxury' => '',
            'premium' => ''
        ],
        'en' => [
            'spring' => 'Spring',
            'summer' => 'Summer',
            'autumn' => 'Autumn',
            'winter' => 'Winter',
            'seoul' => 'Seoul',
            'busan' => 'Busan',
            'jeju' => 'Jeju',
            'gyeongju' => 'Gyeongju',
            'gangwon' => 'Gangwon',
            'asia' => 'Asia',
            'europe' => 'Europe',
            'america' => 'America',
            'culture' => 'Culture',
            'nature' => 'Nature',
            'adventure' => 'Adventure',
            'food' => 'Food',
            'kpop' => 'K-Pop',
            'custom' => 'Custom',
            'vip' => 'VIP',
            'luxury' => 'Luxury',
            'premium' => 'Premium'
        ],
        'tl' => [
            'spring' => 'Tagsibol',
            'summer' => 'Tag-init',
            'autumn' => 'Taglagas',
            'winter' => 'Taglamig',
            'seoul' => 'Seoul',
            'busan' => 'Busan',
            'jeju' => 'Jeju',
            'gyeongju' => 'Gyeongju',
            'gangwon' => 'Gangwon',
            'asia' => 'Asya',
            'europe' => 'Europa',
            'america' => 'Amerika',
            'culture' => 'Kultura',
            'nature' => 'Kalikasan',
            'adventure' => 'Pakikipagsapalaran',
            'food' => 'Pagkain',
            'kpop' => 'K-Pop',
            'custom' => 'Pasadyang',
            'vip' => 'VIP',
            'luxury' => 'Luho',
            'premium' => 'Premium'
        ]
    ];
    
    return $subCategoryNames[$lang][$subCategory] ?? ucfirst($subCategory);
}

//   
function getDayName($day, $lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    // date('D') Sun, Mon, Tue    
    $day = strtoupper($day);
    
    $dayNames = [
        'ko' => [
            'SUN' => '', 'MON' => '', 'TUE' => '', 'WED' => '',
            'THU' => '', 'FRI' => '', 'SAT' => ''
        ],
        'en' => [
            'SUN' => 'Sun', 'MON' => 'Mon', 'TUE' => 'Tue', 'WED' => 'Wed',
            'THU' => 'Thu', 'FRI' => 'Fri', 'SAT' => 'Sat'
        ],
        'tl' => [
            'SUN' => 'LIN', 'MON' => 'LUN', 'TUE' => 'MAR', 'WED' => 'MIY',
            'THU' => 'HUW', 'FRI' => 'BIY', 'SAT' => 'SAB'
        ]
    ];
    
    return $dayNames[$lang][$day] ?? $day;
}

//   
function getMonthName($month, $lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    $monthNames = [
        'ko' => [
            1 => '1', 2 => '2', 3 => '3', 4 => '4',
            5 => '5', 6 => '6', 7 => '7', 8 => '8',
            9 => '9', 10 => '10', 11 => '11', 12 => '12'
        ],
        'en' => [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ],
        'tl' => [
            1 => 'Enero', 2 => 'Pebrero', 3 => 'Marso', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Hunyo', 7 => 'Hulyo', 8 => 'Agosto',
            9 => 'Setyembre', 10 => 'Oktubre', 11 => 'Nobyembre', 12 => 'Disyembre'
        ]
    ];
    
    return $monthNames[$lang][$month] ?? $month;
}

//   
function formatDate($date, $format = 'Y-m-d(D) H:i', $lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    if (empty($date)) return '';

    //     (: YYYY-MM-DD)    
    // -   strtotime  00:00  " "   
    $hasTime = preg_match('/\b\d{1,2}:\d{2}\b/', (string)$date) === 1;
    
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp);
    $month = date('n', $timestamp);
    $day = date('j', $timestamp);
    $dayOfWeek = date('D', $timestamp);
    $hour = date('H', $timestamp);
    $minute = date('i', $timestamp);
    
    $monthName = getMonthName($month, $lang);
    $dayName = getDayName($dayOfWeek, $lang);
    
    switch ($lang) {
        case 'ko':
            return $hasTime ? "{$month}. {$day}. ({$dayName}) {$hour}:{$minute}" : "{$month}. {$day}. ({$dayName})";
        case 'en':
            return $hasTime ? "{$month}. {$day}. ({$dayName}) {$hour}:{$minute}" : "{$month}. {$day}. ({$dayName})";
        case 'tl':
            return $hasTime ? "{$month}. {$day}. ({$dayName}) {$hour}:{$minute}" : "{$month}. {$day}. ({$dayName})";
        default:
            return date($format, $timestamp);
    }
}

//    
function getLanguageSwitchUrl($lang) {
    $currentUrl = $_SERVER['REQUEST_URI'];
    $separator = strpos($currentUrl, '?') !== false ? '&' : '?';
    
    //  lang  
    $currentUrl = preg_replace('/[?&]lang=[^&]*/', '', $currentUrl);
    
    //  lang  
    return $currentUrl . $separator . 'lang=' . $lang;
}

//   HTML 
function getLanguageSelector($currentLang = null) {
    if ($currentLang === null) {
        $currentLang = getCurrentLanguage();
    }
    
    //   ( / : English, Tagalog)
    $languages = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'tl' => ['name' => 'Tagalog', 'flag' => '🇵🇭']
    ];
    
    $html = '<div class="language-selector">';
    $html .= '<select onchange="changeLanguage(this.value)">';
    
    foreach ($languages as $code => $info) {
        $selected = ($code === $currentLang) ? ' selected' : '';
        $html .= "<option value=\"{$code}\"{$selected}>{$info['flag']} {$info['name']}</option>";
    }
    
    $html .= '</select>';
    $html .= '</div>';
    
    return $html;
}
?>
