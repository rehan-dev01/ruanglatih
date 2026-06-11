<?php
require_once __DIR__ . '/../includes/functions.php';
requireGuruOrAdmin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Rekap Nilai Siswa';

$matCond   = getGuruMaterialCondition('m');
$kelasCond = getGuruKelasCondition('u');

// Kelas yang diajar (untuk filter)
if (isAdmin()) {
    $kelasRes = $db->query("SELECT DISTINCT kelas FROM users WHERE role='user' AND kelas IS NOT NULL AND kelas!='' ORDER BY kelas");
    $kelasList = [];
    while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];
} else {
    $assignments = getGuruAssignments($uid);
    $kelasList   = array_unique(array_column($assignments, 'kelas'));
    sort($kelasList);
}

// Quiz yang bisa dilihat guru
$quizRes = $db->query("
    SELECT q.id, q.title, m.title AS mt, m.kelas AS mkelas
    FROM quizzes q JOIN materials m ON q.material_id=m.id
    WHERE $matCond ORDER BY m.kelas, m.title, q.title
");
$quizArr = [];
while ($q = $quizRes->fetch_assoc()) $quizArr[] = $q;

// Filter params
$fKelas = trim($_GET['kelas'] ?? '');
$fQuiz  = (int)($_GET['quiz_id'] ?? 0);
$fName  = trim($_GET['q'] ?? '');

// Build WHERE
$where = "WHERE u.role='user' AND $kelasCond";
if ($fKelas) $where .= " AND u.kelas='" . $db->real_escape_string($fKelas) . "'";
if ($fName)  $where .= " AND u.name LIKE '%" . $db->real_escape_string($fName) . "%'";

// Jika filter quiz spesifik
if ($fQuiz) {
    $results = $db->query("
        SELECT u.id, u.name, u.kelas, u.no_absen,
               qr.score, qr.total_questions, qr.completed_at,
               q.title AS quiz_title, q.duration_minutes
        FROM quiz_results qr
        JOIN users u ON qr.user_id = u.id
        JOIN quizzes q ON qr.quiz_id = q.id
        JOIN materials m ON q.material_id = m.id
        $where AND qr.quiz_id = $fQuiz AND $matCond
        ORDER BY u.kelas, u.no_absen, u.name, qr.completed_at DESC
    ");
} else {
    // Ringkasan per siswa
    $results = $db->query("
        SELECT u.id, u.name, u.kelas, u.no_absen,
               COUNT(DISTINCT qr.id)          AS total_attempts,
               COALESCE(AVG(qr.score), 0)     AS avg_score,
               COALESCE(MAX(qr.score), 0)     AS best_score,
               MAX(qr.completed_at)           AS last_active
        FROM users u
        LEFT JOIN quiz_results qr ON qr.user_id = u.id
            AND qr.quiz_id IN (
                SELECT q2.id FROM quizzes q2
                JOIN materials m ON q2.material_id = m.id
                WHERE $matCond
            )
        $where
        GROUP BY u.id
        ORDER BY u.kelas, u.no_absen, u.name
    ");
}

// Stats ringkas
$totalSiswa = 0; $avgAll = 0; $rows = [];
if ($results) {
    while ($r = $results->fetch_assoc()) { $rows[] = $r; }
    $totalSiswa = count($rows);
    if ($fQuiz && $totalSiswa > 0) {
        $avgAll = round(array_sum(array_column($rows, 'score')) / $totalSiswa, 1);
    } elseif (!$fQuiz && $totalSiswa > 0) {
        $avgAll = round(array_sum(array_column($rows, 'avg_score')) / $totalSiswa, 1);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div style="font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px">Rekap Nilai Siswa</div>
<div style="font-size:13px;color:var(--muted);margin-bottom:18px"><?= isAdmin() ? 'Semua siswa' : 'Siswa di kelas yang Anda ajar' ?></div>

<!-- Filter -->
<div class="card card-pad" style="margin-bottom:16px">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="min-width:140px">
        <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Kelas</div>
        <select name="kelas" class="form-input form-select">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $k): ?>
            <option value="<?= htmlspecialchars($k) ?>" <?= $fKelas===$k?'selected':'' ?>><?= htmlspecialchars($k) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="min-width:220px">
        <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Quiz Spesifik</div>
        <select name="quiz_id" class="form-input form-select">
            <option value="">Ringkasan Semua Quiz</option>
            <?php foreach ($quizArr as $q): ?>
            <option value="<?= $q['id'] ?>" <?= $fQuiz===$q['id']?'selected':'' ?>><?= htmlspecialchars($q['title']) ?> (<?= htmlspecialchars($q['mkelas']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="flex:1;min-width:160px">
        <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Cari Siswa</div>
        <input type="text" name="q" class="form-input" placeholder="Nama siswa..." value="<?= htmlspecialchars($fName) ?>">
    </div>
    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-navy">Tampilkan</button>
        <?php if ($fKelas || $fQuiz || $fName): ?>
        <a href="rekap-nilai.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
    </div>
</form>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:16px">
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:24px;font-weight:900;color:#047857"><?= $totalSiswa ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Siswa Ditemukan</div>
    </div>
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:24px;font-weight:900;color:var(--navy)"><?= count($kelasList) ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Kelas Diajar</div>
    </div>
    <div class="card card-pad" style="text-align:center">
        <?php $g = getGrade($avgAll); ?>
        <div style="font-size:24px;font-weight:900;color:<?= $g['color'] ?>"><?= round($avgAll,1) ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Rata-rata Nilai</div>
    </div>
    <div class="card card-pad" style="text-align:center">
        <div style="font-size:24px;font-weight:900;color:#d97706"><?= count($quizArr) ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Total Quiz</div>
    </div>
</div>

<!-- Tabel -->
<div class="card" style="overflow:hidden">
<div style="overflow-x:auto">
<table class="data-table">
    <thead>
        <tr>
            <th>Siswa</th>
            <th style="text-align:center">Kelas</th>
            <?php if ($fQuiz): ?>
            <th style="text-align:center">Nilai</th>
            <th style="text-align:center">Soal</th>
            <th style="text-align:center">Waktu</th>
            <?php else: ?>
            <th style="text-align:center">Total Pengerjaan</th>
            <th style="text-align:center">Rata-rata</th>
            <th style="text-align:center">Terbaik</th>
            <th style="text-align:center">Terakhir Aktif</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
    <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">
        <div style="font-weight:600">Tidak ada data ditemukan</div>
    </td></tr>
    <?php else: foreach ($rows as $r):
        $avg  = $fQuiz ? (float)$r['score'] : round((float)$r['avg_score'], 1);
        $best = $fQuiz ? (float)$r['score'] : (float)$r['best_score'];
        $g    = getGrade($avg);
    ?>
    <tr>
        <td>
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#047857,#059669);color:#fff;font-weight:700;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= getInitials($r['name']) ?></div>
                <div>
                    <div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($r['name']) ?></div>
                    <?php if ($r['no_absen']): ?><div style="font-size:11px;color:var(--muted)">No. <?= $r['no_absen'] ?></div><?php endif; ?>
                </div>
            </div>
        </td>
        <td style="text-align:center">
            <span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:700;padding:3px 9px;border-radius:6px"><?= htmlspecialchars($r['kelas'] ?? '—') ?></span>
        </td>
        <?php if ($fQuiz): ?>
        <td style="text-align:center">
            <span style="font-weight:800;font-size:16px;color:<?= $g['color'] ?>"><?= $r['score'] ?></span>
            <span style="font-weight:600;font-size:11px;color:<?= $g['color'] ?>">(<?= $g['label'] ?>)</span>
        </td>
        <td style="text-align:center;font-size:12px"><?= $r['total_questions'] ?></td>
        <td style="text-align:center;font-size:12px;color:var(--muted)"><?= timeAgo($r['completed_at']) ?></td>
        <?php else: ?>
        <td style="text-align:center;font-weight:700"><?= (int)$r['total_attempts'] ?></td>
        <td style="text-align:center">
            <span style="font-weight:800;font-size:15px;color:<?= $g['color'] ?>"><?= round($avg,1) ?> <span style="font-size:11px">(<?= $g['label'] ?>)</span></span>
        </td>
        <td style="text-align:center;font-weight:700;color:#047857"><?= round($best) ?></td>
        <td style="text-align:center;font-size:12px;color:var(--muted)"><?= $r['last_active'] ? timeAgo($r['last_active']) : '—' ?></td>
        <?php endif; ?>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
