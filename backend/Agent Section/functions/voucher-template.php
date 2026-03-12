<?php
require_once('../../tcpdf/tcpdf.php');

class PDF extends TCPDF {
  public function Header() {
   if ($this->getPage() == 1) { // Check if it's the first page
    // Add logo
    $this->Image('../../Assets/Logos/SMART LOGO 2 (2).jpg', 10, 10, 65, 13); // Adjust 'logo.png' path, position, and size as needed
    $this->Ln(30); // Adds 30mm of vertical space

    $this->SetFont('Helvetica', 'B', 10);
    $this->Cell(100, 8, '', 1, 0, 'L');
    $this->SetTextColor(0, 0, 0);

    // Date section
    $this->SetXY(135, 30); // Adjust the X and Y position if needed
    $this->Cell(20, 8, '  Date: ', 1, 0, 'L');
    $this->Cell(45, 8, '', 1, 1, 'L'); // Cell width adjusted

    // Voucher title
    $this->SetFillColor(211, 211, 211); // Set the fill color
    $this->SetTextColor(0, 0, 0); // Text color
    $this->SetFont('Helvetica', 'B', 12, true);
    $this->Cell(0, 10, 'VOUCHER', 'LRTB', 1, 'C', true);

    // Add TO, ATTACHMENT, etc.
    $this->SetFont('Helvetica', '', 10, true);
    $this->SetXY(10, 50);
    $this->Cell(25, 8, 'TO', 1, 0, 'C');
    $this->Cell(75, 8, '', 1, 0, 'C');
    $this->Cell(30, 8, 'ATTACHMENT', 1, 0, 'C');
    $this->Cell(60, 8, '', 1, 1, 'C');

    $this->SetXY(10, 58);
    $this->Cell(25, 8, 'FROM', 1, 0, 'C');
    $this->Cell(75, 8, '', 1, 0, 'C');
    $this->Cell(30, 8, 'TOUR PERIOD', 1, 0, 'C');
    $this->Cell(60, 8, '', 1, 1, 'C');

    $this->SetXY(10, 66);
    $this->Cell(25, 8, 'TO', 1, 0, 'C');
    $this->Cell(75, 8, '', 1, 0, 'C');
    $this->Cell(30, 8, 'PAX & ROOM', 1, 0, 'C');
    $this->Cell(60, 8, '', 1, 1, 'C');

    $this->Ln(2); // Adds 2mm of vertical space


    // Voucher title
    $this->SetFillColor(211, 211, 211); // Set the fill color
    $this->SetTextColor(0, 0, 0); // Text color
    $this->SetFont('Helvetica', 'B', 12, true);
    $this->Cell(0, 10, 'TOUR CONDITION', 'LRTB', 1, 'C', true);
   }
}

    
// Add voucher header
public function VoucherHeader() {
 $this->SetFont('Helvetica', 'B', 10);

 // Add some space after the hotel info table (to prevent overlap)
 $this->Ln(2);

 // Set the X and Y for the header
 $this->SetXY(10, 86);

 // Define the widths for each column
 $col1 = 25;
 $col2 = 40;
 $col3 = 15;
 $col4 = 30;
 $col5 = 80;

 $this->SetFont('Helvetica', '', 10, true);
 $this->SetFillColor(255, 255, 255); // White background
 $this->SetTextColor(0, 0, 0); // Black text color

 $this->SetFont('Helvetica', 'B', 10);
 // Render header cells
 $this->Cell($col1, 7, '', 1, 0, 'C', true);  // Empty cell
 $this->Cell($col2, 7, 'DATE', 1, 0, 'C', true);
 $this->Cell($col3, 7, 'N', 1, 0, 'C', true);
 $this->Cell($col4, 7, 'CITY', 1, 0, 'C', true);
 $this->Cell($col5, 7, 'HOTEL', 1, 1, 'C', true);

 // Reset text color
 $this->SetTextColor(0, 0, 0);
}


public function TourConditionFirst($data3) {
 $this->SetFont('Helvetica', '', 10);

 // Set initial X and Y position
 $this->SetXY(10, 93);

 // Define column widths
 $col1 = 25; // Width for "HOTEL" Column
 $col2 = 40; // Width for "Start Date to End Date" Column
 $col3 = 15; // Width for remaining empty space

 // Row 1
 $this->Cell($col1, 90, $data3[0]['hotel'], 1, 0, 'C'); // Hotel
 $this->Cell($col2, 45, $data3[0]['date_range'], 1, 0, 'C'); // Start Date to End Date
 $this->Cell($col3, 45, '1N', 1, 1, 'C'); // Empty space

 
 $this->SetXY(35, 138);
 $this->Cell($col2, 45, $data3[1]['date_range'], 1, 0, 'C'); // Start Date to End Date

 $this->Cell($col3, 45, '2N', 1, 1, 'C'); // Empty space

}

public function TourConditionSecond($data2) {

// First set
$this->SetFont('Helvetica', 'B', 11);

$this->SetXY(90, 93);                                    
$this->Cell(30, 30, $data2[0]['city'], 1, 0, 'C');

$this->SetFont('Helvetica', '', 12);
$this->SetCellPadding(1, 20, 1, 20); 
$this->Cell(80, 9, $data2[0]['hotel'], 'LR', 1, 'C'); 

$this->SetFont('Helvetica', '', 8.5);
$this->SetCellPadding(1, 20, 1, 20); // Set padding for the cell
$this->SetXY(120, 100); // Set the position for the MultiCell
$this->MultiCell(80, 7.5, $data2[0]['address'], 'LR', 'C'); // Center the text

$this->SetFont('Helvetica', '', 9);
$this->SetXY(120, 106); // Set the position for the MultiCell
$this->MultiCell(80, 6, "TEL: " . $data2[0]['telephone'], 'LR', 'C'); 

$this->SetTextColor(0, 0, 255);
$this->SetFont('Helvetica', 'U', 8.5);

$this->SetXY(120, 112);
$this->Cell(80, 11, $data2[0]['hotelLink'], 1, 1, 'C');

$this->SetTextColor(0, 0, 0);
$this->SetFont('Helvetica', '', 8.5);




// Second set
$this->SetFont('Helvetica', 'B', 11);
$this->SetXY(90, 123);  // Adjusted Y position for second set
$this->Cell(30, 30, $data2[1]['city'], 1, 0, 'C');

$this->SetFont('Helvetica', '', 12);
$this->SetCellPadding(1, 20, 1, 20); 
$this->Cell(80, 9, $data2[1]['hotel'], 'LR', 1, 'C'); 

$this->SetFont('Helvetica', '', 8.5);
$this->SetCellPadding(1, 20, 1, 20); // Set padding for the cell
$this->SetXY(120, 130); // Set the position for the MultiCell
$this->MultiCell(80, 7.5, $data2[1]['address'], 'LR', 'C'); // Center the text

$this->SetFont('Helvetica', '', 9);
$this->SetXY(120, 136); // Set the position for the MultiCell
$this->MultiCell(80, 6, "TEL: " . $data2[1]['telephone'], 'LR', 'C'); 

$this->SetTextColor(0, 0, 255);
$this->SetFont('Helvetica', 'U', 8.5);

$this->SetXY(120, 142);
$this->Cell(80, 11, $data2[1]['hotelLink'], 1, 1, 'C');

$this->SetTextColor(0, 0, 0);
$this->SetFont('Helvetica', '', 8.5);





// Third set
$this->SetFont('Helvetica', 'B', 11);
$this->SetXY(90, 153);  // Adjusted Y position for third set
$this->Cell(30, 30, $data2[2]['city'], 1, 0, 'C');

$this->SetFont('Helvetica', '', 12);
$this->SetCellPadding(1, 20, 1, 20); 
$this->Cell(80, 9, $data2[2]['hotel'], 'LR', 1, 'C'); 

$this->SetFont('Helvetica', '', 8.5);
$this->SetCellPadding(1, 20, 1, 20); // Set padding for the cell
$this->SetXY(120, 160); // Set the position for the MultiCell
$this->MultiCell(80, 7.5, $data2[2]['address'], 'LR', 'C'); // Center the text

$this->SetFont('Helvetica', '', 9);
$this->SetXY(120, 166); // Set the position for the MultiCell
$this->MultiCell(80, 6, "TEL: " . $data2[2]['telephone'], 'LR', 'C'); 

$this->SetTextColor(0, 0, 255);
$this->SetFont('Helvetica', 'U', 8.5);

$this->SetXY(120, 172);
$this->Cell(80, 11, $data2[2]['hotelLink'], 1, 1, 'C');

$this->SetTextColor(0, 0, 0);
$this->SetFont('Helvetica', '', 8.5);

}



public function TourConditionThird($FlightData, $guideMeeting) {
 $this->SetFont('Helvetica', '', 10);
 $col1 = 25; 
 $col2 = 165; 

$this->SetFont('Helvetica', 'B', 8);
$this->SetXY(10, 183); 
$this->Cell($col1, 15, 'TOUR GUIDE', 1, 0, 'C');  

$tourGuide = 'Mr. Mikey Lee (+82-10-4789-1157)';

$this->SetFont('Helvetica', 'B', 10);
$this->SetXY(35, 183);

$this->SetCellPadding(3, 10, 3, 10); 


$this->MultiCell($col2, 15, $tourGuide, 1, 'C', 0, 1);

$this->SetFont('Helvetica', 'B', 8);
$this->SetXY(10, 198); 
$this->Cell($col1, 20, 'AIR SCHEDULE', 1, 0, 'C'); 


// Set font and adjust position for the Arrival and Departure labels
$this->SetFont('Helvetica', 'B', 11);

// Set font and adjust position for the labels and data
$this->SetFont('Helvetica', 'B', 10);

// Departure Information
$this->SetXY(35, 197.5); 
$this->Cell($col2, 10, "         Departure: " . $FlightData[0]['deptDate'] . ' ' . $FlightData[0]['flight_id'] . ' ' . $FlightData[0]['origin'] . ' (' . $FlightData[0]['time'] . ')', 'LRB', 0, 'L');

// Arrival Information
$this->SetXY(35, 208); 
$this->Cell($col2, 10, "         Arrival: " . $FlightData[1]['arrivalDate'] . ' ' . $FlightData[1]['flight_id'] . ' ' . $FlightData[1]['origin'] . ' (' . $FlightData[1]['time'] . ')', 'LR', 0, 'L');


 $this->SetFont('Helvetica', 'B', 8);
 $this->SetXY(10, 218); 
 $this->Cell($col1, 20, 'GUIDE MEETING', 1, 0, 'C'); 


 // Set font and position for the MultiCell
 $this->SetFont('Helvetica', '', 10);
 $this->SetXY(35, 218); // Adjust the Y position accordingly

 // Prepare the meeting details with bold 'Date' and normal text for others
 $meetingDetails = "Date: ";
 $this->SetFont('Helvetica', 'B', 10); // Set font to bold for "Date"
 $meetingDetails .= $guideMeeting[0]['date'] . " "; // Add the date in bold
 $this->SetFont('Helvetica', '', 10); // Reset font back to normal for the rest
 $meetingDetails .= "Time: " . $guideMeeting[0]['time'] . " "; // Add time (normal)
 $meetingDetails .= "Airport: " . $guideMeeting[0]['airport']; // Add airport (normal)

 // Output the meeting details horizontally in one MultiCell, centered
 $this->MultiCell($col2, 20, $meetingDetails, 1, 'C');




 $this->SetFont('Helvetica', 'B', 8);
 $this->SetXY(10, 236.5); 
 $this->Cell($col1, 20, 'INCLUDE', 'LR', 0, 'C'); 


 $this->SetFont('Helvetica', '', 9.5);
 $this->SetXY(35, 238); 
 $this->MultiCell($col2, 18.5, 
 'Hotel (4 nights with twin or triple sharing)
  Meals (4 times Lunch, 3 Times Dinner)
 (Coach, Van), Admission as the Itinerary, English guide etc.', 1, 'C'); 


 $this->SetFont('Helvetica', 'B', 8);
 $this->SetXY(10, 256.5); 
 $this->Cell($col1, 10, 'EXCLUDE', 1, 0, 'C'); 


 $this->SetFont('Helvetica', '', 9);
 $this->SetXY(35, 256.5); 
 $this->MultiCell($col2, 11, 'Guide Tip $25 per person', 'LR', 'C');


 $this->SetFont('Helvetica', 'B', 8);
 $this->SetXY(10, 266.5); 
 $this->Cell($col1, 10, 'REMARKS', 1, 0, 'C'); // Center-align the title

 $this->SetFont('Helvetica', '', 9);
 $this->SetXY(35, 266.5); 
 $this->SetFillColor(255, 255, 0);  // Yellow background color
 $this->SetTextColor(255, 0, 0);   // Red text color
 $this->SetCellPadding(2, 8, 2, 8); // Padding around the text

 $this->SetFont('Helvetica', 'B', 10);
 // Justify the text content, center-aligned within the cell
 $this->MultiCell($col2, 10, '** It will be subject to change as the local situation **', 1, 'C', true);

 
}            
}


// Voucher First Part
$data3 = [
 ['hotel' => 'HOTEL', 'date_range' => '01/01/2024 - 01/05/2024'],
 ['hotel' => '', 'date_range' => '01/06/2024 - 01/10/2024'],
];


// Voucher Second Part
$data2 = [
 // Set 1
 [
     'city' => 'Seoul', 
     'hotel' => 'AIR SKY HOTEL', 
     'address' => '31, Eunhasu-ro 29 beon-gil, Jung-gu, Incheon, Korea', 
     'telephone' => '963254125', 
     'hotelLink' => 'https://www.hotelairsky.co.kr'
 ],

 // Set 2
 [
     'city' => 'Busan', 
     'hotel' => 'Busan Grand Hotel', 
     'address' => '123, Haeundae-ro, Haeundae-gu, Busan, Korea', 
     'telephone' => '0512345678', 
     'hotelLink' => 'https://www.busangrandhotel.com'
 ],

 // Set 3
 [
     'city' => 'Jeju', 
     'hotel' => 'Jeju Beach Resort', 
     'address' => '12, Seobendong-ro, Seogwipo-si, Jeju, Korea', 
     'telephone' => '0649876543', 
     'hotelLink' => 'https://www.jejubeachresort.com'
 ]
];

// Data array for Flight Departure and Arrival
$FlightData = [
 ['deptDate' => 'Sep 12', 
 'flight_id' => 'PR468', 
 'origin' => 'MNL - ICN', 
 'time' => '14:10-19:25'],

 ['arrivalDate' => 'Sep 16', 
 'flight_id' => 'PR469', 
 'origin' => 'ICN - MNL', 
 'time' => '20:25-23:30']
];


// Define the guide meeting information
$guideMeeting = [
 ['date' => 'Sept 12, 2024', 'time' => '20:00', 'airport' => 'Incheon Airport (Terminal 1)'],
];


// Create a new PDF instance and add pages as needed
$pdf = new PDF();

// Set margins
$pdf->SetMargins(10, 10, 10); // Adjust to provide consistent spacing

// Add a page and headers
$pdf->AddPage();
$pdf->VoucherHeader();
$pdf->TourConditionFirst($data3);
// Call the TourConditionSecond function with the $data2 array
$pdf->TourConditionSecond($data2);



$pdf->TourConditionThird($FlightData, $guideMeeting);
// Output the PDF
$pdf->Output('itinerary-Winter.pdf', 'I');

?>

           

 //            $this->Ln(2); // Adds 10mm of vertical space

 //            $this->SetY($this->GetY() + 2); // Set the Y position for the line, adjust if needed
 //            $this->Line(10, $this->GetY(), 200, $this->GetY()); // Draw a line from x=10 to x=200 at the current Y position

 //            $this->Ln(1); // Adds 10mm of vertical space

 //            // Header lines
 //            $this->SetFont('Helvetica', 'B', 8);
 //            $this->Cell(15, 0, 'TO :', 0, 0, 'L');
 //            $this->Cell(100, 0, 'TRAVEL', 0, 0, 'L');
 //            $this->Cell(80, 0, 'ATTN :', 0, 0, 'L');
 //            $this->Cell(30, 0, '', 0, 1, 'L');
 //            $this->Cell(15, 5, 'FROM :', 0, 0, 'L');
 //            $this->Cell(100, 5, 'JED KIM', 0, 0, 'L');
 //            $this->Cell(15, 5, 'DATE :', 0, 0, 'L');
 //            $this->Cell(30, 5, '', 0, 1, 'L');

 //            $this->SetY($this->GetY()); // Set the Y position for the line, adjust if needed
 //            $this->Line(10, $this->GetY(), 200, $this->GetY()); // Draw a line from x=10 to x=200 at the current Y position

 //            

 //            // Set up columns for periods and hotel info
 //            $this->SetFont('Helvetica', 'B', 9);

 //            // Create a vertical "HOTEL" cell spanning multiple rows
 //            $this->SetXY(10, 47.3);  // Adjust the X and Y position if needed
 //            $this->Cell(40, 8, 'PERIODS', 'LRB', 0, 'C', false);  // Borders on all sides, center-aligned text
 //            $this->Cell(70, 8, '10, OCT. 2024 - 15, NOV. 2024', 'B', 0, 'C');

 //            $this->Cell(20, 8, 'GUIDE:', 'LB', 0, 'C');
 //            $this->Cell(60, 4, 'Mikey Lee', 'LRB', 1, 'C');

 //            $this->SetXY(140, 51.25);  // Adjust the X and Y position if needed
 //            $this->Cell(60, 4, '82(0)-324-3746', 'LRB', 1, 'C');
 //        }
 //    }

 //    // Add hotel info table
 //    public function addHotelInfoTable() {
 //        $this->SetFont('Helvetica', 'B', 10);

 //        $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
 //        $this->SetTextColor(0,0,0);        // Text color

 //        // Create a vertical "HOTEL" cell spanning multiple rows
 //        $this->SetXY(10, 55.4);  // Adjust the X and Y position if needed
 //        $this->Cell(40, 15, 'HOTEL', 'LRB', 0, 'C', true);  // Borders on all sides, center-aligned text

 //        $this->SetFont('Helvetica', 'B', 10);
 //        $this->SetXY(50, 55.4);  // Adjust the Y to align the cells properly
 //        // Create the cells for the cities (Incheon, Gangwon, Seoul)
 //        $this->Cell(30, 5, 'INCHEON', 'LRB', 0, 'C', true);
 //        $this->Cell(120, 5, '   Air Sky Hotel', 'LRB', 1, 'L', true);

 //        $this->SetXY(50, 60.4);  // Adjust the Y to align the cells properly
 //        $this->Cell(30, 5, 'GANGWON', 'LRB', 0, 'C', true);
 //        $this->Cell(120, 5, '   Centrum Hotel', 'LRB', 1, 'L', true);

 //        $this->SetXY(50, 65.4); // Adjust the Y position after the city names
 //        $this->Cell(30, 5, 'SEOUL', 'LRB', 0, 'C', true);
 //        $this->Cell(120, 5, '   Bernoui Hotel', 'LRB', 1, 'L', true);
 //    }

 //    // Add itinerary header
 //    

 //    public function day($daysData) {
 //     $yPosition = $this->GetY();  // Start from the current Y position (Day 1)
 
 //     // Loop through all provided days and add itinerary, meal plan, and hotel for each day
 //     foreach ($daysData as $index => $dayData) {
 //         // If it's Day 2 or beyond, reset the Y position to Day 1's Y position
 //         if ($index > 0) {
 //             $this->SetY($yPosition);  // Reset Y position to the starting Y position of Day 1
 //         }
 
 //         // Call the function to add the itinerary row
 //         $this->addItineraryRow(
 //             $dayData['day'], 
 //             $dayData['area'], 
 //             $dayData['itinerary'], 
 //             $dayData['mealPlan'], 
 //             $dayData['hotel'],    
 //             $yPosition, 
 //             $dayData['itineraryHeight']
 //         );
         
 //         // Update Y position after each day's content
 //         $yPosition = $this->GetY() + 10;  // Adjust Y position for next day
 //     }
 //   }
 

 //    private function addItineraryRow($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition, $itineraryHeight) {
 //     // Ensure the itinerary content is an array
 //     if (!is_array($itineraryContents)) {
 //         $itineraryContents = [$itineraryContents];
 //     }
     

 //     if (!is_array($mealPlan)) {
 //         $mealPlan = explode(',', $mealPlan);
 //     }
 //     $mealPlan = array_pad($mealPlan, 6, '');

 //     // Call helper function to add the row for each day
 //     $this->addDayItinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition, $itineraryHeight);
 //   }

 //   private function addDayItinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition, $itineraryHeight) {
 //    $this->SetFont('Helvetica', '', 9);
 //    $this->SetCellPadding(1, 8, 0, 8);  // This is the default uniform padding for all sides.

 //    // Set dynamic line height for the itinerary rows
 //    $lineHeight = $itineraryHeight / 10;

 //    // Set initial Y position dynamically
 //    $this->SetY($yPosition);

 //    // Adjust height to match the combined height of itinerary and meal plan
 //    $adjustedHeight = $itineraryHeight - 21.8;

 //    // Render Day column (leftmost column for the day)
 //    $this->SetXY(10, $yPosition);
 //    $this->Cell(13, $adjustedHeight - 7, $day, 1, 0, 'C'); // Adjusted height for Day column

 //    // Render 3 single cells for the area column, one for each part of the area
 //    $this->SetXY(23, $yPosition);
 //    $areaCellHeight = $adjustedHeight / 3; // Divide the total area height into 3 parts


 //    $this->SetFont('Helvetica', 'B', 9);
 //    $this->SetTextColor(0, 150, 255);   

 //    // First area cell
 //    $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

 //    // Second area cell
 //    $this->SetXY(23, $this->GetY() + 4);
 //    $this->Cell(22, $areaCellHeight, $area[1], 'LR', 0, 'C'); 

 //    // Third area cell
 //    $this->SetXY(23, $this->GetY() + 8);
 //    $this->Cell(22, $areaCellHeight, $area[2], 'LR', 0, 'C'); 

 //    $this->SetTextColor(0, 0, 0);
 //    $this->SetFont('Helvetica', '', 9);

 //    // Render Itinerary content vertically, row by row
 //    $this->SetXY(45, $yPosition);
 //    $this->Cell(125, $lineHeight, $itineraryContents[0], 0, 0, 'L');
 //    $this->SetXY(45, $this->GetY() + $lineHeight - 1);
 //    $this->Cell(125, $lineHeight, $itineraryContents[1], 0, 0, 'L');
 //    $this->SetXY(45, $this->GetY() + $lineHeight - 1);
 //    $this->Cell(125, $lineHeight, $itineraryContents[2], 0, 0, 'L');

 //    // Add hotel information
 //    $hotel1 = "Air Sky Hotel";
 //    $hotel2 = "Emporium Hotel";

 //    $this->SetFont('Helvetica', 'B', 9);
 //    $this->SetXY(45, $this->GetY() + $lineHeight + 2.29);
 //    $this->Cell(30, 3, 'HOTEL:', 'B', 0, 'L');
 //    $this->SetTextColor(0, 150, 255); 
 //    $this->Cell(35, 3, $hotel[0], 'B', 0, 'L');
 //    $this->SetTextColor(0, 0, 0);
 //    $this->Cell(25, 3, 'or', 'B', 0, 'L');
 //    $this->SetTextColor(0, 150, 255);
 //    $this->Cell(35, 3, $hotel[1], 'B', 0, 'L');
 //    $this->SetTextColor(0, 0, 0);

 //    $this->SetFont('Helvetica', 'B', 9);
 //    // Render Meal Plan content with 6 rows
 //    $mealPlanHeight = ($itineraryHeight / 15) + 0.68; // Height for each individual row

 //    $this->SetXY(170, $yPosition);
 //    $this->Cell(30, $mealPlanHeight, "", 'LRT', 0, 'C');
 //    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
 //    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //    $this->Cell(30, $mealPlanHeight, "SNACKS", 'LR', 0, 'C');
 //    $this->SetXY(170, $this->GetY() + $mealPlanHeight - 5);
 //    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
 //    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
 //    $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //    $this->Cell(30, $mealPlanHeight, "", 'LR', 0, 'C');
 //  }

  
 //  // For Day 2 & 3
 //  public function day2($daysData) {
 //   $yPosition2 = $this->GetY();  // Start from the current Y position (Day 1)

 //   // Loop through all provided days and add itinerary and meal plan for each day
 //   foreach ($daysData as $index => $dayData) {
 //       // If it's Day 2, reset the Y position to Day 1's Y position or adjust as needed
 //       if ($index > 0) {
 //           $this->SetY($yPosition2);  // Reset Y position to the starting Y position of Day 1
 //       }

 //       // Call the function to add the itinerary row
 //       $this->addItineraryRow2(
 //           $dayData['day'], 
 //           $dayData['area'], 
 //           $dayData['itinerary'], 
 //           $dayData['mealPlan'], 
 //           $dayData['hotel'],   
 //           $yPosition2, 
 //           $dayData['itineraryHeight']
 //       );

 //       // Update Y position after each day's content
 //       $yPosition2 = $this->GetY(); // Update Y position after rendering the day
 //   }
 //  }

 //  // Modify the addItineraryRow2 function to accept dynamic Y position
 //  private function addItineraryRow2($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition2, $itineraryHeight) {
 //   // Ensure the itinerary content is an array
 //   if (!is_array($itineraryContents)) {
 //       $itineraryContents = [$itineraryContents];
 //   }

 //   // Ensure there are at least 10 rows in itinerary content (pad if necessary)
 //   $itineraryContents = array_pad($itineraryContents, 10, '');

 //   // Ensure mealPlan has exactly 6 elements
 //   if (!is_array($mealPlan)) {
 //       $mealPlan = explode(',', $mealPlan);
 //   }
 //   $mealPlan = array_pad($mealPlan, 6, '');

 //   // Call helper function to add the row for each day
 //   $this->addDay2Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition2, $itineraryHeight);
 //  }

  
 //   private function addDay2Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition2, $itineraryHeight) {
 //      $this->SetFont('Helvetica', '', 9.5);
 //      $this->SetCellPadding(1, 8, 0, 8);  // This is the default uniform padding for all sides.

 //      // Set dynamic line height for the itinerary rows
 //      $lineHeight = $itineraryHeight / 10;

 //      // Adjust or reset the Y position here if needed for Day 2
 //      $this->SetY($yPosition2);  // Ensure this is where Day 2 content starts

 //      // Render Day and Area columns (leftmost columns for the day and area)
 //      $this->SetXY(10, $yPosition2 + 6.2);
 //      $this->Cell(13, ($itineraryHeight - 1.85), $day, 'LRBT', 0, 'C');

 //      $cellHeight = $itineraryHeight + 5.6;
 //      $availableHeight = $cellHeight;  // No need to subtract from anything if you start at $yPosition

 //      // Calculate the vertical offset for centering
 //      $centeredYPosition = $yPosition2 + (($availableHeight - $cellHeight) / 2);

 //      // Render 3 single cells for the area column, one for each part of the area
 //      $this->SetXY(23, $yPosition2 + 6.2);
 //      $areaCellHeight = 40 / 3; // Divide the total area height into 3 parts

 //      $this->SetTextColor(0, 150, 255); 
 //      $this->SetFont('Helvetica', 'B', 9);
 //      // First area cell
 //      $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

 //      // Second area cell
 //      $this->SetXY(23, $this->GetY() + 11.1);
 //      $this->Cell(22, $areaCellHeight + 1, $area[1], 'LR', 0, 'C'); 

 //      // Third area cell
 //      $this->SetXY(23, $this->GetY() + 12.7);
 //      $this->Cell(22, $areaCellHeight + 1, $area[2], 'BLR', 0, 'C'); 

 //      $this->SetTextColor(0, 0, 0); 

 //      $this->SetFont('Helvetica', '', 9);

 //      // Render Itinerary content vertically, row by row
 //      $this->SetXY(45, $yPosition2 + 6.2);
 //      foreach ($itineraryContents as $itinerary) {
 //          $this->Cell(125, $lineHeight, $itinerary, 0, 0, 'L');
 //          $this->SetXY(45, $this->GetY() + $lineHeight);
 //      }

      
 //      $this->SetFont('Helvetica', 'B', 9);
 //      $this->SetXY(45, $this->GetY() + $lineHeight - 11.9);
 //      $this->Cell(30, 5, 'HOTEL:', 'B', 0, 'L'); // Bottom border on last line
 //      $this->SetTextColor(0, 150, 255); 
 //      $this->Cell(35, 5, $hotel[0], 'B', 0, 'L'); // Bottom border on last line
 //      $this->SetTextColor(0, 0, 0); 
 //      $this->Cell(25, 5, 'OR', 'B', 0, 'L'); // Bottom border on last line
 //      $this->SetTextColor(0, 150, 255);
 //      $this->Cell(35, 5, $hotel[1], 'B', 0, 'L'); // Bottom border on last line
 //      $this->SetTextColor(0, 0, 0); 


 //      $this->SetFont('Helvetica', '', 8);

 //      // Render Meal Plan content with 6 rows
 //      $mealPlanHeight = ($itineraryHeight / 7) + .65; // Height for each individual row

 //      $this->SetFont('Helvetica', 'B', 8);
 //      $this->SetXY(170, $yPosition2 + 6.2);
 //      $this->Cell(30, $mealPlanHeight, "BREAKFAST", 'LRT', 0, 'C');

 //      $this->SetTextColor(0, 150, 255);
 //      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //      $this->Cell(30, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
 //      $this->SetTextColor(0, 0, 0);

 //      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //      $this->Cell(30, $mealPlanHeight, "LUNCH", 'LR', 0, 'C');

 //      $this->SetTextColor(0, 150, 255);
 //      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //      $this->Cell(30, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
 //      $this->SetTextColor(0, 0, 0);

 //      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //      $this->Cell(30, $mealPlanHeight, "DINNER", 'LR', 0, 'C');

 //      $this->SetTextColor(0, 150, 255);
 //      $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //      $this->Cell(30, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
 //      $this->SetTextColor(0, 0, 0);
 //  }

   

 //  // FOR DAY 4
 //  public function day4($daysData) {
 //   $yPosition4 = $this->GetY();  // Start from the current Y position (Day 3)

 //   // Loop through all provided days and add itinerary and meal plan for each day
 //   foreach ($daysData as $index => $dayData) {
 //       // If it's Day 4, reset the Y position to Day 3's Y position or adjust as needed
 //       if ($index > 0) {
 //           $this->SetY($yPosition4);  // Reset Y position to the starting Y position of Day 3
 //       }

 //       // Call the function to add the itinerary row
 //       $this->addItineraryRow4(
 //           $dayData['day'], 
 //           $dayData['area'], 
 //           $dayData['itinerary'], 
 //           $dayData['mealPlan'], 
 //           $dayData['hotel'],   
 //           $yPosition4, 
 //           $dayData['itineraryHeight']
 //       );

 //       // Update Y position after each day's content
 //       $yPosition4 = $this->GetY(); // Update Y position after rendering the day
 //   }
 //  }

 //  // Modify the addItineraryRow4 function to accept dynamic Y position
 //  private function addItineraryRow4($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition4, $itineraryHeight) {
 //   // Ensure the itinerary content is an array
 //   if (!is_array($itineraryContents)) {
 //       $itineraryContents = [$itineraryContents];
 //   }

 //   // Ensure there are exactly 8 rows in itinerary content (pad if necessary)
 //   $itineraryContents = array_pad($itineraryContents, 8, '');

 //   // Ensure mealPlan has exactly 6 elements
 //   if (!is_array($mealPlan)) {
 //       $mealPlan = explode(',', $mealPlan);
 //   }
 //   $mealPlan = array_pad($mealPlan, 6, '');

 //   // Call helper function to add the row for each day
 //   $this->addDay4Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition4, $itineraryHeight);
 //  }

 //  private function addDay4Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition4, $itineraryHeight) {
 //   $this->SetFont('Helvetica', '', 9.5);
 //   $this->SetCellPadding(1, 8, 0, 8);  // Uniform padding for all sides

 //   // Set dynamic line height for the itinerary rows
 //   $lineHeight = $itineraryHeight / 8;

 //   // Adjust or reset the Y position here if needed for Day 4
 //   $this->SetY($yPosition4);  // Ensure this is where Day 4 content starts

   
 //   // Render Day and Area columns (leftmost columns for the day and area)
 //   $this->SetXY(10, $yPosition4 + 6.2);
 //   $this->Cell(13, ($itineraryHeight + 8.2), $day, 'LRBT', 0, 'C');

 //   $cellHeight = $itineraryHeight + 5.6;

 //   // Render 3 single cells for the area column
 //   $this->SetXY(23, $yPosition4 + 6.2);
 //   $areaCellHeight = 70 / 3; // Divide total area height into 3 parts

 //   $this->SetFont('Helvetica', 'B', 9);
 //   $this->SetTextColor(0, 150, 255); 
 //   // First area cell
 //   $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

 //   // Second area cell
 //   $this->SetXY(23, $this->GetY() + 11.1);
 //   $this->Cell(22, $areaCellHeight + 1, $area[1], 'LR', 0, 'C'); 

 //   // Third area cell
 //   $this->SetXY(23, $this->GetY() + 12.8);
 //   $this->Cell(22, $areaCellHeight + 1, $area[2], 'BLR', 0, 'C'); 

 //   $this->SetTextColor(0, 0, 0); 

 //   $this->SetFont('Helvetica', '', 9);

 //   // Render Itinerary content vertically, row by row
 //   $this->SetXY(45, $yPosition4 + 6.2);
 //   foreach ($itineraryContents as $itinerary) {
 //       $this->Cell(125, $lineHeight , $itinerary, 0, 0, 'L');
 //       $this->SetXY(45, $this->GetY() + $lineHeight);
 //   }

 //   $this->SetFont('Helvetica', 'B', 9);
 //   $this->SetXY(45, $this->GetY() + $lineHeight - 2.8);
 //   $this->Cell(30, 5, 'HOTEL:', 'B', 0, 'L'); // Bottom border on last line
 //   $this->SetTextColor(0, 150, 255); 
 //   $this->Cell(35, 5, $hotel[0], 'B', 0, 'L'); 
 //   $this->SetTextColor(0, 0, 0); 
 //   $this->Cell(25, 5, 'OR', 'B', 0, 'L'); 
 //   $this->SetTextColor(0, 150, 255); 
 //   $this->Cell(35, 5, $hotel[1], 'B', 0, 'L'); 
 //   $this->SetTextColor(0, 0, 0); 

 //   // Render Meal Plan content with 6 rows
 //   $mealPlanHeight = ($itineraryHeight / 7) + 2.32; // Height for each individual row

 //   $this->SetFont('Helvetica', 'B', 8);
 //   $this->SetXY(170, $yPosition4 + 6.2);
 //   $this->Cell(30, $mealPlanHeight, "BREAKFAST", 'LRT', 0, 'C');

 //   $this->SetTextColor(0, 150, 255);
 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
 //   $this->SetTextColor(0, 0, 0);

 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, "LUNCH", 'LR', 0, 'C');

 //   $this->SetTextColor(0, 150, 255);
 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
 //   $this->SetTextColor(0, 0, 0);

 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, "DINNER", 'LR', 0, 'C');

 //   $this->SetTextColor(0, 150, 255);
 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
 //   $this->SetTextColor(0, 0, 0);
 //  }

 //  // FOR DAY 5
 //  public function day5($daysData) {
 //   $yPosition5 = $this->GetY();  // Start from the current Y position (Day 3)

 //   // Loop through all provided days and add itinerary and meal plan for each day
 //   foreach ($daysData as $index => $dayData) {
 //       // If it's Day 4, reset the Y position to Day 3's Y position or adjust as needed
 //       if ($index > 0) {
 //           $this->SetY($yPosition5);  // Reset Y position to the starting Y position of Day 3
 //       }

 //       // Call the function to add the itinerary row
 //       $this->addItineraryRow5(
 //           $dayData['day'], 
 //           $dayData['area'], 
 //           $dayData['itinerary'], 
 //           $dayData['mealPlan'], 
 //           $dayData['hotel'],   
 //           $yPosition5, 
 //           $dayData['itineraryHeight']
 //       );

 //       // Update Y position after each day's content
 //       $yPosition5 = $this->GetY(); // Update Y position after rendering the day
 //   }
 //  }

 //  // Modify the addItineraryRow4 function to accept dynamic Y position
 //  private function addItineraryRow5($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition5, $itineraryHeight) {
 //   // Ensure the itinerary content is an array
 //   if (!is_array($itineraryContents)) {
 //       $itineraryContents = [$itineraryContents];
 //   }

 //   // Ensure there are exactly 8 rows in itinerary content (pad if necessary)
 //   $itineraryContents = array_pad($itineraryContents, 8, '');

 //   // Ensure mealPlan has exactly 6 elements
 //   if (!is_array($mealPlan)) {
 //       $mealPlan = explode(',', $mealPlan);
 //   }
 //   $mealPlan = array_pad($mealPlan, 6, '');

 //   // Call helper function to add the row for each day
 //   $this->addDay5Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition5, $itineraryHeight);
 //  }

 //  private function addDay5Itinerary($day, $area, $itineraryContents, $mealPlan, $hotel, $yPosition5, $itineraryHeight) {
 //   $this->SetFont('Helvetica', '', 9.5);
 //   $this->SetCellPadding(1, 3, 1, );  // Uniform padding for all sides

 //   // Set dynamic line height for the itinerary rows
 //   $lineHeight = $itineraryHeight / 8;

 //   // Adjust or reset the Y position here if needed for Day 4
 //   $this->SetY($yPosition5);  // Ensure this is where Day 4 content starts

   
 //   // Render Day and Area columns (leftmost columns for the day and area)
 //   $this->SetXY(10, $yPosition5 + 8);
 //   $this->Cell(13, ($itineraryHeight + 8.2), $day, 'LRBT', 0, 'C');

 //   $cellHeight = $itineraryHeight + 5.6;

 //   // Render 3 single cells for the area column
 //   $this->SetXY(23, $yPosition5 + 8);
 //   $areaCellHeight = 70 / 3; // Divide total area height into 3 parts

 //   $this->SetFont('Helvetica', 'B', 9);
 //   $this->SetTextColor(0, 150, 255); 

 //   // First area cell
 //   $this->Cell(22, $areaCellHeight, $area[0], 'TLR', 0, 'C'); 

 //   // Second area cell
 //   $this->SetXY(23, $this->GetY() + 11.1);
 //   $this->Cell(22, $areaCellHeight + 1, $area[1], 'LR', 0, 'C'); 

 //   // Third area cell
 //   $this->SetXY(23, $this->GetY() + 12.8);
 //   $this->Cell(22, $areaCellHeight + 1, $area[2], 'BLR', 0, 'C'); 

 //   $this->SetTextColor(0, 0, 0); 

 //   $this->SetFont('Helvetica', '', 9);
 //   // Render Itinerary content vertically, row by row
 //   $this->SetXY(45, $yPosition5 + 6.8);
 //   foreach ($itineraryContents as $itinerary) {
 //       $this->Cell(125, $lineHeight , $itinerary, 0, 0, 'L');
 //       $this->SetXY(45, $this->GetY() + $lineHeight);
 //   }

 //   $this->SetFont('Helvetica', 'B', 9);
 //   $this->SetXY(45, $this->GetY() + $lineHeight - 1.55);
   
 //   $this->Cell(62.5, 5, $hotel[0], 'B', 0, 'C'); 
 //   $this->SetTextColor(0, 150, 255); 
 //   $this->Cell(62.5, 5, $hotel[1], 'B', 0, 'C'); 
 //   $this->SetTextColor(0, 0, 0); 
 //   $this->SetFont('Helvetica', '', 9);


 //   // Render Meal Plan content with 6 rows
 //   $mealPlanHeight = ($itineraryHeight / 7) + 2.32; // Height for each individual row

 //   $this->SetFont('Helvetica', 'B', 8);
 //   $this->SetXY(170, $yPosition5 + 8);
 //   $this->Cell(30, $mealPlanHeight, "BREAKFAST", 'LRT', 0, 'C');

 //   $this->SetTextColor(0, 150, 255);
 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, $mealPlan[0], 'LR', 0, 'C');
 //   $this->SetTextColor(0, 0, 0);

 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, "LUNCH", 'LR', 0, 'C');

 //   $this->SetTextColor(0, 150, 255);
 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, $mealPlan[1], 'LR', 0, 'C');
 //   $this->SetTextColor(0, 0, 0);

 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, "DINNER", 'LR', 0, 'C');

 //   $this->SetTextColor(0, 150, 255);
 //   $this->SetXY(170, $this->GetY() + $mealPlanHeight);
 //   $this->Cell(30, $mealPlanHeight, $mealPlan[2], 'LRB', 0, 'C');
 //   $this->SetTextColor(0, 0, 0);
 //  }


 // // Add hotel info table
 // public function SecondPage() {
 //   $this->SetFont('Helvetica', 'B', 10);

 //   // CONDITIONS ROW
 //   $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
 //   $this->SetXY(10, 30);  // Adjust the X and Y position if needed
 //   $this->Cell(40, 30, 'CONDITIONS', 'LRTB', 0, 'C', TRUE);  // Borders on all sides, center-aligned text

 //   $this->SetFont('Helvetica', '', 9);
 //   $this->SetXY(50, 30); 
 //   $this->MultiCell(150, 30, 
 //   "  * English speaking guide or driving guide
 //  * Four (4) times lunch, Four (4) times dinner,
 //  * Four (4) Nights with twin or triple sharing
 //  * Daily 1 Bottled Water
 //  * Guide Tipping Fee ($25 per/person) is not included,
 //  * Included admission fee : As per Itinerary
 //  * For any unused portion is not refundable ", 'LRTB', 'L');


 //  // REMARKS ROW
 //  $this->SetFont('Helvetica', 'B', 10);

 //  $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
 //  // Create a vertical "HOTEL" cell spanning multiple rows
 //  $this->SetXY(10, 60);  // Adjust the X and Y position if needed
 //  $this->Cell(40, 26, 'REMARKS', 'LRTB', 0, 'C', true);  // Borders on all sides, center-aligned text

 //  $this->SetFont('Helvetica', '', 9);
 //  $this->SetXY(50, 60); 
 //  $this->MultiCell(150, 26, 
 //  "  * The tour fare is valid at least 5 rooms sharing with ADT basic. (TWN/DLB/TRIP sharing)
 //  * The fare is not valid during Japanese holiday.
 //  * The quotation is based on the group tour, So, the individual schedule is not permitted in this itinerary.
 //  * Please be noted that below rate is with compulsory shop visiting, please kindly explain to pax that they
 //    have to visit. Surcharge of not vising the shop is as follows, Ginseng Outlet (USD 30) / Amethyst 
 //    Showcase (USD 30) / Red Pine Shop (USD 30) / Cosmetics Shop (USD 30) / DFS (USD 20)", 'LRTB', 'L');


 //  // Set Colors
 //  $this->SetFillColor(255, 234, 215);

 //  // Position and Styling
 //  $this->SetXY(10, 86); 
 //  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides
 //  $this->SetFont('Helvetica', 'B', 10);

 //  // Render Cell with Fill
 //  $this->MultiCell(190, 5, "GROUP TOUR DATE", 'LRBT', 'C', true);
  
 //  $this->SetXY(10, 92.5); 

 //  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides

 //  $this->SetFont('Helvetica', 'B', 10);
 //  $this->Cell(60, 5, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 5, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 5, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 5, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 5, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 5, "LAND ONLY", 'LRB', 1, 'C');

 //  $this->SetFont('Helvetica', 'B', 10);

 //  $this->SetXY(10, 99); 
 //  $this->MultiCell(30, 26, "", 'LRB', 'C');

 //  $this->SetXY(40, 99); 
 //  $this->Cell(30, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');

 //  $this->SetXY(40, 112); 
 //  $this->Cell(30, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');

 //  $this->SetFont('Helvetica', 'B', 10);

 //  $this->SetFillColor(255, 234, 215); // Set the fill color (Light peach)

 //  $this->SetXY(10, 125); 
 //  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides

 //  $this->MultiCell(190, 5, "FIT TOUR RATE", 'LRTB', 'C', true); // Ensure fill parameter is true


 //  $this->SetXY(10, 131.5); 
 //  $this->MultiCell(30, 26, "", 'LRB', 'C');

 //  $this->SetXY(40, 131.5); 
 //  $this->Cell(30, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');

 //  $this->SetXY(40, 144.5); 
 //  $this->Cell(30, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');
 //  $this->Cell(26, 13, "", 'LRB', 0, 'C');

 //  $this->SetFont('Helvetica', 'B', 10);
 //  $this->SetFillColor(255, 234, 215); // Background color
 //  $this->SetTextColor(0, 0, 0);       // Text color
  
 //  // Positioning and rendering
 //  $this->SetXY(10, 157.5); 
 //  $this->Cell(40, 18, "CHILD POLICY", 'LRBT', 0, 'C', true);

 //  $this->SetFont('Helvetica', '', 8);
 //  $this->SetXY(50, 157.5); 
 //  $this->Cell(50, 9, "CHILD UNDER 2 YEARS OLD", 'LRB', 0, 'C');
 //  $this->SetFont('Helvetica', '', 7);
 //  $this->Cell(50, 9, "3-7 YEARS OLD WITHOUT USING BED", 'LRB', 0, 'C');
 //  $this->SetFont('Helvetica', '', 8);
 //  $this->Cell(50, 9, "SHARING A HALF-TWIN ROOM", 'LRB', 0, 'C');

 //  $this->SetXY(50, 166.5); 
 //  $this->Cell(50, 9, "FREE OF CHARGE", 'LRB', 0, 'C');
 //  $this->Cell(50, 9, "70% ADULT FARE", 'LRB', 0, 'C');
 //  $this->Cell(50, 9, "100% ADULT FARE", 'LRB', 0, 'C');


 //  $this->SetFont('Helvetica', 'B', 10);
 //  $this->SetFillColor(255, 253, 185); // Set the fill color (Light peach)
 //  $this->SetXY(10, 175.5); 
 //  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides
 //  $this->MultiCell(190, 5, "CANCELLATION POLICY", 'LRTB', 'C', true); // Ensure fill parameter is true

 //  $this->SetFont('Helvetica', '', 10);
 //  $this->SetXY(10, 182); 
 //  $this->SetCellPadding(1, 6, 1); // Uniform padding for all sides
 //  $this->MultiCell(190, 10,
 //  "  
 //  * Before 15 days of Departure: 100% tour fare refund (No cancellation charge)
 //  * Before 8-14 days of Departure: 50% tour fare refund (50% cancellation charge)
 //  * Before 4-7 days of Departure: 30% tour fare refund (70% cancellation charge)
 //  * Before 1-3 days of Departure: 0% tour fare refund (100% cancellation charge)
 //  ", 'LRTB', 'L', false);
 
