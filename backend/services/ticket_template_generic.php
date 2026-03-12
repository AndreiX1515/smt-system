<?php
/**
 * Generic Airline Ticket PDF Template (original)
 * Expected: $data array
 */

$booking = $data['booking'];
$passengers = $data['passengers'];
$flights = $data['flights'];
$bookingRef = $data['bookingRef'];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page {
        margin: 15mm 12mm 15mm 12mm;
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
    .header {
        text-align: center;
        border-bottom: 3px solid #003366;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .header h1 {
        font-size: 20px;
        color: #003366;
        margin: 0 0 4px 0;
    }
    .header .sub {
        font-size: 11px;
        color: #666;
    }
    .info-row {
        display: flex;
        margin-bottom: 12px;
    }
    .info-box {
        flex: 1;
        padding: 8px 12px;
        background: #f0f4f8;
        border-radius: 4px;
        margin-right: 8px;
    }
    .info-box:last-child { margin-right: 0; }
    .info-box .label {
        font-size: 8px;
        color: #666;
        text-transform: uppercase;
        font-weight: bold;
        margin-bottom: 2px;
    }
    .info-box .value {
        font-size: 11px;
        font-weight: bold;
        color: #003366;
    }
    h2 {
        font-size: 13px;
        color: #003366;
        border-bottom: 2px solid #003366;
        padding-bottom: 3px;
        margin: 16px 0 8px 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .flight-table th, .flight-table td {
        border: 1px solid #ccc;
        padding: 6px 8px;
        font-size: 9px;
        text-align: left;
    }
    .flight-table th {
        background: #003366;
        color: #fff;
        font-size: 9px;
    }
    .pax-table th, .pax-table td {
        border: 1px solid #ccc;
        padding: 6px 8px;
        font-size: 9px;
        text-align: left;
    }
    .pax-table th {
        background: #003366;
        color: #fff;
    }
    .pax-table tr:nth-child(even) {
        background: #f8f9fa;
    }
    .addon-tag {
        display: inline-block;
        background: #e8f0fe;
        color: #1a5276;
        padding: 1px 6px;
        border-radius: 3px;
        font-size: 8px;
        margin: 1px 2px;
    }
    .footer {
        text-align: center;
        font-size: 8px;
        color: #999;
        margin-top: 24px;
        border-top: 1px solid #ddd;
        padding-top: 8px;
    }
    .notice-box {
        background: #fff8e1;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 9px;
        margin-top: 12px;
    }
    .notice-box strong { color: #e65100; }
</style>
</head>
<body>

<div class="header">
    <h1>AIRLINE TICKET</h1>
    <div class="sub"><?php echo htmlspecialchars($booking['packageName'] ?? ''); ?></div>
</div>

<!-- Booking Info -->
<table style="width:100%;margin-bottom:12px;">
    <tr>
        <td style="width:33%;padding:6px 10px;background:#f0f4f8;border-radius:4px;">
            <div style="font-size:8px;color:#666;text-transform:uppercase;font-weight:bold;">Booking ID</div>
            <div style="font-size:11px;font-weight:bold;color:#003366;"><?php echo htmlspecialchars($booking['bookingId'] ?? ''); ?></div>
        </td>
        <td style="width:5%"></td>
        <td style="width:33%;padding:6px 10px;background:#f0f4f8;border-radius:4px;">
            <div style="font-size:8px;color:#666;text-transform:uppercase;font-weight:bold;">Airline Booking Ref</div>
            <div style="font-size:11px;font-weight:bold;color:#003366;"><?php echo htmlspecialchars($bookingRef); ?></div>
        </td>
        <td style="width:5%"></td>
        <td style="width:24%;padding:6px 10px;background:#f0f4f8;border-radius:4px;">
            <div style="font-size:8px;color:#666;text-transform:uppercase;font-weight:bold;">Passengers</div>
            <div style="font-size:11px;font-weight:bold;color:#003366;"><?php echo count($passengers); ?> PAX</div>
        </td>
    </tr>
</table>

<!-- Flight Details -->
<h2>Flight Details</h2>
<table class="flight-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Flight No.</th>
            <th>Route</th>
            <th>Departure</th>
            <th>Arrival</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($flights['details']['departure'])): $dep = $flights['details']['departure']; ?>
        <tr>
            <td><strong>Departure</strong></td>
            <td><?php echo htmlspecialchars($dep['flightNo']); ?></td>
            <td><?php echo htmlspecialchars($dep['route']); ?></td>
            <td><?php echo htmlspecialchars($dep['depDate'] . ' ' . $dep['depTime']); ?></td>
            <td><?php echo htmlspecialchars(($dep['arrDate'] ?? $dep['depDate']) . ' ' . ($dep['arrTime'] ?? '')); ?></td>
        </tr>
    <?php endif; ?>
    <?php if (!empty($flights['details']['return'])): $ret = $flights['details']['return']; ?>
        <tr>
            <td><strong>Return</strong></td>
            <td><?php echo htmlspecialchars($ret['flightNo']); ?></td>
            <td><?php echo htmlspecialchars($ret['route']); ?></td>
            <td><?php echo htmlspecialchars($ret['depDate'] . ' ' . $ret['depTime']); ?></td>
            <td><?php echo htmlspecialchars(($ret['arrDate'] ?? $ret['depDate']) . ' ' . ($ret['arrTime'] ?? '')); ?></td>
        </tr>
    <?php endif; ?>
    <?php if (empty($flights['details'])): ?>
        <tr>
            <td colspan="5" style="text-align:center;color:#999;">
                <?php
                    $fns = $flights['flightNumbers'] ?? [];
                    echo $fns ? 'Flight: ' . htmlspecialchars(implode(', ', $fns)) : 'Flight details not available';
                ?>
            </td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Passenger List -->
<h2>Passenger List</h2>
<table class="pax-table">
    <thead>
        <tr>
            <th style="width:30px">#</th>
            <th>Passenger Name</th>
            <th>Type</th>
            <th>Outbound (MNL→ICN)</th>
            <th>Return (ICN→MNL)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($passengers as $idx => $pax): ?>
        <tr>
            <td style="text-align:center"><?php echo $idx + 1; ?></td>
            <td><strong><?php echo htmlspecialchars($pax['parsedName']); ?></strong></td>
            <td><?php echo htmlspecialchars($pax['parsedType']); ?></td>
            <td>
                <?php
                $ob = $pax['outbound'] ?? [];
                if (!empty($ob['fare'])) echo '<span class="addon-tag">' . htmlspecialchars($ob['fare']) . '</span>';
                if (!empty($ob['baggage'])) echo '<span class="addon-tag">' . htmlspecialchars($ob['baggage']) . '</span>';
                if (!empty($ob['seat']) && $ob['seat'] !== 'Unassigned') echo '<span class="addon-tag">Seat: ' . htmlspecialchars($ob['seat']) . '</span>';
                ?>
            </td>
            <td>
                <?php
                $rt = $pax['return'] ?? [];
                if (!empty($rt['fare'])) echo '<span class="addon-tag">' . htmlspecialchars($rt['fare']) . '</span>';
                if (!empty($rt['baggage'])) echo '<span class="addon-tag">' . htmlspecialchars($rt['baggage']) . '</span>';
                if (!empty($rt['seat']) && $rt['seat'] !== 'Unassigned') echo '<span class="addon-tag">Seat: ' . htmlspecialchars($rt['seat']) . '</span>';
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Notice -->
<div class="notice-box">
    <strong>Important:</strong> This ticket is extracted from a group booking (Ref: <?php echo htmlspecialchars($bookingRef); ?>).
    Please present this document along with a valid passport at check-in.
    Baggage allowance and seat assignments are as indicated above.
</div>

<div class="footer">
    Generated by SMT Escape Travel &amp; Tours on <?php echo date('Y-m-d H:i'); ?>
</div>

</body>
</html>
