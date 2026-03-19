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
 *                          e.g. 'overview', 'b2b-booking-detail'
 *                          If omitted, JS derives it from the filename.
 *   array  $pageScripts  — additional <script src="..."> paths
 *   array  $pageStyles   — additional <link rel="stylesheet"> paths
 */

$pageTitle   = $pageTitle   ?? 'Dashboard';
$navUrl      = $navUrl      ?? '../public/inc/nav_super.php';
$pageContent = $pageContent ?? '';
$pageSlug    = $pageSlug    ?? '';
$pageScripts = $pageScripts ?? [];
$pageStyles  = $pageStyles  ?? [];
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- <link rel="stylesheet" href="../../public/css/a_components.css">
  <link rel="stylesheet" href="../../public/css/a_variables.css">
  <link rel="stylesheet" href="../../public/css/a_components.css">
  <link rel="stylesheet" href="../../public/css/a_contents.css"> -->



  <link rel="stylesheet" href="../../public/css/general/root.css">
  <link rel="stylesheet" href="../../public/css/general/layout.css">

  <link rel="stylesheet" href="../../public/css/components/header.css">
  <link rel="stylesheet" href="../../public/css/components/sidebar.css">

  
  <?php foreach ($pageStyles as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
  <?php endforeach; ?>

</head>
<body>

  <header class="layout-header" id="layoutHeader"></header>


  <main class="layout-main" id="layoutMain">

    <nav class="layout-nav" id="layoutNav"></nav>

    <section
      class="layout-content"
      id="layoutContent"
      data-page-slug="<?= htmlspecialchars($pageSlug) ?>"
    >
      <?= $pageContent ?>
    </section>
    
  </main>

  <script src="../js/default.js?v=20260311"></script>

  <?php foreach ($pageScripts as $src): ?>
    <script src="<?= htmlspecialchars($src) ?>"></script>
  <?php endforeach; ?>

  <script>
    init({
      headerUrl: '../inc/header.php',
      navUrl:    '<?= htmlspecialchars($navUrl) ?>'
    });
  </script>

</body>
</html>