<?php
/* Usage:
   <?php require __DIR__ . '/../helpers/header.php'; ?>
   ... page content ...
   <?php require __DIR__ . '/../helpers/footer.php'; */

// ── Compute the PortManagement base path once ──
$appRoot = str_replace('\\', '/', dirname(__DIR__));  // .../PortManagement
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$basePath = rtrim(str_replace($docRoot, '', $appRoot), '/');

/* Set $extra_head before including for extra CSS/JS (e.g. Chart.js, Leaflet) */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'Port Management System') ?></title>
  <base href="<?= $basePath ?>/">
  <link rel="stylesheet" href="static/css/app.css">
  <script>window.PORT_BASE_PATH = <?= json_encode($basePath) ?>;</script>
  <script src="https://unpkg.com/htmx.org@1.9.10"></script>
  <script>(function(){function t(){const n=new Date(),d=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],m=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],o=n.getDate(),e=m[n.getMonth()],a=d[n.getDay()];let i=n.getHours();const l=i>=12?'PM':'AM';i=i%12||12;const h=String(n.getMinutes()).padStart(2,'0'),s=String(n.getSeconds()).padStart(2,'0');document.getElementById('clockDate')&&(document.getElementById('clockDate').textContent=a+', '+o+' '+e);document.getElementById('clockTime')&&(document.getElementById('clockTime').textContent=i+':'+h+':'+s+' '+l)}t();setInterval(t,1000);})();</script>
  <script>
  // Auto-fix all absolute links (starting with /) to include PortManagement base path
  (function() {
    var base = '<?= $basePath ?>';
    if (!base || base === '') return;
    // Fix <a href="/xxx">
    document.addEventListener('click', function(e) {
      var a = e.target.closest('a');
      if (!a) return;
      var href = a.getAttribute('href');
      if (href && href.startsWith('/') && !href.startsWith('//') && !href.startsWith(base) && !href.startsWith('/static')) {
        e.preventDefault();
        window.location.href = base + href;
      }
    }, false);
    // Fix <form action="/xxx">
    document.addEventListener('submit', function(e) {
      var form = e.target;
      var action = form.getAttribute('action');
      if (action && action.startsWith('/') && !action.startsWith('//') && !action.startsWith(base)) {
        form.action = base + action;
      }
    }, false);
  })();
  </script>
  <?= $extra_head ?? '' ?>
</head>
<body>
  <div class="page">
    <?php require __DIR__ . '/nav.php'; ?>
    <main class="container">
