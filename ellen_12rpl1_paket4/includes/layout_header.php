<?php
$pageTitle = $pageTitle ?? 'RuangBaca';
$pageDescription = $pageDescription ?? '';
require_once __DIR__ . '/../config/app.php';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - RuangBaca</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/images/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#konten">Lewati ke konten</a>

<div class="announce" aria-label="Pengumuman">
    <div class="announce-track">
        <?php for ($i = 0; $i < 2; $i++): ?>
        <div class="announce-group" <?= $i === 1 ? 'aria-hidden="true"' : '' ?>>
            <?php for ($j = 0; $j < 4; $j++): ?>
                <span>✦ Pinjam buku, kembalikan tepat waktu — Rp<?= number_format(DENDA_PER_BUKU_PER_HARI, 0, ',', '.') ?>/buku/hari untuk keterlambatan</span>
            <?php endfor; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="site-header-inner">
        <button class="menu-btn" type="button" onclick="toggleMenu()" aria-label="Buka menu" aria-expanded="false" aria-controls="mainNav">
            <span></span><span></span><span></span>
        </button>

        <a class="brand-logo" href="<?= e(url('dashboard/dashboard.php')) ?>">
            <img class="brand-img" src="<?= e(url('assets/images/logo.png')) ?>" alt="Logo RuangBaca">
            <span class="brand-text">
                <strong>RuangBaca</strong>
                <small>Portal Koleksi Sekolah</small>
            </span>
        </a>

        <?php include __DIR__ . '/sidebar.php'; ?>
    </div>
</header>

<main class="main" id="konten">
    <div class="page-banner">
        <div class="topbar-title">
            <h2><?= e($pageTitle) ?></h2>
            <?php if ($pageDescription): ?><p class="muted"><?= e($pageDescription) ?></p><?php endif; ?>
        </div>
    </div>
