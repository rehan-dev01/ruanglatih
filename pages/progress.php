<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Progress Belajar';

$total   = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid")->fetch_assoc()['c'];
$avg     = round((float)$db->query("SELECT COALESCE(AVG(score),0) as a FROM quiz_results WHERE user_id=$uid")->fetch_assoc()['a'], 1);
$best    = (int)$db->query("SELECT COALESCE(MAX(score),0) as m FROM quiz_results WHERE user_id=$uid")->fetch_assoc()['m'];
$perfect = (int)$db->query("SELECT COUNT(*) as c FROM quiz_results WHERE user_id=$uid AND score=100")->fetch_assoc()['c'];

$labels = []; $scores = [];
for ($i = 27; $i >= 0; $i -= 3) {
    $d       = date('Y-m-d', strtotime("-{$i} days"));
    $r       = $db->query("SELECT COALESCE(AVG(score),0) as a FROM quiz_results WHERE user_id=$uid AND DATE(completed_at)='$d'")->fetch_assoc();
    $labels[] = date('d/m', strtotime($d));
    $scores[] = round((float)$r['a']);
}

$catStats = $db->query("SELECT c.name, c.icon, COUNT(qr.id) as att, COALESCE(AVG(qr.score),0) as avg
    FROM quiz_results qr
    JOIN quizzes q    ON qr.quiz_id    = q.id
    JOIN materials m  ON q.material_id = m.id
    JOIN categories c ON m.category_id = c.id
    WHERE qr.user_id=$uid GROUP BY c.id ORDER BY avg DESC");

$results = $db->query("SELECT qr.*, q.title as qt, c.name as cn
    FROM quiz_results qr
    JOIN quizzes q    ON qr.quiz_id    = q.id
    JOIN materials m  ON q.material_id = m.id
    JOIN categories c ON m.category_id = c.id
    WHERE qr.user_id=$uid ORDER BY qr.completed_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner" style="margin-bottom:20px">
    <div class="pb-label">Statistik</div>
    <div class="pb-title">Progress Belajar</div>
    <div class="pb-sub">
        Pantau perkembangan belajarmu dari waktu ke waktu
        <?= $user['kelas'] ? ' &nbsp;&middot;&nbsp; Kelas ' . htmlspecialchars($user['kelas']) : '' ?>
    </div>
</div>

<!-- Stats: angka + label teks saja, tanpa ikon -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:20px">
    <?php foreach ([
        [$total,   'Total Quiz',    '#1e3055'],
        [$avg,     'Rata-rata',     '#059669'],
        [$best,    'Nilai Terbaik', '#d97706'],
        [$perfect, 'Nilai 100',     '#7c3aed'],
    ] as [$v, $l, $c]): ?>
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:26px;font-weight:900;color:<?= $c ?>;line-height:1"><?= $v ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:16px;margin-bottom:20px">
    <!-- Grafik -->
    <div class="card card-pad">
        <div style="font-weight:700;font-size:15px;color:var(--navy);margin-bottom:4px">Grafik Nilai 30 Hari</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:16px">Rata-rata nilai per 3 hari</div>
        <canvas id="lineChart" height="160"></canvas>
    </div>

    <!-- Performa per Kategori -->
    <div class="card card-pad">
        <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:14px">Performa per Kategori</div>
        <?php if ($catStats->num_rows === 0): ?>
        <div style="text-align:center;color:var(--muted);font-size:13px;padding:20px 0">Belum ada data</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px">
        <?php while ($cs = $catStats->fetch_assoc()):
            $pct = min(100, round($cs['avg']));
            $bc  = $pct >= 80 ? '#059669' : ($pct >= 60 ? '#d97706' : '#dc2626'); ?>
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                    <!-- Nama kategori saja, tanpa ikon -->
                    <span style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($cs['name']) ?></span>
                    <span style="font-weight:800;font-size:13px;color:var(--navy)"><?= round($cs['avg'], 1) ?></span>
                </div>
                <div class="prog-wrap" style="height:7px">
                    <div class="prog-bar" style="width:<?= $pct ?>%;background:<?= $bc ?>"></div>
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:3px"><?= $cs['att'] ?> kali dikerjakan</div>
            </div>
        <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Riwayat Quiz -->
<div class="card" style="overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <div style="font-weight:700;font-size:14px;color:var(--navy)">Riwayat Quiz</div>
        <span style="font-size:11px;color:var(--muted)"><?= $total ?> entri</span>
    </div>

    <?php if ($results->num_rows === 0): ?>
    <div style="text-align:center;padding:48px 20px;color:var(--muted)">
        <div style="font-weight:600;font-size:14px;margin-bottom:12px">Belum ada riwayat quiz</div>
        <a href="quiz.php" class="btn btn-navy btn-sm" style="display:inline-flex">Mulai Quiz &rarr;</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Quiz</th>
                <th>Kategori</th>
                <th style="text-align:center">Nilai</th>
                <th style="text-align:center">Grade</th>
                <th style="text-align:right">Tanggal</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($r = $results->fetch_assoc()):
            $g  = getGrade((float)$r['score']);
            $gc = 'grade-' . $g['label']; ?>
        <tr>
            <td style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($r['qt']) ?></td>
            <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($r['cn']) ?></td>
            <td style="text-align:center">
                <span style="font-weight:800;font-size:16px;color:var(--navy)"><?= $r['score'] ?></span>
                <span style="color:var(--muted);font-size:11px">/100</span>
            </td>
            <td style="text-align:center"><span class="grade <?= $gc ?>"><?= $g['label'] ?></span></td>
            <td style="text-align:right;color:var(--muted);font-size:11px"><?= date('d M Y', strtotime($r['completed_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<script>
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Nilai', data: <?= json_encode($scores) ?>,
            borderColor: '#1e3055', backgroundColor: 'rgba(30,48,85,.06)',
            borderWidth: 2.5, tension: 0.4, fill: true,
            pointBackgroundColor: '#f59e0b', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8', font: { size: 10 }, stepSize: 25 } },
            x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 0 } }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
