<?php
/**
 * inc/layout.php
 * Simple layout loader with error logging
 */

$pageTitle   = $pageTitle   ?? 'Dashboard';
$navUrl      = $navUrl      ?? '../../public/inc/nav_super.php';
$pageContent = $pageContent ?? '';
$pageSlug    = $pageSlug    ?? '';
$pageStyles  = $pageStyles  ?? [];
$pageScripts = $pageScripts ?? [];
$pageModals  = $pageModals  ?? '';
$pageInit    = $pageInit    ?? []; // always an array

/* ── Default CSS ───────────────────────── */
$defaultStyles = [
    '../../public/css/general/root.css',
    '../../public/css/general/layout.css',
    '../../public/css/components/header.css',
    '../../public/css/components/sidebar.css',
    '../../public/css/app.css', // Tailwind + FlyonUI
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css'
];

try {
    if (!is_array($pageStyles)) {
        error_log("[layout.php] \$pageStyles is not an array. Reverting to empty array.");
        $pageStyles = [];
    }
    $pageStyles = array_merge($defaultStyles, $pageStyles);
} catch (Throwable $e) {
    error_log("[layout.php] Failed to merge CSS arrays: " . $e->getMessage());
}

/* ── Default JS ────────────────────────── */
$defaultScripts = [
    '../../node_modules/flyonui/flyonui.js',
    '../js/default.js'
];

try {
    if (!is_array($pageScripts)) {
        error_log("[layout.php] \$pageScripts is not an array. Reverting to empty array.");
        $pageScripts = [];
    }
    $pageScripts = array_merge($defaultScripts, $pageScripts);
} catch (Throwable $e) {
    error_log("[layout.php] Failed to merge JS arrays: " . $e->getMessage());
}

/* ── Check $pageInit ───────────────────── */
if (!is_array($pageInit)) {
    error_log("[layout.php] \$pageInit is not an array. Reverting to empty array.");
    $pageInit = [];
}
?>


<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- Tab Icon -->
  <link rel="icon" type="image/png" href="../../public/image/logo.png">

  <!-- CSS -->
  <?php foreach ($pageStyles as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
  <?php endforeach; ?>
</head>

<body>

  <header class="layout-header" id="layoutHeader"></header>

  <main class="layout-main" id="layoutMain">

    <nav class="layout-nav" id="layoutNav"></nav>

    <div class="layout-content" id="layoutContent">
      <div class="content-wrapper">
        <div class="dashboard-section">
          <?= $pageContent ?>
        </div>
      </div>
    </div>


  </main>

  <!-- MODAL PORTAL — always outside layout-content and layout-main -->
  <?= $pageModals ?>

  <!-- JS -->
  <?php foreach ($pageScripts as $src): ?>
    <script src="<?= htmlspecialchars($src) ?>"></script>
  <?php endforeach; ?>


  <?php
  // Build ONE clean init config
  $jsOptions = [
    'headerUrl' => '../inc/header.php',
    'navUrl'    => $navUrl
  ];

  if (!empty($pageInit) && is_array($pageInit)) {
    $jsOptions = array_merge($jsOptions, $pageInit);
  }

  // Encode safely for JS
  $jsOptionsJson = json_encode($jsOptions, JSON_UNESCAPED_SLASHES);
  ?>

  <!-- Init Script (SAFE) -->
  <script>
    window.addEventListener('DOMContentLoaded', function() {
      if (typeof init !== 'function') {
        console.error('init() is not defined. Check default.js path.');
        return;
      }
      init(<?= $jsOptionsJson ?>);
    });
  </script>








</body>

</html>