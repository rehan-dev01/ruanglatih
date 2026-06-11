<?php
$cp   = basename($_SERVER['PHP_SELF'], '.php');
$dir  = basename(dirname($_SERVER['PHP_SELF']));
$user = getCurrentUser();

function sbItem($href, $label, $active = false, $badge = '') {
    $cls = 'sb-item' . ($active ? ' active' : '');
    $bdg = $badge ? "<span class='sb-item-new'>$badge</span>" : '';
    echo "<a href='{$href}' class='{$cls}'><span>{$label}</span>{$bdg}</a>";
}

$root    = APP_URL;
$isDash  = ($cp === 'dashboard' && $dir !== 'admin' && $dir !== 'guru');
$isMat   = in_array($cp, ['materi','materi-detail']) && $dir !== 'admin' && $dir !== 'guru';
$isQuiz  = in_array($cp, ['quiz','quiz-play','quiz-result']) && $dir !== 'admin' && $dir !== 'guru';
$isFlash = ($cp === 'flashcard') && $dir !== 'admin' && $dir !== 'guru';
$isProg  = ($cp === 'progress');
$isLb    = ($cp === 'leaderboard');
// Admin pages
$isAdmD  = ($cp === 'dashboard'  && $dir === 'admin');
$isAdmM  = ($cp === 'materi'     && $dir === 'admin');
$isAdmQ  = ($cp === 'quiz'       && $dir === 'admin');
$isAdmU  = ($cp === 'users'      && $dir === 'admin');
$isAdmF  = ($cp === 'flashcard'  && $dir === 'admin');
$isAdmK  = ($cp === 'kelas'      && $dir === 'admin');
// Guru pages
$isGuruD = ($cp === 'dashboard'   && $dir === 'guru');
$isGuruM = ($cp === 'materi'      && $dir === 'guru');
$isGuruQ = ($cp === 'quiz'        && $dir === 'guru');
$isGuruF = ($cp === 'flashcard'   && $dir === 'guru');
$isGuruR = ($cp === 'rekap-nilai' && $dir === 'guru');
$isGuruP = ($cp === 'pengumuman'  && $dir === 'guru');
?>

<div id="sidebarOverlay" class="sb-overlay" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="sidebar <?= isAdmin() ? 'sidebar-admin' : (isGuru() ? 'sidebar-guru' : '') ?>">
    <!-- Logo -->
    <div class="sb-logo">
        <div>
            <div class="sb-logo-name">RuangLatih</div>
            <div class="sb-logo-sub">Platform Belajar Interaktif</div>
        </div>
        <button class="sb-close-btn" onclick="toggleSidebar()">
            <i class="ti ti-x"></i>
        </button>
    </div>

    <?php if (!isAdmin() && !isGuru() && $user): ?>
    <!-- Info Siswa -->
    <div style="padding:10px 18px;margin-bottom:4px">
        <div style="background:var(--navy-pale);border-radius:10px;padding:10px 14px">
            <div style="font-size:13px;font-weight:700;color:var(--navy)"><?= htmlspecialchars($user['name']) ?></div>
            <?php if (!empty($user['kelas'])): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">Kelas: <?= htmlspecialchars($user['kelas']) ?></div>
            <?php endif; ?>
            <?php if (!empty($user['nisn'])): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:1px">NISN: <?= htmlspecialchars($user['nisn']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (isGuru() && $user): ?>
    <!-- Info Guru -->
    <div style="padding:10px 18px;margin-bottom:4px">
        <div style="background:rgba(16,185,129,.1);border-radius:10px;padding:10px 14px;border:1px solid rgba(16,185,129,.2)">
            <div style="font-size:13px;font-weight:700;color:#065f46"><?= htmlspecialchars($user['name']) ?></div>
            <div style="font-size:11px;color:#047857;margin-top:2px">Pengajar</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Menu Utama -->
    <div class="sb-section-label">Menu Utama</div>
    <nav class="sb-nav">
        <?php sbItem("$root/dashboard.php",           'Dashboard',       $isDash) ?>
        <?php sbItem("$root/pages/materi.php",        'Materi Belajar',  $isMat ) ?>
        <?php sbItem("$root/pages/quiz.php",          'Quiz',            $isQuiz) ?>
        <?php sbItem("$root/pages/flashcard.php",     'Flashcard',       $isFlash) ?>
    </nav>

    <div class="sb-divider"></div>

    <!-- Statistik -->
    <div class="sb-section-label">Statistik</div>
    <nav class="sb-nav">
        <?php sbItem("$root/pages/progress.php",    'Progress Saya', $isProg) ?>
        <?php sbItem("$root/pages/leaderboard.php", 'Leaderboard',   $isLb  ) ?>
    </nav>

    <?php if (isGuruOrAdmin()): ?>
    <div class="sb-divider"></div>
    <div class="sb-section-label" style="color:#047857">Panel Guru</div>
    <nav class="sb-nav">
        <?php sbItem("$root/guru/dashboard.php",   'Dashboard Guru',   $isGuruD) ?>
        <?php sbItem("$root/guru/materi.php",      'Kelola Materi',    $isGuruM) ?>
        <?php sbItem("$root/guru/quiz.php",        'Kelola Quiz',      $isGuruQ) ?>
        <?php sbItem("$root/guru/flashcard.php",   'Kelola Flashcard', $isGuruF) ?>
        <?php sbItem("$root/guru/rekap-nilai.php", 'Rekap Nilai',      $isGuruR) ?>
        <?php sbItem("$root/guru/pengumuman.php",  'Pengumuman',       $isGuruP) ?>
    </nav>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <div class="sb-divider"></div>
    <div class="sb-section-label">Admin Panel</div>
    <nav class="sb-nav">
        <?php sbItem("$root/admin/dashboard.php",  'Dashboard Admin',  $isAdmD) ?>
        <?php sbItem("$root/admin/materi.php",     'Kelola Materi',    $isAdmM) ?>
        <?php sbItem("$root/admin/quiz.php",       'Kelola Quiz',      $isAdmQ) ?>
        <?php sbItem("$root/admin/flashcard.php",  'Kelola Flashcard', $isAdmF) ?>
        <?php sbItem("$root/admin/users.php",      'Data Pengguna',    $isAdmU) ?>
        <?php sbItem("$root/admin/kelas.php",      'Kelola Kelas',     $isAdmK) ?>
    </nav>
    <?php endif; ?>

    <div class="sb-divider"></div>
    <nav class="sb-nav">
        <a href="<?= $root ?>/auth/logout.php" class="sb-item sb-item-logout">Keluar</a>
    </nav>
</aside>
