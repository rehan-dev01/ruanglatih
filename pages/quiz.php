<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Quiz';

$fKelas  = trim($_GET['kelas'] ?? '');
$fCat    = (int)($_GET['cat']  ?? 0);
$fStatus = trim($_GET['status'] ?? ''); // 'belum' | 'sudah' | ''

$where = "1=1";
if ($fKelas) $where .= " AND m.kelas='" . $db->real_escape_string($fKelas) . "'";
if ($fCat)   $where .= " AND m.category_id=$fCat";

$quizzes = $db->query("
    SELECT q.*, m.title as mt, m.kelas as mkelas, c.name as cn,
        (SELECT COUNT(*) FROM questions    WHERE quiz_id=q.id) as qc,
        (SELECT COUNT(*) FROM quiz_results WHERE quiz_id=q.id AND user_id=$uid) as attempts,
        (SELECT MAX(score) FROM quiz_results WHERE quiz_id=q.id AND user_id=$uid) as best
    FROM quizzes q
    JOIN materials m ON q.material_id=m.id
    JOIN categories c ON m.category_id=c.id
    WHERE $where
    ORDER BY
        CASE WHEN m.kelas LIKE 'XII%' THEN 3 WHEN m.kelas LIKE 'XI%' THEN 2
             WHEN m.kelas LIKE 'X%'   THEN 1 WHEN m.kelas = 'Semua' THEN 0 ELSE 9 END ASC,
        m.kelas ASC, c.name ASC, q.title ASC
");

// Filter sudah/belum di PHP (setelah query)
$allRows = []; while ($r = $quizzes->fetch_assoc()) $allRows[] = $r;
if ($fStatus === 'belum') $allRows = array_filter($allRows, fn($r) => $r['attempts'] == 0);
if ($fStatus === 'sudah') $allRows = array_filter($allRows, fn($r) => $r['attempts'] >  0);
$allRows = array_values($allRows);
$total   = count($allRows);

// Dropdown options
$kelasRes  = $db->query("SELECT DISTINCT m.kelas FROM quizzes q JOIN materials m ON q.material_id=m.id
    ORDER BY CASE WHEN m.kelas LIKE 'XII%' THEN 3 WHEN m.kelas LIKE 'XI%' THEN 2
                  WHEN m.kelas LIKE 'X%' THEN 1 ELSE 9 END, m.kelas");
$kelasOpts = [];
while ($k = $kelasRes->fetch_assoc()) $kelasOpts[] = $k['kelas'];
$cats = $db->query("SELECT DISTINCT c.id, c.name FROM quizzes q
    JOIN materials m ON q.material_id=m.id JOIN categories c ON m.category_id=c.id ORDER BY c.name");

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="pb-label">Latihan Soal</div>
    <div class="pb-title">Pilih Quiz</div>
    <div class="pb-sub">
        <?= $total ?> quiz tersedia dari semua kelas
        <?= $user['kelas'] ? ' &nbsp;&middot;&nbsp; Kelas kamu: <strong>' . htmlspecialchars($user['kelas']) . '</strong>' : '' ?>
    </div>
</div>

<!-- Filter -->
<form method="GET" class="card card-pad" style="margin-bottom:16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:140px">
            <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Kelas</div>
            <select name="kelas" class="form-input form-select">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasOpts as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $fKelas===$k?'selected':'' ?>>
                    <?= htmlspecialchars($k) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:150px">
            <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Mata Pelajaran</div>
            <select name="cat" class="form-input form-select">
                <option value="">Semua Mapel</option>
                <?php while ($c = $cats->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $fCat==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div style="flex:1;min-width:130px">
            <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Status</div>
            <select name="status" class="form-input form-select">
                <option value="">Semua</option>
                <option value="belum" <?= $fStatus==='belum'?'selected':'' ?>>Belum Dikerjakan</option>
                <option value="sudah" <?= $fStatus==='sudah'?'selected':'' ?>>Sudah Dikerjakan</option>
            </select>
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-navy">Filter</button>
            <?php if ($fKelas||$fCat||$fStatus): ?>
            <a href="quiz.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (empty($allRows)): ?>
<div class="card card-pad" style="text-align:center;padding:60px 20px">
    <div style="font-weight:700;font-size:16px;color:var(--navy)">Belum ada quiz</div>
    <div style="color:var(--muted);margin-top:6px;margin-bottom:16px">Coba ubah filter di atas</div>
    <a href="quiz.php" class="btn btn-navy">Lihat Semua Quiz</a>
</div>

<?php else:
    $curTingkat = null;
    foreach ($allRows as $q):
        $done  = $q['attempts'] > 0;
        $best  = (int)$q['best'];
        $g     = $done ? getGrade($best) : null;
        $k     = $q['mkelas'];
        if      ($k === 'Semua')            $tingkat = 'Semua Tingkat';
        elseif  (str_starts_with($k,'XII')) $tingkat = 'Kelas XII';
        elseif  (str_starts_with($k,'XI'))  $tingkat = 'Kelas XI';
        elseif  (str_starts_with($k,'X'))   $tingkat = 'Kelas X';
        else                                $tingkat = 'Lainnya';

        if ($tingkat !== $curTingkat):
            if ($curTingkat !== null) echo '</div>';
            $curTingkat = $tingkat;
?>
<div style="display:flex;align-items:center;gap:12px;margin:20px 0 12px">
    <div style="font-size:15px;font-weight:800;color:var(--navy)"><?= $tingkat ?></div>
    <div style="flex:1;height:1px;background:var(--border)"></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
<?php endif; ?>

<div class="card card-hover" style="padding:20px;display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;align-items:start;justify-content:space-between;gap:8px">
        <span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:700;padding:3px 10px;border-radius:6px">
            <?= htmlspecialchars($q['cn']) ?>
        </span>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">
            <?php if ($q['mkelas'] && $q['mkelas'] !== 'Semua'): ?>
            <span style="background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:600;padding:2px 8px;border-radius:5px">
                <?= htmlspecialchars($q['mkelas']) ?>
            </span>
            <?php endif; ?>
            <?php if ($done): ?>
            <span style="background:#ecfdf5;color:#065f46;font-size:10px;font-weight:700;padding:2px 8px;border-radius:5px">
                Sudah Dikerjakan
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <div style="font-weight:700;font-size:14px;color:var(--navy);line-height:1.4;margin-bottom:4px">
            <?= htmlspecialchars($q['title']) ?>
        </div>
        <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($q['mt']) ?></div>
    </div>
    <div style="font-size:11px;color:var(--muted)">
        <?= $q['qc'] ?> soal &nbsp;&middot;&nbsp; <?= $q['duration_minutes'] ?> menit
        <?php if ($done): ?>
        &nbsp;&middot;&nbsp; <span style="color:<?= $g['color'] ?>;font-weight:700">Nilai: <?= $best ?></span>
        <?php endif; ?>
    </div>
    <a href="quiz-play.php?id=<?= $q['id'] ?>" class="btn btn-navy btn-full">
        <?= $done ? 'Ulangi Quiz' : 'Mulai Quiz' ?>
    </a>
</div>

    <?php endforeach;
    if ($curTingkat !== null) echo '</div>';
endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
