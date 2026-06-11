<?php $user = getCurrentUser(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle).' — '.APP_NAME : APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.40.0/tabler-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
<?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>
<div class="app-wrap">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-area">

<!-- Topbar -->
<header class="topbar">
    <div class="topbar-left">
        <button class="topbar-menu-btn" onclick="toggleSidebar()">
            <i class="ti ti-menu-2"></i>
        </button>
        <div class="topbar-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : APP_NAME ?></div>
    </div>
    <div class="topbar-right">
        <?php if ($user): ?>
        <!-- Streak — teks saja tanpa ikon -->
        <div class="topbar-badge topbar-streak">
            Streak: <strong><?= (int)$user['streak'] ?></strong> hari
        </div>
        <!-- Kelas — teks saja tanpa ikon -->
        <?php if (!empty($user['kelas'])): ?>
        <div class="topbar-badge" style="background:var(--navy-pale);color:var(--navy);border:1px solid #c7d2e8">
            Kelas: <strong><?= htmlspecialchars($user['kelas']) ?></strong>
        </div>
        <?php endif; ?>
        <!-- Poin — teks saja tanpa ikon -->
        <div class="topbar-badge topbar-pts">
            Poin: <strong><?= number_format((int)$user['total_points']) ?></strong>
        </div>
        <div class="topbar-avatar" title="<?= htmlspecialchars($user['name']) ?>">
            <?= getInitials($user['name']) ?>
        </div>
        <?php endif; ?>
    </div>
</header>

<?php if (!empty($_GET['err'])): ?>
<div class="alert alert-error" style="margin:16px 24px 0">
    <?= htmlspecialchars($_GET['err']) ?>
</div>
<?php endif; ?>

<main class="page-content">
