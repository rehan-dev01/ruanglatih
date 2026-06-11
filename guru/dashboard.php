<?php
require_once __DIR__ . '/../includes/functions.php';
requireGuruOrAdmin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Dashboard Guru';

$cond = getGuruMaterialCondition('m');

// Stats
$tMat   = (int)$db->query("SELECT COUNT(*) as c FROM materials m WHERE $cond")->fetch_assoc()['c'];
$tQuiz  = (int)$db->query("SELECT COUNT(*) as c FROM quizzes q JOIN materials m ON q.material_id=m.id WHERE $cond")->fetch_assoc()['c'];
$tFlash = (int)$db->query("SELECT COUNT(*) as c FROM flashcards f JOIN materials m ON f.material_id=m.id WHERE $cond")->fetch_assoc()['c'];
$tPengumuman = (int)$db->query("SELECT COUNT(*) as c FROM announcements WHERE guru_id=$uid")->fetch_assoc()['c'];

// Kelas & assignments
$assignments = getGuruAssignments($uid);
$kelasSet    = array_unique(array_column($assignments, 'kelas'));
$tSiswa      = 0;
if ($kelasSet) {
    $inKelas = implode(',', array_map(fn($k) => "'" . $db->real_escape_string($k) . "'", $kelasSet));
    $tSiswa  = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role='user' AND kelas IN ($inKelas)")->fetch_assoc()['c'];
}

// Materi terbaru
$recentMat = $db->query("
    SELECT m.*, c.name as cn, c.icon as ci
    FROM materials m JOIN categories c ON m.category_id=c.id
    WHERE $cond
    ORDER BY m.created_at DESC LIMIT 5
");

// Quiz terbaru
$recentQuiz = $db->query("
    SELECT q.*, m.title as mt,
           (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) as qcount,
           (SELECT COUNT(*) FROM quiz_results WHERE quiz_id=q.id) as attempts
    FROM quizzes q JOIN materials m ON q.material_id=m.id
    WHERE $cond
    ORDER BY q.created_at DESC LIMIT 5
");

// Pengumuman terbaru
$recentAnn = $db->query("
    SELECT * FROM announcements WHERE guru_id=$uid ORDER BY created_at DESC LIMIT 3
");

include __DIR__ . '/../includes/header.php';
?>

<!-- Banner -->
<div class="page-banner" style="margin-bottom:20px;background:linear-gradient(135deg,#065f46 0%,#047857 50%,#059669 100%)">
    <div class="pb-label">Panel Guru</div>
    <div class="pb-title">Selamat datang, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</div>
    <div class="pb-sub">
        <?php if ($assignments): ?>
        Mengajar: <?= implode(', ', array_unique(array_column($assignments, 'cat_name'))) ?>
        &nbsp;&middot;&nbsp; <?= count($kelasSet) ?> kelas &nbsp;&middot;&nbsp; <?= $tSiswa ?> siswa
        <?php else: ?>
        Belum ada penugasan. Hubungi admin untuk mendapatkan akses materi.
        <?php endif; ?>
    </div>
    <div class="pb-btns">
        <a href="<?= APP_URL ?>/guru/materi.php"     class="btn btn-gold">Kelola Materi</a>
        <a href="<?= APP_URL ?>/guru/pengumuman.php" class="btn btn-white">Buat Pengumuman</a>
    </div>
</div>

<!-- Quick Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ([
        [$tMat,           'Total Materi'   ],
        [$tQuiz,          'Total Quiz'     ],
        [$tFlash,         'Total Flashcard'],
        [count($kelasSet),'Kelas Diajar'   ],
        [$tSiswa,         'Total Siswa'    ],
        [$tPengumuman,    'Pengumuman'     ],
    ] as [$v, $l]): ?>
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:28px;font-weight:900;color:var(--navy);line-height:1"><?= $v ?></div>
        <div style="font-size:12px;font-weight:600;color:var(--muted);margin-top:6px"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

<!-- Penugasan -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy);display:flex;align-items:center;justify-content:space-between">
        <span>Penugasan Saya</span>
        <span style="font-size:11px;color:var(--muted);font-weight:500"><?= count($assignments) ?> penugasan</span>
    </div>
    <?php if (!$assignments): ?>
    <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">
        Belum ada penugasan. Hubungi admin.
    </div>
    <?php else: ?>
    <?php foreach ($assignments as $a): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid var(--border)">
        <div style="width:8px;height:8px;border-radius:50%;background:#047857;flex-shrink:0"></div>
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--navy)"><?= htmlspecialchars($a['cat_name']) ?></div>
            <div style="font-size:11px;color:var(--muted)">Kelas <?= htmlspecialchars($a['kelas']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pengumuman terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy);display:flex;align-items:center;justify-content:space-between">
        <span>Pengumuman Terbaru</span>
        <a href="<?= APP_URL ?>/guru/pengumuman.php" style="font-size:11px;color:#047857;font-weight:600;text-decoration:none">Kelola →</a>
    </div>
    <?php if (!$recentAnn || $recentAnn->num_rows === 0): ?>
    <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">
        Belum ada pengumuman.
    </div>
    <?php else: while ($an = $recentAnn->fetch_assoc()): ?>
    <div style="padding:12px 18px;border-bottom:1px solid var(--border)">
        <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($an['title']) ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">
            <?= $an['target_kelas'] === 'Semua' ? 'Semua kelas' : 'Kelas '.$an['target_kelas'] ?>
            &nbsp;·&nbsp; <?= timeAgo($an['created_at']) ?>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- Materi terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy);display:flex;align-items:center;justify-content:space-between">
        <span>Materi Terbaru</span>
        <a href="<?= APP_URL ?>/guru/materi.php" style="font-size:11px;color:#047857;font-weight:600;text-decoration:none">Lihat Semua →</a>
    </div>
    <?php if (!$recentMat || $recentMat->num_rows === 0): ?>
    <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Belum ada materi</div>
    <?php else: while ($m = $recentMat->fetch_assoc()): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid var(--border)">
        <div style="width:8px;height:8px;border-radius:50%;background:#047857;flex-shrink:0"></div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($m['title']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($m['cn']) ?> · Kelas <?= htmlspecialchars($m['kelas']) ?></div>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<!-- Quiz terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy);display:flex;align-items:center;justify-content:space-between">
        <span>Quiz Terbaru</span>
        <a href="<?= APP_URL ?>/guru/quiz.php" style="font-size:11px;color:#047857;font-weight:600;text-decoration:none">Lihat Semua →</a>
    </div>
    <?php if (!$recentQuiz || $recentQuiz->num_rows === 0): ?>
    <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Belum ada quiz</div>
    <?php else: while ($q = $recentQuiz->fetch_assoc()): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 18px;border-bottom:1px solid var(--border)">
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($q['title']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= $q['qcount'] ?> soal &nbsp;·&nbsp; <?= $q['attempts'] ?> pengerjaan</div>
        </div>
        <span style="font-size:11px;background:var(--navy-pale);color:var(--navy);padding:3px 9px;border-radius:6px;white-space:nowrap;margin-left:8px"><?= $q['duration_minutes'] ?> menit</span>
    </div>
    <?php endwhile; endif; ?>
</div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
