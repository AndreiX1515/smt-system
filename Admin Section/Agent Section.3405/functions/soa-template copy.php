<?php
require_once('../../tcpdf/tcpdf.php');

class PDF extends TCPDF {

    private $yPosition;

    // Header function
    public function Header() {
        if ($this->getPage() == 1) { // Check if it's the first page
            // Add logo
            $this->Image('../../Assets/Logos/SMART LOGO 2 (2).jpg', 10, 10, 65, 13); // Adjust 'logo.png' path, position, and size as needed
            $this->Ln(30); // Adds 30mm of vertical space

            // Voucher title
            $this->SetFillColor(211, 211, 211); // Set the fill color
            $this->SetTextColor(0, 0, 0); // Text color
            $this->SetFont('Helvetica', 'B', 12, true);
            $this->Cell(0, 10, 'STATEMENT OF ACCOUNT (SOA)', 'LRTB', 1, 'C', true);

            // Add TO, ATTACHMENT, etc.
            $this->SetFont('Helvetica', '', 10, true);
            $this->SetXY(10, 43);
            $this->Cell(30, 8, 'SOA NO.:', 1, 0, 'C');
            $this->Cell(70, 8, '', 1, 0, 'C');
            $this->Cell(30, 8, 'DATE RANGE:', 1, 0, 'C');
            $this->Cell(60, 8, '', 1, 1, 'C');

            $this->SetXY(10, 51);
            $this->Cell(30, 8, 'BILL TO:', 1, 0, 'C');
            $this->Cell(70, 8, '', 1, 0, 'C');
            $this->Cell(30, 8, 'FROM', 1, 0, 'C');
            $this->Cell(60, 8, '', 1, 1, 'C');

            $this->SetXY(110, 59);

            $this->Cell(30, 8, 'UPDATE DATE:', 1, 0, 'C');
            $this->Cell(60, 8, '', 1, 1, 'C');

            // Add a bit of space before starting the table
            $this->Ln(2); 
        }
    }

    // Table Header function
    public function tableHeader() {
        $this->SetFont('Helvetica', 'B', 10);

        // Add some space after the hotel info table (to prevent overlap)
        $this->Ln(2);

        // Set the X and Y for the header
        $this->SetXY(10, 69);

        $this->SetFont('Helvetica', 'B', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color

        // Render header cells
        $this->Cell(10, 6, 'NO.', 1, 0, 'C', true); 
        $this->Cell(55, 6, 'CONTENTS', 1, 0, 'C', true);
        $this->Cell(27, 6, 'Price (USD)', 1, 0, 'C', true);
        $this->Cell(27, 6, 'Price (PHP)', 1, 0, 'C', true);
        $this->Cell(10, 6, 'PAX', 1, 0, 'C', true);
        $this->Cell(30.5, 6, 'TOTAL (USD)', 1, 0, 'C', true);
        $this->Cell(30.5, 6, 'TOTAL (PHP)', 1, 1, 'C', true);


        $this->SetFont('Helvetica', '', 10, true);
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }
    public function tableContent($tableData, $yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the hotel info table (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header
        $this->SetXY(10, $yPosition);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color
    
        $col1 = 10;
        $col2 = 55;
        $col3 = 27;
        $col4 = 10;
        $col5 = 30.5;
        $col6 = 30.5;
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    
        // Loop through content rows and adjust Y position
        foreach ($tableData as $row) {
            // Set the X and Y for each row based on the current Y position
            $this->SetXY(10, $yPosition);
    
            // Render cells with data
            $this->Cell($col1, 7, $row['no'], 1, 0, 'C');
            $this->Cell($col2, 7, $row['contents'], 1, 0, 'C');
            $this->Cell($col3, 7, $row['price'], 1, 0, 'C');
            $this->Cell($col3, 7, $row['price'], 1, 0, 'C');
            $this->Cell($col4, 7, $row['pax'], 1, 0, 'C');
            $this->Cell($col5, 7, $row['total_usd'], 1, 0, 'C');
            $this->Cell($col6, 7, $row['total_php'], 1, 1, 'C');
    
            // Increment Y position for the next row
            $yPosition += 7;
        }
    
        // Return the final Y position for reference
        return $yPosition;
    }
    
    public function tableContentSubTotal($yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the previous content (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header based on passed Y position
        $this->SetXY(10, $yPosition);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color
    
        // Render subtotal cells
        $this->Cell(92, 7, '', 1, 0, 'C', true);
        $this->Cell(37, 7, 'SUB TOTAL', 1, 0, 'C', true);
        $this->Cell(30.5, 7, 'PHP 999,999', 1, 0, 'C', true);
        $this->Cell(30.5, 7, 'PHP 999,999', 1, 0, 'C', true);
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    
        // Return the final Y position for reference (add 7 for the row height)
        return $yPosition + 7;
    }
    
    public function tablePayment($tableData2, $yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the hotel info table (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header
        $this->SetXY(10, $yPosition);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color
    
        $col1 = 10;
        $col2 = 55;
        $col3 = 27;
        $col4 = 10;
        $col5 = 30.5;
        $col6 = 30.5;
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    
        // Loop through content rows and adjust Y position
        foreach ($tableData2 as $row) {
            // Set the X and Y for each row based on the current Y position
            $this->SetXY(10, $yPosition);
    
            // Render cells with data
            $this->Cell($col1, 7, $row['no'], 1, 0, 'C');
            $this->Cell($col2, 7, $row['contents'], 1, 0, 'C');
            $this->Cell($col3, 7, $row['price'], 1, 0, 'C');
            $this->Cell($col3, 7, $row['price'], 1, 0, 'C');
            $this->Cell($col4, 7, $row['pax'], 1, 0, 'C');
            $this->Cell($col5, 7, $row['total_usd'], 1, 0, 'C');
            $this->Cell($col6, 7, $row['total_php'], 1, 1, 'C');
    
            // Increment Y position for the next row
            $yPosition += 7;
        }
    
        // Return the final Y position for reference
        return $yPosition;
    }
    
    public function tableContentSubTotal2($yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the previous content (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header based on passed Y position
        $this->SetXY(10, $yPosition);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color
    
        // Render subtotal cells
        $this->Cell(92, 7, '', 1, 0, 'C', true);
        $this->Cell(37, 7, 'SUB TOTAL', 1, 0, 'C', true);
        $this->Cell(30.5, 7, 'PHP 999,999', 1, 0, 'C', true);
        $this->Cell(30.5, 7, 'PHP 999,999', 1, 0, 'C', true);
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    
        // Return the final Y position for reference (add 10 for the row height)
        return $yPosition + 7;
    }
    
    public function tableTippingFee($tableData3, $yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
        
        // Add some space after the previous content (to prevent overlap)
        $this->Ln(2);
        
        // Set the X and Y for the header based on passed Y position
        $this->SetXY(10, $yPosition);
        
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color
        
        $col1 = 10;
        $col2 = 55;
        $col3 = 27;
        $col4 = 10;
        $col5 = 30.5;
        $col6 = 30.5;
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
        
        // Loop through content rows and adjust Y position
        foreach ($tableData3 as $row) {
            // Set the X and Y for each row based on the current Y position
            $this->SetXY(10, $yPosition);
        
            // Render cells with data
            $this->Cell($col1, 7, $row['no'], 1, 0, 'C');
            $this->Cell($col2, 7, $row['contents'], 1, 0, 'C');
            $this->Cell($col3, 7, $row['price'], 1, 0, 'C');
            $this->Cell($col3, 7, $row['price'], 1, 0, 'C');
            $this->Cell($col4, 7, $row['pax'], 1, 0, 'C');
            $this->Cell($col5, 7, $row['total_usd'], 1, 0, 'C');
            $this->Cell($col6, 7, $row['total_php'], 1, 1, 'C');
        
            // Increment Y position for the next row
            $yPosition += 7;
        }
        
        // Return the final Y position for reference
        return $yPosition;
    }
    
    
    public function tableBalance($yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the previous content (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header based on passed Y position
        $this->SetXY(10, $yPosition);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color
    
        // Render header cells for Balance table
        $this->Cell(129, 7, 'BALANCE', 1, 0, 'C', true); 
        $this->Cell(30.5, 7, '$', 1, 0, 'L', true); 
        $this->Cell(30.5, 7, '$', 1, 0, 'L', true); 
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    
        // Return the final Y position for reference (add 7 for the row height)
        return $yPosition + 9;
    }

    public function accountInfo($yPosition) {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the previous content (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header based on passed Y position
        $this->SetXY(10, $yPosition);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color   
    
        // Render header cells for Balance table using MultiCell
        $this->SetFont('Helvetica', 'B', 10, true);
        // Render MultiCell with no bottom border
$this->MultiCell(190, 4, 'ACCOUNT INFORMATION', 'LTR', 'L', true);
$this->SetFont('Helvetica', '', 10, true);
$this->MultiCell(190, 7, 
'Bank Name: Banco De Oro (BDO) - Zuellig Branch Makati Avenue
Name of Account: Hyung Sub Kim (Nickname: Jed Kim)
Peso Account No.: 007800203252
US Dollar Account No.: 107800113512','LBR', 'L', true);

        // Reset text color
        $this->SetTextColor(0, 0, 0);
    
        // Return the final Y position for reference (add 7 for the row height)
        return $yPosition + 10;
    }
    

}

// Create a new PDF instance and add pages as needed
$pdf = new PDF();

$tableData = [
    ['no' => 1, 'contents' => 'Content 1', 'price' => 100, 'pax' => 2, 'total_usd' => 200, 'total_php' => 10000],
    ['no' => 2, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 3, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 4, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    
    // More rows...
];

$tableData2 = [
    ['no' => 1, 'contents' => 'Content 1', 'price' => 100, 'pax' => 2, 'total_usd' => 200, 'total_php' => 10000],
    ['no' => 2, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 3, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 4, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 4, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 4, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 4, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    ['no' => 4, 'contents' => 'Content 2', 'price' => 150, 'pax' => 3, 'total_usd' => 450, 'total_php' => 22500],
    
    // More rows...
];

$tableData3 = [
    ['no' => 1, 'contents' => 'Content 1', 'price' => 100, 'pax' => 2, 'total_usd' => 200, 'total_php' => 10000],
];


// Set margins
$pdf->SetMargins(10, 10, 10); // Adjust to provide consistent spacing


// $pdf->tableBalance();
// $pdf->tableContentSubTotal();
// Output the PDF

$pdf->AddPage();
$pdf->tableHeader();

// Get the initial Y position after rendering the header
$yPosition = 75; // Set the starting position for the first table

// Pass the Y position to tableContent and get the updated position
$yPosition = $pdf->tableContent($tableData, $yPosition);

// Pass the updated Y position to tableContentSubTotal and get the final position
$yPosition = $pdf->tableContentSubTotal($yPosition);

// Pass the final Y position to tablePayment and get the final position
$yPosition = $pdf->tablePayment($tableData2, $yPosition);

// Pass the final Y position to tableContentSubTotal2 and get the final position
$yPosition = $pdf->tableContentSubTotal2($yPosition);

// Pass the final Y position to tableTippingFee and get the final position
$yPosition = $pdf->tableTippingFee($tableData3, $yPosition);

// Pass the updated Y position to tableBalance and get the final position
$yPosition = $pdf->tableBalance($yPosition);

$yPosition = $pdf->accountInfo($yPosition);

// Output the PDF
$pdf->Output('itinerary-Winter.pdf', 'I');




?>

 
