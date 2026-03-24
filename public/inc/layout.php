<?php

/**
 * inc/layout.php
 * ─────────────────────────────────────────────────────
 * The single HTML shell every page includes.
 *
 * Required variables (set BEFORE including this file):
 *   string $pageTitle    — plain text for <title>
 *   string $navUrl       — which nav fragment, e.g. '../inc/nav_super.php'
 *   string $pageContent  — inner HTML for #layoutContent
 *
 * Optional variables:
 *   string $pageSlug     — current page slug for nav active state
 *   array  $pageScripts  — additional <script src="..."> paths OR inline scripts
 *   array  $pageStyles   — additional <link rel="stylesheet"> paths
 *   string $pageModals   — modal HTML to inject at <body> level
 *   array  $pageInit     — init config for JS init({...}) call
 */
$pageTitle   = $pageTitle   ?? 'Dashboard';
$navUrl      = $navUrl      ?? '../public/inc/nav_super.php';
$pageContent = $pageContent ?? '';
$pageSlug    = $pageSlug    ?? '';
$pageStyles  = $pageStyles  ?? [];
$pageScripts = $pageScripts ?? [];
$pageModals  = $pageModals  ?? '';
$pageInit    = $pageInit    ?? [];  // NEW: page-specific init config

// ─── Default CSS & JS ────────────────────────────────
$defaultStyles = [
  '../../public/css/general/root.css?v=' . time(),
  '../../public/css/general/layout.css?v=' . time(),
  '../../public/css/components/header.css?v=' . time(),
  '../../public/css/components/sidebar.css?v=' . time(),
  '../../public/css/app.css?v=' . time(), // Tailwind + FlyonUI
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css' // Font Awesome CDN
];
$pageStyles = array_merge($defaultStyles, $pageStyles);

$defaultScripts = [
  '../../node_modules/flyonui/flyonui.js',
  '../js/default.js?v=' . time()
];
$pageScripts = array_merge($defaultScripts, $pageScripts);

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
  // Build JS options
  $jsOptions = [
    'headerUrl' => '../inc/header.php',
    'navUrl'    => $navUrl
  ];

  if (!empty($pageInit) && is_array($pageInit)) {
    foreach ($pageInit as $key => $value) {
      $jsOptions[$key] = $value;
    }
  }

  // Encode entire object safely for JS
  $jsOptionsJson = json_encode($jsOptions, JSON_UNESCAPED_SLASHES);
  ?>

  <!-- Modular Init Script -->
  <script>
    init(<?= $jsOptionsJson ?>);
  </script>

</body>

</html>