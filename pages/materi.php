<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Materi Belajar';

$q       = trim($_GET['q']     ?? '');
$cat_id  = (int)($_GET['cat']  ?? 0);
$fKelas  = trim($_GET['kelas'] ?? '');   // filter opsional

// Ambil semua materi — tanpa filter kelas (semua kelas bisa lihat semua)
$sql = "SELECT m.*, c.name as cn, c.icon as ci, c.color as cc,
    (SELECT COUNT(*) FROM quizzes    WHERE material_id=m.id) as qc,
    (SELECT COUNT(*) FROM flashcards WHERE material_id=m.id) as fc
    FROM materials m JOIN categories c ON m.category_id=c.id
    WHERE 1=1";
if ($q)       $sql .= " AND m.title LIKE '%" . $db->real_escape_string($q) . "%'";
if ($cat_id)  $sql .= " AND m.category_id=$cat_id";
if ($fKelas)  $sql .= " AND m.kelas='" . $db->real_escape_string($fKelas) . "'";
// Urut: Kelas X dulu → XI → XII → Semua → Lainnya
$sql .= " ORDER BY
    CASE
        WHEN m.kelas LIKE 'XII%' THEN 3
        WHEN m.kelas LIKE 'XI%'  THEN 2
        WHEN m.kelas LIKE 'X%'   THEN 1
        WHEN m.kelas = 'Semua'   THEN 0
        ELSE 9
    END ASC, m.kelas ASC, c.name ASC, m.title ASC";

$mats  = $db->query($sql);
$total = $db->query("SELECT COUNT(*) as c FROM materials")->fetch_assoc()['c'];
$cats  = $db->query("SELECT * FROM categories ORDER BY name");

// Semua opsi kelas yang tersedia (untuk dropdown filter)
$kelasRes  = $db->query("SELECT DISTINCT kelas FROM materials WHERE kelas IS NOT NULL AND kelas!='' ORDER BY
    CASE WHEN kelas LIKE 'XII%' THEN 3 WHEN kelas LIKE 'XI%' THEN 2
         WHEN kelas LIKE 'X%' THEN 1 WHEN kelas='Semua' THEN 0 ELSE 9 END, kelas");
$kelasOpts = [];
while ($k = $kelasRes->fetch_assoc()) $kelasOpts[] = $k['kelas'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="pb-label">Belajar</div>
    <div class="pb-title">Materi Belajar</div>
    <div class="pb-sub">
        <?= $total ?> materi tersedia dari semua kelas
        <?= $user['kelas'] ? ' &nbsp;&middot;&nbsp; Kelas kamu: <strong>' . htmlspecialchars($user['kelas']) . '</strong>' : '' ?>
    </div>
</div>

<!-- Search + Filter -->
<form method="GET" class="card card-pad" style="margin-bottom:16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
            placeholder="Cari materi..." class="form-input" style="flex:1;min-width:180px">
        <select name="cat" class="form-input form-select" style="min-width:160px">
            <option value="">Semua Mata Pelajaran</option>
            <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $cat_id == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
            </option>
            <?php endwhile; ?>
        </select>
        <select name="kelas" class="form-input form-select" style="min-width:150px">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasOpts as $k): ?>
            <option value="<?= htmlspecialchars($k) ?>" <?= $fKelas === $k ? 'selected' : '' ?>>
                <?= $k === 'Semua' ? 'Semua Kelas' : htmlspecialchars($k) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-navy">Cari</button>
        <?php if ($q || $cat_id || $fKelas): ?>
        <a href="materi.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Kategori Pills -->
<div class="pill-wrap" style="margin-bottom:16px">
    <a href="materi.php<?= $fKelas ? '?kelas='.urlencode($fKelas) : '' ?>" class="pill <?= !$cat_id ? 'active' : '' ?>">Semua</a>
    <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?>
    <a href="?cat=<?= $c['id'] ?><?= $fKelas ? '&kelas='.urlencode($fKelas) : '' ?>"
       class="pill <?= $cat_id == $c['id'] ? 'active' : '' ?>">
        <?= htmlspecialchars($c['name']) ?>
    </a>
    <?php endwhile; ?>
</div>

<?php if (!$mats || $mats->num_rows === 0): ?>
<div class="card card-pad" style="text-align:center;padding:60px 20px">
    <div style="font-weight:700;font-size:16px;color:var(--navy)">Materi tidak ditemukan</div>
    <div style="color:var(--muted);margin-top:6px;margin-bottom:16px">Coba kata kunci atau filter lain</div>
    <a href="materi.php" class="btn btn-navy">Lihat Semua Materi</a>
</div>

<?php else:
    // Kelompokkan materi per tingkat untuk section header
    $rows = []; while ($m = $mats->fetch_assoc()) $rows[] = $m;
    $curTingkat = null;
?>

<?php foreach ($rows as $m):
    // Tentukan tingkat dari kelas
    $k = $m['kelas'];
    if      ($k === 'Semua')              $tingkat = 'Semua Tingkat';
    elseif  (str_starts_with($k,'XII'))   $tingkat = 'Kelas XII';
    elseif  (str_starts_with($k,'XI'))    $tingkat = 'Kelas XI';
    elseif  (str_starts_with($k,'X'))     $tingkat = 'Kelas X';
    else                                  $tingkat = 'Lainnya';

    // Tampilkan section header saat tingkat berubah
    if ($tingkat !== $curTingkat):
        if ($curTingkat !== null) echo '</div>'; // tutup grid sebelumnya
        $curTingkat = $tingkat;
?>
<div style="display:flex;align-items:center;gap:12px;margin:20px 0 12px">
    <div style="font-size:15px;font-weight:800;color:var(--navy)"><?= $tingkat ?></div>
    <div style="flex:1;height:1px;background:var(--border)"></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
<?php endif; ?>

<a href="materi-detail.php?id=<?= $m['id'] ?>" style="text-decoration:none">
<div class="card card-hover" style="padding:20px">
    <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:12px">
        <span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:700;padding:3px 10px;border-radius:6px">
            <?= htmlspecialchars($m['cn']) ?>
        </span>
        <?php if ($m['kelas'] && $m['kelas'] !== 'Semua'): ?>
        <span style="background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:600;padding:3px 8px;border-radius:5px">
            <?= htmlspecialchars($m['kelas']) ?>
        </span>
        <?php endif; ?>
    </div>
    <div style="font-weight:700;font-size:14px;color:var(--navy);line-height:1.5;margin-bottom:12px">
        <?= htmlspecialchars($m['title']) ?>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);padding-top:12px">
        <div style="font-size:11px;color:var(--muted)">
            <?= $m['qc'] ?> quiz &nbsp;&middot;&nbsp; <?= $m['fc'] ?> flashcard
        </div>
        <span style="font-size:12px;font-weight:700;color:var(--navy)">Baca &rarr;</span>
    </div>
</div>
</a>

<?php endforeach;
    if ($curTingkat !== null) echo '</div>'; // tutup grid terakhir
endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
