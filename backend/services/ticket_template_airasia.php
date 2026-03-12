<?php
/**
 * AirAsia Airline Ticket PDF Template
 * Replicates the AirAsia booking confirmation layout
 * Expected: $data array
 */

$booking = $data['booking'];
$passengers = $data['passengers'];
$flights = $data['flights'];
$bookingRef = $data['bookingRef'];
$bookingDate = $data['bookingDate'] ?? '';
$logoBase64 = $data['logoBase64'] ?? null;

$depDetail = $flights['details']['departure'] ?? [];
$retDetail = $flights['details']['return'] ?? [];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page {
        margin: 12mm 12mm 12mm 12mm;
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
        display: table;
        width: 100%;
        margin-bottom: 20px;
    }
    .header-left {
        display: table-cell;
        vertical-align: top;
        width: 120px;
    }
    .header-right {
        display: table-cell;
        vertical-align: top;
        text-align: right;
        font-size: 11px;
        color: #555;
        padding-top: 10px;
    }
    .logo-circle {
        width: 80px;
        height: 80px;
        background: #e42421;
        border-radius: 50%;
        text-align: center;
        line-height: 80px;
        color: #fff;
        font-size: 16px;
        font-weight: bold;
        font-style: italic;
    }
    .booking-confirmed {
        font-size: 22px;
        color: #4CAF50;
        margin: 16px 0 8px 0;
        font-weight: 300;
    }
    .red-line {
        border: 0;
        border-top: 2px solid #e42421;
        margin: 8px 0 16px 0;
    }
    .booking-no-label {
        font-size: 11px;
        color: #777;
        margin-bottom: 2px;
    }
    .booking-no {
        font-size: 24px;
        font-weight: bold;
        color: #222;
        margin-bottom: 16px;
    }
    .warning-box {
        background: #fff8e1;
        border: 1px solid #f9a825;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 9px;
        color: #555;
        margin-bottom: 16px;
        line-height: 1.6;
    }
    .warning-box .icon {
        color: #f9a825;
        font-size: 14px;
        margin-right: 6px;
    }
    .section-box {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        margin-bottom: 16px;
        overflow: hidden;
    }
    .section-title {
        font-size: 13px;
        font-weight: bold;
        color: #222;
        padding: 10px 14px;
        border-bottom: 1px solid #e0e0e0;
        background: #fafafa;
    }
    .section-body {
        padding: 14px;
    }
    .flight-summary-route {
        font-size: 20px;
        font-weight: bold;
        color: #222;
        margin-bottom: 4px;
    }
    .flight-summary-route .plane-icon {
        color: #888;
        margin-right: 6px;
    }
    .flight-summary-dates {
        font-size: 11px;
        color: #777;
        margin-bottom: 14px;
    }
    .flight-leg {
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eee;
    }
    .flight-leg:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .flight-leg-label {
        font-size: 10px;
        color: #777;
        margin-bottom: 4px;
    }
    .flight-leg-airline {
        font-size: 10px;
        color: #555;
        margin-bottom: 4px;
    }
    .flight-times {
        display: table;
        width: 100%;
    }
    .flight-time-cell {
        display: table-cell;
        vertical-align: middle;
    }
    .flight-time-big {
        font-size: 18px;
        font-weight: bold;
        color: #222;
    }
    .flight-duration {
        font-size: 9px;
        color: #999;
    }
    .flight-nonstop {
        font-size: 9px;
        color: #999;
    }
    .guest-table {
        width: 100%;
        border-collapse: collapse;
    }
    .guest-table td {
        padding: 6px 8px;
        font-size: 10px;
        vertical-align: top;
        width: 50%;
        border-bottom: 1px solid #f0f0f0;
    }
    .guest-name {
        font-weight: bold;
        color: #333;
        text-transform: uppercase;
        font-size: 10px;
    }
    .guest-type {
        color: #888;
        font-weight: normal;
        font-size: 9px;
    }
    .addons-segment-header {
        background: #f5f5f5;
        padding: 6px 14px;
        font-size: 10px;
        color: #555;
        border-bottom: 1px solid #e0e0e0;
        border-top: 1px solid #e0e0e0;
    }
    .addon-table {
        width: 100%;
        border-collapse: collapse;
    }
    .addon-table td {
        padding: 8px;
        font-size: 9px;
        vertical-align: top;
        width: 50%;
        border-bottom: 1px solid #f0f0f0;
    }
    .addon-pax-name {
        font-weight: bold;
        color: #555;
        text-transform: uppercase;
        font-size: 9px;
        margin-bottom: 3px;
        border-bottom: 1px solid #e8e8e8;
        padding-bottom: 3px;
    }
    .addon-item {
        color: #666;
        font-size: 9px;
        padding: 1px 0;
    }
    .addon-item .icon {
        color: #bbb;
        margin-right: 4px;
    }
    .footer {
        text-align: center;
        font-size: 8px;
        color: #999;
        margin-top: 16px;
        border-top: 1px solid #ddd;
        padding-top: 8px;
    }
</style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <?php if ($logoBase64): ?>
        <img src="data:image/png;base64,<?php echo $logoBase64; ?>" style="width:80px;height:80px;border-radius:50%;" alt="AirAsia">
        <?php else: ?>
        <div class="logo-circle">AirAsia</div>
        <?php endif; ?>
    </div>
    <div class="header-right">
        Booking date: <?php echo htmlspecialchars($bookingDate); ?>
    </div>
</div>

<!-- Booking Confirmed -->
<div class="booking-confirmed">Booking Confirmed</div>
<hr class="red-line">

<!-- Booking No -->
<div class="booking-no-label">Booking no.</div>
<div class="booking-no"><?php echo htmlspecialchars($bookingRef); ?></div>

<!-- Warning box -->
<div class="warning-box">
    <strong style="color:#f9a825;font-size:13px;">!</strong>
    Please make sure that you have all the required travel documents including passport and visa for your flight.
    Ensure your travel documents are valid and up to date before departure.
</div>

<!-- Guest details -->
<div class="section-box">
    <div class="section-title">Guest details</div>
    <div class="section-body">
        <table class="guest-table">
            <?php
            $paxList = array_values($passengers);
            $paxCount = count($paxList);
            for ($i = 0; $i < $paxCount; $i += 2):
            ?>
            <tr>
                <td>
                    <span class="guest-name"><?php echo htmlspecialchars($paxList[$i]['parsedName']); ?></span>
                    <span class="guest-type">(<?php echo strtolower(htmlspecialchars($paxList[$i]['parsedType'])); ?>)</span>
                </td>
                <?php if (isset($paxList[$i + 1])): ?>
                <td>
                    <span class="guest-name"><?php echo htmlspecialchars($paxList[$i + 1]['parsedName']); ?></span>
                    <span class="guest-type">(<?php echo strtolower(htmlspecialchars($paxList[$i + 1]['parsedType'])); ?>)</span>
                </td>
                <?php else: ?>
                <td></td>
                <?php endif; ?>
            </tr>
            <?php endfor; ?>
        </table>
    </div>
</div>

<!-- Flight summary -->
<div class="section-box">
    <div class="section-title">Flight summary</div>
    <div class="section-body">
        <div class="flight-summary-route">
            Manila &lt;&gt; Seoul
        </div>
        <div class="flight-summary-dates">
            <?php
            $depSummary = $flights['departureSummary'] ?? '';
            $retSummary = $flights['returnSummary'] ?? '';
            if ($depSummary || $retSummary) {
                echo 'Departure: ' . htmlspecialchars($depSummary) . ' &bull; Return: ' . htmlspecialchars($retSummary);
            } elseif (!empty($depDetail['depDate']) && !empty($retDetail['depDate'])) {
                echo 'Departure: ' . htmlspecialchars($depDetail['depDate']) . ' &bull; Return: ' . htmlspecialchars($retDetail['depDate']);
            }
            ?>
        </div>

        <?php if (!empty($depDetail)): ?>
        <div class="flight-leg">
            <div class="flight-leg-label">Depart : <?php echo htmlspecialchars($depDetail['airline'] ?? 'Philippines AirAsia'); ?></div>
            <div class="flight-times">
                <table style="width:100%;border:none;">
                    <tr>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-time-big"><?php echo htmlspecialchars($depDetail['depTime']); ?></div>
                        </td>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-time-big"><?php echo htmlspecialchars($depDetail['arrTime']); ?></div>
                        </td>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-duration"><?php echo htmlspecialchars($depDetail['duration'] ?? ''); ?></div>
                        </td>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-nonstop">Non-stop</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($retDetail)): ?>
        <div class="flight-leg">
            <div class="flight-leg-label">Return : <?php echo htmlspecialchars($retDetail['airline'] ?? 'Philippines AirAsia'); ?></div>
            <div class="flight-times">
                <table style="width:100%;border:none;">
                    <tr>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-time-big"><?php echo htmlspecialchars($retDetail['depTime']); ?></div>
                        </td>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-time-big"><?php echo htmlspecialchars($retDetail['arrTime']); ?></div>
                        </td>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-duration"><?php echo htmlspecialchars($retDetail['duration'] ?? ''); ?></div>
                        </td>
                        <td style="width:20%;border:none;padding:4px 0;">
                            <div class="flight-nonstop">Non-stop</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add-ons -->
<div class="section-box">
    <div class="section-title">Add-ons</div>

    <!-- Outbound segment -->
    <div class="addons-segment-header">Manila to Seoul - Incheon</div>
    <div class="section-body">
        <table class="addon-table">
            <?php
            $paxList = array_values($passengers);
            $paxCount = count($paxList);
            for ($i = 0; $i < $paxCount; $i += 2):
            ?>
            <tr>
                <td>
                    <div class="addon-pax-name"><?php echo htmlspecialchars($paxList[$i]['parsedName']); ?></div>
                    <?php
                    $ob = $paxList[$i]['outbound'] ?? [];
                    if (!empty($ob['baggage'])) echo '<div class="addon-item">' . htmlspecialchars($ob['baggage']) . '</div>';
                    if (!empty($ob['carryon'])) echo '<div class="addon-item">' . htmlspecialchars($ob['carryon']) . '</div>';
                    if (!empty($ob['seat'])) echo '<div class="addon-item">Seat ' . htmlspecialchars($ob['seat']) . '</div>';
                    if (empty($ob)) echo '<div class="addon-item" style="color:#bbb;">No add-ons</div>';
                    ?>
                </td>
                <?php if (isset($paxList[$i + 1])): ?>
                <td>
                    <div class="addon-pax-name"><?php echo htmlspecialchars($paxList[$i + 1]['parsedName']); ?></div>
                    <?php
                    $ob2 = $paxList[$i + 1]['outbound'] ?? [];
                    if (!empty($ob2['baggage'])) echo '<div class="addon-item">' . htmlspecialchars($ob2['baggage']) . '</div>';
                    if (!empty($ob2['carryon'])) echo '<div class="addon-item">' . htmlspecialchars($ob2['carryon']) . '</div>';
                    if (!empty($ob2['seat'])) echo '<div class="addon-item">Seat ' . htmlspecialchars($ob2['seat']) . '</div>';
                    if (empty($ob2)) echo '<div class="addon-item" style="color:#bbb;">No add-ons</div>';
                    ?>
                </td>
                <?php else: ?>
                <td></td>
                <?php endif; ?>
            </tr>
            <?php endfor; ?>
        </table>
    </div>

    <!-- Return segment -->
    <div class="addons-segment-header">Seoul - Incheon to Manila</div>
    <div class="section-body">
        <table class="addon-table">
            <?php
            for ($i = 0; $i < $paxCount; $i += 2):
            ?>
            <tr>
                <td>
                    <div class="addon-pax-name"><?php echo htmlspecialchars($paxList[$i]['parsedName']); ?></div>
                    <?php
                    $rt = $paxList[$i]['return'] ?? [];
                    if (!empty($rt['baggage'])) echo '<div class="addon-item">' . htmlspecialchars($rt['baggage']) . '</div>';
                    if (!empty($rt['carryon'])) echo '<div class="addon-item">' . htmlspecialchars($rt['carryon']) . '</div>';
                    if (!empty($rt['seat'])) echo '<div class="addon-item">Seat ' . htmlspecialchars($rt['seat']) . '</div>';
                    if (empty($rt)) echo '<div class="addon-item" style="color:#bbb;">No add-ons</div>';
                    ?>
                </td>
                <?php if (isset($paxList[$i + 1])): ?>
                <td>
                    <div class="addon-pax-name"><?php echo htmlspecialchars($paxList[$i + 1]['parsedName']); ?></div>
                    <?php
                    $rt2 = $paxList[$i + 1]['return'] ?? [];
                    if (!empty($rt2['baggage'])) echo '<div class="addon-item">' . htmlspecialchars($rt2['baggage']) . '</div>';
                    if (!empty($rt2['carryon'])) echo '<div class="addon-item">' . htmlspecialchars($rt2['carryon']) . '</div>';
                    if (!empty($rt2['seat'])) echo '<div class="addon-item">Seat ' . htmlspecialchars($rt2['seat']) . '</div>';
                    if (empty($rt2)) echo '<div class="addon-item" style="color:#bbb;">No add-ons</div>';
                    ?>
                </td>
                <?php else: ?>
                <td></td>
                <?php endif; ?>
            </tr>
            <?php endfor; ?>
        </table>
    </div>
</div>


</body>
</html>
