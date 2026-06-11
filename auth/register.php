<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (isLoggedIn()) { header('Location: '.APP_URL.'/dashboard.php'); exit; }

$error = $success = '';
$kelasOpts = getKelasOptions();

// Bangun daftar tingkat & jurusan dari data kelas yang ada di database
$tingkatList = []; $jurusanList = [];
foreach ($kelasOpts as $k) {
    $parts = explode(' ', trim($k));
    if (isset($parts[0]) && !in_array($parts[0], $tingkatList)) $tingkatList[] = $parts[0];
    if (isset($parts[1]) && !in_array($parts[1], $jurusanList)) $jurusanList[] = $parts[1];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $pass    = $_POST['password']     ?? '';
    $conf    = $_POST['confirm']      ?? '';
    $nisn    = trim($_POST['nisn']    ?? '');
    $absen   = (int)($_POST['no_absen'] ?? 0);
    // Gabungkan tingkat + jurusan + rombel menjadi format "X IPA 1"
    $tingkat = trim($_POST['tingkat'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');
    $rombel  = trim($_POST['rombel']  ?? '');
    $kelas   = trim("$tingkat $jurusan $rombel");

    if (!$name||!$email||!$pass) { $error='Semua kolom wajib diisi!'; }
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $error='Format email tidak valid!'; }
    elseif (strlen($pass)<6) { $error='Password minimal 6 karakter!'; }
    elseif ($pass!==$conf)   { $error='Password tidak cocok!'; }
    elseif ($nisn && !ctype_digit($nisn)) { $error='NISN harus berupa angka!'; }
    elseif (!$kelas) { $error='Pilih kelas!'; }
    else {
        $db  = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $chk->bind_param('s',$email); $chk->execute();
        if ($chk->get_result()->num_rows>0) { $error='Email sudah terdaftar!'; }
        elseif ($nisn) {
            $cn=$db->prepare("SELECT id FROM users WHERE nisn=? LIMIT 1");
            $cn->bind_param('s',$nisn); $cn->execute();
            if ($cn->get_result()->num_rows>0) $error='NISN sudah terdaftar!';
        }
        if (!$error) {
            $hash = password_hash($pass,PASSWORD_DEFAULT);
            $en=$db->real_escape_string($name); $em=$db->real_escape_string($email);
            $ek=$db->real_escape_string($kelas);
            $nisnVal = $nisn ? "'{$nisn}'" : 'NULL';
            $absenVal = $absen > 0 ? $absen : 'NULL';
            $db->query("INSERT INTO users(name,email,password,role,nisn,no_absen,kelas) VALUES('$en','$em','$hash','user',$nisnVal,$absenVal,'$ek')");
            $success='Akun berhasil dibuat! Silakan login.';
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.40.0/tabler-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
body{min-height:100vh;display:flex;background:#eef2f7}
.auth-left{width:420px;flex-shrink:0;background:linear-gradient(155deg,#131f3a 0%,#1e3055 45%,#2d4a7a 100%);padding:48px 44px;display:flex;flex-direction:column;position:relative;overflow:hidden}
.auth-left::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(245,158,11,.08);top:-80px;right:-80px}
.al-logo{display:flex;align-items:center;gap:10px;margin-bottom:48px;position:relative;z-index:1}
.al-logo-name{font-weight:800;font-size:17px;color:#fff}
.al-logo-sub{font-size:11px;color:rgba(255,255,255,.5);margin-top:1px}
.al-body{flex:1;display:flex;flex-direction:column;justify-content:center;position:relative;z-index:1}
.al-title{font-size:28px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:12px}
.al-title span{color:#f59e0b}
.al-sub{font-size:13px;color:rgba(255,255,255,.6);line-height:1.7}
.auth-right{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 28px;overflow-y:auto}
.auth-card{background:#fff;border-radius:24px;padding:32px 36px;width:100%;max-width:480px;box-shadow:0 8px 40px rgba(30,48,85,.1)}
.auth-card-title{font-size:21px;font-weight:800;color:#131f3a;margin-bottom:4px}
.auth-card-sub{font-size:13px;color:#64748b;margin-bottom:22px}
.form-group{margin-bottom:13px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}

.form-label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px}
.form-hint{font-size:10px;color:#94a3b8;font-weight:400;margin-left:4px}
.form-input{width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s;background:#fff}
.form-input:focus{border-color:#1e3055;box-shadow:0 0 0 3px rgba(30,48,85,.08)}
.form-section{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin:16px 0 10px;padding-bottom:6px;border-bottom:1px solid #f1f5f9}
.error-box{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:10px 13px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.success-box{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 13px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:14px}
.btn-submit{width:100%;padding:13px;background:#f59e0b;color:#1a1a1a;font-weight:800;font-size:14px;border:none;border-radius:12px;cursor:pointer;transition:all .15s;margin-top:6px}
.btn-submit:hover{background:#d97706;transform:translateY(-1px)}
.auth-link{text-align:center;font-size:13px;color:#64748b;margin-top:16px}
.auth-link a{color:#1e3055;font-weight:700}
@media(max-width:768px){.auth-left{display:none}.auth-right{padding:20px 16px}.form-row,.form-row-3{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="auth-left">
    <div class="al-logo">
        <div><div class="al-logo-name">RuangLatih</div><div class="al-logo-sub">Platform Belajar Interaktif</div></div>
    </div>
    <div class="al-body">
        <div class="al-title">Bergabung &<br><span>mulai belajar</span></div>
        <div class="al-sub">Daftar gratis dan akses semua materi, quiz interaktif, serta flashcard sesuai kelasmu.</div>
    </div>
</div>
<div class="auth-right">
<div class="auth-card">
    <div class="auth-card-title">Buat Akun Baru</div>
    <div class="auth-card-sub">Sudah punya akun? <a href="<?= APP_URL ?>/auth/login.php">Masuk di sini</a></div>

    <?php if($error): ?>
    <div class="error-box"><i class="ti ti-alert-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
    <div class="success-box"><i class="ti ti-circle-check"></i> <?= $success ?>
        <a href="<?= APP_URL ?>/auth/login.php" style="font-weight:700;color:#065f46"> Login sekarang →</a>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-section">Informasi Akun</div>
        <div class="form-group">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" class="form-input" placeholder="Nama lengkap kamu"
                value="<?= htmlspecialchars($_POST['name']??'') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" placeholder="email@example.com"
                value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Password <span class="form-hint">min. 6 karakter</span></label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm" class="form-input" placeholder="••••••••" required>
            </div>
        </div>

        <div class="form-section">Data Siswa</div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">NISN <span class="form-hint">angka</span></label>
                <input type="text" name="nisn" class="form-input" placeholder="0087654321"
                    value="<?= htmlspecialchars($_POST['nisn']??'') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">No. Absen</label>
                <input type="number" name="no_absen" class="form-input" placeholder="1"
                    min="1" max="60" value="<?= htmlspecialchars($_POST['no_absen']??'') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Kelas <span class="form-hint">Pilih tingkat, jurusan, dan rombel</span></label>
            <div class="form-row-3">
                <select name="tingkat" class="form-input" required>
                    <option value="">Tingkat</option>
                    <?php foreach($tingkatList as $t): ?>
                    <option value="<?=$t?>" <?=($_POST['tingkat']??'')===$t?'selected':''?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
                <select name="jurusan" class="form-input" required>
                    <option value="">Jurusan</option>
                    <?php foreach($jurusanList as $j): ?>
                    <option value="<?=$j?>" <?=($_POST['jurusan']??'')===$j?'selected':''?>><?=$j?></option>
                    <?php endforeach; ?>
                </select>
                <select name="rombel" class="form-input">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <option value="<?=$i?>" <?=(int)($_POST['rombel']??1)===$i?'selected':''?>><?=$i?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="font-size:11px;color:#94a3b8;margin-top:5px">
                <i class="ti ti-info-circle"></i> Contoh: X + IPA + 1 = "X IPA 1"
            </div>
        </div>

        <button type="submit" class="btn-submit">Daftar Sekarang →</button>
    </form>
    <div class="auth-link">Sudah punya akun? <a href="<?= APP_URL ?>/auth/login.php">Masuk</a></div>
</div>
</div>
</body>
</html>
