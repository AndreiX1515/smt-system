<?php
require '../../vendor/autoload.php'; // PhpSpreadsheet autoload
require "../../conn.php";
session_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['tableData1'], $_SESSION['tableData2'], $_SESSION['tableData3'])) {
    die("No data available to export.");
}

// Create spreadsheet and active sheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Sheet title
$sheet->setTitle('SOA Report');

// Set headers
$sheet->setCellValue('A1', 'No.');
$sheet->setCellValue('B1', 'Contents');
$sheet->setCellValue('C1', 'Price (USD)');
$sheet->setCellValue('D1', 'Price (PHP)');
$sheet->setCellValue('E1', 'PAX');
$sheet->setCellValue('F1', 'Total (USD)');
$sheet->setCellValue('G1', 'Total (PHP)');

$row = 2;

// Write Table 1 (Flight Data)
foreach ($_SESSION['tableData1'] as $item) {
    $sheet->fromArray(array_values($item), NULL, "A$row");
    $row++;
}
$sheet->setCellValue("G$row", '₱ ' . $_SESSION['totalPriceSum']);
$row++;

// Write Table 2 (Request Data)
foreach ($_SESSION['tableData2'] as $item) {
    $sheet->fromArray(array_values($item), NULL, "A$row");
    $row++;
}
$sheet->setCellValue("G$row", '₱ ' . $_SESSION['totalRequestCost']);
$row++;

// Write Table 3 (Payment Data)
foreach ($_SESSION['tableData3'] as $item) {
    $sheet->fromArray(array_values($item), NULL, "A$row");
    $row++;
}
$sheet->setCellValue("G$row", '₱ ' . $_SESSION['totalAmount']);
$row++;

// Final Balance
$sheet->setCellValue("F$row", 'Balance:');
$sheet->setCellValue("G$row", '₱ ' . $_SESSION['balance']);

// Output headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="SOA_Report.xlsx"');
header('Cache-Control: max-age=0');

// Write and output the file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>