<?php
require_once('../../tcpdf/tcpdf.php');

class PDF extends TCPDF {

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
            $this->Cell(0, 10, 'STATEMENT OF ACCOUNTS (SOA)', 'LRTB', 1, 'C', true);

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
        $this->SetXY(10, 63);

        $this->SetFont('Helvetica', 'B', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color

        // Render header cells
        $this->Cell(15, 6, 'NO.', 1, 0, 'C', true); 
        $this->Cell(65, 6, 'CONTENTS', 1, 0, 'C', true);
        $this->Cell(35, 6, 'Price ($)', 1, 0, 'C', true);
        $this->Cell(15, 6, 'PAX', 1, 0, 'C', true);
        $this->Cell(30, 6, 'TOTAL ($)', 1, 0, 'C', true);
        $this->Cell(30, 6, 'TOTAL (₱)', 1, 1, 'C', true);

        $this->SetFont('Helvetica', '', 10, true);
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }

    public function tableContent() {
        $this->SetFont('Helvetica', 'B', 10);
    
        // Add some space after the hotel info table (to prevent overlap)
        $this->Ln(2);
    
        // Set the X and Y for the header
        $this->SetXY(10, 69);
    
        // Header cells
        $this->SetFont('Helvetica', '', 10, true);
        $this->SetFillColor(255, 255, 255); // White background
        $this->SetTextColor(0, 0, 0); // Black text color

        $col1 = 15;
        $col2 = 65;
        $col3 = 35;
        $col4 = 15;
        $col5 = 30;
        $col6 = 30;
    
        // Render header cells contents
        $this->Cell($col1, 7, 'NO.', 1, 0, 'C', true); 
        $this->Cell($col2, 7, 'CONTENTS', 1, 0, 'C', true);
        $this->Cell($col3 , 7, 'Price ($)', 1, 0, 'C', true);
        $this->Cell($col4, 7, 'PAX', 1, 0, 'C', true);
        $this->Cell($col5, 7, 'TOTAL ($)', 1, 0, 'C', true);
        $this->Cell($col6, 7, 'TOTAL (₱)', 1, 1, 'C', true);
    
        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }

    // public function tableContentSubTotal() {
    //     $this->SetFont('Helvetica', 'B', 10);
    
    //     // Add some space after the hotel info table (to prevent overlap)
    //     $this->Ln(2);
    
    //     // Set the X and Y for the header
    //     $this->SetXY(125, 76);
    
    //     // Header cells
    //     $this->SetFont('Helvetica', '', 10, true);
    //     $this->SetFillColor(255, 255, 255); // White background
    //     $this->SetTextColor(0, 0, 0); // Black text color
    
    //     // Render header cells
    //     $this->Cell(35, 7, 'Sub Total', 1, 0, 'C', true); 
    //     $this->Cell(40, 7, 'PHP 999,999', 1, 0, 'C', true);
    
    //     // Reset text color
    //     $this->SetTextColor(0, 0, 0);
    
    // }
    
    
    // public function tableBalance() {
    //     $this->SetFont('Helvetica', 'B', 10);
    
    //     // Add some space after the hotel info table (to prevent overlap)
    //     $this->Ln(2);
    
    //     // Set the X and Y for the header
    //     $this->SetXY(10, 86);
    
    //     // Header cells
    //     $this->SetFont('Helvetica', '', 10, true);
    //     $this->SetFillColor(255, 255, 255); // White background
    //     $this->SetTextColor(0, 0, 0); // Black text color
    
    //     // Render header cells
    //     $this->Cell(115, 7, 'BALANCE', 1, 0, 'C', true); 
    //     $this->Cell(35, 7, '$', 1, 0, 'L', true); 
    //     $this->Cell(40, 7, '$', 1, 0, 'L', true); 
        
    // }



}

// Create a new PDF instance and add pages as needed
$pdf = new PDF();

// Set margins
$pdf->SetMargins(10, 10, 10); // Adjust to provide consistent spacing

// Add a page and headers
$pdf->AddPage();
$pdf->tableHeader();
$pdf->tableContent();
// $pdf->tableContentSubTotal();




// $pdf->tableBalance();
// $pdf->tableContentSubTotal();
// Output the PDF
$pdf->Output('itinerary-Winter.pdf', 'I');
?>

 
