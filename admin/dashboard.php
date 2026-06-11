<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$user = getCurrentUser(); $db = getDB(); $pageTitle = 'Dashboard Admin';

$tUsers      = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$tBelumKelas = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role='user' AND (kelas IS NULL OR kelas='')")->fetch_assoc()['c'];
$tMat        = (int)$db->query("SELECT COUNT(*) as c FROM materials")->fetch_assoc()['c'];
$tQuiz       = (int)$db->query("SELECT COUNT(*) as c FROM quizzes")->fetch_assoc()['c'];
$tAtt        = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results")->fetch_assoc()['c'];
$avgP        = round((float)$db->query("SELECT COALESCE(AVG(score),0) as a FROM quiz_results")->fetch_assoc()['a'], 1);

$dlabels = []; $dcounts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dlabels[] = date('D', strtotime($d));
    $dcounts[] = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE DATE(completed_at)='$d'")->fetch_assoc()['c'];
}

$rUsers = $db->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 6");
$rRes   = $db->query("SELECT qr.*,u.name as un,q.title as qt FROM quiz_results qr JOIN users u ON qr.user_id=u.id JOIN quizzes q ON qr.quiz_id=q.id ORDER BY qr.completed_at DESC LIMIT 7");
$kelasStat = $db->query("SELECT u.kelas, COUNT(u.id) as jml, COALESCE(AVG(qr.score),0) as avg_score FROM users u LEFT JOIN quiz_results qr ON u.id=qr.user_id WHERE u.role='user' AND u.kelas IS NOT NULL AND u.kelas!='' GROUP BY u.kelas ORDER BY u.kelas");

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner" style="margin-bottom:20px">
    <div class="pb-label">Panel Administrator</div>
    <div class="pb-title">Selamat datang, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</div>
    <div class="pb-sub">Kelola konten platform dan pantau aktivitas belajar siswa</div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ([
        [$tUsers,       'Total Siswa',   '#3b82f6'],
        [$tMat,         'Total Materi',  '#7c3aed'],
        [$tQuiz,        'Total Quiz',    '#059669'],
        [$tAtt,         'Pengerjaan',    '#ea580c'],
        [$avgP,         'Rata-rata',     '#d97706'],
        [$tBelumKelas,  'Belum Kelas',   '#dc2626'],
    ] as [$v, $l, $c]): ?>
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:24px;font-weight:900;color:<?= $c ?>;line-height:1"><?= $v ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:6px"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
<!-- Chart -->
<div class="card card-pad">
    <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:14px">Aktivitas 7 Hari</div>
    <canvas id="actChart" height="180"></canvas>
</div>

<!-- Siswa terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">Siswa Terbaru</div>
    <?php while ($u = $rUsers->fetch_assoc()): ?>
    <div style="padding:10px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div>
            <div style="font-size:12px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($u['name']) ?></div>
            <div style="font-size:10px;color:var(--muted)"><?= $u['kelas'] ? htmlspecialchars($u['kelas']) : 'Belum ada kelas' ?></div>
        </div>
        <span style="font-size:10px;color:var(--muted)"><?= timeAgo($u['created_at']) ?></span>
    </div>
    <?php endwhile; ?>
    <div style="padding:10px 18px">
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline btn-full" style="font-size:12px">Lihat Semua</a>
    </div>
</div>
</div>

<!-- Stats per Kelas -->
<?php if ($kelasStat && $kelasStat->num_rows > 0): ?>
<div class="card" style="overflow:hidden;margin-bottom:20px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">Ringkasan per Kelas</div>
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead><tr><th>Kelas</th><th style="text-align:center">Jumlah Siswa</th><th style="text-align:center">Rata-rata Nilai</th></tr></thead>
        <tbody>
        <?php while ($ks = $kelasStat->fetch_assoc()):
            $g = getGrade((float)$ks['avg_score']); ?>
        <tr>
            <td><span style="background:var(--navy-pale);color:var(--navy);font-size:12px;font-weight:700;padding:4px 12px;border-radius:6px"><?= htmlspecialchars($ks['kelas']) ?></span></td>
            <td style="text-align:center;font-weight:700"><?= $ks['jml'] ?> siswa</td>
            <td style="text-align:center;font-weight:800;color:<?= $g['color'] ?>"><?= round($ks['avg_score'],1) ?> (<?= $g['label'] ?>)</td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- Aktivitas Terbaru -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">Aktivitas Terbaru</div>
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead><tr><th>Siswa</th><th>Quiz</th><th style="text-align:center">Nilai</th><th>Waktu</th></tr></thead>
        <tbody>
        <?php while ($r = $rRes->fetch_assoc()): $g = getGrade((float)$r['score']); ?>
        <tr>
            <td style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($r['un']) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['qt']) ?></td>
            <td style="text-align:center;font-weight:800;color:<?= $g['color'] ?>"><?= $r['score'] ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= timeAgo($r['completed_at']) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
new Chart(document.getElementById('actChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($dlabels) ?>,
        datasets: [{ label:'Pengerjaan Quiz', data:<?= json_encode($dcounts) ?>, backgroundColor:'rgba(30,48,85,.75)', borderRadius:6, borderSkipped:false }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
