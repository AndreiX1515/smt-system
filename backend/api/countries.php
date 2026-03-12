<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

//   
$countries = [
    ['code' => '+63', 'name' => 'Philippines'],
    ['code' => '+82', 'name' => 'South Korea'],
    ['code' => '+1', 'name' => 'United States'],
    ['code' => '+86', 'name' => 'China'],
    ['code' => '+81', 'name' => 'Japan'],
    ['code' => '+44', 'name' => 'United Kingdom'],
    ['code' => '+49', 'name' => 'Germany'],
    ['code' => '+33', 'name' => 'France'],
    ['code' => '+39', 'name' => 'Italy'],
    ['code' => '+34', 'name' => 'Spain'],
    ['code' => '+61', 'name' => 'Australia'],
    ['code' => '+64', 'name' => 'New Zealand'],
    ['code' => '+65', 'name' => 'Singapore'],
    ['code' => '+60', 'name' => 'Malaysia'],
    ['code' => '+66', 'name' => 'Thailand'],
    ['code' => '+84', 'name' => 'Vietnam'],
    ['code' => '+62', 'name' => 'Indonesia'],
    ['code' => '+91', 'name' => 'India'],
    ['code' => '+7', 'name' => 'Russia'],
    ['code' => '+55', 'name' => 'Brazil'],
    ['code' => '+52', 'name' => 'Mexico'],
    ['code' => '+54', 'name' => 'Argentina'],
    ['code' => '+56', 'name' => 'Chile'],
    ['code' => '+57', 'name' => 'Colombia'],
    ['code' => '+51', 'name' => 'Peru'],
    ['code' => '+27', 'name' => 'South Africa'],
    ['code' => '+20', 'name' => 'Egypt'],
    ['code' => '+971', 'name' => 'UAE'],
    ['code' => '+966', 'name' => 'Saudi Arabia'],
    ['code' => '+90', 'name' => 'Turkey'],
    ['code' => '+98', 'name' => 'Iran'],
    ['code' => '+92', 'name' => 'Pakistan'],
    ['code' => '+880', 'name' => 'Bangladesh'],
    ['code' => '+94', 'name' => 'Sri Lanka'],
    ['code' => '+977', 'name' => 'Nepal'],
    ['code' => '+975', 'name' => 'Bhutan'],
    ['code' => '+93', 'name' => 'Afghanistan'],
    ['code' => '+998', 'name' => 'Uzbekistan'],
    ['code' => '+7', 'name' => 'Kazakhstan']
];

//   
usort($countries, function($a, $b) {
    return strcmp($a['code'], $b['code']);
});

echo json_encode([
    'success' => true,
    'countries' => $countries
], JSON_UNESCAPED_UNICODE);
?>
