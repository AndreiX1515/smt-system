<?php
require_once('../../assets/tcpdf/tcpdf.php');  // Ensure you have the correct TCPDF path

class PDF extends TCPDF {
    // Page header
    public function Header() {
        if ($this->getPage() == 1) {  // Check if it's the first page
            // Add logo
            $this->Image('../../assets/images/SMART LOGO 2 (2).jpg', 45, 5, 105, 15); // Adjust 'logo.png' path, position, and size as needed
            $this->Ln(25); // Adds 10mm of vertical space

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetY($this->GetY() + 2); // Set the Y position for the line, adjust if needed
            $this->SetTextColor(255, 0, 0); // Set text color to red (RGB: 255, 0, 0)
            $this->Cell(173, 0, '**Subject to hange w/o prior notice based on local Situiation**', 0, 0, 'L');
            $this->SetTextColor(0, 0, 0); 
            $this->Cell(5, 0, 'TN: 1029365', 0, 0, 'L');

            $this->Ln(3); // Adds 10mm of vertical space

            $this->SetY($this->GetY() + 2); // Set the Y position for the line, adjust if needed
            $this->Line(10, $this->GetY(), 200, $this->GetY()); // Draw a line from x=10 to x=200 at the current Y position

            $this->Ln(1); // Adds 10mm of vertical space

            // Header lines
            $this->SetFont('Helvetica', 'B', 8);
            $this->Cell(15, 0, 'TO :', 0, 0, 'L');
            $this->Cell(100, 0, 'TRAVEL', 0, 0, 'L');
            $this->Cell(80, 0, 'ATTN :', 0, 0, 'L');
            $this->Cell(30, 0, '', 0, 1, 'L');

            $this->Cell(15, 5, 'FROM :', 0, 0, 'L');
            $this->Cell(100, 5, 'JED KIM', 0, 0, 'L');
            $this->Cell(15, 5, 'DATE :', 0, 0, 'L');
            $this->Cell(30, 5, '', 0, 1, 'L');

            $this->SetY($this->GetY() + 1); // Set the Y position for the line, adjust if needed
            $this->Line(10, $this->GetY(), 200, $this->GetY()); // Draw a line from x=10 to x=200 at the current Y position

            $this->Ln(3); // Adds 10mm of vertical space

            // Main title
            $this->SetFont('Helvetica', 'B', 16);
            $this->Cell(0, 7, 'WINTER', 'LRT', 1, 'C');
            $this->SetFont('Helvetica', 'B', 10);
            $this->Cell(0, 7, 'KOREA TOUR 5 DAYS & 4 NIGHTS', 'LRB', 1, 'C');

            // Set up columns for periods and hotel info
            $this->SetFont('Helvetica', 'B', 9);

            // Create a vertical "HOTEL" cell spanning multiple rows
            $this->SetXY(10, 59.5);  // Adjust the X and Y position if needed
            $this->Cell(40, 10, 'PERIODS', 'LRB', 0, 'C', false);  // Borders on all sides, center-aligned text
            $this->Cell(70, 10, '10, OCT. 2024 - 15, NOV. 2024', 'B', 0, 'C');

            $this->Cell(20, 10, 'GUIDE:', 'LB', 0, 'C');
            $this->Cell(60, 5, 'Mikey Lee', 'LRB', 1, 'C');

            $this->SetXY(140, 64.5);  // Adjust the X and Y position if needed
            $this->Cell(60, 5, '82(0)-324-3746', 1, 1, 'C');
        }
    }

    // Add hotel info table
    public function addHotelInfoTable() {
        $this->SetFont('Helvetica', 'B', 10);

        // Create a vertical "HOTEL" cell spanning multiple rows
        $this->SetXY(10, 69.5);  // Adjust the X and Y position if needed
        $this->Cell(40, 15, 'HOTEL', 'LRB', 0, 'C', false);  // Borders on all sides, center-aligned text

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetXY(50, 70);  // Adjust the Y to align the cells properly
        // Create the cells for the cities (Incheon, Gangwon, Seoul)
        $this->Cell(30, 4, 'INCHEON', 'LRB', 0, 'C');
        $this->Cell(120, 4, '   Air Sky Hotel', 'LRB', 1, 'L');

        $this->SetXY(50, 75);  // Adjust the Y to align the cells properly
        $this->Cell(30, 4, 'GANGWON', 'LRB', 0, 'C');
        $this->Cell(120, 4, '   Centrum Hotel', 'LRB', 1, 'L');

        $this->SetXY(50, 80); // Adjust the Y position after the city names
        $this->Cell(30, 4, 'SEOUL', 'LRB', 0, 'C');
        $this->Cell(120, 4, '   Bernoui Hotel', 'LRB', 1, 'L');
    }

    // Add itinerary header
    public function addItineraryHeader() {
        if ($this->getPage() == 1) {  // Only show this header on the first page
            $this->SetFont('Helvetica', 'B', 10);

            // Add some space after the hotel info table (to prevent overlap)
            $this->Ln(2);

            // Set the X and Y for the header
            $this->SetXY(10, $this->GetY());

            // Define the widths for each column
            $dayWidth = 13;
            $areaWidth = 18;
            $itineraryWidth = 124;
            $mealPlanWidth = 35; // Adjusted width for Meal Plan

            // Render header cells
            $this->Cell($dayWidth, 7, 'DAY', 1, 0, 'C');
            $this->Cell($areaWidth, 7, 'AREA', 1, 0, 'C');
            $this->Cell($itineraryWidth, 7, 'ITINERARY', 1, 0, 'C');
            $this->Cell($mealPlanWidth, 7, 'MEAL PLAN', 1, 1, 'C');
        }
    }

     public function day($daysData) {
      $yPosition = $this->GetY();  // Start from the current Y position (Day 1)
  
      // Loop through all provided days and add itinerary and meal plan for each day
      foreach ($daysData as $index => $dayData) {
          // If it's Day 2, reset the Y position to Day 1's Y position
          if ($index > 0) {
              $this->SetY($yPosition);  // Reset Y position to the starting Y position of Day 1
          }
  
          // Call the function to add the itinerary row
          $this->addItineraryRow(
              $dayData['day'], 
              $dayData['area'], 
              $dayData['itinerary'], 
              $dayData['mealPlan'], 
              $yPosition, 
              $dayData['itineraryHeight']
          );
          
          // Update Y position after each day's content
          $yPosition = $this->GetY() + 10;
      }
  }
  
  // Modify the addItineraryRow function to accept dynamic Y position
  private function addItineraryRow($day, $area, $itineraryContents, $mealPlan, $yPosition, $itineraryHeight) {
      // Ensure the itinerary content is an array
      if (!is_array($itineraryContents)) {
          $itineraryContents = [$itineraryContents];
      }
  
      // Ensure there are at least 10 rows in itinerary content (pad if necessary)
      $itineraryContents = array_pad($itineraryContents, 10, '');
  
      // Ensure mealPlan has exactly 6 elements
      if (!is_array($mealPlan)) {
          $mealPlan = explode(',', $mealPlan);
      }
      $mealPlan = array_pad($mealPlan, 6, '');
  
      // Call helper function to add the row for each day
      $this->addDayItinerary($day, $area, $itineraryContents, $mealPlan, $yPosition, $itineraryHeight);
  }
  
  // Helper function to add each day's itinerary to the PDF
  private function addDayItinerary($day, $area, $itineraryContents, $mealPlan, $yPosition, $itineraryHeight) {
      $this->SetFont('Helvetica', '', 9.5);
      $this->SetCellPadding(3);
  
      // Set dynamic line height for the itinerary rows
      $lineHeight = $itineraryHeight / 10;
  
      // Set initial Y position dynamically
      $this->SetY($yPosition);
  
      // Render Day and Area columns (leftmost columns for the day and area)
      $this->SetXY(10, $yPosition);
      $this->Cell(13, ($itineraryHeight + 5.2), $day, 1, 0, 'C');
  
      // Convert the area to MultiCell (with height and width retention)
      // Calculate the total height of the cell and the Y position
   $cellHeight = $itineraryHeight + 5.2;
   $availableHeight = $cellHeight;  // No need to subtract from anything if you start at $yPosition

   // Calculate the vertical offset for centering
   $centeredYPosition = $yPosition + (($availableHeight - $cellHeight) / 2);

   // Now set the Y position for the centered content
   $this->SetXY(23, $centeredYPosition);

   // Add the MultiCell content, center-aligned
   $this->MultiCell(18, $cellHeight, $area, 1, 'C');

     
      // Render Itinerary content vertically, row by row
      $this->SetXY(41, $yPosition);
      $this->Cell(124, $lineHeight, $itineraryContents[0], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[1], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[2], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[3], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[4], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[5], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[6], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[7], 'LR', 0, 'L');
  
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(124, $lineHeight, $itineraryContents[8], 'LR', 0, 'L');


      $hotel1= "Air Sky Hotel";
      $hotel2= "Emporium Hotel";

    
      $this->SetXY(41, $this->GetY() + $lineHeight);
      $this->Cell(30, 3, 'HOTEL:', 'TB', 0, 'L'); // Bottom border on last line
      $this->Cell(35, 3, $hotel1, 'TB', 0, 'L'); // Bottom border on last line
      $this->Cell(25, 3, 'OR', 'TB', 0, 'L'); // Bottom border on last line
      $this->Cell(34, 5,  $hotel2, 'TB', 0, 'L'); // Bottom border on last line
  
      // Render Meal Plan content with 6 rows
      $mealPlanHeight = ($itineraryHeight / 6) + .670; // Height for each individual row
  
      $this->SetXY(165, $yPosition);
      $this->Cell(35, $mealPlanHeight, "Breakfast", 'LRT', 0, 'C');
  
      $this->SetXY(165, $this->GetY() + $mealPlanHeight);
      $this->Cell(35, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
  
      $this->SetXY(165, $this->GetY() + $mealPlanHeight);
      $this->Cell(35, $mealPlanHeight, "Lunch", 'LR', 0, 'C');
  
      $this->SetXY(165, $this->GetY() + $mealPlanHeight);
      $this->Cell(35, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
  
      $this->SetXY(165, $this->GetY() + $mealPlanHeight);
      $this->Cell(35, $mealPlanHeight, "Dinner", 'LR', 0, 'C');
  
      $this->SetXY(165, $this->GetY() + $mealPlanHeight);
      $this->Cell(35, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
  }
 
  

}
// Create a new PDF instance and add pages as needed
$pdf = new PDF();
$pdf->AddPage();
$pdf->addHotelInfoTable();
$pdf->addItineraryHeader();

// Set heights for itinerary and meal plans
$lineHeight = 4;
$itineraryHeight = 6 * $lineHeight;  // Height for 10 itinerary rows
$mealPlanHeight = 6 * $lineHeight; // Height for 6 rows in meal plan

$daysData = [
 [
     'day' => 1, 
     'area' => 'Seoul', 
     'itinerary' => [
         'Arrive at Incheon Airport, transfer to the hotel.',
         'Check-in and freshen up at the hotel.',
         'Explore the hotel surroundings or relax.',
         'Welcome dinner at a local restaurant featuring Korean cuisine.',
         'Transfer to Incheon Airport for Departure.',
         'Shopping for Korean Food & Souvenir.',
         'Check-in and freshen up at the hotel.',
         'Explore the hotel surroundings or relax.',
         '',  // Empty item for flexibility
         'Transfer to Incheon Airport for Departure.',
         'Shopping for Korean Food & Souvenir.',
     ], 
     'mealPlan' => ['Hotel B/F', 'BBQ Chicken', 'Korean Chinese Food'],
     'itineraryHeight' => 50
 ],
 [
     'day' => 2,
     'area' => 'Busan',
     'itinerary' => [
         'Arrive at Busan, transfer to the hotel.',
         'Check-in and freshen up at the hotel.',
         'Explore Haeundae Beach.',
         'Lunch at a famous seafood restaurant.',
         'Visit the Gamcheon Culture Village.',
         'Dinner at a traditional Korean BBQ restaurant.',
         'Evening stroll along Gwangalli Beach.',
         '',  // Empty item for flexibility
         'Rest at the hotel after a full day.',
         'Prepare for the next day\'s activities.'
     ],
     'mealPlan' => ['Hotel B/F', 'Seafood Lunch', 'Korean BBQ Dinner'],
     'itineraryHeight' => 50
 ],
];



$pdf->day($daysData);



$pdf->Output('itinerary.pdf', 'I');
?>
