<?php
require_once '../backend/i18n_helper.php';
require_once '../backend/conn.php';

//
$currentLang = getCurrentLanguage();

// Load partners from database
$partners = [];
try {
    // Check if partners table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'partners'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $sql = "SELECT * FROM partners WHERE isActive = 1 ORDER BY partnerOrder ASC, partnerId ASC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $partners[] = $row;
            }
        }
    }
} catch (Exception $e) {
    // If error, partners array stays empty and we'll show fallback
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echoI18nText('partnershipInformation', $currentLang); ?> | <?php echoI18nText('smart_travel', $currentLang); ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../js/button.js" defer></script>
    <link rel="stylesheet" href="/css/i18n-boot.css">
    <script src="/js/i18n-boot.js"></script>
    <script src="../js/i18n.js" defer></script>
</head>
<body>
    
    <div class="main pb20">
        <header class="header-type2">
            <a class="btn-mypage" href="#none"><img src="../images/ico_back_black.svg"></a>
            <div class="title" data-i18n="partnershipInformation"><?php echoI18nText('partnershipInformation', $currentLang); ?></div>
            <div></div>
        </header>
        <div class="partnership-banner">
            <img class="w100" src="../images/@img_partnership.jpg" alt="">
            <div class="partnership-banner__text">
                <div class="w300 text fz20 fw600 lh28 white txt-center" data-i18n="trustedGlobalPartners">
                    <?php echoI18nTextHTML('trustedGlobalPartners', $currentLang); ?>
                </div>
                <p class="text fz14 fw400 lh22 white txt-center mt12" data-i18n="partnershipDescription">
                    <?php echoI18nText('partnershipDescription', $currentLang); ?>
                </p>
            </div>
        </div>
        <div>
            <div class="text fz16 fw600 lh26 black12 txt-center mt48" data-i18n="partners"><?php echoI18nText('partners', $currentLang); ?></div>
            <ul class="list-type3 px20 mt20">
                <?php if (!empty($partners)): ?>
                    <?php foreach ($partners as $partner): ?>
                        <li>
                            <div class="thumb">
                                <?php if (!empty($partner['imageUrl'])): ?>
                                    <img src="<?php echo htmlspecialchars($partner['imageUrl']); ?>" alt="<?php echo htmlspecialchars($partner['partnerName']); ?>">
                                <?php else: ?>
                                    <img src="../images/partner_placeholder.png" alt="<?php echo htmlspecialchars($partner['partnerName']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="red-line"></div>
                            <p class="text fz12 fw400 lh22 black12 txt-center">
                                <?php echo htmlspecialchars($partner['partnerName']); ?>
                                <?php if (!empty($partner['partnerSubtitle'])): ?>
                                    <br><?php echo htmlspecialchars($partner['partnerSubtitle']); ?>
                                <?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback: Original hardcoded partners (shown if DB is empty) -->
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner1.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">ESCAPE TRAVEL</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner2.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">B2</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner3.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">EWINER</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner4.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">APD TRAVEL</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner5.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">FRANCIA TRAVEL<br>&nbsp;</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner6.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">WOW TRAVEL<br>CEBU-ICN-CEBU ONLY</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner7.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">SULIT TRAVEL</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner8.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">LIPAD LAKBAY</p>
                    </li>
                    <li>
                        <div class="thumb">
                            <img src="../images/@img_partner9.jpg">
                        </div>
                        <div class="red-line"></div>
                        <p class="text fz12 fw400 lh22 black12 txt-center">AMABEE TRAVEL</p>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>
</html>