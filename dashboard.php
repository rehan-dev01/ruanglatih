<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Dashboard';

// Redirect guru dan admin ke dashboard khusus mereka
if (isAdmin())   { header('Location: '.APP_URL.'/admin/dashboard.php');  exit; }
if (isGuru())    { header('Location: '.APP_URL.'/guru/dashboard.php');   exit; }

$totalQuiz = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid")->fetch_assoc()['c'];
$avgScore  = round((float)$db->query("SELECT COALESCE(AVG(score),0) as a FROM quiz_results WHERE user_id=$uid")->fetch_assoc()['a'], 1);
$bestScore = (int)$db->query("SELECT COALESCE(MAX(score),0) as m FROM quiz_results WHERE user_id=$uid")->fetch_assoc()['m'];

$recentRes = $db->query("
    SELECT qr.*, q.title as qt, c.name as cn
    FROM quiz_results qr
    JOIN quizzes q ON qr.quiz_id=q.id
    JOIN materials m ON q.material_id=m.id
    JOIN categories c ON m.category_id=c.id
    WHERE qr.user_id=$uid ORDER BY qr.completed_at DESC LIMIT 4
");

$kf = getKelasFilter($user['kelas'] ?? '', 'm.kelas');
$latestMat = $db->query("
    SELECT m.*, c.name as cn
    FROM materials m JOIN categories c ON m.category_id=c.id
    WHERE $kf ORDER BY m.created_at DESC LIMIT 5
");

// Pengumuman untuk siswa ini (berdasarkan kelas)
$userKelas = $db->real_escape_string($user['kelas'] ?? '');
$annQuery  = $userKelas
    ? "WHERE a.target_kelas = 'Semua' OR a.target_kelas = '$userKelas'"
    : "WHERE a.target_kelas = 'Semua'";
$announcements = $db->query("
    SELECT a.*, u.name AS guru_name
    FROM announcements a JOIN users u ON a.guru_id = u.id
    $annQuery
    ORDER BY a.created_at DESC LIMIT 5
");

$chartLabels = []; $chartScores = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('D', strtotime($d));
    $r = $db->query("SELECT COALESCE(AVG(score),0) as a FROM quiz_results WHERE user_id=$uid AND DATE(completed_at)='$d'")->fetch_assoc();
    $chartScores[] = round((float)$r['a'], 1);
}

include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<div class="page-banner" style="margin-bottom:20px">
    <div class="pb-label">Selamat datang kembali</div>
    <div class="pb-title">Halo, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</div>
    <div class="pb-sub">
        <?php if ($user['kelas']): ?>Kelas <?= htmlspecialchars($user['kelas']) ?> &nbsp;&middot;&nbsp; <?php endif; ?>
        Streak <?= $user['streak'] ?> hari &nbsp;&middot;&nbsp; <?= $totalQuiz ?> quiz dikerjakan
    </div>
    <div class="pb-btns">
        <a href="<?= APP_URL ?>/pages/quiz.php"      class="btn btn-gold">Mulai Quiz</a>
        <a href="<?= APP_URL ?>/pages/materi.php"    class="btn btn-white">Baca Materi</a>
        <a href="<?= APP_URL ?>/pages/flashcard.php" class="btn btn-white">Flashcard</a>
    </div>
</div>

<!-- Quick Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ([
        [$totalQuiz,       'Quiz Dikerjakan', '#1e3055'],
        [$avgScore,        'Rata-rata Nilai', '#059669'],
        [$bestScore,       'Nilai Terbaik',   '#d97706'],
        [$user['streak'],  'Hari Streak',     '#ea580c'],
    ] as [$v, $l, $c]): ?>
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:28px;font-weight:900;color:<?= $c ?>;line-height:1"><?= $v ?></div>
        <div style="font-size:12px;font-weight:600;color:var(--muted);margin-top:6px"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
<!-- Chart -->
<div class="card card-pad">
    <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:14px">Nilai 7 Hari Terakhir</div>
    <canvas id="dashChart" height="180"></canvas>
</div>

<!-- Menu Belajar -->
<div class="card card-pad">
    <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:12px">Menu Belajar</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <?php foreach ([
            ['quiz.php',        'Quiz',       'Soal & jawaban',   '#eef2ff','#4f46e5'],
            ['materi.php',      'Materi',     'Baca & pelajari',  '#f0fdf4','#15803d'],
            ['flashcard.php',   'Flashcard',  'Hafal cepat',      '#fffbeb','#d97706'],
            ['leaderboard.php', 'Peringkat',  'Lihat ranking',    '#fef3c7','#b45309'],
        ] as [$href, $label, $sub, $bg, $col]): ?>
        <a href="<?= APP_URL ?>/pages/<?= $href ?>" style="text-decoration:none">
        <div class="card-hover" style="background:<?= $bg ?>;border-radius:12px;padding:14px;border:1px solid <?= $col ?>22">
            <div style="font-weight:700;font-size:13px;color:<?= $col ?>;margin-bottom:4px"><?= $label ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= $sub ?></div>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<!-- Materi terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">
        Materi Terbaru<?= $user['kelas'] ? ' — ' . htmlspecialchars($user['kelas']) : '' ?>
    </div>
    <?php if ($latestMat->num_rows === 0): ?>
    <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Belum ada materi untuk kelas ini</div>
    <?php else: while ($m = $latestMat->fetch_assoc()): ?>
    <a href="<?= APP_URL ?>/pages/materi-detail.php?id=<?= $m['id'] ?>"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--border);text-decoration:none;transition:background .15s"
        onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <div>
            <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($m['title']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($m['cn']) ?></div>
        </div>
        <span style="font-size:12px;color:var(--muted)">&rarr;</span>
    </a>
    <?php endwhile; endif; ?>
</div>

<!-- Hasil quiz terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">
        Hasil Quiz Terbaru
    </div>
    <?php if ($recentRes->num_rows === 0): ?>
    <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Belum ada quiz yang dikerjakan</div>
    <?php else: while ($r = $recentRes->fetch_assoc()):
        $g = getGrade((float)$r['score']); ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--border)">
        <div>
            <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($r['qt']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= timeAgo($r['completed_at']) ?></div>
        </div>
        <span style="font-weight:800;font-size:16px;color:<?= $g['color'] ?>"><?= $r['score'] ?></span>
    </div>
    <?php endwhile; endif; ?>
</div>
</div>

<!-- ── Pengumuman dari Guru ── -->
<?php if ($announcements && $announcements->num_rows > 0): ?>
<div class="card" style="overflow:hidden;margin-top:16px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div style="font-weight:700;font-size:14px;color:var(--navy)">Pengumuman dari Guru</div>
        <span style="font-size:11px;color:var(--muted)"><?= $announcements->num_rows ?> pengumuman</span>
    </div>
    <?php while ($an = $announcements->fetch_assoc()): ?>
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);border-left:3px solid #047857">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:4px"><?= htmlspecialchars($an['title']) ?></div>
                <div style="font-size:13px;color:#374151;line-height:1.55"><?= nl2br(htmlspecialchars($an['content'])) ?></div>
            </div>
            <div style="flex-shrink:0;text-align:right">
                <div style="font-size:11px;font-weight:600;color:#047857"><?= htmlspecialchars($an['guru_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= timeAgo($an['created_at']) ?></div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<script>
new Chart(document.getElementById('dashChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Rata-rata Nilai',
            data: <?= json_encode($chartScores) ?>,
            borderColor: '#1e3055', backgroundColor: 'rgba(30,48,85,.08)',
            borderWidth: 2.5, tension: .4, fill: true,
            pointBackgroundColor: '#f59e0b', pointRadius: 4,
        }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{min:0,max:100,ticks:{stepSize:20}}} }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
