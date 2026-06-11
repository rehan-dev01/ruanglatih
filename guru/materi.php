<?php
require_once __DIR__ . '/../includes/functions.php';
requireGuruOrAdmin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Kelola Materi';
$msg = ''; $msgType = 'success';
$cond = getGuruMaterialCondition('m');

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $title      = $db->real_escape_string(trim($_POST['title']   ?? ''));
        $content    = $db->real_escape_string(trim($_POST['content'] ?? ''));
        $cat        = (int)($_POST['category_id'] ?? 0);
        $kelasList  = $_POST['kelas_list'] ?? [];   // array dari checkbox

        if (!$title || !$content || !$cat || empty($kelasList)) {
            $msg = 'Judul, konten, kategori, dan minimal 1 kelas wajib diisi!';
            $msgType = 'error';
            goto render;
        }

        $added = 0; $skipped = 0;
        foreach ($kelasList as $kl) {
            $kl = trim($kl);
            if (!$kl) continue;
            $klEsc = $db->real_escape_string($kl);

            // Validasi guru boleh akses kelas+kategori ini
            if (!isAdmin()) {
                $r = $db->query("SELECT 1 FROM guru_assignments
                                 WHERE guru_id=$uid AND category_id=$cat AND kelas='$klEsc' LIMIT 1");
                if (!$r || $r->num_rows === 0) { $skipped++; continue; }
            }

            // Cek duplikat (judul + kategori + kelas sama)
            $chk = $db->query("SELECT 1 FROM materials
                               WHERE title='$title' AND category_id=$cat AND kelas='$klEsc' LIMIT 1");
            if ($chk && $chk->num_rows > 0) { $skipped++; continue; }

            $db->query("INSERT INTO materials(category_id,title,content,kelas)
                        VALUES($cat,'$title','$content','$klEsc')");
            $added++;
        }

        if ($added > 0) {
            $msg = "Materi berhasil ditambahkan ke <strong>$added kelas</strong>!"
                 . ($skipped > 0 ? " ($skipped dilewati: sudah ada atau akses ditolak)" : '');
        } else {
            $msg = 'Tidak ada materi yang ditambahkan. Cek kelas dan izin akses.';
            $msgType = 'error';
        }

    } elseif ($act === 'edit') {
        $id      = (int)$_POST['id'];
        $title   = $db->real_escape_string(trim($_POST['title']   ?? ''));
        $content = $db->real_escape_string(trim($_POST['content'] ?? ''));
        $cat     = (int)($_POST['category_id'] ?? 0);
        $kl      = $db->real_escape_string(trim($_POST['kelas']   ?? ''));

        if (!canGuruManageMaterial($id)) {
            $msg = 'Anda tidak berhak mengubah materi ini!'; $msgType = 'error'; goto render;
        }
        if ($id && $title && $cat && $kl) {
            $db->query("UPDATE materials SET title='$title',content='$content',
                        category_id=$cat,kelas='$kl' WHERE id=$id");
            $msg = 'Materi berhasil diperbarui!';
        } else { $msg = 'Data tidak lengkap!'; $msgType = 'error'; }

    } elseif ($act === 'delete') {
        $id = (int)$_POST['id'];
        if (!canGuruManageMaterial($id)) {
            $msg = 'Anda tidak berhak menghapus materi ini!'; $msgType = 'error'; goto render;
        }
        $db->query("DELETE FROM materials WHERE id=$id");
        $msg = 'Materi dihapus.';
    }
}

render:
// ── Fetch data ─────────────────────────────────────────────────────────────────
$mats = $db->query("
    SELECT m.*, c.name AS cn, m.kelas AS mkelas,
           (SELECT COUNT(*) FROM quizzes    WHERE material_id=m.id) AS qc,
           (SELECT COUNT(*) FROM flashcards WHERE material_id=m.id) AS fc
    FROM materials m JOIN categories c ON m.category_id=c.id
    WHERE $cond ORDER BY m.created_at DESC
");
$totalMat = $mats ? $mats->num_rows : 0;

// Kategori & mapping kelas-per-kategori
if (isAdmin()) {
    $catRes = $db->query("SELECT * FROM categories ORDER BY name");
    $catArr = [];
    while ($c = $catRes->fetch_assoc()) $catArr[] = $c;
    // admin: semua kelas, dikelompok per tingkat
    $allKelas = getKelasOptions();
    $assignMap = null; // null = admin (semua kelas)
} else {
    $assignments = getGuruAssignments($uid);
    $catIds = array_unique(array_column($assignments, 'category_id'));
    $catArr = [];
    if ($catIds) {
        $inCat = implode(',', array_map('intval', $catIds));
        $catRes = $db->query("SELECT * FROM categories WHERE id IN ($inCat) ORDER BY name");
        while ($c = $catRes->fetch_assoc()) $catArr[] = $c;
    }
    // Build map: category_id => [kelas, ...]
    $assignMap = [];
    foreach ($assignments as $a) {
        $assignMap[(int)$a['category_id']][] = $a['kelas'];
    }
    $allKelas = array_unique(array_column($assignments, 'kelas'));
}

// Kelompokkan kelas per tingkat untuk admin
function groupKelasByTingkat(array $kelas): array {
    $grp = ['X'=>[],'XI'=>[],'XII'=>[],'Lainnya'=>[]];
    foreach ($kelas as $k) {
        if (str_starts_with($k,'XII')) $grp['XII'][] = $k;
        elseif (str_starts_with($k,'XI')) $grp['XI'][] = $k;
        elseif (str_starts_with($k,'X'))  $grp['X'][] = $k;
        else $grp['Lainnya'][] = $k;
    }
    return array_filter($grp);
}

$extraHead = '<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Materi</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">
            <?= isAdmin() ? 'Seluruh materi platform' : 'Materi sesuai penugasan Anda' ?>
            &nbsp;·&nbsp; <?= $totalMat ?> materi
        </div>
    </div>
    <?php if ($catArr): ?>
    <button onclick="openModal('addModal')" class="btn" style="background:#047857;color:#fff">+ Tambah Materi</button>
    <?php elseif (!isAdmin()): ?>
    <div style="font-size:12px;color:var(--muted);background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:8px 14px">
        Anda belum memiliki penugasan. Hubungi admin untuk mendapatkan akses kategori dan kelas.
    </div>
    <?php endif; ?>
</div>

<!-- Tabel Materi -->
<div class="card" style="overflow:hidden">
<div style="overflow-x:auto">
<table class="data-table">
    <thead>
        <tr>
            <th>Materi</th>
            <th style="text-align:center">Kategori</th>
            <th style="text-align:center">Kelas</th>
            <th style="text-align:center">Quiz</th>
            <th style="text-align:center">Flashcard</th>
            <th style="text-align:center">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$mats || $mats->num_rows === 0): ?>
    <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">
        <div style="font-weight:600">Belum ada materi<?= !isAdmin() ? ' untuk penugasan Anda' : '' ?></div>
    </td></tr>
    <?php else: while ($m = $mats->fetch_assoc()): ?>
    <tr>
        <td>
            <div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($m['title']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= timeAgo($m['created_at']) ?></div>
        </td>
        <td style="text-align:center">
            <span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:600;padding:3px 9px;border-radius:6px">
                <?= htmlspecialchars($m['cn']) ?>
            </span>
        </td>
        <td style="text-align:center;font-size:12px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($m['mkelas']) ?></td>
        <td style="text-align:center;font-weight:700"><?= $m['qc'] ?></td>
        <td style="text-align:center;font-weight:700"><?= $m['fc'] ?></td>
        <td>
            <div style="display:flex;gap:5px;justify-content:center">
                <button onclick='openEditModal(<?= json_encode([
                    "id"=>$m["id"],"title"=>$m["title"],"content"=>$m["content"],
                    "category_id"=>$m["category_id"],"kelas"=>$m["mkelas"]
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus materi ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $m['id'] ?>">
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

<!-- ═══ MODAL TAMBAH ══════════════════════════════════════════════════════════ -->
<div id="addModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:720px">
    <div class="modal-header">
        <div>
            <div class="modal-title">Tambah Materi Baru</div>
            <div class="modal-sub" style="color:var(--muted)">Bisa ditambahkan ke beberapa kelas sekaligus</div>
        </div>
        <button onclick="closeModal('addModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" id="addForm">
        <input type="hidden" name="action" value="add">

        <!-- Judul -->
        <div class="form-group">
            <label class="form-label">Judul Materi</label>
            <input type="text" name="title" class="form-input" placeholder="Contoh: Persamaan Linear" required>
        </div>

        <!-- Kategori -->
        <div class="form-group">
            <label class="form-label">Kategori / Mata Pelajaran</label>
            <select name="category_id" id="addCatSel" class="form-input form-select" required onchange="renderKelasCheckboxes()">
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($catArr as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Kelas — multi-select checkboxes -->
        <div class="form-group">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                <label class="form-label" style="margin:0">Tambahkan ke Kelas</label>
                <div style="display:flex;gap:6px">
                    <button type="button" onclick="selectAllKelas()" class="btn btn-sm" style="background:#047857;color:#fff;font-size:11px;padding:4px 10px">Pilih Semua</button>
                    <button type="button" onclick="clearAllKelas()"  class="btn btn-sm btn-outline" style="font-size:11px;padding:4px 10px">Kosongkan</button>
                </div>
            </div>

            <div id="kelasWrap" style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#f8fafc;min-height:52px">
                <div id="kelasCheckboxes" style="display:flex;flex-wrap:wrap;gap:8px">
                    <span style="color:var(--muted);font-size:13px">← Pilih kategori terlebih dahulu</span>
                </div>
            </div>

            <!-- Shortcut tingkat (hanya muncul setelah kategori dipilih) -->
            <div id="tingkatBtns" style="display:none;margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                <span style="font-size:11px;color:var(--muted);align-self:center">Pilih tingkat:</span>
                <button type="button" onclick="selectByTingkat('X')"   class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 10px">Semua Kelas X</button>
                <button type="button" onclick="selectByTingkat('XI')"  class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 10px">Semua Kelas XI</button>
                <button type="button" onclick="selectByTingkat('XII')" class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 10px">Semua Kelas XII</button>
            </div>

            <div id="selectedCount" style="margin-top:6px;font-size:12px;color:#047857;font-weight:600"></div>
        </div>

        <!-- Konten -->
        <div class="form-group">
            <label class="form-label">Konten Materi</label>
            <div id="addEditor" style="height:220px"></div>
            <input type="hidden" name="content" id="addContent">
        </div>

        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="button" onclick="closeModal('addModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" id="addSubmitBtn" class="btn btn-full" style="background:#047857;color:#fff">Simpan ke Kelas Terpilih</button>
        </div>
    </form>
    </div>
</div>
</div>

<!-- ═══ MODAL EDIT ════════════════════════════════════════════════════════════ -->
<div id="editModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:700px">
    <div class="modal-header">
        <div><div class="modal-title">Edit Materi</div></div>
        <button onclick="closeModal('editModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id"     id="eMatId">
        <div class="form-group">
            <label class="form-label">Judul Materi</label>
            <input type="text" name="title" id="eMatTitle" class="form-input" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
                <label class="form-label">Kategori</label>
                <select name="category_id" id="eMatCat" class="form-input form-select" required>
                    <?php foreach ($catArr as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Kelas</label>
                <select name="kelas" id="eMatKelas" class="form-input form-select" required>
                    <?php foreach ($allKelas as $k): ?>
                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Konten Materi</label>
            <div id="editEditor" style="height:220px"></div>
            <input type="hidden" name="content" id="editContent">
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
            <button type="button" onclick="closeModal('editModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan Perubahan</button>
        </div>
    </form>
    </div>
</div>
</div>

<!-- ═══ JavaScript ════════════════════════════════════════════════════════════ -->
<?php
// Data untuk JS: map kategori → daftar kelas yang boleh dipilih
if (isAdmin()) {
    // Admin: tampilkan semua kelas, dikelompok per tingkat
    $kelasForJs = [];
    foreach ($catArr as $c) {
        $kelasForJs[$c['id']] = $allKelas;
    }
} else {
    $kelasForJs = $assignMap ?? [];
}
?>
<script>
const KELAS_MAP = <?= json_encode($kelasForJs, JSON_UNESCAPED_UNICODE) ?>;

// ── Checkbox renderer ─────────────────────────────────────────────────────────
function renderKelasCheckboxes() {
    const catId = parseInt(document.getElementById('addCatSel').value);
    const wrap  = document.getElementById('kelasCheckboxes');
    const tBtns = document.getElementById('tingkatBtns');

    wrap.innerHTML = '';
    updateCount();

    if (!catId || !KELAS_MAP[catId] || !KELAS_MAP[catId].length) {
        wrap.innerHTML = '<span style="color:var(--muted);font-size:13px">Tidak ada kelas tersedia untuk kategori ini</span>';
        tBtns.style.display = 'none';
        return;
    }

    const kelas = KELAS_MAP[catId];
    // Kelompokkan per tingkat
    const grp = {X:[], XI:[], XII:[], Lainnya:[]};
    kelas.forEach(k => {
        if (k.startsWith('XII'))       grp.XII.push(k);
        else if (k.startsWith('XI'))   grp.XI.push(k);
        else if (k.startsWith('X'))    grp.X.push(k);
        else grp.Lainnya.push(k);
    });

    Object.entries(grp).forEach(([tingkat, list]) => {
        if (!list.length) return;
        const label = document.createElement('div');
        label.style = 'width:100%;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin:6px 0 4px';
        label.textContent = 'Kelas ' + tingkat;
        wrap.appendChild(label);

        list.sort().forEach(k => {
            const div = document.createElement('label');
            div.style = 'display:flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--border);border-radius:8px;padding:6px 12px;cursor:pointer;font-size:13px;font-weight:500;transition:.15s';
            div.onmouseover = () => div.style.borderColor = '#047857';
            div.onmouseout  = () => { if (!div.querySelector('input').checked) div.style.borderColor = 'var(--border)'; };

            const cb = document.createElement('input');
            cb.type  = 'checkbox';
            cb.name  = 'kelas_list[]';
            cb.value = k;
            cb.style = 'accent-color:#047857';
            cb.onchange = () => {
                div.style.borderColor = cb.checked ? '#047857' : 'var(--border)';
                div.style.background  = cb.checked ? '#f0fdf4' : '#fff';
                updateCount();
            };

            div.appendChild(cb);
            div.appendChild(document.createTextNode(k));
            wrap.appendChild(div);
        });
    });

    tBtns.style.display = 'flex';
    updateCount();
}

function selectAllKelas() {
    document.querySelectorAll('#kelasCheckboxes input[type=checkbox]').forEach(cb => {
        cb.checked = true;
        cb.dispatchEvent(new Event('change'));
    });
}
function clearAllKelas() {
    document.querySelectorAll('#kelasCheckboxes input[type=checkbox]').forEach(cb => {
        cb.checked = false;
        cb.dispatchEvent(new Event('change'));
    });
}
function selectByTingkat(t) {
    document.querySelectorAll('#kelasCheckboxes input[type=checkbox]').forEach(cb => {
        const match = t === 'XII' ? cb.value.startsWith('XII') :
                      t === 'XI'  ? cb.value.startsWith('XI') && !cb.value.startsWith('XII') :
                                    cb.value.startsWith('X')  && !cb.value.startsWith('XI') && !cb.value.startsWith('XII');
        if (match) { cb.checked = true; cb.dispatchEvent(new Event('change')); }
    });
}
function updateCount() {
    const n = document.querySelectorAll('#kelasCheckboxes input:checked').length;
    const el = document.getElementById('selectedCount');
    el.textContent = n > 0 ? `${n} kelas dipilih — materi akan dibuat ${n}×` : '';
    document.getElementById('addSubmitBtn').textContent =
        n > 1 ? `Simpan ke ${n} Kelas Sekaligus` : (n === 1 ? 'Simpan ke 1 Kelas' : 'Simpan ke Kelas Terpilih');
}

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden');    document.body.style.overflow=''; }

// ── Quill editors ─────────────────────────────────────────────────────────────
const addQuill  = new Quill('#addEditor',  {theme:'snow', placeholder:'Tulis konten materi...'});
const editQuill = new Quill('#editEditor', {theme:'snow'});

document.getElementById('addForm').addEventListener('submit', function(e) {
    const n = document.querySelectorAll('#kelasCheckboxes input:checked').length;
    if (n === 0) { e.preventDefault(); alert('Pilih minimal 1 kelas!'); return; }
    document.getElementById('addContent').value = addQuill.root.innerHTML;
});
document.getElementById('editForm').addEventListener('submit', function() {
    document.getElementById('editContent').value = editQuill.root.innerHTML;
});

function openEditModal(m) {
    document.getElementById('eMatId').value    = m.id;
    document.getElementById('eMatTitle').value = m.title;
    document.getElementById('eMatCat').value   = m.category_id;
    document.getElementById('eMatKelas').value = m.kelas;
    editQuill.root.innerHTML = m.content;
    openModal('editModal');
}

['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if(e.target.id===id) closeModal(id); });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
