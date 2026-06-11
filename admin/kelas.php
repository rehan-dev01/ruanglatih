<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$user = getCurrentUser(); $db = getDB(); $pageTitle = 'Kelola Kelas';

$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    /* Tambah kelas */
    if ($act === 'add') {
        $nama = trim($_POST['nama'] ?? '');
        if (!$nama) {
            $msg = 'Nama kelas tidak boleh kosong!'; $msgType = 'error';
        } else {
            $n = $db->real_escape_string($nama);
            $cek = $db->query("SELECT id FROM kelas WHERE nama='$n' LIMIT 1");
            if ($cek && $cek->num_rows > 0) {
                $msg = "Kelas \"$nama\" sudah ada!"; $msgType = 'error';
            } else {
                $db->query("INSERT INTO kelas(nama) VALUES('$n')");
                $msg = "Kelas \"$nama\" berhasil ditambahkan.";
            }
        }
        header("Location: kelas.php?msg=" . urlencode($msg) . "&type=$msgType"); exit;

    /* Tambah dari dropdown (generate nama otomatis) */
    } elseif ($act === 'add_generate') {
        $tingkat = trim($_POST['tingkat'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');
        $rombel  = (int)($_POST['rombel'] ?? 1);
        if (!$tingkat || !$jurusan) {
            $msg = 'Pilih tingkat dan jurusan!'; $msgType = 'error';
            header("Location: kelas.php?msg=" . urlencode($msg) . "&type=$msgType"); exit;
        }
        $nama = "$tingkat $jurusan $rombel";
        $n    = $db->real_escape_string($nama);
        $cek  = $db->query("SELECT id FROM kelas WHERE nama='$n' LIMIT 1");
        if ($cek && $cek->num_rows > 0) {
            $msg = "Kelas \"$nama\" sudah ada!"; $msgType = 'error';
        } else {
            $db->query("INSERT INTO kelas(nama) VALUES('$n')");
            $msg = "Kelas \"$nama\" berhasil ditambahkan.";
        }
        header("Location: kelas.php?msg=" . urlencode($msg) . "&type=$msgType"); exit;

    /* Hapus kelas */
    } elseif ($act === 'delete') {
        $id    = (int)$_POST['id'];
        $force = ($_POST['force'] ?? '') === '1';
        $kRow  = $db->query("SELECT nama FROM kelas WHERE id=$id LIMIT 1")->fetch_assoc();
        if ($kRow) {
            $nama = $kRow['nama'];
            $jml  = (int)$db->query("SELECT COUNT(*) as c FROM users WHERE kelas='{$db->real_escape_string($nama)}'")->fetch_assoc()['c'];
            if ($jml > 0 && !$force) {
                $msg = "Kelas \"$nama\" masih memiliki $jml siswa. Hapus paksa?";
                $msgType = 'confirm';
                header("Location: kelas.php?msg=" . urlencode($msg) . "&type=$msgType&confirm_id=$id&confirm_nama=" . urlencode($nama)); exit;
            }
            if ($force) {
                // Reset kelas siswa yang terkena
                $n2 = $db->real_escape_string($nama);
                $db->query("UPDATE users SET kelas=NULL WHERE kelas='$n2'");
            }
            $db->query("DELETE FROM kelas WHERE id=$id");
            $msg = "Kelas \"$nama\" dihapus." . ($force ? " $jml siswa direset kelasnya." : '');
        }
        header("Location: kelas.php?msg=" . urlencode($msg)); exit;
    }
}

if (!$msg && !empty($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    $msgType = $_GET['type'] ?? 'success';
}
$confirmId   = (int)($_GET['confirm_id']   ?? 0);
$confirmNama = htmlspecialchars($_GET['confirm_nama'] ?? '');

// Data kelas + jumlah siswa
$kelasList = $db->query("
    SELECT k.*, 
        (SELECT COUNT(*) FROM users WHERE kelas=k.nama AND role='user') as jml_siswa,
        (SELECT COUNT(*) FROM materials WHERE kelas=k.nama) as jml_materi
    FROM kelas k ORDER BY k.nama
");

$tingkatList = ['X','XI','XII'];
$jurusanList = ['IPA','IPS','Bahasa','RPL','TKJ','MM','AKL','BDP','OTKP'];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg && $msgType !== 'confirm'): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- Konfirmasi hapus paksa -->
<?php if ($msgType === 'confirm' && $confirmId): ?>
<div style="background:#fef3c7;border:1.5px solid #f59e0b;border-radius:12px;padding:16px 20px;margin-bottom:16px">
    <div style="font-weight:700;color:#92400e;margin-bottom:10px"><?= $msg ?></div>
    <div style="display:flex;gap:10px">
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id"    value="<?= $confirmId ?>">
            <input type="hidden" name="force"  value="1">
            <button type="submit" class="btn btn-red btn-sm">Ya, Hapus &amp; Reset Siswa</button>
        </form>
        <a href="kelas.php" class="btn btn-outline btn-sm">Batal</a>
    </div>
</div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Kelas</div>
        <div style="font-size:13px;color:var(--muted);margin-top:2px">Tambah atau hapus kelas yang tersedia di sistem</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">

<!-- Tabel Kelas -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">
        Daftar Kelas (<?= $kelasList ? $kelasList->num_rows : 0 ?>)
    </div>
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama Kelas</th>
                <th style="text-align:center">Jumlah Siswa</th>
                <th style="text-align:center">Materi Terkait</th>
                <th style="text-align:center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$kelasList || $kelasList->num_rows === 0): ?>
        <tr>
            <td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">
                <div style="font-weight:600;margin-bottom:6px">Belum ada kelas</div>
                <div style="font-size:12px">Tambah kelas menggunakan form di samping</div>
            </td>
        </tr>
        <?php else: $no = 1; while ($k = $kelasList->fetch_assoc()): ?>
        <tr>
            <td style="color:var(--muted);font-size:12px"><?= $no++ ?></td>
            <td>
                <span style="background:var(--navy-pale);color:var(--navy);font-size:13px;font-weight:700;padding:4px 14px;border-radius:8px">
                    <?= htmlspecialchars($k['nama']) ?>
                </span>
            </td>
            <td style="text-align:center">
                <?php if ($k['jml_siswa'] > 0): ?>
                <span style="font-weight:700;color:var(--navy)"><?= $k['jml_siswa'] ?></span>
                <span style="font-size:11px;color:var(--muted)"> siswa</span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:12px">—</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center">
                <?php if ($k['jml_materi'] > 0): ?>
                <span style="font-weight:700;color:var(--navy)"><?= $k['jml_materi'] ?></span>
                <span style="font-size:11px;color:var(--muted)"> materi</span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:12px">—</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center">
                <form method="POST" onsubmit="return confirm('Hapus kelas <?= htmlspecialchars($k['nama']) ?>?<?= $k['jml_siswa'] > 0 ? " \\n\\nPeringatan: ada {$k['jml_siswa']} siswa di kelas ini!" : '' ?>')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $k['id'] ?>">
                    <button type="submit" class="btn btn-red btn-sm">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Form Tambah -->
<div style="display:flex;flex-direction:column;gap:14px">

    <!-- Form: Generate dari dropdown -->
    <div class="card card-pad">
        <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:14px">Tambah dari Pilihan</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_generate">
            <div class="form-group">
                <label class="form-label">Tingkat</label>
                <select name="tingkat" class="form-input form-select" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($tingkatList as $t): ?>
                    <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jurusan / Program</label>
                <select name="jurusan" class="form-input form-select" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($jurusanList as $j): ?>
                    <option value="<?= $j ?>"><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Rombel (Nomor Kelas)</label>
                <select name="rombel" class="form-input form-select">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="background:var(--navy-pale);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--navy);margin-bottom:14px">
                Contoh hasil: <strong id="previewKelas">X IPA 1</strong>
            </div>
            <button type="submit" class="btn btn-navy btn-full">Tambah Kelas</button>
        </form>
    </div>

    <!-- Form: Nama manual -->
    <div class="card card-pad">
        <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:6px">Tambah Manual</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:14px">Ketik nama kelas secara langsung</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Nama Kelas</label>
                <input type="text" name="nama" class="form-input"
                    placeholder="Contoh: XII IPA 3"
                    maxlength="30" required>
            </div>
            <button type="submit" class="btn btn-outline btn-full">Tambah</button>
        </form>
    </div>

</div>
</div>

<script>
// Live preview nama kelas dari dropdown
const t = document.querySelector('select[name="tingkat"]');
const j = document.querySelector('select[name="jurusan"]');
const r = document.querySelector('select[name="rombel"]');
const prev = document.getElementById('previewKelas');
function updatePreview() {
    const tv = t?.value || 'X';
    const jv = j?.value || 'IPA';
    const rv = r?.value || '1';
    if (prev) prev.textContent = (t?.value && j?.value) ? `${tv} ${jv} ${rv}` : '—';
}
[t, j, r].forEach(el => el?.addEventListener('change', updatePreview));
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
