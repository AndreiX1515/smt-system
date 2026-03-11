<?php
/**
 * Service Voucher HTML Template
 * Used by voucher_generator.php to render PDF via Dompdf
 *
 * Expected variables: $data array with all voucher sections
 */

if (!isset($data)) {
    die('Template requires $data');
}

$booking = $data['booking'];
$flights = $data['flights'];
$accommodations = $data['accommodations'];
$rooms = $data['rooms'];
$schedules = $data['schedules'];
$weather = $data['weather'];
$totalDays = $data['totalDays'];
$departFlight = $data['departFlight'];
$returnFlight = $data['returnFlight'];

// Format dates
$depDate = new DateTime($booking['departureDate']);
$endDate = clone $depDate;
$endDate->modify('+' . ($totalDays - 1) . ' days');

$dateRange = strtoupper($depDate->format('M d')) . ' - ' . strtoupper($endDate->format('M d, Y'));
$nights = $totalDays - 1;
$durationText = "{$nights}N/{$totalDays}D";

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page {
        margin: 20mm 15mm 20mm 15mm;
        size: A4 portrait;
    }
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: 10px;
        line-height: 1.4;
        color: #333;
        margin: 0;
        padding: 0;
    }
    h1 { font-size: 18px; margin: 0 0 5px 0; color: #003366; }
    h2 { font-size: 13px; margin: 15px 0 8px 0; color: #003366; border-bottom: 2px solid #003366; padding-bottom: 3px; }
    h3 { font-size: 11px; margin: 10px 0 5px 0; color: #333; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .info-table td { padding: 3px 8px; vertical-align: top; }
    .info-table .label { font-weight: bold; width: 140px; background: #f0f4f8; }
    .bordered-table { border: 1px solid #ccc; }
    .bordered-table th, .bordered-table td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; font-size: 9px; }
    .bordered-table th { background: #003366; color: #fff; font-size: 9px; }
    .section { margin-bottom: 12px; page-break-inside: avoid; }
    .header-section { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #003366; padding-bottom: 10px; }
    .header-section .subtitle { font-size: 12px; color: #666; margin-top: 3px; }
    .note { font-size: 9px; color: #666; font-style: italic; margin-top: 5px; }
    .highlight-box { background: #f0f4f8; border-left: 3px solid #003366; padding: 8px 12px; margin: 8px 0; font-size: 9px; }
    .weather-grid { width: 100%; }
    .weather-grid td { text-align: center; padding: 5px 3px; font-size: 8px; border: 1px solid #ddd; }
    .weather-grid th { background: #003366; color: #fff; padding: 4px; font-size: 8px; }
    .emergency-box { background: #fff3f3; border: 1px solid #e53935; padding: 8px 12px; border-radius: 4px; margin: 8px 0; }
    .footer-note { font-size: 8px; color: #999; text-align: center; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 8px; }
    ul { margin: 5px 0; padding-left: 20px; }
    li { margin-bottom: 3px; font-size: 9px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header-section">
    <h1>SERVICE VOUCHER</h1>
    <div class="subtitle"><?php echo htmlspecialchars($booking['packageName']); ?></div>
    <div class="subtitle"><?php echo $dateRange; ?> (<?php echo $durationText; ?>)</div>
</div>

<!-- HOTEL INFORMATION -->
<div class="section">
    <h2>HOTEL INFORMATION</h2>
    <table class="bordered-table">
        <thead>
            <tr><th style="width:30%">Hotel Name</th><th>Address / Contact</th></tr>
        </thead>
        <tbody>
        <?php foreach ($accommodations as $acc): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($acc['accommodation_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($acc['accommodation_address']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- AIRLINE INFORMATION -->
<div class="section">
    <h2>AIRLINE INFORMATION</h2>
    <table class="info-table bordered-table">
        <tr>
            <td class="label">Airline</td>
            <td><?php echo htmlspecialchars($departFlight['airline_name'] ?? 'TBA'); ?></td>
            <td class="label">Flight No.</td>
            <td><?php echo htmlspecialchars($departFlight['flight_number'] ?? 'TBA'); ?></td>
        </tr>
        <tr>
            <td class="label">Route</td>
            <td colspan="3"><?php echo htmlspecialchars(($departFlight['departure_point'] ?? '') . ' → ' . ($departFlight['destination'] ?? '')); ?></td>
        </tr>
    </table>
</div>

<!-- PASSENGER LIST & ROOM ASSIGNMENTS -->
<div class="section">
    <h2>PASSENGER LIST &amp; ROOM ASSIGNMENTS</h2>
    <table class="bordered-table">
        <thead>
            <tr><th>#</th><th>Room Type</th><th>Passenger Name</th><th>Type</th></tr>
        </thead>
        <tbody>
        <?php
        $counter = 1;
        // Group rooms by room_type
        $roomGroups = [];
        foreach ($rooms as $r) {
            $key = $r['room_type'] ?? 'unassigned';
            if (!isset($roomGroups[$key])) $roomGroups[$key] = [];
            $roomGroups[$key][] = $r;
        }

        foreach ($roomGroups as $roomType => $travelers):
            // Format room type: "twin-1" → "*Twin"
            $parts = explode('-', $roomType);
            $displayType = '*' . ucfirst($parts[0] ?? 'Room');
            if (isset($parts[1])) {
                $displayType .= ' ' . $parts[1];
            }
            foreach ($travelers as $t):
        ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($displayType); ?></td>
                <td><?php echo htmlspecialchars(trim(($t['lastName'] ?? '') . ', ' . ($t['firstName'] ?? ''), ', ')); ?></td>
                <td><?php echo ucfirst($t['travelerType'] ?? 'adult'); ?></td>
            </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
    </table>
    <div class="note">* Room assignments are subject to change based on hotel availability.</div>
</div>

<!-- ITINERARY -->
<div class="section">
    <h2>ITINERARY</h2>
    <table class="bordered-table">
        <thead>
            <tr><th style="width:60px">Day</th><th>Date</th><th>Tour Description / Attraction</th><th>Hotel</th><th>Meals</th></tr>
        </thead>
        <tbody>
        <?php foreach ($schedules as $sch):
            $dayDate = clone $depDate;
            $dayDate->modify('+' . ($sch['day_number'] - 1) . ' days');
            $dateStr = $dayDate->format('M d (D)');

            // Meals
            $meals = [];
            if (!empty($sch['breakfast'])) $meals[] = 'B: ' . $sch['breakfast'];
            if (!empty($sch['lunch'])) $meals[] = 'L: ' . $sch['lunch'];
            if (!empty($sch['dinner'])) $meals[] = 'D: ' . $sch['dinner'];
            $mealStr = implode(', ', $meals);

            // Accommodation
            $accName = $sch['accommodation_name'] ?? '';
            if (empty($accName) && isset($sch['matched_accommodation'])) {
                $accName = $sch['matched_accommodation'];
            }

            // Description + attraction
            $desc = $sch['description'] ?? '';
            if (!empty($sch['airport_location'])) {
                $desc .= ($desc ? ' - ' : '') . $sch['airport_location'];
            }
        ?>
            <tr>
                <td style="text-align:center">Day <?php echo $sch['day_number']; ?></td>
                <td style="white-space:nowrap"><?php echo $dateStr; ?></td>
                <td><?php echo htmlspecialchars($desc); ?></td>
                <td style="font-size:8px"><?php echo htmlspecialchars($accName); ?></td>
                <td style="font-size:8px"><?php echo htmlspecialchars($mealStr); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- WEATHER FORECAST -->
<div class="section">
    <h2>WEATHER FORECAST</h2>
    <?php if ($weather['available'] && !empty($weather['forecasts'])): ?>
    <table class="weather-grid">
        <thead>
            <tr>
                <th>Date</th>
                <?php foreach ($weather['forecasts'] as $w): ?>
                <th><?php echo date('M d', strtotime($w['date'])); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Condition</strong></td>
                <?php foreach ($weather['forecasts'] as $w): ?>
                <td><?php echo htmlspecialchars($w['condition']); ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td><strong>High / Low</strong></td>
                <?php foreach ($weather['forecasts'] as $w): ?>
                <td><?php echo $w['maxtemp_c']; ?>° / <?php echo $w['mintemp_c']; ?>°C</td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td><strong>Humidity</strong></td>
                <?php foreach ($weather['forecasts'] as $w): ?>
                <td><?php echo $w['humidity']; ?>%</td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td><strong>Rain</strong></td>
                <?php foreach ($weather['forecasts'] as $w): ?>
                <td><?php echo $w['chance_of_rain']; ?>%</td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
    <?php else: ?>
    <div class="highlight-box"><?php echo htmlspecialchars($weather['message'] ?? 'Weather forecast will be available closer to departure date.'); ?></div>
    <?php endif; ?>
</div>

<!-- FLIGHT DETAILS -->
<div class="section">
    <h2>FLIGHT DETAILS</h2>
    <table class="bordered-table">
        <thead>
            <tr><th>Type</th><th>Flight</th><th>Route</th><th>Departure</th><th>Arrival</th></tr>
        </thead>
        <tbody>
        <?php if ($departFlight): ?>
            <tr>
                <td><strong>Departure</strong></td>
                <td><?php echo htmlspecialchars($departFlight['flight_number'] ?? 'TBA'); ?></td>
                <td><?php echo htmlspecialchars(($departFlight['departure_point'] ?? '') . ' → ' . ($departFlight['destination'] ?? '')); ?></td>
                <td><?php echo $departFlight['departure_time'] ? date('h:i A', strtotime($departFlight['departure_time'])) : 'TBA'; ?></td>
                <td><?php echo $departFlight['arrival_time'] ? date('h:i A', strtotime($departFlight['arrival_time'])) : 'TBA'; ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($returnFlight): ?>
            <tr>
                <td><strong>Return</strong></td>
                <td><?php echo htmlspecialchars($returnFlight['flight_number'] ?? 'TBA'); ?></td>
                <td><?php echo htmlspecialchars(($returnFlight['departure_point'] ?? '') . ' → ' . ($returnFlight['destination'] ?? '')); ?></td>
                <td><?php echo $returnFlight['departure_time'] ? date('h:i A', strtotime($returnFlight['departure_time'])) : 'TBA'; ?></td>
                <td><?php echo $returnFlight['arrival_time'] ? date('h:i A', strtotime($returnFlight['arrival_time'])) : 'TBA'; ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAGE BREAK for compliance sections -->
<div class="page-break"></div>

<!-- COMPLIANCE NOTICE -->
<div class="section">
    <h2>COMPLIANCE NOTICE</h2>
    <div class="highlight-box">
        This Service Voucher is issued in compliance with the Department of Tourism (DOT) and the Department of Migrant Workers (DMW)
        guidelines for outbound travel packages. All services listed herein are confirmed and guaranteed by the tour operator.
    </div>
</div>

<!-- PRE-DEPARTURE CHECKLIST -->
<div class="section">
    <h2>PRE-DEPARTURE CHECKLIST</h2>
    <ul>
        <li><strong>Passport:</strong> Must be valid for at least 6 months from departure date. Ensure all pages are intact and not damaged.</li>
        <li><strong>Visa:</strong> Check visa requirements for your destination. Ensure visa is obtained and valid for your travel dates.</li>
        <li><strong>Travel Insurance:</strong> Recommended for all travelers. Check if your package includes travel insurance coverage.</li>
        <li><strong>Health Requirements:</strong> Check vaccination requirements. Carry necessary medications with prescriptions.</li>
        <li><strong>Currency:</strong> Prepare local currency or check ATM/card acceptance at your destination.</li>
        <li><strong>Communication:</strong> Inform your bank about travel plans. Consider purchasing a local SIM card or pocket WiFi.</li>
        <li><strong>Copies:</strong> Keep photocopies of passport, visa, and tickets separate from originals.</li>
        <li><strong>Meeting Point:</strong> Arrive at the designated meeting point at least 3 hours before departure for international flights.</li>
    </ul>
</div>

<!-- ARRIVAL GUIDELINES -->
<div class="section">
    <h2>ARRIVAL GUIDELINES</h2>
    <ul>
        <li>Upon arrival, proceed to Immigration. Present your passport, visa (if applicable), and return ticket.</li>
        <li>Collect your luggage at the baggage carousel and proceed through Customs.</li>
        <li>Your tour guide will meet you at the arrival hall with a name placard.</li>
        <li>Keep your Service Voucher and booking confirmation accessible for verification.</li>
        <li>In case you cannot locate the guide, contact the emergency numbers listed below.</li>
    </ul>
</div>

<!-- EMERGENCY CONTACTS -->
<div class="section">
    <h2>EMERGENCY CONTACTS</h2>
    <div class="emergency-box">
        <table class="info-table" style="margin:0">
            <tr><td class="label" style="background:none;font-size:9px">Philippine Embassy (Korea)</td><td style="font-size:9px">+82-2-796-7387</td></tr>
            <tr><td class="label" style="background:none;font-size:9px">Korea Tourism Helpline</td><td style="font-size:9px">+82-1330 (multilingual)</td></tr>
            <tr><td class="label" style="background:none;font-size:9px">Emergency (Police/Fire/Ambulance)</td><td style="font-size:9px">112 / 119</td></tr>
            <tr><td class="label" style="background:none;font-size:9px">Tour Operator</td><td style="font-size:9px">SMT Escape Travel &amp; Tours</td></tr>
        </table>
    </div>
</div>

<!-- INCLUSIONS -->
<div class="section">
    <h2>TOUR INCLUSIONS</h2>
    <ul>
        <li>Round-trip airfare (economy class) with baggage allowance as per airline policy</li>
        <li>Hotel accommodation as indicated in the itinerary</li>
        <li>Meals as specified in the itinerary (B=Breakfast, L=Lunch, D=Dinner)</li>
        <li>Airport transfers and land transportation as per itinerary</li>
        <li>English/Filipino-speaking tour guide</li>
        <li>Entrance fees to tourist attractions as listed in the itinerary</li>
        <li>Travel insurance (basic coverage)</li>
    </ul>
</div>

<!-- EXCLUSIONS -->
<div class="section">
    <h2>TOUR EXCLUSIONS</h2>
    <ul>
        <li>Personal expenses (shopping, mini-bar, telephone calls, laundry, etc.)</li>
        <li>Optional tours and activities not included in the itinerary</li>
        <li>Visa processing fee (if applicable)</li>
        <li>Travel tax and terminal fees (if not included in airfare)</li>
        <li>Excess baggage charges</li>
        <li>Tips for guide and driver (recommended: USD 5-10/day per person)</li>
        <li>Single room supplement (if applicable)</li>
        <li>PCR test or other health-related requirements (if applicable)</li>
    </ul>
</div>

<!-- BAGGAGE INFORMATION -->
<div class="section">
    <h2>BAGGAGE INFORMATION</h2>
    <table class="bordered-table">
        <thead>
            <tr><th>Type</th><th>Allowance</th><th>Notes</th></tr>
        </thead>
        <tbody>
            <tr><td>Check-in Baggage</td><td>20 kg per person</td><td>As per airline policy. Excess charges apply.</td></tr>
            <tr><td>Hand Carry</td><td>7 kg per person</td><td>Max dimensions: 56cm x 36cm x 23cm</td></tr>
            <tr><td>Prohibited Items</td><td colspan="2">Sharp objects, flammable materials, liquids over 100ml (hand carry)</td></tr>
        </tbody>
    </table>
</div>

<!-- REQUIREMENTS -->
<div class="section">
    <h2>TRAVEL REQUIREMENTS</h2>
    <ul>
        <li>Valid passport (minimum 6 months validity from travel date)</li>
        <li>Valid visa for destination country (if required)</li>
        <li>Confirmed round-trip airline ticket</li>
        <li>Hotel booking confirmation / Service Voucher</li>
        <li>Travel insurance certificate</li>
        <li>Proof of sufficient funds (if requested by immigration)</li>
        <li>Vaccination certificates (if required by destination)</li>
    </ul>
</div>

<!-- TERMS AND CONDITIONS -->
<div class="section">
    <h2>TERMS AND CONDITIONS</h2>
    <ul>
        <li>All rates are subject to change without prior notice due to currency fluctuations or fuel surcharge adjustments.</li>
        <li>The tour operator reserves the right to modify the itinerary due to force majeure, weather conditions, or safety concerns.</li>
        <li>Hotel substitution of similar or higher category may occur based on availability.</li>
        <li>No refund for unused services, missed tours, or early departure.</li>
        <li>Cancellation policy applies as per the signed tour agreement.</li>
        <li>The tour operator is not liable for loss of personal belongings, delays, or events beyond its control.</li>
        <li>By joining this tour, participants agree to follow the group schedule and guidelines set by the tour guide.</li>
    </ul>
</div>

<!-- FOOTER -->
<div class="footer-note">
    This Service Voucher is electronically generated by SMT Escape Travel &amp; Tours.
    For inquiries, contact us at noreply@smt-escape.com<br>
    Generated on: <?php echo date('Y-m-d H:i'); ?>
</div>

</body>
</html>
