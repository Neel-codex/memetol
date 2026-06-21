<?php
/**
 * Shared <head> + opening layout. Expects optional $pageTitle, $pageDesc, $bodyClass.
 */
$siteName = Settings::get('site_name', 'MemeRadar AI');
$title    = isset($pageTitle) ? ($pageTitle . ' | ' . $siteName) : Settings::get('seo_title', $siteName);
$desc     = $pageDesc ?? Settings::get('seo_description', '');
$keywords = Settings::get('seo_keywords', '');
$primary  = Settings::get('theme_primary', '#00ff99');
$secondary= Settings::get('theme_secondary', '#00c3ff');
$accent   = Settings::get('theme_accent', '#ffcc00');
$logo     = Settings::get('logo', '');
$favicon  = Settings::get('favicon', '');
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="keywords" content="<?= e($keywords) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= e(base_url(ltrim($_SERVER['REQUEST_URI'] ?? '', '/'))) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:url" content="<?= e(base_url(ltrim($_SERVER['REQUEST_URI'] ?? '', '/'))) ?>">
    <?php if ($logo): ?><meta property="og:image" content="<?= e($logo) ?>"><?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($desc) ?>">

    <!-- Schema.org -->
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"WebApplication","name":<?= json_encode($siteName) ?>,"applicationCategory":"FinanceApplication","operatingSystem":"Web","description":<?= json_encode($desc) ?>,"offers":{"@type":"Offer","price":"0","priceCurrency":"USD"}}
    </script>

    <?php if ($favicon): ?>
        <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
        <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><circle cx="16" cy="16" r="14" fill="#0f1117" stroke="#00ff99" stroke-width="2"/><circle cx="16" cy="16" r="4" fill="#00ff99"/></svg>') ?>">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
    <style>
        :root{
            --mr-primary: <?= e($primary) ?>;
            --mr-secondary: <?= e($secondary) ?>;
            --mr-accent: <?= e($accent) ?>;
        }
    </style>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
