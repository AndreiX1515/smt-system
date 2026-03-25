<?php
/**
 * layout.php
 * Dynamic layout template for pages
 *
 * Variables that pages can define:
 *   - $pageTitle    : string
 *   - $bodyClass    : string
 *   - $content      : string (HTML content)
 *   - $pageCSS      : array of CSS URLs
 *   - $pageJS       : array of JS URLs
 *   - $pageModals   : string (HTML for modals)
 *   - $headerPath   : string (header template path)
 *   - $navPath      : string (nav template path)
 */

$pageTitle  = $pageTitle ?? 'Default Title';
$bodyClass  = $bodyClass ?? '';
$content    = $content ?? '';
$pageCSS    = is_array($pageCSS) ? $pageCSS : [];
$pageJS     = is_array($pageJS) ? $pageJS : [];
$pageModals = $pageModals ?? '';

$headerPath = $headerPath ?? __DIR__ . '../../../public/templates/header.php';
$navPath    = $navPath ?? __DIR__ . '../../../public/templates/nav_super.php';

// Default CSS
$defaultCSS = [
    '../../public/css/general/root.css',
    '../../public/css/general/layout.css',
    '../../public/css/components/header.css',
    '../../public/css/components/sidebar.css',
    '../../public/css/app.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css'
];

// Default JS
$defaultJS = [
    '../../public/assets/js/default.js',
    '../../node_modules/flyonui/flyonui.js'
];

// Merge page-specific with defaults
$pageCSS = array_merge($defaultCSS, $pageCSS);
$pageJS  = array_merge($defaultJS, $pageJS);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- CSS -->
  <?php foreach ($pageCSS as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
  <?php endforeach; ?>
</head>

<body class="<?= htmlspecialchars($bodyClass) ?>">

  <!-- Header -->
  <?php
  if (file_exists($headerPath)) {
      include $headerPath;
  } else {
      error_log("[layout.php] Header template not found: {$headerPath}");
      echo "<!-- Header missing -->";
  }
  ?>

  <main class="layout-main" id="layoutMain">

    <!-- Navigation -->
    <?php
    if (file_exists($navPath)) {
        include $navPath;
    } else {
        error_log("[layout.php] Nav template not found: {$navPath}");
        echo "<!-- Nav missing -->";
    }
    ?>

    <div class="layout-content" id="layoutContent">
      <div class="content-wrapper">
        <div class="dashboard-section">
          <?= $content ?>
        </div>
      </div>
    </div>

  </main>

  <!-- Page Modals -->
  <?= $pageModals ?>

  <!-- JS -->
  <?php foreach ($pageJS as $js): ?>
    <script src="<?= htmlspecialchars($js) ?>"></script>
  <?php endforeach; ?>

  <!-- FlyonUI init -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      try {
        if (window.FlyonUI && typeof FlyonUI.initAll === 'function') {
          FlyonUI.initAll(); // initialize all FlyonUI components
        }
      } catch (err) {
        console.error('[layout.php] FlyonUI init error:', err);
      }
    });
  </script>

</body>
</html>