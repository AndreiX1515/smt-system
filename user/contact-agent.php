<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Agent | Smart Travel</title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/api.js" defer></script>
    <script src="../js/auth-guard.js" defer></script>
    <link rel="stylesheet" href="../css/i18n-boot.css">
    <script src="../js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
    <style>
        #map {
            width: 100%;
            height: 300px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .agent-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .agent-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s;
        }
        .agent-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .agent-card.selected {
            border-color: #FF6B6B;
            box-shadow: 0 0 0 2px rgba(255, 107, 107, 0.2);
        }
        .agent-name {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .agent-address {
            font-size: 14px;
            color: #6b6b6b;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .agent-contact {
            font-size: 14px;
            color: #333;
            margin-bottom: 12px;
        }
        .agent-distance {
            font-size: 13px;
            color: #FF6B6B;
            font-weight: 500;
            margin-bottom: 12px;
        }
        .agent-actions {
            display: flex;
            gap: 8px;
        }
        .agent-actions .btn {
            flex: 1;
            padding: 10px 16px;
            font-size: 14px;
        }
        .product-info-banner {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E8E 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .product-info-banner .product-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .product-info-banner .product-date {
            font-size: 14px;
            opacity: 0.9;
        }
        .gps-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            color: #6b6b6b;
        }
        .gps-status.success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .gps-status.error {
            background: #ffebee;
            color: #c62828;
        }
        .loading-map {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            background: #f5f5f5;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .no-agents {
            text-align: center;
            padding: 40px 20px;
            color: #6b6b6b;
        }
        .no-agents img {
            width: 80px;
            opacity: 0.5;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/../backend/config/session.php';

// 로그인 체크 (PHP 세션)
if (!isset($_SESSION['user_id'])) {
    // JavaScript에서 auth-guard가 처리하도록 둠
}

// URL 파라미터
$productId = isset($_GET['product_id']) ? htmlspecialchars($_GET['product_id']) : '';
$productName = isset($_GET['product_name']) ? htmlspecialchars(urldecode($_GET['product_name'])) : '';
$departureDate = isset($_GET['departure_date']) ? htmlspecialchars($_GET['departure_date']) : '';
?>
    <div class="main bg white mh100 pb80">
        <header class="header-type2 bg white" style="box-shadow: none;">
            <a class="btn-mypage" href="javascript:history.back();"><img src="../images/ico_back_black.svg" alt="Back"></a>
            <div class="title">Contact Agent</div>
            <div></div>
        </header>

        <div class="px20 pt20">
            <?php if ($productName): ?>
            <div class="product-info-banner">
                <div class="product-name"><?php echo $productName; ?></div>
                <?php if ($departureDate): ?>
                <div class="product-date">Departure: <?php echo $departureDate; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <h3 class="text fz18 fw600 lh26 black12 mb16">Find Nearby Agent</h3>

            <div class="gps-status" id="gpsStatus">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
                </svg>
                <span>Detecting your location...</span>
            </div>

            <div class="loading-map" id="mapLoading">
                <div class="loading-spinner"></div>
            </div>
            <div id="map" style="display: none;"></div>

            <h4 class="text fz16 fw600 lh24 black12 mb4">Agent Locations</h4>
            <p style="font-size:13px;color:#888;margin-bottom:12px;">Sorted by nearest location to you.</p>
            <div class="agent-search-wrap" style="margin-bottom:14px;">
                <input type="text" id="agentSearchInput" placeholder="Search by name, address or phone" style="width:100%;padding:10px 14px;border:1px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;" />
            </div>
            <ul class="agent-list" id="agentList">
                <li class="no-agents">
                    <img src="../images/ico_location.svg" alt="">
                    <p>Loading agent locations...</p>
                </li>
            </ul>
        </div>
    </div>

    <script>
        // URL 파라미터 저장
        window.contactAgentParams = {
            productId: '<?php echo addslashes($productId); ?>',
            productName: '<?php echo addslashes($productName); ?>',
            departureDate: '<?php echo addslashes($departureDate); ?>'
        };
    </script>
    <script src="../js/contact-agent.js"></script>
    <script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCn9NqedIRpJrhkLDNRRr8yIPFaUFl-FnU&loading=async&callback=initMap"></script>
</body>
</html>
