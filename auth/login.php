<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi!';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['role']    = $u['role'];
            updateStreak($u['id']);
            header('Location: ' . APP_URL . '/dashboard.php'); exit;
        } else { $error = 'Email atau password salah!'; }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
body{min-height:100vh;display:flex;background:#eef2f7}
.auth-left{
    width:420px;flex-shrink:0;
    background:linear-gradient(155deg,#131f3a 0%,#1e3055 45%,#2d4a7a 100%);
    padding:48px 44px;display:flex;flex-direction:column;
    position:relative;overflow:hidden;
}
.auth-left::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(245,158,11,.08);top:-80px;right:-80px}
.auth-left::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.04);bottom:-40px;left:-40px}
.al-logo{display:flex;align-items:center;gap:10px;margin-bottom:60px;position:relative;z-index:1}
.al-logo-name{font-weight:800;font-size:18px;color:#fff}
.al-logo-sub{font-size:11px;color:rgba(255,255,255,.5);margin-top:1px}
.al-body{flex:1;display:flex;flex-direction:column;justify-content:center;position:relative;z-index:1}
.al-title{font-size:30px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:12px}
.al-title span{color:#f59e0b}
.al-sub{font-size:14px;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:40px}
.al-stat{display:flex;align-items:flex-start;gap:0;margin-bottom:18px;padding-left:0}
.al-stat-val{font-weight:700;font-size:14px;color:#fff;margin-bottom:2px}
.al-stat-lbl{font-size:12px;color:rgba(255,255,255,.5)}
.al-footer{font-size:11px;color:rgba(255,255,255,.3);margin-top:auto;position:relative;z-index:1}
.auth-right{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 32px}
.auth-card{background:#fff;border-radius:24px;padding:40px;width:100%;max-width:400px;box-shadow:0 8px 40px rgba(30,48,85,.1)}
.auth-card-title{font-size:24px;font-weight:800;color:#131f3a;margin-bottom:4px}
.auth-card-sub{font-size:13px;color:#64748b;margin-bottom:28px}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.form-input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s}
.form-input:focus{border-color:#1e3055;box-shadow:0 0 0 3px rgba(30,48,85,.08)}
.error-box{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:18px}
.btn-submit{width:100%;padding:13px;background:#f59e0b;color:#1a1a1a;font-weight:800;font-size:14px;border:none;border-radius:12px;cursor:pointer;transition:background .15s;margin-top:4px}
.btn-submit:hover{background:#d97706}
.auth-link{text-align:center;font-size:13px;color:#64748b;margin-top:18px}
.auth-link a{color:#1e3055;font-weight:700}
@media(max-width:768px){.auth-left{display:none}.auth-right{padding:24px 16px}}
</style>
</head>
<body>

<!-- Kiri: Branding -->
<div class="auth-left">
    <!-- Logo: teks saja, tanpa kotak ikon -->
    <div class="al-logo">
        <div>
            <div class="al-logo-name">RuangLatih</div>
            <div class="al-logo-sub">Platform Belajar Interaktif</div>
        </div>
    </div>

    <div class="al-body">
        <div class="al-title">Belajar lebih<br><span>efektif &amp; terukur</span></div>
        <div class="al-sub">Platform latihan soal interaktif untuk siswa. Belajar materi, kerjakan quiz, dan pantau progressmu setiap hari.</div>

        <!-- Fitur: teks saja, tanpa kotak ikon -->
        <div class="al-stat">
            <div>
                <div class="al-stat-val">Quiz Interaktif</div>
                <div class="al-stat-lbl">Dengan timer &amp; feedback real-time</div>
            </div>
        </div>
        <div class="al-stat">
            <div>
                <div class="al-stat-val">Progress Tracking</div>
                <div class="al-stat-lbl">Grafik perkembangan belajarmu</div>
            </div>
        </div>
        <div class="al-stat">
            <div>
                <div class="al-stat-val">Leaderboard</div>
                <div class="al-stat-lbl">Bersaing &amp; raih peringkat terbaik</div>
            </div>
        </div>
    </div>

    <div class="al-footer">&copy; 2026 RuangLatih. All rights reserved.</div>
</div>

<!-- Kanan: Form Login -->
<div class="auth-right">
    <div class="auth-card">
        <div class="auth-card-title">Selamat Datang!</div>
        <div class="auth-card-sub">Masuk untuk melanjutkan belajar</div>

        <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="emailInput" class="form-input"
                    placeholder="email@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autocomplete="email">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="passInput" class="form-input"
                    placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit">Masuk &rarr;</button>
        </form>

        <div class="auth-link">
            Belum punya akun? <a href="<?= APP_URL ?>/auth/register.php">Daftar Sekarang</a>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('emailInput').value = email;
    document.getElementById('passInput').value  = pass;
}
</script>
</body>
</html>
