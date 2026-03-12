<?php
require_once('../../tcpdf/tcpdf.php');  // Ensure you have the correct TCPDF path

class PDF extends TCPDF {
    // Page header
    public function Header() {
        if ($this->getPage() == 1) {  // Check if it's the first page
            // Add logo
            $this->Image('../../Assets/Logos/SMART LOGO 2 (2).jpg', 45, 4, 105, 15); // Adjust 'logo.png' path, position, and size as needed
            $this->Ln(25); // Adds 10mm of vertical space

            $this->SetFont('Helvetica', 'B', 6);
            $this->SetY($this->GetY() - 4); // Set the Y position for the line, adjust if needed
            $this->SetTextColor(255, 0, 0); // Set text color to red (RGB: 255, 0, 0)
            $this->Cell(170, 0, '**Subject to change w/o prior notice based on local Situiation**', 0, 0, 'L');
            $this->SetTextColor(0, 0, 0); 
            $this->Cell(5, 0, 'TN: 1029365', 0, 0, 'L');

            $this->Ln(2); // Adds 10mm of vertical space

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

            $this->SetY($this->GetY()); // Set the Y position for the line, adjust if needed
            $this->Line(10, $this->GetY(), 200, $this->GetY()); // Draw a line from x=10 to x=200 at the current Y position

            $this->Ln(1); // Adds 10mm of vertical space

            // Main title

            $this->SetFillColor(137, 207, 240); // Set the fill color (Light peach)
            $this->SetTextColor(0,0,0);       // Text color

            $this->SetFont('Helvetica', 'B', 16, true);
            $this->Cell(0, 3, 'WINTER', 'LRT', 1, 'C', true);
            $this->SetFont('Helvetica', 'B', 10, true);
            $this->Cell(0, 3, 'KOREA TOUR 5 DAYS & 4 NIGHTS', 'LRB', 1, 'C', true);

            // Set up columns for periods and hotel info
            $this->SetFont('Helvetica', 'B', 9);

            // Create a vertical "HOTEL" cell spanning multiple rows
            $this->SetXY(10, 47.3);  // Adjust the X and Y position if needed
            $this->Cell(40, 8, 'PERIODS', 'LRB', 0, 'C', false);  // Borders on all sides, center-aligned text
            $this->Cell(70, 8, '10, OCT. 2024 - 15, NOV. 2024', 'B', 0, 'C');

            $this->Cell(20, 8, 'GUIDE:', 'LB', 0, 'C');
            $this->Cell(60, 4, 'Mikey Lee', 'LRB', 1, 'C');

            $this->SetXY(140, 51.25);  // Adjust the X and Y position if needed
            $this->Cell(60, 4, '82(0)-324-3746', 'LRB', 1, 'C');
        }
    }

    // Add hotel info table
    public function addHotelInfoTable() {
        $this->SetFont('Helvetica', 'B', 10);

        $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
        $this->SetTextColor(0,0,0);        // Text color

        // Create a vertical "HOTEL" cell spanning multiple rows
        $this->SetXY(10, 55.4);  // Adjust the X and Y position if needed
        $this->Cell(40, 15, 'HOTEL', 'LRB', 0, 'C', true);  // Borders on all sides, center-aligned text

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetXY(50, 55.4);  // Adjust the Y to align the cells properly
        // Create the cells for the cities (Incheon, Gangwon, Seoul)
        $this->Cell(30, 5, 'INCHEON', 'LRB', 0, 'C', true);
        $this->Cell(120, 5, '   Air Sky Hotel', 'LRB', 1, 'L', true);

        $this->SetXY(50, 60.4);  // Adjust the Y to align the cells properly
        $this->Cell(30, 5, 'GANGWON', 'LRB', 0, 'C', true);
        $this->Cell(120, 5, '   Centrum Hotel', 'LRB', 1, 'L', true);

        $this->SetXY(50, 65.4); // Adjust the Y position after the city names
        $this->Cell(30, 5, 'SEOUL', 'LRB', 0, 'C', true);
        $this->Cell(120, 5, '   Bernoui Hotel', 'LRB', 1, 'L', true);
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
            $areaWidth = 22;
            $itineraryWidth = 125;
            $mealPlanWidth = 30; // Adjusted width for Meal Plan

            // Render header cells
            $this->SetFillColor(220,20,60); // Set the fill color (Light peach)
            $this->SetTextColor(255,255,255);       // Text color
            $this->Cell($dayWidth, 7, 'DAY', 1, 0, 'C', true);
            $this->Cell($areaWidth, 7, 'AREA', 1, 0, 'C', true);
            $this->Cell($itineraryWidth, 7, 'ITINERARY', 1, 0, 'C', true);
            $this->Cell($mealPlanWidth, 7, 'MEAL PLAN', 1, 1, 'C', true);
            $this->SetTextColor(0,0,0);       // Text color
        }
    }

    public function day($daysData) {
     $yPosition = $this->GetY();  // Start from the current Y position (Day 1)
 
     // Loop through all provided days and add itinerary, meal plan, and hotel for each day
     foreach ($daysData as $index => $dayData) {
         // If it's Day 2 or beyond, reset the Y position to Day 1's Y position
         if ($index > 0) {
             $this->SetY($yPosition);  // Reset Y position to the starting Y position of Day 1
         }
 
         // Call the function to add the itinerary row
         $this->addItineraryRow(
             $dayData['day'], 
             $dayData['area'], 
             $dayData['itinerary'], 
             $dayData['mealPlan'], 
             $dayData['hotel'],    
             $yPosition, 
             $dayData['itineraryHeight']
         );
         
         // Update Y position after each day's content
         $yPosition = $this->GetY() + 10;  // Adjust Y position for next day
     }
   }
 

    private function addItineraryRow($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition, $itineraryHeight) {
     // Ensure the itinerary content is an array
     if (!is_array($itineraryContents)) {
         $itineraryContents = [$itineraryContents];
     }
     

     if (!is_array($mealPlan)) {
         $mealPlan = explode(',', $mealPlan);
     }
     $mealPlan = array_pad($mealPlan, 6, '');

     // Call helper function to add the row for each day
     $this->addDayItinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition, $itineraryHeight);
   }

   private function addDayItinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition, $itineraryHeight) {
    $this->SetFont('Helvetica', '', 9);
    $this->SetCellPadding(1, 8, 0, 8);  // This is the default uniform padding for all sides.

    // Set dynamic line height for the itinerary rows
    $lineHeight = $itineraryHeight / 10;

    // Set initial Y position dynamically
    $this->SetY($yPosition);

    // Adjust height to match the combined height of itinerary and meal plan
    $adjustedHeight = $itineraryHeight - 21.8;

    // Render Day column (leftmost column for the day)
    $this->SetXY(10, $yPosition);
    $this->Cell(13, $adjustedHeight - 7, $day, 1, 0, 'C'); // Adjusted height for Day column

    // Render 3 single cells for the area column, one for each part of the area
    $this->SetXY(23, $yPosition);
    $areaCellHeight = $adjustedHeight / 3; // Divide the total area height into 3 parts


    $this->SetFont('Helvetica', 'B', 9);
    $this->SetTextColor(0, 150, 255);   

    // First area cell
    $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

    // Second area cell
    $this->SetXY(23, $this->GetY() + 4);
    $this->Cell(22, $areaCellHeight, $area[1], 'LR', 0, 'C'); 

    // Third area cell
    $this->SetXY(23, $this->GetY() + 8);
    $this->Cell(22, $areaCellHeight, $area[2], 'LR', 0, 'C'); 

    $this->SetTextColor(0, 0, 0);
    $this->SetFont('Helvetica', '', 9);

    // Render Itinerary content vertically, row by row
    $this->SetXY(45, $yPosition);
    $this->Cell(125, $lineHeight, $itineraryContents[0], 0, 0, 'L');
    $this->SetXY(45, $this->GetY() + $lineHeight - 1);
    $this->Cell(125, $lineHeight, $itineraryContents[1], 0, 0, 'L');
    $this->SetXY(45, $this->GetY() + $lineHeight - 1);
    $this->Cell(125, $lineHeight, $itineraryContents[2], 0, 0, 'L');

    // Add hotel information
    $hotel1 = "Air Sky Hotel";
    $hotel2 = "Emporium Hotel";

    $this->SetFont('Helvetica', 'B', 9);
    $this->SetXY(45, $this->GetY() + $lineHeight + 2.29);
    $this->Cell(30, 3, 'HOTEL:', 'B', 0, 'L');
    $this->SetTextColor(0, 150, 255); 
    $this->Cell(35, 3, $hotel[0], 'B', 0, 'L');
    $this->SetTextColor(0, 0, 0);
    $this->Cell(25, 3, 'or', 'B', 0, 'L');
    $this->SetTextColor(0, 150, 255);
    $this->Cell(35, 3, $hotel[1], 'B', 0, 'L');
    $this->SetTextColor(0, 0, 0);

    $this->SetFont('Helvetica', 'B', 9);
    // Render Meal Plan content with 6 rows
    $mealPlanHeight = ($itineraryHeight / 15) + 0.68; // Height for each individual row

    $this->SetXY(170, $yPosition);
    $this->Cell(30, $mealPlanHeight, "", 'LRT', 0, 'C');
    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
    $this->Cell(30, $mealPlanHeight, "SNACKS", 'LR', 0, 'C');
    $this->SetXY(170, $this->GetY() + $mealPlanHeight - 5);
    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
  }

  
  // For Day 2 & 3
  public function day2($daysData) {
   $yPosition2 = $this->GetY();  // Start from the current Y position (Day 1)

   // Loop through all provided days and add itinerary and meal plan for each day
   foreach ($daysData as $index => $dayData) {
       // If it's Day 2, reset the Y position to Day 1's Y position or adjust as needed
       if ($index > 0) {
           $this->SetY($yPosition2);  // Reset Y position to the starting Y position of Day 1
       }

       // Call the function to add the itinerary row
       $this->addItineraryRow2(
           $dayData['day'], 
           $dayData['area'], 
           $dayData['itinerary'], 
           $dayData['mealPlan'], 
           $dayData['hotel'],   
           $yPosition2, 
           $dayData['itineraryHeight']
       );

       // Update Y position after each day's content
       $yPosition2 = $this->GetY(); // Update Y position after rendering the day
   }
  }

  // Modify the addItineraryRow2 function to accept dynamic Y position
  private function addItineraryRow2($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition2, $itineraryHeight) {
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
   $this->addDay2Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition2, $itineraryHeight);
  }

  
   private function addDay2Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition2, $itineraryHeight) {
      $this->SetFont('Helvetica', '', 9.5);
      $this->SetCellPadding(1, 8, 0, 8);  // This is the default uniform padding for all sides.

      // Set dynamic line height for the itinerary rows
      $lineHeight = $itineraryHeight / 10;

      // Adjust or reset the Y position here if needed for Day 2
      $this->SetY($yPosition2);  // Ensure this is where Day 2 content starts

      // Render Day and Area columns (leftmost columns for the day and area)
      $this->SetXY(10, $yPosition2 + 6.2);
      $this->Cell(13, ($itineraryHeight - 1.85), $day, 'LRBT', 0, 'C');

      $cellHeight = $itineraryHeight + 5.6;
      $availableHeight = $cellHeight;  // No need to subtract from anything if you start at $yPosition

      // Calculate the vertical offset for centering
      $centeredYPosition = $yPosition2 + (($availableHeight - $cellHeight) / 2);

      // Render 3 single cells for the area column, one for each part of the area
      $this->SetXY(23, $yPosition2 + 6.2);
      $areaCellHeight = 40 / 3; // Divide the total area height into 3 parts

      $this->SetTextColor(0, 150, 255); 
      $this->SetFont('Helvetica', 'B', 9);
      // First area cell
      $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

      // Second area cell
      $this->SetXY(23, $this->GetY() + 11.1);
      $this->Cell(22, $areaCellHeight + 1, $area[1], 'LR', 0, 'C'); 

      // Third area cell
      $this->SetXY(23, $this->GetY() + 12.7);
      $this->Cell(22, $areaCellHeight + 1, $area[2], 'BLR', 0, 'C'); 

      $this->SetTextColor(0, 0, 0); 

      $this->SetFont('Helvetica', '', 9);

      // Render Itinerary content vertically, row by row
      $this->SetXY(45, $yPosition2 + 6.2);
      foreach ($itineraryContents as $itinerary) {
          $this->Cell(125, $lineHeight, $itinerary, 0, 0, 'L');
          $this->SetXY(45, $this->GetY() + $lineHeight);
      }

      
      $this->SetFont('Helvetica', 'B', 9);
      $this->SetXY(45, $this->GetY() + $lineHeight - 11.9);
      $this->Cell(30, 5, 'HOTEL:', 'B', 0, 'L'); // Bottom border on last line
      $this->SetTextColor(0, 150, 255); 
      $this->Cell(35, 5, $hotel[0], 'B', 0, 'L'); // Bottom border on last line
      $this->SetTextColor(0, 0, 0); 
      $this->Cell(25, 5, 'OR', 'B', 0, 'L'); // Bottom border on last line
      $this->SetTextColor(0, 150, 255);
      $this->Cell(35, 5, $hotel[1], 'B', 0, 'L'); // Bottom border on last line
      $this->SetTextColor(0, 0, 0); 


      $this->SetFont('Helvetica', '', 8);

      // Render Meal Plan content with 6 rows
      $mealPlanHeight = ($itineraryHeight / 7) + .65; // Height for each individual row

      $this->SetFont('Helvetica', 'B', 8);
      $this->SetXY(170, $yPosition2 + 6.2);
      $this->Cell(30, $mealPlanHeight, "BREAKFAST", 'LRT', 0, 'C');

      $this->SetTextColor(0, 150, 255);
      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
      $this->Cell(30, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
      $this->SetTextColor(0, 0, 0);

      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
      $this->Cell(30, $mealPlanHeight, "LUNCH", 'LR', 0, 'C');

      $this->SetTextColor(0, 150, 255);
      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
      $this->Cell(30, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
      $this->SetTextColor(0, 0, 0);

      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
      $this->Cell(30, $mealPlanHeight, "DINNER", 'LR', 0, 'C');

      $this->SetTextColor(0, 150, 255);
      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
      $this->Cell(30, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
      $this->SetTextColor(0, 0, 0);
  }

   

  // FOR DAY 4
  public function day4($daysData) {
   $yPosition4 = $this->GetY();  // Start from the current Y position (Day 3)

   // Loop through all provided days and add itinerary and meal plan for each day
   foreach ($daysData as $index => $dayData) {
       // If it's Day 4, reset the Y position to Day 3's Y position or adjust as needed
       if ($index > 0) {
           $this->SetY($yPosition4);  // Reset Y position to the starting Y position of Day 3
       }

       // Call the function to add the itinerary row
       $this->addItineraryRow4(
           $dayData['day'], 
           $dayData['area'], 
           $dayData['itinerary'], 
           $dayData['mealPlan'], 
           $dayData['hotel'],   
           $yPosition4, 
           $dayData['itineraryHeight']
       );

       // Update Y position after each day's content
       $yPosition4 = $this->GetY(); // Update Y position after rendering the day
   }
  }

  // Modify the addItineraryRow4 function to accept dynamic Y position
  private function addItineraryRow4($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition4, $itineraryHeight) {
   // Ensure the itinerary content is an array
   if (!is_array($itineraryContents)) {
       $itineraryContents = [$itineraryContents];
   }

   // Ensure there are exactly 8 rows in itinerary content (pad if necessary)
   $itineraryContents = array_pad($itineraryContents, 8, '');

   // Ensure mealPlan has exactly 6 elements
   if (!is_array($mealPlan)) {
       $mealPlan = explode(',', $mealPlan);
   }
   $mealPlan = array_pad($mealPlan, 6, '');

   // Call helper function to add the row for each day
   $this->addDay4Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition4, $itineraryHeight);
  }

  private function addDay4Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition4, $itineraryHeight) {
   $this->SetFont('Helvetica', '', 9.5);
   $this->SetCellPadding(1, 8, 0, 8);  // Uniform padding for all sides

   // Set dynamic line height for the itinerary rows
   $lineHeight = $itineraryHeight / 8;

   // Adjust or reset the Y position here if needed for Day 4
   $this->SetY($yPosition4);  // Ensure this is where Day 4 content starts

   
   // Render Day and Area columns (leftmost columns for the day and area)
   $this->SetXY(10, $yPosition4 + 6.2);
   $this->Cell(13, ($itineraryHeight + 8.2), $day, 'LRBT', 0, 'C');

   $cellHeight = $itineraryHeight + 5.6;

   // Render 3 single cells for the area column
   $this->SetXY(23, $yPosition4 + 6.2);
   $areaCellHeight = 70 / 3; // Divide total area height into 3 parts

   $this->SetFont('Helvetica', 'B', 9);
   $this->SetTextColor(0, 150, 255); 
   // First area cell
   $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

   // Second area cell
   $this->SetXY(23, $this->GetY() + 11.1);
   $this->Cell(22, $areaCellHeight + 1, $area[1], 'LR', 0, 'C'); 

   // Third area cell
   $this->SetXY(23, $this->GetY() + 12.8);
   $this->Cell(22, $areaCellHeight + 1, $area[2], 'BLR', 0, 'C'); 

   $this->SetTextColor(0, 0, 0); 

   $this->SetFont('Helvetica', '', 9);

   // Render Itinerary content vertically, row by row
   $this->SetXY(45, $yPosition4 + 6.2);
   foreach ($itineraryContents as $itinerary) {
       $this->Cell(125, $lineHeight , $itinerary, 0, 0, 'L');
       $this->SetXY(45, $this->GetY() + $lineHeight);
   }

   $this->SetFont('Helvetica', 'B', 9);
   $this->SetXY(45, $this->GetY() + $lineHeight - 2.8);
   $this->Cell(30, 5, 'HOTEL:', 'B', 0, 'L'); // Bottom border on last line
   $this->SetTextColor(0, 150, 255); 
   $this->Cell(35, 5, $hotel[0], 'B', 0, 'L'); 
   $this->SetTextColor(0, 0, 0); 
   $this->Cell(25, 5, 'OR', 'B', 0, 'L'); 
   $this->SetTextColor(0, 150, 255); 
   $this->Cell(35, 5, $hotel[1], 'B', 0, 'L'); 
   $this->SetTextColor(0, 0, 0); 

   // Render Meal Plan content with 6 rows
   $mealPlanHeight = ($itineraryHeight / 7) + 2.32; // Height for each individual row

   $this->SetFont('Helvetica', 'B', 8);
   $this->SetXY(170, $yPosition4 + 6.2);
   $this->Cell(30, $mealPlanHeight, "BREAKFAST", 'LRT', 0, 'C');

   $this->SetTextColor(0, 150, 255);
   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
   $this->SetTextColor(0, 0, 0);

   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, "LUNCH", 'LR', 0, 'C');

   $this->SetTextColor(0, 150, 255);
   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
   $this->SetTextColor(0, 0, 0);

   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, "DINNER", 'LR', 0, 'C');

   $this->SetTextColor(0, 150, 255);
   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
   $this->SetTextColor(0, 0, 0);
  }

  // FOR DAY 5
  public function day5($daysData) {
   $yPosition5 = $this->GetY();  // Start from the current Y position (Day 3)

   // Loop through all provided days and add itinerary and meal plan for each day
   foreach ($daysData as $index => $dayData) {
       // If it's Day 4, reset the Y position to Day 3's Y position or adjust as needed
       if ($index > 0) {
           $this->SetY($yPosition5);  // Reset Y position to the starting Y position of Day 3
       }

       // Call the function to add the itinerary row
       $this->addItineraryRow5(
           $dayData['day'], 
           $dayData['area'], 
           $dayData['itinerary'], 
           $dayData['mealPlan'], 
           $dayData['hotel'],   
           $yPosition5, 
           $dayData['itineraryHeight']
       );

       // Update Y position after each day's content
       $yPosition5 = $this->GetY(); // Update Y position after rendering the day
   }
  }

  // Modify the addItineraryRow4 function to accept dynamic Y position
  private function addItineraryRow5($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition5, $itineraryHeight) {
   // Ensure the itinerary content is an array
   if (!is_array($itineraryContents)) {
       $itineraryContents = [$itineraryContents];
   }

   // Ensure there are exactly 8 rows in itinerary content (pad if necessary)
   $itineraryContents = array_pad($itineraryContents, 8, '');

   // Ensure mealPlan has exactly 6 elements
   if (!is_array($mealPlan)) {
       $mealPlan = explode(',', $mealPlan);
   }
   $mealPlan = array_pad($mealPlan, 6, '');

   // Call helper function to add the row for each day
   $this->addDay5Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition5, $itineraryHeight);
  }

  private function addDay5Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition5, $itineraryHeight) {
   $this->SetFont('Helvetica', '', 9.5);
   $this->SetCellPadding(1, 3, 1, );  // Uniform padding for all sides

   // Set dynamic line height for the itinerary rows
   $lineHeight = $itineraryHeight / 8;

   // Adjust or reset the Y position here if needed for Day 4
   $this->SetY($yPosition5);  // Ensure this is where Day 4 content starts

   
   // Render Day and Area columns (leftmost columns for the day and area)
   $this->SetXY(10, $yPosition5 + 8);
   $this->Cell(13, ($itineraryHeight + 8.2), $day, 'LRBT', 0, 'C');

   $cellHeight = $itineraryHeight + 5.6;

   // Render 3 single cells for the area column
   $this->SetXY(23, $yPosition5 + 8);
   $areaCellHeight = 70 / 3; // Divide total area height into 3 parts

   $this->SetFont('Helvetica', 'B', 9);
   $this->SetTextColor(0, 150, 255); 

   // First area cell
   $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

   // Second area cell
   $this->SetXY(23, $this->GetY() + 11.1);
   $this->Cell(22, $areaCellHeight + 1, $area[1], 'LR', 0, 'C'); 

   // Third area cell
   $this->SetXY(23, $this->GetY() + 12.8);
   $this->Cell(22, $areaCellHeight + 1, $area[2], 'BLR', 0, 'C'); 

   $this->SetTextColor(0, 0, 0); 

   $this->SetFont('Helvetica', '', 9);
   // Render Itinerary content vertically, row by row
   $this->SetXY(45, $yPosition5 + 6.8);
   foreach ($itineraryContents as $itinerary) {
       $this->Cell(125, $lineHeight , $itinerary, 0, 0, 'L');
       $this->SetXY(45, $this->GetY() + $lineHeight);
   }

   $this->SetFont('Helvetica', 'B', 9);
   $this->SetXY(45, $this->GetY() + $lineHeight - 1.55);
   
   $this->Cell(62.5, 5, $hotel[0], 'B', 0, 'C'); 
   $this->SetTextColor(0, 150, 255); 
   $this->Cell(62.5, 5, $hotel[1], 'B', 0, 'C'); 
   $this->SetTextColor(0, 0, 0); 
   $this->SetFont('Helvetica', '', 9);


   // Render Meal Plan content with 6 rows
   $mealPlanHeight = ($itineraryHeight / 7) + 2.32; // Height for each individual row

   $this->SetFont('Helvetica', 'B', 8);
   $this->SetXY(170, $yPosition5 + 8);
   $this->Cell(30, $mealPlanHeight, "BREAKFAST", 'LRT', 0, 'C');

   $this->SetTextColor(0, 150, 255);
   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
   $this->SetTextColor(0, 0, 0);

   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, "LUNCH", 'LR', 0, 'C');

   $this->SetTextColor(0, 150, 255);
   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
   $this->SetTextColor(0, 0, 0);

   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, "DINNER", 'LR', 0, 'C');

   $this->SetTextColor(0, 150, 255);
   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
   $this->Cell(30, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
   $this->SetTextColor(0, 0, 0);
  }


 // Add hotel info table
 public function SecondPage() {
   $this->SetFont('Helvetica', 'B', 10);

   // CONDITIONS ROW
   $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
   $this->SetXY(10, 30);  // Adjust the X and Y position if needed
   $this->Cell(40, 30, 'CONDITIONS', 'LRTB', 0, 'C', TRUE);  // Borders on all sides, center-aligned text

   $this->SetFont('Helvetica', '', 9);
   $this->SetXY(50, 30); 
   $this->MultiCell(150, 30, 
   "  * English speaking guide or driving guide
  * Four (4) times lunch, Four (4) times dinner,
  * Four (4) Nights with twin or triple sharing
  * Daily 1 Bottled Water
  * Guide Tipping Fee ($25 per/person) is not included,
  * Included admission fee : As per Itinerary
  * For any unused portion is not refundable ", 'LRTB', 'L');


  // REMARKS ROW
  $this->SetFont('Helvetica', 'B', 10);

  $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
  // Create a vertical "HOTEL" cell spanning multiple rows
  $this->SetXY(10, 60);  // Adjust the X and Y position if needed
  $this->Cell(40, 26, 'REMARKS', 'LRTB', 0, 'C', true);  // Borders on all sides, center-aligned text

  $this->SetFont('Helvetica', '', 9);
  $this->SetXY(50, 60); 
  $this->MultiCell(150, 26, 
  "  * The tour fare is valid at least 5 rooms sharing with ADT basic. (TWN/DLB/TRIP sharing)
  * The fare is not valid during Japanese holiday.
  * The quotation is based on the group tour, So, the individual schedule is not permitted in this itinerary.
  * Please be noted that below rate is with compulsory shop visiting, please kindly explain to pax that they
    have to visit. Surcharge of not vising the shop is as follows, Ginseng Outlet (USD 30) / Amethyst 
    Showcase (USD 30) / Red Pine Shop (USD 30) / Cosmetics Shop (USD 30) / DFS (USD 20)", 'LRTB', 'L');


  // Set Colors
  $this->SetFillColor(255, 234, 215);

  // Position and Styling
  $this->SetXY(10, 86); 
  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides
  $this->SetFont('Helvetica', 'B', 10);

  // Render Cell with Fill
  $this->MultiCell(190, 5, "GROUP TOUR DATE", 'LRBT', 'C', true);
  
  $this->SetXY(10, 92.5); 

  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides

  $this->SetFont('Helvetica', 'B', 10);
  $this->Cell(60, 5, "", 'LRB', 0, 'C');
  $this->Cell(26, 5, "", 'LRB', 0, 'C');
  $this->Cell(26, 5, "", 'LRB', 0, 'C');
  $this->Cell(26, 5, "", 'LRB', 0, 'C');
  $this->Cell(26, 5, "", 'LRB', 0, 'C');
  $this->Cell(26, 5, "LAND ONLY", 'LRB', 1, 'C');

  $this->SetFont('Helvetica', 'B', 10);

  $this->SetXY(10, 99); 
  $this->MultiCell(30, 26, "", 'LRB', 'C');

  $this->SetXY(40, 99); 
  $this->Cell(30, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');

  $this->SetXY(40, 112); 
  $this->Cell(30, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');

  $this->SetFont('Helvetica', 'B', 10);

  $this->SetFillColor(255, 234, 215); // Set the fill color (Light peach)

  $this->SetXY(10, 125); 
  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides

  $this->MultiCell(190, 5, "FIT TOUR RATE", 'LRTB', 'C', true); // Ensure fill parameter is true


  $this->SetXY(10, 131.5); 
  $this->MultiCell(30, 26, "", 'LRB', 'C');

  $this->SetXY(40, 131.5); 
  $this->Cell(30, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');

  $this->SetXY(40, 144.5); 
  $this->Cell(30, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');
  $this->Cell(26, 13, "", 'LRB', 0, 'C');

  $this->SetFont('Helvetica', 'B', 10);
  $this->SetFillColor(255, 234, 215); // Background color
  $this->SetTextColor(0, 0, 0);       // Text color
  
  // Positioning and rendering
  $this->SetXY(10, 157.5); 
  $this->Cell(40, 18, "CHILD POLICY", 'LRBT', 0, 'C', true);

  $this->SetFont('Helvetica', '', 8);
  $this->SetXY(50, 157.5); 
  $this->Cell(50, 9, "CHILD UNDER 2 YEARS OLD", 'LRB', 0, 'C');
  $this->SetFont('Helvetica', '', 7);
  $this->Cell(50, 9, "3-7 YEARS OLD WITHOUT USING BED", 'LRB', 0, 'C');
  $this->SetFont('Helvetica', '', 8);
  $this->Cell(50, 9, "SHARING A HALF-TWIN ROOM", 'LRB', 0, 'C');

  $this->SetXY(50, 166.5); 
  $this->Cell(50, 9, "FREE OF CHARGE", 'LRB', 0, 'C');
  $this->Cell(50, 9, "70% ADULT FARE", 'LRB', 0, 'C');
  $this->Cell(50, 9, "100% ADULT FARE", 'LRB', 0, 'C');


  $this->SetFont('Helvetica', 'B', 10);
  $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
  $this->SetXY(10, 175.5); 
  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides
  $this->MultiCell(190, 5, "CANCELLATION POLICY", 'LRTB', 'C', true); // Ensure fill parameter is true

  $this->SetFont('Helvetica', '', 10);
  $this->SetXY(10, 182); 
  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides
  $this->MultiCell(190, 10,
  "  
  * Before 15 days of Departure: 100% tour fare refund (No cancellation charge)
  * Before 8-14 days of Departure: 50% tour fare refund (50% cancellation charge)
  * Before 4-7 days of Departure: 30% tour fare refund (70% cancellation charge)
  * Before 1-3 days of Departure: 0% tour fare refund (100% cancellation charge)
  ", 'LRTB', 'L', false);
 }

}

// Create a new PDF instance and add pages as needed
$pdf = new PDF();

// Set margins
$pdf->SetMargins(10, 10, 10); // Adjust to provide consistent spacing

$pdf->AddPage();
$pdf->addHotelInfoTable();
$pdf->addItineraryHeader();

$daysData = [
  [
      'day' => 1, 
      'area' => ['','INCHEON' ,''],
      'itinerary' => [
          'Arrive at Incheon Airport, transfer to the hotel.',
          'Check-in and freshen up at the hotel.',
          'Explore the hotel surroundings or relax.',        
       ], 
      'hotel' => ['Air Sky Hotel', 'Emporium Hotel'],
      'mealPlan' => ['Hotel B/F', 'BBQ Chicken', 'Korean Chinese Food'],
      'itineraryHeight' => 50
  ],
];

$pdf->day($daysData);


$daysData = [
   [
       'day' => 2,
       'area' => ['SEOUL', 'INCHEON', 'GANGWOON'], 
       'itinerary' => [
           'Arrive at Incheon Airport, transfer to the hotel.',
           'Check-in and freshen up at the hotel.',
           'Explore the hotel surroundings or relax.',
           'Welcome dinner at a local restaurant featuring Korean cuisine.',
           'Transfer to Incheon Airport for Departure.',
           'Shopping for Korean Food & Souvenir.',
           'Visit Gyeongbokgung Palace.',
       ],
       'hotel' => ['Centum Hotel', 'Marina Bay Hotel'],
       'mealPlan' => ['Hotel B/F', 'BBQ Chicken', 'Korean Chinese Food'],
       'itineraryHeight' => 40
   ],
   [
    'day' => 3,
    'area' => ['INCHEON', 'GYEONGGI', 'SEOUL'], 
    'itinerary' => [
        'Arrive at Incheon Airport, transfer to the hotel.',
        'Check-in and freshen up at the hotel.',
        'Explore the hotel surroundings or relax.',
        'Welcome dinner at a local restaurant featuring Korean cuisine.',
        'Transfer to Incheon Airport for Departure.',
        'Shopping for Korean Food & Souvenir.',
        'Visit Gyeongbokgung Palace.',
    ],
    'hotel' => ['Smart Hotel', 'Marina Bay Hotel'],
    'mealPlan' => ['Hotel B/F', 'BBQ Chicken', 'Korean Chinese Food'],
    'itineraryHeight' => 40
  ],
];

$pdf->day2($daysData);

$daysData = [
 [
     'day' => 4,
     'area' => ['', 'SEOUL', ''], 
     'itinerary' => [
         'Morning coffee at the hotel.',
         'Departure to Bukchon Hanok Village for sightseeing.',
         'Experience traditional Korean tea ceremony.',
         'Lunch at a Michelin-starred Korean restaurant.',
         'Visit to the National Museum of Korea.',
         'Relax and explore Namsan Seoul Tower.',
         'Evening shopping at Myeongdong Market.',
         'End the day with a K-pop concert at a local venue.',
     ],
     'hotel' => ['Smart Hotel', 'Marina Bay Hotel'],
     'mealPlan' => ['Coffee & Snacks', 'Korean Lunch', 'Buffet Dinner'],
     'itineraryHeight' => 40
    ],
];

$pdf->day4($daysData);


$daysData = [
 [
     'day' => 5,
     'area' => ['SEOUL', 'INCHEON', ''], 
     'itinerary' => [
         'Morning coffee at the hotel.',
         'Departure to Bukchon Hanok Village for sightseeing.',
         'Experience traditional Korean tea ceremony.',
         'Lunch at a Michelin-starred Korean restaurant.',
         'Visit to the National Museum of Korea.',
         'Relax and explore Namsan Seoul Tower.',
         'Evening shopping at Myeongdong Market.',
         'End the day with a K-pop concert at a local venue.',
     ],
     'hotel' => ['Depart From Incheon Airport', '(5J187 00:45)'],
     'mealPlan' => ['Coffee & Snacks', 'Korean Lunch', 'Buffet Dinner'],
     'itineraryHeight' => 40
    ],
];

$pdf->day5($daysData);

$pdf->AddPage();

$pdf->SecondPage();

$pdf->Output('itinerary-Winter.pdf', 'I');
?>
