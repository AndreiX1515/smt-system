<?php
require_once '../../public/inc/general/error_logger.php';
const LOG_SOURCE = 'layout.php';

// ── Defaults ──────────────────────────────────────────────────────────────────
$pageTitle  = $pageTitle  ?? 'Default Title';
$bodyClass  = $bodyClass  ?? '';
$pageModals = $pageModals ?? '';

$headerPath = $headerPath ?? __DIR__ . '/../../../public/templates/header.php';
$navPath    = $navPath    ?? __DIR__ . '/../../../public/templates/nav_super.php';

$content = $content ?? '';
if (!is_string($content)) {
    AppLogger::warn('$content is not a string — defaulting to empty.', LOG_SOURCE);
    $content = '';
}

// DEV cache-busting
$version = time();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../public/css/app.css?v=<?= $version ?>">
    <link rel="stylesheet" href="../../public/css/general/layout.css?v=<?= $version ?>">
    <link rel="stylesheet" href="../../public/css/components/header.css?v=<?= $version ?>">
    <link rel="stylesheet" href="../../public/css/components/sidebar.css?v=<?= $version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body class="<?= htmlspecialchars($bodyClass) ?>">

    <!-- Header -->
    <?php
    if (file_exists($headerPath)) {
        include $headerPath;
    } else {
        AppLogger::error('Header template not found.', LOG_SOURCE, ['path' => $headerPath]);
        echo '<!-- Header missing -->';
    }
    ?>

    <main class="layout-main flex min-h-screen" id="layoutMain">

        <!-- Sidebar -->
        <?php
        if (file_exists($navPath)) {
            include $navPath;
        } else {
            AppLogger::error('Nav template not found.', LOG_SOURCE, ['path' => $navPath]);
            echo '<!-- Nav missing -->';
        }
        ?>

        <!-- Content -->
        <div class="layout-content" id="layoutContent">
            <div class="content-wrapper">
                <div class="dashboard-section">
                    <?= $content ?>
                </div>
            </div>
        </div>

    </main>

    <!-- Modals -->
    <?= $pageModals ?>

    <!-- JS -->
    <script src="../../public/assets/js/default.js"></script>



</body>

</html>