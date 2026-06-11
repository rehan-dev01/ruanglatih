<?php
require_once __DIR__ . '/../includes/functions.php';
requireGuruOrAdmin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Pengumuman';
$msg = ''; $msgType = 'success';

// Kelas yang boleh ditarget guru ini
if (isAdmin()) {
    $kelasRes  = $db->query("SELECT DISTINCT kelas FROM users WHERE role='user' AND kelas IS NOT NULL AND kelas!='' ORDER BY kelas");
    $kelasList = [];
    while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];
} else {
    $assignments = getGuruAssignments($uid);
    $kelasList   = array_unique(array_column($assignments, 'kelas'));
    sort($kelasList);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $title   = $db->real_escape_string(trim($_POST['title']   ?? ''));
        $content = $db->real_escape_string(trim($_POST['content'] ?? ''));
        $tkelas  = $db->real_escape_string(trim($_POST['target_kelas'] ?? 'Semua'));

        // Validasi: guru hanya boleh kirim ke kelas yang dia ajar
        if (!isAdmin() && $tkelas !== 'Semua' && !in_array($tkelas, $kelasList)) {
            $msg = 'Anda tidak berhak mengirim ke kelas tersebut!'; $msgType = 'error';
        } elseif ($title && $content) {
            $db->query("INSERT INTO announcements(guru_id,title,content,target_kelas) VALUES($uid,'$title','$content','$tkelas')");
            $msg = 'Pengumuman berhasil dikirim!';
        } else { $msg = 'Judul dan isi tidak boleh kosong!'; $msgType = 'error'; }

    } elseif ($act === 'edit') {
        $id      = (int)$_POST['id'];
        $title   = $db->real_escape_string(trim($_POST['title']   ?? ''));
        $content = $db->real_escape_string(trim($_POST['content'] ?? ''));
        $tkelas  = $db->real_escape_string(trim($_POST['target_kelas'] ?? 'Semua'));
        // Hanya bisa edit pengumuman sendiri (kecuali admin)
        $ownerChk = $db->query("SELECT guru_id FROM announcements WHERE id=$id LIMIT 1");
        $owner    = $ownerChk ? $ownerChk->fetch_assoc() : null;
        if (!isAdmin() && (!$owner || (int)$owner['guru_id'] !== $uid)) {
            $msg = 'Anda tidak berhak mengubah pengumuman ini!'; $msgType = 'error';
        } elseif ($title && $content) {
            $db->query("UPDATE announcements SET title='$title',content='$content',target_kelas='$tkelas' WHERE id=$id");
            $msg = 'Pengumuman berhasil diperbarui!';
        } else { $msg = 'Data tidak lengkap!'; $msgType = 'error'; }

    } elseif ($act === 'delete') {
        $id       = (int)$_POST['id'];
        $ownerChk = $db->query("SELECT guru_id FROM announcements WHERE id=$id LIMIT 1");
        $owner    = $ownerChk ? $ownerChk->fetch_assoc() : null;
        if (isAdmin() || ($owner && (int)$owner['guru_id'] === $uid)) {
            $db->query("DELETE FROM announcements WHERE id=$id");
            $msg = 'Pengumuman dihapus.';
        } else { $msg = 'Akses ditolak!'; $msgType = 'error'; }
    }

    header('Location: pengumuman.php?msg=' . urlencode($msg) . '&type=' . $msgType); exit;
}

if (!$msg && isset($_GET['msg'])) { $msg = htmlspecialchars($_GET['msg']); $msgType = $_GET['type'] ?? 'success'; }

// Ambil semua pengumuman (admin: semua, guru: hanya miliknya)
$annWhere = isAdmin() ? '' : "WHERE a.guru_id = $uid";
$anns = $db->query("
    SELECT a.*, u.name AS guru_name
    FROM announcements a JOIN users u ON a.guru_id = u.id
    $annWhere
    ORDER BY a.created_at DESC
");

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Pengumuman</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">
            Kirim pengumuman ke siswa di kelas Anda
        </div>
    </div>
    <?php if ($kelasList || isAdmin()): ?>
    <button onclick="openModal('addModal')" class="btn" style="background:#047857;color:#fff">+ Buat Pengumuman</button>
    <?php endif; ?>
</div>

<?php if (!$kelasList && !isAdmin()): ?>
<div class="card card-pad" style="text-align:center;padding:48px;color:var(--muted)">
    <div style="font-weight:700;font-size:15px;color:var(--navy);margin-bottom:6px">Belum Ada Penugasan Kelas</div>
    <div style="font-size:13px">Hubungi admin untuk mendapatkan penugasan kelas sebelum bisa mengirim pengumuman.</div>
</div>
<?php else: ?>

<!-- Daftar Pengumuman -->
<?php if (!$anns || $anns->num_rows === 0): ?>
<div class="card card-pad" style="text-align:center;padding:48px;color:var(--muted)">
    <div style="font-weight:700;font-size:15px;color:var(--navy)">Belum Ada Pengumuman</div>
    <div style="font-size:13px;margin-top:6px">Klik "Buat Pengumuman" untuk mulai.</div>
</div>
<?php else: while ($an = $anns->fetch_assoc()): ?>
<div class="card card-pad" style="margin-bottom:12px;border-left:4px solid #047857">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                <span style="font-size:15px;font-weight:700;color:var(--navy)"><?= htmlspecialchars($an['title']) ?></span>
                <span style="font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;<?= $an['target_kelas']==='Semua' ? 'background:#dcfce7;color:#166534' : 'background:var(--navy-pale);color:var(--navy)' ?>">
                    <?= $an['target_kelas'] === 'Semua' ? 'Semua Kelas' : 'Kelas '.$an['target_kelas'] ?>
                </span>
            </div>
            <div style="font-size:13px;color:#374151;line-height:1.6;margin-bottom:8px"><?= nl2br(htmlspecialchars($an['content'])) ?></div>
            <div style="font-size:11px;color:var(--muted)">
                <?php if (isAdmin()): ?>
                Oleh: <strong><?= htmlspecialchars($an['guru_name']) ?></strong> &nbsp;·&nbsp;
                <?php endif; ?>
                <?= date('d M Y, H:i', strtotime($an['created_at'])) ?>
                &nbsp;·&nbsp; <?= timeAgo($an['created_at']) ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0">
            <button onclick='openEditModal(<?= json_encode([
                "id"=>$an["id"],"title"=>$an["title"],
                "content"=>$an["content"],"target_kelas"=>$an["target_kelas"]
            ]) ?>)' class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">Edit</button>
            <form method="POST" onsubmit="return confirm('Hapus pengumuman ini?')" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $an['id'] ?>">
                <button type="submit" class="btn btn-red btn-sm">Hapus</button>
            </form>
        </div>
    </div>
</div>
<?php endwhile; endif; ?>

<?php endif; ?>

<!-- Modal Tambah -->
<div id="addModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:520px">
    <div class="modal-header">
        <div><div class="modal-title">Buat Pengumuman</div></div>
        <button onclick="closeModal('addModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label class="form-label">Target Kelas</label>
            <select name="target_kelas" class="form-input form-select">
                <option value="Semua">Semua Kelas (<?= isAdmin() ? 'semua' : implode(', ', $kelasList) ?>)</option>
                <?php foreach ($kelasList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>">Kelas <?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Judul Pengumuman</label>
            <input type="text" name="title" class="form-input" placeholder="Contoh: Jadwal Ulangan Minggu Depan" required>
        </div>
        <div class="form-group">
            <label class="form-label">Isi Pengumuman</label>
            <textarea name="content" class="form-input" rows="5" placeholder="Tulis isi pengumuman di sini..." required></textarea>
        </div>
        <div style="display:flex;gap:10px;margin-top:4px">
            <button type="button" onclick="closeModal('addModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Kirim</button>
        </div>
    </form></div>
</div></div>

<!-- Modal Edit -->
<div id="editModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:520px">
    <div class="modal-header">
        <div><div class="modal-title">Edit Pengumuman</div></div>
        <button onclick="closeModal('editModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     id="eAnnId">
        <div class="form-group">
            <label class="form-label">Target Kelas</label>
            <select name="target_kelas" id="eAnnKelas" class="form-input form-select">
                <option value="Semua">Semua Kelas</option>
                <?php foreach ($kelasList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>">Kelas <?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Judul Pengumuman</label>
            <input type="text" name="title" id="eAnnTitle" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Isi Pengumuman</label>
            <textarea name="content" id="eAnnContent" class="form-input" rows="5" required></textarea>
        </div>
        <div style="display:flex;gap:10px;margin-top:4px">
            <button type="button" onclick="closeModal('editModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan</button>
        </div>
    </form></div>
</div></div>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow=''; }
function openEditModal(a) {
    document.getElementById('eAnnId').value      = a.id;
    document.getElementById('eAnnTitle').value   = a.title;
    document.getElementById('eAnnContent').value = a.content;
    document.getElementById('eAnnKelas').value   = a.target_kelas;
    openModal('editModal');
}
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) closeModal(id); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
