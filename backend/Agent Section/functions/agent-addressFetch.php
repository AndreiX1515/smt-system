<?php
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$code = $_GET['code'] ?? '';

$basePath = __DIR__ . '/../../vendor/jaydoesphp/psgc-php/resources/json/';

switch ($type) {
  case 'province':
    $provinces = json_decode(file_get_contents($basePath . 'provinces.json'), true);
    echo json_encode(array_values(array_filter($provinces, fn($p) => $p['reg_code'] === $code)));
    break;

  case 'citymun':
    $cities = json_decode(file_get_contents($basePath . 'cities-municipalities.json'), true);
    echo json_encode(array_values(array_filter($cities, fn($c) => $c['prov_code'] === $code)));
    break;

  case 'barangay':
    $barangays = json_decode(file_get_contents($basePath . 'barangays.json'), true);
    echo json_encode(array_values(array_filter($barangays, fn($b) => $b['citymun_code'] === $code)));
    break;

  default:
    echo json_encode([]);
}
