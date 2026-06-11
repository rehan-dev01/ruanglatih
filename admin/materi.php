<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$user = getCurrentUser();
$db   = getDB();
$pageTitle = 'Kelola Materi';

$msg = ''; $msgType = 'success';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $title   = $db->real_escape_string(trim($_POST['title']));
        $content = $db->real_escape_string(trim($_POST['content']));
        $cat     = (int)$_POST['category_id'];
        if ($title && $content && $cat) {
            $kl=$db->real_escape_string(trim($_POST['kelas']??'Semua'));
            $db->query("INSERT INTO materials(category_id,title,content,kelas) VALUES($cat,'$title','$content','$kl')");
            $msg = 'Materi berhasil ditambahkan!';
        } else {
            $msg = 'Semua kolom wajib diisi!'; $msgType = 'error';
        }

    } elseif ($act === 'edit') {
        $id      = (int)$_POST['id'];
        $title   = $db->real_escape_string(trim($_POST['title']));
        $content = $db->real_escape_string(trim($_POST['content']));
        $cat     = (int)$_POST['category_id'];
        $kl      = $db->real_escape_string(trim($_POST['kelas'] ?? 'Semua'));
        if ($id && $title && $cat) {
            $db->query("UPDATE materials SET title='$title',content='$content',category_id=$cat,kelas='$kl' WHERE id=$id");
            $msg = 'Materi berhasil diperbarui!';
        } else {
            $msg = 'Data tidak lengkap!'; $msgType = 'error';
        }

    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM materials WHERE id=$id");
        $msg = 'Materi dihapus.';
    }
}


$mats = $db->query("
    SELECT m.*, c.name AS cn, c.icon AS ci, m.kelas AS mkelas,
           (SELECT COUNT(*) FROM quizzes WHERE material_id=m.id) AS qc,
           (SELECT COUNT(*) FROM flashcards WHERE material_id=m.id) AS fc
    FROM materials m
    JOIN categories c ON m.category_id = c.id
    ORDER BY m.created_at DESC
");
$cats   = $db->query("SELECT * FROM categories ORDER BY name");
$catArr = [];
while ($c = $cats->fetch_assoc()) $catArr[] = $c;

$extraHead = '<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>


<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Materi</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">Tambah, edit, atau hapus materi belajar</div>
    </div>
    <button onclick="openModal('addModal')" class="btn btn-navy">+ Tambah Materi</button>
</div>


<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
    <?php
    $totalMat  = $db->query("SELECT COUNT(*) AS c FROM materials")->fetch_assoc()['c'];
    $totalCat  = $db->query("SELECT COUNT(*) AS c FROM categories")->fetch_assoc()['c'];
    $totalQuiz = $db->query("SELECT COUNT(*) AS c FROM quizzes")->fetch_assoc()['c'];
    $totalFC   = $db->query("SELECT COUNT(*) AS c FROM flashcards")->fetch_assoc()['c'];
    ?>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= $totalMat ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Total Materi</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= $totalCat ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Kategori</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= $totalQuiz ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Quiz Terbuat</div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:800;color:var(--navy)"><?= $totalFC ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Flashcard</div>
    </div>
</div>


<div class="card" style="overflow:hidden">
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Judul Materi</th>
                <th>Kategori</th>
                <th style="text-align:center">Kelas</th><th style="text-align:center">Quiz</th>
                <th style="text-align:center">Flashcard</th>
                <th>Dibuat</th>
                <th style="text-align:center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php $no = 1; $mats->data_seek(0); while ($m = $mats->fetch_assoc()): ?>
        <tr>
            <td style="color:var(--muted);font-size:12px"><?= $no++ ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div>
                        <div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($m['title']) ?></div>
                        <div style="font-size:10px;color:var(--muted)">ID: <?= $m['id'] ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px">
                    <?= htmlspecialchars($m['cn']) ?>
                </span>
            </td>
            <td style="text-align:center;font-weight:700;color:var(--navy)"><?= $m['qc'] ?></td>
            <td style="text-align:center;font-weight:700;color:var(--navy)"><?= $m['fc'] ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
            <td>
                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                    
                    <button
                        onclick='openEditMateri(<?= json_encode([
                            "id"          => $m["id"],
                            "title"       => $m["title"],
                            "content"     => $m["content"],
                            "category_id" => $m["category_id"]
                        ]) ?>)'
                        class="btn btn-sm"
                        style="background:var(--navy-pale);color:var(--navy)">
                        Edit
                    </button>
                    <form method="POST" onsubmit="return confirm('Hapus materi ini? Semua quiz terkait juga akan terhapus.')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($mats->num_rows === 0): ?>
        <tr>
            <td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">
                                <div style="font-weight:600">Belum ada materi</div>
                <div style="font-size:12px;margin-top:4px">Klik "+ Tambah Materi" untuk mulai</div>
            </td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>


<div id="addModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:660px">
    <div class="modal-header">
        <div>
            <div class="modal-title">Tambah Materi Baru</div>
            <div class="modal-sub">Tulis konten seperti di Word — tanpa kode HTML</div>
        </div>
        <button onclick="closeModal('addModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" id="addForm" onsubmit="return syncAdd()">
        <input type="hidden" name="action" value="add">

        <div class="form-group">
            <label class="form-label">Kategori <span style="color:red">*</span></label>
            <select name="category_id" required class="form-input form-select">
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($catArr as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Judul Materi <span style="color:red">*</span></label>
            <input type="text" name="title" required placeholder="Contoh: Aljabar Dasar" class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Akses Kelas <span class="form-hint" style="font-size:11px;color:#94a3b8">"Semua" = semua kelas bisa lihat</span></label>
            <select name="kelas" class="form-input form-select">
                <option value="Semua">Semua Kelas</option>
                <optgroup label="Per Tingkat">
                    <option value="X">Kelas X (semua jurusan)</option>
                    <option value="XI">Kelas XI (semua jurusan)</option>
                    <option value="XII">Kelas XII (semua jurusan)</option>
                </optgroup>
                <?php $kops=getKelasOptions(); if($kops): ?>
                <optgroup label="Per Kelas Spesifik">
                    <?php foreach($kops as $ko): ?>
                    <option value="<?=htmlspecialchars($ko)?>"><?=htmlspecialchars($ko)?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Isi Materi
                <span class="form-hint">Bold, Heading, List tersedia di toolbar</span>
            </label>
            <div id="addEditor" style="min-height:220px;background:#fff;border-radius:0 0 8px 8px"></div>
            <input type="hidden" name="content" id="addContent">
        </div>

        <div class="modal-footer" style="padding:0">
            <button type="button" onclick="closeModal('addModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full">Simpan Materi</button>
        </div>
    </form>
    </div>
</div>
</div>


<div id="editModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:660px">
    <div class="modal-header">
        <div>
            <div class="modal-title">Edit Materi</div>
            <div class="modal-sub">Ubah konten materi yang sudah ada</div>
        </div>
        <button onclick="closeModal('editModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" id="editForm" onsubmit="return syncEdit()">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     id="editId">

        <div class="form-group">
            <label class="form-label">Kategori <span style="color:red">*</span></label>
            <select name="category_id" id="editCat" required class="form-input form-select">
                <?php foreach ($catArr as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Judul Materi <span style="color:red">*</span></label>
            <input type="text" name="title" id="editTitle" required class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Akses Kelas</label>
            <select name="kelas" id="editKelas" class="form-input form-select">
                <option value="Semua">Semua Kelas</option>
                <option value="X">Kelas X (semua jurusan)</option>
                <option value="XI">Kelas XI (semua jurusan)</option>
                <option value="XII">Kelas XII (semua jurusan)</option>
                <?php foreach(getKelasOptions() as $ko): ?>
                <option value="<?=$ko?>"><?=$ko?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Isi Materi
                <span class="form-hint">Edit konten di bawah ini</span>
            </label>
            <div id="editEditor" style="min-height:220px;background:#fff;border-radius:0 0 8px 8px"></div>
            <input type="hidden" name="content" id="editContent">
        </div>

        <div class="modal-footer" style="padding:0">
            <button type="button" onclick="closeModal('editModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full">Simpan Perubahan</button>
        </div>
    </form>
    </div>
</div>
</div>


<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>

const toolbarOptions = [
    [{ header: [2, 3, false] }],
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['blockquote'],
    ['clean']
];

const qAdd  = new Quill('#addEditor',  { theme:'snow', modules:{toolbar:toolbarOptions}, placeholder:'Tulis isi materi di sini...' });
const qEdit = new Quill('#editEditor', { theme:'snow', modules:{toolbar:toolbarOptions}, placeholder:'Edit isi materi...' });


function syncAdd() {
    const html = qAdd.root.innerHTML;
    document.getElementById('addContent').value = (html === '<p><br></p>') ? '' : html;
    if (!document.getElementById('addContent').value.trim()) {
        alert('Isi materi tidak boleh kosong!');
        return false;
    }
    return true;
}

function syncEdit() {
    const html = qEdit.root.innerHTML;
    document.getElementById('editContent').value = (html === '<p><br></p>') ? '' : html;
    if (!document.getElementById('editContent').value.trim()) {
        alert('Isi materi tidak boleh kosong!');
        return false;
    }
    return true;
}


function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = '';
}


function openEditMateri(m) {
    document.getElementById('editId').value    = m.id;
    document.getElementById('editTitle').value = m.title;
    document.getElementById('editCat').value   = m.category_id;
    document.getElementById('editKelas').value  = m.kelas || 'Semua';
    // Muat konten HTML ke Quill editor
    qEdit.root.innerHTML = m.content || '';
    openModal('editModal');
}


['addModal', 'editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
