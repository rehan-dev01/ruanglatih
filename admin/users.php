<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$user = getCurrentUser(); $db = getDB(); $pageTitle = 'Data Pengguna';

$kelasOpts   = getKelasOptions();
$tingkatList = ['X','XI','XII'];
$msg = ''; $msgType = 'success';

$activeTab = $_GET['tab'] ?? 'siswa'; // 'siswa' | 'guru'

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $tab = $_POST['active_tab'] ?? 'siswa';

    /* ======== SISWA ======== */
    if ($act === 'edit_siswa') {
        $id    = (int)$_POST['id'];
        $nisn  = trim($_POST['nisn']     ?? '');
        $absen = (int)($_POST['no_absen'] ?? 0);
        $kelas = trim($_POST['kelas']    ?? '');
        if ($nisn && (!ctype_digit($nisn) || strlen($nisn) !== 10)) {
            $msg = 'NISN harus 10 digit angka!'; $msgType = 'error';
        } else {
            $nisnVal  = $nisn ? "'". $db->real_escape_string($nisn) ."'" : 'NULL';
            $absenVal = $absen > 0 ? $absen : 'NULL';
            $kelasEsc = $db->real_escape_string($kelas);
            $db->query("UPDATE users SET nisn=$nisnVal,no_absen=$absenVal,kelas='$kelasEsc' WHERE id=$id AND role='user'");
            $msg = 'Data siswa berhasil diperbarui.';
        }
        header('Location: users.php?tab=siswa&msg='.urlencode($msg).'&type='.$msgType); exit;

    } elseif ($act === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$_SESSION['user_id']) $db->query("DELETE FROM users WHERE id=$id AND role='user'");
        header('Location: users.php?tab=siswa&msg='.urlencode('Siswa dihapus.')); exit;

    /* ======== GURU ======== */
    } elseif ($act === 'add_guru') {
        $name  = $db->real_escape_string(trim($_POST['name']  ?? ''));
        $email = $db->real_escape_string(trim($_POST['email'] ?? ''));
        $pass  = trim($_POST['password'] ?? '');
        if (!$name || !$email || !$pass) {
            $msg = 'Nama, email, dan password wajib diisi!'; $msgType = 'error';
        } else {
            $chk = $db->query("SELECT id FROM users WHERE email='$email' LIMIT 1");
            if ($chk && $chk->num_rows > 0) {
                $msg = 'Email sudah terdaftar!'; $msgType = 'error';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $hash = $db->real_escape_string($hash);
                $db->query("INSERT INTO users(name,email,password,role) VALUES('$name','$email','$hash','guru')");
                $msg = "Akun guru '$name' berhasil dibuat!";
            }
        }
        header('Location: users.php?tab=guru&msg='.urlencode($msg).'&type='.$msgType); exit;

    } elseif ($act === 'edit_guru') {
        $id   = (int)$_POST['id'];
        $name = $db->real_escape_string(trim($_POST['name']  ?? ''));
        $pass = trim($_POST['password'] ?? '');
        if ($name) {
            if ($pass) {
                $hash = $db->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
                $db->query("UPDATE users SET name='$name',password='$hash' WHERE id=$id AND role='guru'");
            } else {
                $db->query("UPDATE users SET name='$name' WHERE id=$id AND role='guru'");
            }
            $msg = 'Data guru berhasil diperbarui.';
        } else { $msg = 'Nama tidak boleh kosong!'; $msgType = 'error'; }
        header('Location: users.php?tab=guru&msg='.urlencode($msg).'&type='.$msgType); exit;

    } elseif ($act === 'delete_guru') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$_SESSION['user_id']) $db->query("DELETE FROM users WHERE id=$id AND role='guru'");
        header('Location: users.php?tab=guru&msg='.urlencode('Guru dihapus.')); exit;

    /* ======== PENUGASAN GURU ======== */
    } elseif ($act === 'add_assign') {
        $gid      = (int)$_POST['guru_id'];
        $cid      = (int)$_POST['category_id'];
        $kelasList = $_POST['kelas_list'] ?? [];   // array dari checkboxes

        if (!$gid || !$cid || empty($kelasList)) {
            $msg = 'Pilih kategori dan minimal 1 kelas!'; $msgType = 'error';
        } else {
            $added = 0; $skip = 0;
            foreach ($kelasList as $kl) {
                $kl = trim($kl); if (!$kl) continue;
                $klEsc = $db->real_escape_string($kl);
                $r = $db->query("INSERT IGNORE INTO guru_assignments(guru_id,category_id,kelas)
                                 VALUES($gid,$cid,'$klEsc')");
                ($db->affected_rows > 0) ? $added++ : $skip++;
            }
            $msg = "Berhasil menambahkan <strong>$added penugasan</strong>!"
                 . ($skip > 0 ? " ($skip sudah ada, dilewati)" : '');
        }
        header('Location: users.php?tab=guru&guru_id='.$gid.'&msg='.urlencode(strip_tags($msg)).'&type='.$msgType); exit;

    } elseif ($act === 'delete_assign') {
        $aid = (int)$_POST['assign_id'];
        $gid = (int)$_POST['guru_id'];
        $db->query("DELETE FROM guru_assignments WHERE id=$aid");
        $msg = 'Penugasan dihapus.';
        header('Location: users.php?tab=guru&guru_id='.$gid.'&msg='.urlencode($msg)); exit;
    }
}

if (!$msg && !empty($_GET['msg'])) { $msg = htmlspecialchars($_GET['msg']); $msgType = $_GET['type'] ?? 'success'; }

// ── SISWA data ────────────────────────────────────────────────────────────────
$q        = trim($_GET['q']       ?? '');
$fKelas   = trim($_GET['kelas']   ?? '');
$fTingkat = trim($_GET['tingkat'] ?? '');

$whereS = "WHERE u.role='user'";
if ($q)        $whereS .= " AND (u.name LIKE '%".$db->real_escape_string($q)."%' OR u.email LIKE '%".$db->real_escape_string($q)."%' OR u.nisn LIKE '%".$db->real_escape_string($q)."%')";
if ($fKelas)   $whereS .= " AND u.kelas='".$db->real_escape_string($fKelas)."'";
elseif ($fTingkat) $whereS .= " AND u.kelas LIKE '".$db->real_escape_string($fTingkat)." %'";

$users = $db->query("
    SELECT u.*,
        (SELECT COUNT(*)              FROM quiz_results WHERE user_id=u.id) as tq,
        (SELECT COALESCE(AVG(score),0) FROM quiz_results WHERE user_id=u.id) as avg_score
    FROM users u $whereS ORDER BY u.kelas, u.no_absen, u.name
");
$kelasList = [];
$kr = $db->query("SELECT DISTINCT kelas FROM users WHERE role='user' AND kelas IS NOT NULL AND kelas!='' ORDER BY kelas");
if ($kr) while ($kk = $kr->fetch_assoc()) $kelasList[] = $kk['kelas'];

$totalSiswa = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$belumKelas = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role='user' AND (kelas IS NULL OR kelas='')")->fetch_assoc()['c'];

// ── GURU data ─────────────────────────────────────────────────────────────────
$totalGuru = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE role='guru'")->fetch_assoc()['c'];
$gurus = $db->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM guru_assignments WHERE guru_id=u.id) as total_assign
    FROM users u WHERE u.role='guru' ORDER BY u.name
");
$guruArr = [];
while ($g = $gurus->fetch_assoc()) $guruArr[] = $g;

// Penugasan untuk guru yang sedang di-manage
$mgGuru = null; $mgAssignments = [];
$mgGid  = (int)($_GET['guru_id'] ?? 0);
if ($mgGid) {
    $mgR = $db->query("SELECT * FROM users WHERE id=$mgGid AND role='guru' LIMIT 1");
    if ($mgR) $mgGuru = $mgR->fetch_assoc();
    if ($mgGuru) $mgAssignments = getGuruAssignments($mgGid);
}

$categories = $db->query("SELECT * FROM categories ORDER BY name");
$catArr = [];
while ($c = $categories->fetch_assoc()) $catArr[] = $c;

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--navy-pale);border-radius:12px;padding:4px;width:fit-content">
    <a href="users.php?tab=siswa" class="btn <?= $activeTab==='siswa' ? 'btn-navy' : 'btn-outline' ?>" style="border-radius:8px;border:none">
        Data Siswa <span style="font-size:11px;background:<?= $activeTab==='siswa' ? 'rgba(255,255,255,.2)' : 'var(--navy-pale)' ?>;padding:1px 7px;border-radius:10px;margin-left:4px"><?= $totalSiswa ?></span>
    </a>
    <a href="users.php?tab=guru" class="btn <?= $activeTab==='guru' ? 'btn-navy' : 'btn-outline' ?>" style="border-radius:8px;border:none">
        Data Guru <span style="font-size:11px;background:<?= $activeTab==='guru' ? 'rgba(255,255,255,.2)' : 'var(--navy-pale)' ?>;padding:1px 7px;border-radius:10px;margin-left:4px"><?= $totalGuru ?></span>
    </a>
</div>

<?php if ($activeTab === 'siswa'): ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TAB SISWA                                                            -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div style="font-size:20px;font-weight:800;color:var(--navy);margin-bottom:4px">Data Siswa</div>
<div style="font-size:13px;color:var(--muted);margin-bottom:16px">Kelola NISN, Nomor Absen, dan Kelas siswa</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:18px">
    <div class="card card-pad" style="text-align:center"><div style="font-size:24px;font-weight:800;color:var(--navy)"><?= $totalSiswa ?></div><div style="font-size:11px;color:var(--muted);margin-top:2px">Total Siswa</div></div>
    <div class="card card-pad" style="text-align:center"><div style="font-size:24px;font-weight:800;color:var(--navy)"><?= count($kelasList) ?></div><div style="font-size:11px;color:var(--muted);margin-top:2px">Kelas Terdaftar</div></div>
    <div class="card card-pad" style="text-align:center"><div style="font-size:24px;font-weight:800;color:<?= $belumKelas>0?'#dc2626':'#059669' ?>"><?= $belumKelas ?></div><div style="font-size:11px;color:var(--muted);margin-top:2px">Belum Ada Kelas</div></div>
</div>

<div class="card card-pad" style="margin-bottom:16px">
<form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <input type="hidden" name="tab" value="siswa">
    <div style="flex:1;min-width:180px"><div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Cari Nama / Email / NISN</div>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-input" placeholder="Ketik untuk cari..."></div>
    <div style="min-width:130px"><div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Tingkat</div>
        <select name="tingkat" class="form-input form-select">
            <option value="">Semua</option>
            <?php foreach ($tingkatList as $t): ?><option value="<?= $t ?>" <?= $fTingkat===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
        </select></div>
    <div style="min-width:160px"><div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:4px">Kelas Spesifik</div>
        <select name="kelas" class="form-input form-select">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $kk): ?><option value="<?= htmlspecialchars($kk) ?>" <?= $fKelas===$kk?'selected':'' ?>><?= htmlspecialchars($kk) ?></option><?php endforeach; ?>
        </select></div>
    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-navy">Tampilkan</button>
        <?php if ($q||$fKelas||$fTingkat): ?><a href="users.php?tab=siswa" class="btn btn-outline">Reset</a><?php endif; ?>
    </div>
</form>
</div>

<div class="card" style="overflow:hidden"><div style="overflow-x:auto"><table class="data-table">
    <thead><tr><th>Siswa</th><th style="text-align:center">NISN</th><th style="text-align:center">No. Absen</th><th style="text-align:center">Kelas</th><th style="text-align:center">Quiz</th><th style="text-align:center">Avg</th><th style="text-align:center">Poin</th><th style="text-align:center">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$users||$users->num_rows===0): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">Tidak ada siswa ditemukan</td></tr>
    <?php else: while ($u=$users->fetch_assoc()): $avg=round((float)$u['avg_score'],1); $g=getGrade($avg); ?>
    <tr>
        <td><div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--navy-lt),var(--navy-dk));color:#fff;font-weight:700;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= getInitials($u['name']) ?></div>
            <div><div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($u['name']) ?></div><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></div></div>
        </div></td>
        <td style="text-align:center;font-size:12px;font-family:monospace;font-weight:600"><?= $u['nisn']?htmlspecialchars($u['nisn']):'<span style="color:var(--muted)">—</span>' ?></td>
        <td style="text-align:center;font-weight:700"><?= $u['no_absen']?:'<span style="color:var(--muted)">—</span>' ?></td>
        <td style="text-align:center"><?php if($u['kelas']): ?><span style="background:var(--navy-pale);color:var(--navy);font-size:11px;font-weight:700;padding:3px 10px;border-radius:6px"><?= htmlspecialchars($u['kelas']) ?></span><?php else: ?><span style="background:#fef3c7;color:#92400e;font-size:11px;font-weight:600;padding:3px 10px;border-radius:6px">Belum</span><?php endif; ?></td>
        <td style="text-align:center;font-weight:700"><?= $u['tq'] ?></td>
        <td style="text-align:center;font-weight:800;color:<?= $g['color'] ?>"><?= $avg ?> (<?= $g['label'] ?>)</td>
        <td style="text-align:center;font-weight:800;color:var(--navy)"><?= number_format((int)$u['total_points']) ?></td>
        <td><div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
            <button onclick='openEditSiswa(<?= json_encode(["id"=>$u["id"],"name"=>$u["name"],"nisn"=>$u["nisn"]??"","no_absen"=>$u["no_absen"]??"",'kelas'=>$u["kelas"]??""]) ?>)' class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)"><?= icon('pencil','0.9em') ?> Edit</button>
            <form method="POST" onsubmit="return confirm('Hapus siswa ini?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="btn btn-red btn-sm">Hapus</button></form>
        </div></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table></div></div>

<!-- Modal Edit Siswa -->
<div id="editSiswaModal" class="modal-backdrop hidden"><div class="modal-box" style="max-width:460px">
    <div class="modal-header"><div><div class="modal-title"><?= icon('pencil') ?> Edit Data Siswa</div><div class="modal-sub" id="editSiswaName"></div></div><button onclick="closeModal('editSiswaModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="edit_siswa"><input type="hidden" name="id" id="eSiswaId">
        <div class="form-group"><label class="form-label">NISN</label><input type="text" name="nisn" id="eSiswaNisn" class="form-input" placeholder="0087654321" maxlength="10"></div>
        <div class="form-group"><label class="form-label">Nomor Absen</label><input type="number" name="no_absen" id="eSiswaAbsen" class="form-input" placeholder="1" min="1" max="60"></div>
        <div class="form-group"><label class="form-label">Kelas</label>
            <select name="kelas" id="eSiswaKelas" class="form-input form-select"><option value="">-- Belum ada kelas --</option>
                <?php foreach ($kelasOpts as $ko): ?><option value="<?= htmlspecialchars($ko) ?>"><?= htmlspecialchars($ko) ?></option><?php endforeach; ?>
            </select></div>
        <div style="display:flex;gap:10px;margin-top:6px"><button type="button" onclick="closeModal('editSiswaModal')" class="btn btn-outline btn-full">Batal</button><button type="submit" class="btn btn-navy btn-full">Simpan</button></div>
    </form></div>
</div></div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TAB GURU                                                             -->
<!-- ══════════════════════════════════════════════════════════════════════ -->

<?php if ($mgGuru): ?>
<!-- ── Sub-panel: Kelola Penugasan Guru ── -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">
    <a href="users.php?tab=guru" style="font-size:13px;color:var(--muted);text-decoration:none">← Kembali ke Daftar Guru</a>
    <span style="color:var(--muted)">/</span>
    <span style="font-weight:700;color:var(--navy)"><?= htmlspecialchars($mgGuru['name']) ?></span>
    <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 10px;border-radius:10px;font-weight:600">Guru</span>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<!-- Penugasan saat ini -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">Penugasan Aktif</div>
    <?php if (!$mgAssignments): ?>
    <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">Belum ada penugasan. Tambah di form sebelah →</div>
    <?php else: foreach ($mgAssignments as $a): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:8px;height:8px;border-radius:50%;background:#047857;flex-shrink:0"></div>
            <div>
                <div style="font-size:13px;font-weight:700;color:var(--navy)"><?= htmlspecialchars($a['cat_name']) ?></div>
                <div style="font-size:11px;color:var(--muted)">Kelas <?= htmlspecialchars($a['kelas']) ?></div>
            </div>
        </div>
        <form method="POST" onsubmit="return confirm('Hapus penugasan ini?')">
            <input type="hidden" name="action"    value="delete_assign">
            <input type="hidden" name="assign_id" value="<?= $a['id'] ?>">
            <input type="hidden" name="guru_id"   value="<?= $mgGid ?>">
            <button type="submit" class="btn btn-red btn-sm">Hapus</button>
        </form>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Form Tambah Penugasan -->
<div class="card card-pad">
    <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:14px">Tambah Penugasan</div>
    <form method="POST" id="assignForm">
        <input type="hidden" name="action"  value="add_assign">
        <input type="hidden" name="guru_id" value="<?= $mgGid ?>">

        <!-- Kategori -->
        <div class="form-group">
            <label class="form-label">Kategori / Mata Pelajaran</label>
            <select name="category_id" id="assignCatSel" class="form-input form-select" required
                    onchange="renderAssignKelas()">
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($catArr as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Kelas multi-checkbox -->
        <div class="form-group">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:6px">
                <label class="form-label" style="margin:0">Pilih Kelas</label>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                    <button type="button" onclick="assignSelectAll()"
                        class="btn btn-sm" style="background:#047857;color:#fff;font-size:11px;padding:3px 9px">Pilih Semua</button>
                    <button type="button" onclick="assignClearAll()"
                        class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 9px">Kosongkan</button>
                </div>
            </div>

            <!-- Shortcut per tingkat -->
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px">
                <span style="font-size:11px;color:var(--muted);align-self:center">Tingkat:</span>
                <button type="button" onclick="assignByTingkat('X')"
                    class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 9px">Kelas X</button>
                <button type="button" onclick="assignByTingkat('XI')"
                    class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 9px">Kelas XI</button>
                <button type="button" onclick="assignByTingkat('XII')"
                    class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 9px">Kelas XII</button>
            </div>

            <div id="assignKelasWrap" style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#f8fafc;min-height:48px">
                <div id="assignKelasBox" style="display:flex;flex-wrap:wrap;gap:6px">
                    <span style="color:var(--muted);font-size:12px">← Pilih kategori dulu</span>
                </div>
            </div>

            <div id="assignCount" style="margin-top:6px;font-size:12px;color:#047857;font-weight:600"></div>
        </div>

        <button type="submit" id="assignSubmitBtn"
            class="btn btn-full" style="background:#047857;color:#fff">Tambah Penugasan</button>
    </form>
</div>
</div>

<?php
// Kelas yang sudah ada untuk guru ini (untuk filter "sudah ditugaskan")
$existingAssign = [];
foreach ($mgAssignments as $a) {
    $existingAssign[(int)$a['category_id']][] = $a['kelas'];
}
?>
<script>
const ALL_KELAS = <?= json_encode($kelasOpts, JSON_UNESCAPED_UNICODE) ?>;
const EXISTING  = <?= json_encode($existingAssign, JSON_UNESCAPED_UNICODE) ?>;

function renderAssignKelas() {
    const catId = parseInt(document.getElementById('assignCatSel').value);
    const box   = document.getElementById('assignKelasBox');
    box.innerHTML = '';
    assignUpdateCount();

    if (!catId) {
        box.innerHTML = '<span style="color:var(--muted);font-size:12px">← Pilih kategori dulu</span>';
        return;
    }

    const already = EXISTING[catId] || [];

    // Kelompok per tingkat
    const grp = {X:[], XI:[], XII:[], Lainnya:[]};
    ALL_KELAS.forEach(k => {
        if (k.startsWith('XII'))     grp.XII.push(k);
        else if (k.startsWith('XI')) grp.XI.push(k);
        else if (k.startsWith('X'))  grp.X.push(k);
        else grp.Lainnya.push(k);
    });

    Object.entries(grp).forEach(([t, list]) => {
        if (!list.length) return;
        const hdr = document.createElement('div');
        hdr.style = 'width:100%;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;margin:6px 0 3px';
        hdr.textContent = 'Kelas ' + t;
        box.appendChild(hdr);

        list.sort().forEach(k => {
            const isDone = already.includes(k);
            const lbl = document.createElement('label');
            lbl.title = isDone ? 'Sudah ditugaskan' : '';
            lbl.style = `display:flex;align-items:center;gap:5px;border-radius:7px;padding:5px 10px;cursor:${isDone?'default':'pointer'};
                         font-size:12px;font-weight:500;transition:.15s;
                         background:${isDone?'#f3f4f6':'#fff'};border:1px solid ${isDone?'#e5e7eb':'var(--border)'};
                         color:${isDone?'#9ca3af':'inherit'}`;

            const cb = document.createElement('input');
            cb.type     = 'checkbox';
            cb.name     = 'kelas_list[]';
            cb.value    = k;
            cb.disabled = isDone;
            cb.style    = 'accent-color:#047857';
            cb.onchange = () => {
                lbl.style.borderColor = cb.checked ? '#047857' : 'var(--border)';
                lbl.style.background  = cb.checked ? '#f0fdf4' : '#fff';
                assignUpdateCount();
            };

            const txt = document.createElement('span');
            txt.textContent = k + (isDone ? ' ✓' : '');
            lbl.appendChild(cb);
            lbl.appendChild(txt);
            box.appendChild(lbl);
        });
    });

    assignUpdateCount();
}

function assignSelectAll() {
    document.querySelectorAll('#assignKelasBox input:not(:disabled)').forEach(cb => {
        cb.checked = true; cb.dispatchEvent(new Event('change'));
    });
}
function assignClearAll() {
    document.querySelectorAll('#assignKelasBox input:not(:disabled)').forEach(cb => {
        cb.checked = false; cb.dispatchEvent(new Event('change'));
    });
}
function assignByTingkat(t) {
    document.querySelectorAll('#assignKelasBox input:not(:disabled)').forEach(cb => {
        const match = t === 'XII' ? cb.value.startsWith('XII') :
                      t === 'XI'  ? cb.value.startsWith('XI') && !cb.value.startsWith('XII') :
                                    cb.value.startsWith('X')  && !cb.value.startsWith('XI') && !cb.value.startsWith('XII');
        if (match) { cb.checked = true; cb.dispatchEvent(new Event('change')); }
    });
}
function assignUpdateCount() {
    const n   = document.querySelectorAll('#assignKelasBox input:checked').length;
    const el  = document.getElementById('assignCount');
    const btn = document.getElementById('assignSubmitBtn');
    el.textContent = n > 0 ? `${n} kelas dipilih` : '';
    btn.textContent = n > 1 ? `Tambah ${n} Penugasan Sekaligus` : (n === 1 ? 'Tambah 1 Penugasan' : 'Tambah Penugasan');
}

document.getElementById('assignForm').addEventListener('submit', function(e) {
    const n = document.querySelectorAll('#assignKelasBox input:checked').length;
    if (n === 0) { e.preventDefault(); alert('Pilih minimal 1 kelas!'); }
});
</script>

<?php else: ?>
<!-- ── Daftar semua Guru ── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Data Guru</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">Kelola akun guru dan penugasan materi</div>
    </div>
    <button onclick="openModal('addGuruModal')" class="btn" style="background:#047857;color:#fff">+ Tambah Guru</button>
</div>

<div class="card" style="overflow:hidden"><div style="overflow-x:auto"><table class="data-table">
    <thead><tr><th>Guru</th><th style="text-align:center">Penugasan</th><th style="text-align:center">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$guruArr): ?>
    <tr><td colspan="3" style="text-align:center;padding:40px;color:var(--muted)">
        <div style="font-weight:600">Belum ada guru terdaftar</div>
    </td></tr>
    <?php else: foreach ($guruArr as $g): $assigns = getGuruAssignments((int)$g['id']); ?>
    <tr>
        <td><div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#047857,#059669);color:#fff;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= getInitials($g['name']) ?></div>
            <div>
                <div style="font-weight:700;font-size:13px;color:var(--navy)"><?= htmlspecialchars($g['name']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($g['email']) ?></div>
            </div>
        </div></td>
        <td style="text-align:center">
            <?php if (!$assigns): ?>
            <span style="font-size:11px;color:#dc2626;font-weight:600">Belum ada penugasan</span>
            <?php else: foreach ($assigns as $a): ?>
            <span style="display:inline-block;margin:2px;font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:5px;font-weight:600">
                <?= htmlspecialchars($a['cat_name']) ?> / <?= htmlspecialchars($a['kelas']) ?>
            </span>
            <?php endforeach; endif; ?>
        </td>
        <td>
            <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                <a href="?tab=guru&guru_id=<?= $g['id'] ?>" class="btn btn-sm" style="background:#f0fdf4;color:#047857">Penugasan</a>
                <button onclick='openEditGuruModal(<?= json_encode(["id"=>$g["id"],"name"=>$g["name"],"email"=>$g["email"]]) ?>)' class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus guru ini? Semua penugasan juga akan dihapus.')">
                    <input type="hidden" name="action" value="delete_guru">
                    <input type="hidden" name="id"     value="<?= $g['id'] ?>">
                    <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table></div></div>

<!-- Modal Tambah Guru -->
<div id="addGuruModal" class="modal-backdrop hidden"><div class="modal-box" style="max-width:440px">
    <div class="modal-header"><div><div class="modal-title">Tambah Akun Guru</div></div><button onclick="closeModal('addGuruModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="add_guru">
        <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="name" class="form-input" placeholder="Mis: Pak Andi Matematika" required></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input" placeholder="guru@sekolah.com" required></div>
        <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-input" placeholder="Min. 8 karakter" minlength="6" required></div>
        <div style="font-size:12px;color:var(--muted);background:var(--navy-pale);border-radius:8px;padding:10px;margin-bottom:12px">
            💡 Setelah membuat akun, klik <strong>Penugasan</strong> untuk mengatur materi yang diajar guru ini.
        </div>
        <div style="display:flex;gap:10px"><button type="button" onclick="closeModal('addGuruModal')" class="btn btn-outline btn-full">Batal</button><button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Buat Akun</button></div>
    </form></div>
</div></div>

<!-- Modal Edit Guru -->
<div id="editGuruModal" class="modal-backdrop hidden"><div class="modal-box" style="max-width:440px">
    <div class="modal-header"><div><div class="modal-title">Edit Data Guru</div><div class="modal-sub" id="eGuruEmail" style="color:var(--muted)"></div></div><button onclick="closeModal('editGuruModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="edit_guru"><input type="hidden" name="id" id="eGuruId">
        <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="name" id="eGuruName" class="form-input" required></div>
        <div class="form-group"><label class="form-label">Password Baru <span class="form-hint">Kosongkan jika tidak ingin mengubah</span></label><input type="password" name="password" class="form-input" placeholder="Password baru..."></div>
        <div style="display:flex;gap:10px"><button type="button" onclick="closeModal('editGuruModal')" class="btn btn-outline btn-full">Batal</button><button type="submit" class="btn btn-navy btn-full">Simpan</button></div>
    </form></div>
</div></div>

<?php endif; /* end: mgGuru check */ ?>
<?php endif; /* end: tab guru */ ?>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden');    document.body.style.overflow=''; }

function openEditSiswa(s) {
    document.getElementById('eSiswaId').value    = s.id;
    document.getElementById('eSiswaNisn').value  = s.nisn || '';
    document.getElementById('eSiswaAbsen').value = s.no_absen || '';
    document.getElementById('editSiswaName').textContent = s.name;
    document.getElementById('eSiswaKelas').value = s.kelas || '';
    openModal('editSiswaModal');
}
function openEditGuruModal(g) {
    document.getElementById('eGuruId').value    = g.id;
    document.getElementById('eGuruName').value  = g.name;
    document.getElementById('eGuruEmail').textContent = g.email;
    openModal('editGuruModal');
}
document.querySelectorAll('.modal-backdrop').forEach(el => {
    el.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
