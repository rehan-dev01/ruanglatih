<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$user = getCurrentUser();
$db   = getDB();
$pageTitle = 'Kelola Flashcard';
$msg = ''; $msgType = 'success';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $filterRedirect = (int)($_POST['material_id_filter'] ?? 0);

    if ($act === 'add') {
        $mid = (int)$_POST['material_id'];
        $q   = $db->real_escape_string(trim($_POST['question']));
        $a   = $db->real_escape_string(trim($_POST['answer']));
        if ($mid && $q && $a) {
            $db->query("INSERT INTO flashcards(material_id,question,answer) VALUES($mid,'$q','$a')");
            $msg = 'Flashcard berhasil ditambahkan!';
        } else {
            $msg = 'Semua kolom wajib diisi!'; $msgType = 'error';
        }

    } elseif ($act === 'edit') {
        $id  = (int)$_POST['id'];
        $mid = (int)$_POST['material_id'];
        $q   = $db->real_escape_string(trim($_POST['question']));
        $a   = $db->real_escape_string(trim($_POST['answer']));
        if ($id && $mid && $q && $a) {
            $db->query("UPDATE flashcards SET material_id=$mid,question='$q',answer='$a' WHERE id=$id");
            $msg = 'Flashcard berhasil diperbarui!';
        } else {
            $msg = 'Semua kolom wajib diisi!'; $msgType = 'error';
        }

    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM flashcards WHERE id=$id");
        $msg = 'Flashcard dihapus.';
    }

    // Redirect agar filter tetap terjaga & mencegah resubmit
    if ($msgType === 'success') {
        $qs = $filterRedirect ? "?material_id=$filterRedirect&msg=" . urlencode($msg) : "?msg=" . urlencode($msg);
        header("Location: flashcard.php$qs");
        exit;
    }
}

// Tampilkan pesan dari redirect
if (!$msg && isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
}


$filterMid = (int)($_GET['material_id'] ?? 0);


$mats   = $db->query("SELECT m.*,c.name AS cat_name,c.icon AS cat_icon FROM materials m JOIN categories c ON m.category_id=c.id ORDER BY c.name,m.title");
$matArr = [];
while ($m = $mats->fetch_assoc()) $matArr[] = $m;

$where = $filterMid ? "WHERE f.material_id=$filterMid" : '';
$cards = $db->query("
    SELECT f.*, m.title AS mat_title, c.name AS cat_name, c.icon AS cat_icon
    FROM flashcards f
    JOIN materials m ON f.material_id = m.id
    JOIN categories c ON m.category_id = c.id
    $where
    ORDER BY c.name, m.title, f.id
");

$countRes = $db->query("SELECT material_id, COUNT(*) AS total FROM flashcards GROUP BY material_id");
$countMap = [];
while ($r = $countRes->fetch_assoc()) $countMap[$r['material_id']] = $r['total'];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <?= $msgType === 'success' ? icon('check') : icon('warning') ?>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>


<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Flashcard</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">Tambah, edit, atau hapus kartu soal-jawab</div>
    </div>
    <button onclick="openModal('addModal')" class="btn btn-navy">+ Tambah Flashcard</button>
</div>


<?php
$totalCards        = $db->query("SELECT COUNT(*) AS c FROM flashcards")->fetch_assoc()['c'];
$totalMatsWithCards= $db->query("SELECT COUNT(DISTINCT material_id) AS c FROM flashcards")->fetch_assoc()['c'];
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= $totalCards ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Total Flashcard</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= $totalMatsWithCards ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Materi Terlibat</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= count($matArr) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Total Materi</div>
    </div>
</div>


<div class="card" style="padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:13px;font-weight:600;color:var(--navy);white-space:nowrap">
        <?= icon('search') ?> Filter Materi:
    </span>
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
        <select name="material_id" class="form-input form-select" style="flex:1;font-size:13px;padding:8px 12px">
            <option value="0">-- Semua Materi --</option>
            <?php foreach ($matArr as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterMid == $m['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['title']) ?> (<?= $countMap[$m['id']] ?? 0 ?> kartu)
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-navy" style="padding:8px 16px;font-size:13px">Tampilkan</button>
        <?php if ($filterMid): ?>
        <a href="flashcard.php" class="btn btn-outline" style="padding:8px 16px;font-size:13px">Reset</a>
        <?php endif; ?>
    </form>
</div>


<div class="card" style="overflow:hidden">
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th>Pertanyaan (Depan)</th>
                <th>Jawaban (Belakang)</th>
                <th>Materi</th>
                <th style="text-align:center;width:130px">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; $hasRows = false; while ($f = $cards->fetch_assoc()): $hasRows = true; ?>
        <tr>
            <td style="color:var(--muted);font-size:12px"><?= $no++ ?></td>
            <td>
                <div style="font-size:13px;font-weight:600;color:var(--navy);max-width:260px;line-height:1.5">
                    <?= htmlspecialchars(mb_strimwidth($f['question'], 0, 90, '…')) ?>
                </div>
            </td>
            <td>
                <div style="font-size:12px;color:#555;max-width:260px;line-height:1.5">
                    <?= htmlspecialchars(mb_strimwidth($f['answer'], 0, 90, '…')) ?>
                </div>
            </td>
            <td>
                <span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;white-space:nowrap">
                    <?= htmlspecialchars($f['mat_title']) ?>
                </span>
            </td>
            <td>
                <div style="display:flex;gap:6px;justify-content:center">
                    
                    <button onclick='openEditFC(<?= json_encode([
                        "id"          => $f["id"],
                        "material_id" => $f["material_id"],
                        "question"    => $f["question"],
                        "answer"      => $f["answer"]
                    ]) ?>)'
                        class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">
                        <?= icon('pencil', '0.9em') ?> Edit
                    </button>
                    <form method="POST" onsubmit="return confirm('Hapus flashcard ini?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                        <?php if ($filterMid): ?>
                        <input type="hidden" name="material_id_filter" value="<?= $filterMid ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        <?php if (!$hasRows): ?>
        <tr>
            <td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">
                <div style="font-size:36px;margin-bottom:8px"><?= icon('flashcard', '36px') ?></div>
                <div style="font-weight:600;margin-bottom:4px">Belum ada flashcard</div>
                <div style="font-size:12px">
                    <?= $filterMid ? 'Tidak ada flashcard untuk materi ini.' : 'Klik "+ Tambah Flashcard" untuk mulai.' ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>


<div id="addModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:560px">
    <div class="modal-header">
        <div>
            <div class="modal-title"><?= icon('flashcard') ?> Tambah Flashcard</div>
            <div class="modal-sub">Buat kartu soal dan jawaban baru</div>
        </div>
        <button onclick="closeModal('addModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <?php if ($filterMid): ?>
        <input type="hidden" name="material_id_filter" value="<?= $filterMid ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Materi <span style="color:red">*</span></label>
            <select name="material_id" required class="form-input form-select">
                <option value="">-- Pilih Materi --</option>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $filterMid == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['cat_name']) ?> — <?= htmlspecialchars($m['title']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Pertanyaan / Soal (Depan Kartu) <span style="color:red">*</span></label>
            <textarea name="question" id="addQ" required rows="3"
                      placeholder="Tulis pertanyaan di sini..."
                      class="form-input" style="resize:vertical;font-size:13px;line-height:1.6"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Jawaban (Belakang Kartu) <span style="color:red">*</span></label>
            <textarea name="answer" id="addA" required rows="4"
                      placeholder="Tulis jawaban lengkap di sini..."
                      class="form-input" style="resize:vertical;font-size:13px;line-height:1.6"></textarea>
        </div>

        <!-- Preview -->
        <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:14px;margin-bottom:16px">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                Preview Kartu
            </div>
            <div style="display:flex;gap:10px">
                <div style="flex:1;background:#fff;border-radius:8px;padding:12px;border:1px solid #e2e8f0">
                    <div style="font-size:10px;color:var(--muted);margin-bottom:4px">DEPAN</div>
                    <div id="previewQ" style="font-size:12px;font-weight:600;color:var(--navy);min-height:32px">—</div>
                </div>
                <div style="flex:1;background:#eef2ff;border-radius:8px;padding:12px;border:1px solid #c7d2fe">
                    <div style="font-size:10px;color:var(--muted);margin-bottom:4px">BELAKANG</div>
                    <div id="previewA" style="font-size:12px;color:#3730a3;min-height:32px">—</div>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding:0">
            <button type="button" onclick="closeModal('addModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full">Simpan Flashcard</button>
        </div>
    </form>
    </div>
</div>
</div>


<div id="editModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:560px">
    <div class="modal-header">
        <div>
            <div class="modal-title"><?= icon('pencil') ?> Edit Flashcard</div>
            <div class="modal-sub">Ubah kartu soal dan jawaban</div>
        </div>
        <button onclick="closeModal('editModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     id="editId">
        <?php if ($filterMid): ?>
        <input type="hidden" name="material_id_filter" value="<?= $filterMid ?>">
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Materi <span style="color:red">*</span></label>
            <select name="material_id" id="editMid" required class="form-input form-select">
                <option value="">-- Pilih Materi --</option>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= $m['id'] ?>">
                    <?= htmlspecialchars($m['cat_name']) ?> — <?= htmlspecialchars($m['title']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Pertanyaan / Soal (Depan Kartu) <span style="color:red">*</span></label>
            <textarea name="question" id="editQ" required rows="3"
                      class="form-input" style="resize:vertical;font-size:13px;line-height:1.6"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Jawaban (Belakang Kartu) <span style="color:red">*</span></label>
            <textarea name="answer" id="editA" required rows="4"
                      class="form-input" style="resize:vertical;font-size:13px;line-height:1.6"></textarea>
        </div>

        <!-- Preview Edit -->
        <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:14px;margin-bottom:16px">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                Preview Kartu
            </div>
            <div style="display:flex;gap:10px">
                <div style="flex:1;background:#fff;border-radius:8px;padding:12px;border:1px solid #e2e8f0">
                    <div style="font-size:10px;color:var(--muted);margin-bottom:4px">DEPAN</div>
                    <div id="previewQE" style="font-size:12px;font-weight:600;color:var(--navy);min-height:32px">—</div>
                </div>
                <div style="flex:1;background:#eef2ff;border-radius:8px;padding:12px;border:1px solid #c7d2fe">
                    <div style="font-size:10px;color:var(--muted);margin-bottom:4px">BELAKANG</div>
                    <div id="previewAE" style="font-size:12px;color:#3730a3;min-height:32px">—</div>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding:0">
            <button type="button" onclick="closeModal('editModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full">Simpan Perubahan</button>
        </div>
    </form>
    </div>
</div>
</div>


<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden');    document.body.style.overflow=''; }

/* Live preview — Tambah */
const addQta = document.getElementById('addQ');
const addAta = document.getElementById('addA');
if (addQta) addQta.addEventListener('input', () => { document.getElementById('previewQ').textContent  = addQta.value || '—'; });
if (addAta) addAta.addEventListener('input', () => { document.getElementById('previewA').textContent  = addAta.value || '—'; });

/* Live preview — Edit */
const editQta = document.getElementById('editQ');
const editAta = document.getElementById('editA');
if (editQta) editQta.addEventListener('input', () => { document.getElementById('previewQE').textContent = editQta.value || '—'; });
if (editAta) editAta.addEventListener('input', () => { document.getElementById('previewAE').textContent = editAta.value || '—'; });


function openEditFC(f) {
    document.getElementById('editId').value  = f.id;
    document.getElementById('editMid').value = f.material_id;
    document.getElementById('editQ').value   = f.question;
    document.getElementById('editA').value   = f.answer;
    document.getElementById('previewQE').textContent = f.question || '—';
    document.getElementById('previewAE').textContent = f.answer   || '—';
    openModal('editModal');
}

/* Tutup modal saat klik backdrop */
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
