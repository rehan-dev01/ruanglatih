<?php
require_once __DIR__ . '/../includes/functions.php';
requireGuruOrAdmin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Kelola Flashcard';
$msg = ''; $msgType = 'success';

$matCond = getGuruMaterialCondition('m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act         = $_POST['action'] ?? '';
    $filterRedir = (int)($_POST['material_id_filter'] ?? 0);

    if ($act === 'add') {
        $mid = (int)$_POST['material_id'];
        $q   = $db->real_escape_string(trim($_POST['question'] ?? ''));
        $a   = $db->real_escape_string(trim($_POST['answer']   ?? ''));
        if (!canGuruManageMaterial($mid)) { $msg='Akses ditolak!'; $msgType='error'; goto render; }
        if ($mid && $q && $a) {
            $db->query("INSERT INTO flashcards(material_id,question,answer) VALUES($mid,'$q','$a')");
            $msg = 'Flashcard berhasil ditambahkan!';
        } else { $msg='Semua kolom wajib diisi!'; $msgType='error'; }

    } elseif ($act === 'edit') {
        $id  = (int)$_POST['id'];
        $mid = (int)$_POST['material_id'];
        $q   = $db->real_escape_string(trim($_POST['question'] ?? ''));
        $a   = $db->real_escape_string(trim($_POST['answer']   ?? ''));
        if (!canGuruManageMaterial($mid)) { $msg='Akses ditolak!'; $msgType='error'; goto render; }
        if ($id && $mid && $q && $a) {
            $db->query("UPDATE flashcards SET material_id=$mid,question='$q',answer='$a' WHERE id=$id");
            $msg = 'Flashcard diperbarui!';
        } else { $msg='Semua kolom wajib diisi!'; $msgType='error'; }

    } elseif ($act === 'delete') {
        $id  = (int)$_POST['id'];
        $chk = $db->query("SELECT m.id FROM flashcards f JOIN materials m ON f.material_id=m.id WHERE f.id=$id LIMIT 1");
        if ($chk && ($row=$chk->fetch_assoc()) && canGuruManageMaterial((int)$row['id'])) {
            $db->query("DELETE FROM flashcards WHERE id=$id");
            $msg = 'Flashcard dihapus.';
        } else { $msg='Akses ditolak!'; $msgType='error'; }
    }

    if ($msgType === 'success') {
        $qs = $filterRedir ? "?material_id=$filterRedir&msg=".urlencode($msg) : "?msg=".urlencode($msg);
        header("Location: flashcard.php$qs"); exit;
    }
}

render:
if (!$msg && isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$filterMid = (int)($_GET['material_id'] ?? 0);

// Materials dropdown
$mats = $db->query("
    SELECT m.id, m.title, m.kelas, c.name AS cat_name, c.icon AS cat_icon
    FROM materials m JOIN categories c ON m.category_id=c.id
    WHERE $matCond ORDER BY c.name, m.title
");
$matArr = [];
while ($m = $mats->fetch_assoc()) $matArr[] = $m;

// Flashcards
$fWhere = "WHERE 1=1";
if ($filterMid) $fWhere .= " AND f.material_id=$filterMid";
$cards = $db->query("
    SELECT f.*, m.title AS mat_title, m.kelas AS mkelas, c.name AS cat_name, c.icon AS cat_icon
    FROM flashcards f
    JOIN materials m ON f.material_id=m.id
    JOIN categories c ON m.category_id=c.id
    $fWhere AND $matCond
    ORDER BY c.name, m.title, f.id
");

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Flashcard</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px"><?= isAdmin() ? 'Semua flashcard' : 'Flashcard dari materi Anda' ?></div>
    </div>
    <?php if ($matArr): ?>
    <button onclick="openModal('addModal')" class="btn" style="background:#047857;color:#fff">+ Tambah Flashcard</button>
    <?php elseif (!isAdmin()): ?>
    <div style="font-size:12px;color:var(--muted);background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:8px 14px">
        Belum ada materi dalam penugasan Anda. Tambahkan materi terlebih dahulu di menu <a href="materi.php" style="color:var(--navy);font-weight:700">Kelola Materi</a>.
    </div>
    <?php endif; ?>
</div>

<!-- Filter materi -->
<div class="card card-pad" style="margin-bottom:16px">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:200px">
        <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Filter Materi</div>
        <select name="material_id" class="form-input form-select">
            <option value="">Semua Materi</option>
            <?php foreach ($matArr as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterMid===$m['id']?'selected':'' ?>><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-navy">Filter</button>
        <?php if ($filterMid): ?><a href="flashcard.php" class="btn btn-outline">Reset</a><?php endif; ?>
    </div>
</form>
</div>

<!-- Tabel Flashcard -->
<div class="card" style="overflow:hidden">
<div style="overflow-x:auto">
<table class="data-table">
    <thead><tr><th>Pertanyaan</th><th>Jawaban</th><th>Materi / Kelas</th><th style="text-align:center">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$cards || $cards->num_rows === 0): ?>
    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--muted)">Belum ada flashcard.</td></tr>
    <?php else: while ($fc = $cards->fetch_assoc()): ?>
    <tr>
        <td style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($fc['question']) ?></td>
        <td style="font-size:13px;color:#047857;font-weight:500"><?= htmlspecialchars($fc['answer']) ?></td>
        <td>
            <div style="font-size:12px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($fc['mat_title']) ?></div>
            <div style="font-size:11px;color:var(--muted)">Kelas <?= htmlspecialchars($fc['mkelas']) ?></div>
        </td>
        <td>
            <div style="display:flex;gap:5px;justify-content:center">
                <button onclick='openEditModal(<?= json_encode([
                    "id"=>$fc["id"],"material_id"=>$fc["material_id"],
                    "question"=>$fc["question"],"answer"=>$fc["answer"]
                ]) ?>)' class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus flashcard ini?')">
                    <input type="hidden" name="action"            value="delete">
                    <input type="hidden" name="id"                value="<?= $fc['id'] ?>">
                    <input type="hidden" name="material_id_filter" value="<?= $filterMid ?>">
                    <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                </form>
            </div>
        </td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>
</div>
</div>

<!-- Modal Tambah -->
<div id="addModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:480px">
    <div class="modal-header"><div class="modal-title">Tambah Flashcard</div>
        <button onclick="closeModal('addModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="material_id_filter" value="<?= $filterMid ?>">
        <div class="form-group"><label class="form-label">Materi</label>
            <select name="material_id" class="form-input form-select" required>
                <option value="">-- Pilih Materi --</option>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group"><label class="form-label">Pertanyaan</label>
            <textarea name="question" class="form-input" rows="2" required></textarea></div>
        <div class="form-group"><label class="form-label">Jawaban</label>
            <textarea name="answer"   class="form-input" rows="2" required></textarea></div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('addModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan</button>
        </div>
    </form></div>
</div></div>

<!-- Modal Edit -->
<div id="editModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:480px">
    <div class="modal-header"><div class="modal-title">Edit Flashcard</div>
        <button onclick="closeModal('editModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     id="eFcId">
        <input type="hidden" name="material_id_filter" value="<?= $filterMid ?>">
        <div class="form-group"><label class="form-label">Materi</label>
            <select name="material_id" id="eFcMat" class="form-input form-select" required>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group"><label class="form-label">Pertanyaan</label>
            <textarea name="question" id="eFcQ" class="form-input" rows="2" required></textarea></div>
        <div class="form-group"><label class="form-label">Jawaban</label>
            <textarea name="answer"   id="eFcA" class="form-input" rows="2" required></textarea></div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('editModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan</button>
        </div>
    </form></div>
</div></div>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow=''; }
function openEditModal(fc) {
    document.getElementById('eFcId').value  = fc.id;
    document.getElementById('eFcMat').value = fc.material_id;
    document.getElementById('eFcQ').value   = fc.question;
    document.getElementById('eFcA').value   = fc.answer;
    openModal('editModal');
}
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) closeModal(id); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
