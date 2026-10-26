<?php
// $pageTitle should be set by the including page before this file is required.
if (!isset($pageTitle)) $pageTitle = 'Allied Metals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — Allied Metals</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<script>
try {
    if (localStorage.getItem('allied-metals-theme') === 'dark') {
        document.documentElement.dataset.theme = 'dark';
    }
} catch (error) {
    console.warn('Could not read the saved theme preference.', error);
}
</script>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES, 'UTF-8') ?>">
