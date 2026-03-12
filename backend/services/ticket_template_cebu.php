<?php
/**
 * Cebu Pacific Airline Ticket PDF Template
 * Replicates the Cebu Pacific "Itinerary Receipt" layout
 * Expected: $data array
 */

$booking = $data['booking'];
$passengers = $data['passengers'];
$flights = $data['flights'];
$bookingRef = $data['bookingRef'];
$bookingDate = $data['bookingDate'] ?? '';
$qrCodeBase64 = $data['qrCodeBase64'] ?? null;

$depDetail = $flights['details']['departure'] ?? [];
$retDetail = $flights['details']['return'] ?? [];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page {
        margin: 12mm 14mm 12mm 14mm;
        size: A4 portrait;
    }
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: 10px;
        line-height: 1.5;
        color: #333;
        margin: 0;
        padding: 0;
    }
    .page-title {
        font-size: 26px;
        font-weight: bold;
        color: #222;
        margin: 0 0 16px 0;
    }
    .header-bar {
        display: table;
        width: 100%;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .header-bar-left {
        display: table-cell;
        vertical-align: middle;
        width: 50%;
    }
    .header-bar-right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        width: 50%;
    }
    .cebu-logo {
        font-size: 18px;
        font-weight: bold;
        color: #f5a623;
    }
    .cebu-logo .cebu-text {
        color: #222;
    }
    .cebu-tagline {
        font-size: 9px;
        color: #888;
        font-style: italic;
    }
    .itinerary-receipt-right {
        font-size: 20px;
        font-weight: bold;
        color: #222;
    }
    .confirmed-section {
        margin-bottom: 12px;
    }
    .confirmed-badge {
        font-size: 18px;
        color: #555;
        font-weight: 500;
    }
    .confirmed-badge .check {
        color: #4CAF50;
        font-weight: bold;
    }
    .confirmed-msg {
        font-size: 10px;
        color: #888;
        margin-top: 2px;
    }
    .booking-info-row {
        display: table;
        width: 100%;
        margin-bottom: 8px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }
    .booking-info-cell {
        display: table-cell;
        vertical-align: top;
    }
    .booking-info-label {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        color: #555;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .booking-info-value {
        font-size: 14px;
        font-weight: bold;
        color: #222;
    }
    h2.section-heading {
        font-size: 18px;
        font-weight: bold;
        color: #222;
        margin: 20px 0 12px 0;
        padding: 0;
        border: none;
    }
    .flight-block {
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eee;
    }
    .flight-block:last-child {
        border-bottom: none;
    }
    .flight-route {
        font-size: 13px;
        font-weight: bold;
        color: #222;
        margin-bottom: 6px;
    }
    .flight-no-badge {
        font-size: 9px;
        color: #555;
        margin-bottom: 8px;
    }
    .flight-no-badge .plane {
        color: #888;
    }
    .flight-detail-grid {
        display: table;
        width: 100%;
        margin-bottom: 4px;
    }
    .flight-detail-left {
        display: table-cell;
        width: 30%;
        vertical-align: top;
    }
    .flight-detail-right {
        display: table-cell;
        width: 70%;
        vertical-align: top;
    }
    .flight-date {
        font-size: 10px;
        color: #555;
    }
    .flight-time {
        font-size: 10px;
        color: #555;
    }
    .flight-duration-small {
        font-size: 9px;
        color: #999;
    }
    .flight-loc-label {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        color: #555;
    }
    .flight-loc-city {
        font-size: 11px;
        font-weight: bold;
        color: #222;
    }
    .flight-loc-airport {
        font-size: 9px;
        color: #888;
    }
    .operated-by {
        font-size: 9px;
        color: #888;
        border-top: 1px dotted #ccc;
        padding-top: 8px;
        margin-top: 12px;
    }
    .guest-section {
        margin-top: 20px;
    }
    .guest-block {
        border-bottom: 2px solid #e0e0e0;
        padding: 12px 0;
    }
    .guest-block:last-child {
        border-bottom: none;
    }
    .guest-header-row {
        display: table;
        width: 100%;
        margin-bottom: 4px;
    }
    .guest-col-name {
        display: table-cell;
        width: 35%;
        vertical-align: top;
    }
    .guest-col-flight {
        display: table-cell;
        width: 25%;
        vertical-align: top;
    }
    .guest-col-addons {
        display: table-cell;
        width: 40%;
        vertical-align: top;
    }
    .guest-label {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        color: #555;
        letter-spacing: 0.5px;
    }
    .guest-name {
        font-size: 11px;
        font-weight: bold;
        color: #222;
    }
    .guest-type {
        font-size: 9px;
        color: #888;
    }
    .guest-flight-route {
        font-size: 10px;
        color: #333;
        font-weight: bold;
    }
    .guest-flight-route .plane {
        color: #888;
    }
    .guest-addon-text {
        font-size: 9px;
        color: #555;
    }
    .guest-flight-row {
        display: table;
        width: 100%;
        padding: 4px 0;
    }
    .footer {
        text-align: center;
        font-size: 8px;
        color: #999;
        margin-top: 20px;
        border-top: 1px solid #ddd;
        padding-top: 8px;
    }
</style>
</head>
<body>

<!-- Page Title -->
<div class="page-title">Itinerary Receipt</div>

<!-- Header bar -->
<div class="header-bar">
    <div class="header-bar-left">
        <div class="cebu-logo">
            <span class="cebu-text">cebu pacific</span>
        </div>
        <div class="cebu-tagline">Let's fly every Juan</div>
    </div>
    <div class="header-bar-right">
        <div class="itinerary-receipt-right">Itinerary Receipt</div>
    </div>
</div>

<!-- Confirmed + QR Code -->
<div style="display:table;width:100%;margin-bottom:12px;">
    <div style="display:table-cell;vertical-align:top;width:65%;">
        <div class="confirmed-badge"><span class="check" style="color:#4CAF50;font-weight:bold;">V</span> Confirmed</div>
        <div class="confirmed-msg">Thank you. Your transaction was successful.</div>
    </div>
    <?php if ($qrCodeBase64): ?>
    <div style="display:table-cell;vertical-align:top;text-align:right;width:35%;">
        <img src="data:image/png;base64,<?php echo $qrCodeBase64; ?>" style="width:80px;height:80px;" alt="QR Code">
        <div style="font-size:8px;color:#888;margin-top:2px;text-align:right;">Scan QR Code to check in</div>
    </div>
    <?php endif; ?>
</div>

<!-- Booking Info -->
<div class="booking-info-row">
    <div class="booking-info-cell" style="width:40%;">
        <div class="booking-info-label">Booking Date</div>
        <div class="booking-info-value"><?php echo htmlspecialchars($bookingDate); ?></div>
    </div>
    <div class="booking-info-cell" style="width:60%;">
        <div class="booking-info-label">Booking Reference No.</div>
        <div class="booking-info-value"><?php echo htmlspecialchars($bookingRef); ?></div>
    </div>
</div>

<!-- Flight Details -->
<h2 class="section-heading">Flight Details</h2>

<?php if (!empty($depDetail)): ?>
<div class="flight-block">
    <div class="flight-route">MNL &ndash; ICN</div>
    <div class="flight-no-badge"><span class="plane">&gt;</span> FLIGHT NO. <?php echo htmlspecialchars($depDetail['flightNo']); ?></div>

    <div class="flight-detail-grid">
        <div class="flight-detail-left">
            <div class="flight-date"><?php echo htmlspecialchars($depDetail['depDate']); ?></div>
            <div class="flight-time"><?php echo htmlspecialchars($depDetail['depTime']); ?></div>
            <?php if (!empty($depDetail['duration'])): ?>
            <div class="flight-duration-small"><?php echo htmlspecialchars($depDetail['duration']); ?></div>
            <?php endif; ?>
        </div>
        <div class="flight-detail-right">
            <div class="flight-loc-label">DEPARTURE</div>
            <div class="flight-loc-city">Manila</div>
            <div class="flight-loc-airport">Ninoy Aquino International Airport - Terminal 3</div>
        </div>
    </div>

    <?php if (!empty($depDetail['arrDate']) || !empty($depDetail['arrTime'])): ?>
    <div class="flight-detail-grid" style="margin-top:10px;">
        <div class="flight-detail-left">
            <div class="flight-date"><?php echo htmlspecialchars($depDetail['arrDate'] ?? ''); ?></div>
            <div class="flight-time"><?php echo htmlspecialchars($depDetail['arrTime']); ?></div>
        </div>
        <div class="flight-detail-right">
            <div class="flight-loc-label">ARRIVAL</div>
            <div class="flight-loc-city">Seoul (Incheon)</div>
            <div class="flight-loc-airport">Incheon International Airport - Terminal 1</div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($retDetail)): ?>
<div class="flight-block">
    <div class="flight-route">ICN &ndash; MNL</div>
    <div class="flight-no-badge"><span class="plane">&gt;</span> FLIGHT NO. <?php echo htmlspecialchars($retDetail['flightNo']); ?></div>

    <div class="flight-detail-grid">
        <div class="flight-detail-left">
            <div class="flight-date"><?php echo htmlspecialchars($retDetail['depDate']); ?></div>
            <div class="flight-time"><?php echo htmlspecialchars($retDetail['depTime']); ?></div>
            <?php if (!empty($retDetail['duration'])): ?>
            <div class="flight-duration-small"><?php echo htmlspecialchars($retDetail['duration']); ?></div>
            <?php endif; ?>
        </div>
        <div class="flight-detail-right">
            <div class="flight-loc-label">DEPARTURE</div>
            <div class="flight-loc-city">Seoul (Incheon)</div>
            <div class="flight-loc-airport">Incheon International Airport - Terminal 1</div>
        </div>
    </div>

    <?php if (!empty($retDetail['arrDate']) || !empty($retDetail['arrTime'])): ?>
    <div class="flight-detail-grid" style="margin-top:10px;">
        <div class="flight-detail-left">
            <div class="flight-date"><?php echo htmlspecialchars($retDetail['arrDate'] ?? ''); ?></div>
            <div class="flight-time"><?php echo htmlspecialchars($retDetail['arrTime']); ?></div>
        </div>
        <div class="flight-detail-right">
            <div class="flight-loc-label">ARRIVAL</div>
            <div class="flight-loc-city">Manila</div>
            <div class="flight-loc-airport">Ninoy Aquino International Airport - Terminal 3</div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="operated-by">
    Flight operated by: Cebu Pacific | Cebgo | AirSWIFT
</div>

<!-- Guest Details -->
<h2 class="section-heading">Guest Details</h2>

<!-- Header labels -->
<div class="guest-header-row" style="border-bottom:1px solid #e0e0e0;padding-bottom:4px;margin-bottom:0;">
    <div class="guest-col-name"><span class="guest-label">NAME</span></div>
    <div class="guest-col-flight"><span class="guest-label">FLIGHT</span></div>
    <div class="guest-col-addons"><span class="guest-label">ADD-ONS</span></div>
</div>

<?php foreach ($passengers as $pax): ?>
<div class="guest-block">
    <!-- Passenger name -->
    <div class="guest-header-row">
        <div class="guest-col-name">
            <div class="guest-name"><?php echo htmlspecialchars($pax['parsedName']); ?></div>
            <div class="guest-type"><?php echo htmlspecialchars($pax['parsedType']); ?></div>
        </div>
        <div class="guest-col-flight">
            <div class="guest-flight-route">MNL <span class="plane">&gt;</span> ICN</div>
        </div>
        <div class="guest-col-addons">
            <?php
            $ob = $pax['outbound'] ?? [];
            if (!empty($ob['fare'])) echo '<div class="guest-addon-text">' . htmlspecialchars($ob['fare']) . '</div>';
            if (!empty($ob['baggage'])) echo '<div class="guest-addon-text">' . htmlspecialchars($ob['baggage']) . '</div>';
            if (!empty($ob['seat'])) echo '<div class="guest-addon-text">Seat: ' . htmlspecialchars($ob['seat']) . '</div>';
            if (empty($ob)) echo '<div class="guest-addon-text" style="color:#bbb;">-</div>';
            ?>
        </div>
    </div>
    <!-- Return leg -->
    <div class="guest-flight-row">
        <div class="guest-col-name"></div>
        <div class="guest-col-flight">
            <div class="guest-flight-route">ICN <span class="plane">&gt;</span> MNL</div>
        </div>
        <div class="guest-col-addons">
            <?php
            $rt = $pax['return'] ?? [];
            if (!empty($rt['fare'])) echo '<div class="guest-addon-text">' . htmlspecialchars($rt['fare']) . '</div>';
            if (!empty($rt['baggage'])) echo '<div class="guest-addon-text">' . htmlspecialchars($rt['baggage']) . '</div>';
            if (!empty($rt['seat']) && $rt['seat'] !== 'Unassigned') {
                echo '<div class="guest-addon-text">Seat: ' . htmlspecialchars($rt['seat']) . '</div>';
            } else {
                echo '<div class="guest-addon-text">Seat: Unassigned</div>';
            }
            ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="footer">
    Generated by SMT Escape Travel &amp; Tours on <?php echo date('Y-m-d H:i'); ?>
</div>

</body>
</html>
