<?php
// includes/header.php
// Expected vars: $pageTitle (string), $bodyClass (string, optional)
$bodyClass = $bodyClass ?? '';
?><!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($pageTitle ?? 'نظام إدارة الودائع') ?> - العسافي للاستثمارات
    </title>

    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Theme -->
    <link rel="icon" href="/assets/img/ag-logo.png?v=2" type="image/png">

    <link rel="stylesheet"
        href="/assets/css/theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/theme.css') ?>">
    <?php if (!empty($extraHead))
        echo $extraHead; ?>
    <script>
        // Apply theme early to prevent FOUC
        let currentTheme = localStorage.getItem('theme');
        if (!currentTheme) {
            // Check system preference
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                currentTheme = 'light';
            } else {
                currentTheme = 'dark'; // Default
            }
        }
        document.documentElement.setAttribute('data-theme', currentTheme);

        // Apply sidebar state immediately
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
</head>

<body class="<?= htmlspecialchars($bodyClass) ?>">