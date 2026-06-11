<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn()  { return isset($_SESSION['user_id']); }
function isAdmin()     { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isGuru()      { return isset($_SESSION['role']) && $_SESSION['role'] === 'guru'; }
function isGuruOrAdmin() { return isAdmin() || isGuru(); }

function requireLogin() {
    if (!isLoggedIn()) { header('Location: '.APP_URL.'/auth/login.php'); exit; }
}
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) { header('Location: '.APP_URL.'/dashboard.php'); exit; }
}
function requireGuruOrAdmin() {
    requireLogin();
    if (!isGuruOrAdmin()) { header('Location: '.APP_URL.'/dashboard.php'); exit; }
}

// ── Guru helpers ─────────────────────────
// Ambil semua penugasan (category+kelas) milik satu guru
function getGuruAssignments(int $guru_id): array {
    $db  = getDB(); $id = (int)$guru_id;
    $res = $db->query("
        SELECT ga.id, ga.category_id, ga.kelas, c.name AS cat_name, c.icon AS cat_icon
        FROM guru_assignments ga
        JOIN categories c ON ga.category_id = c.id
        WHERE ga.guru_id = $id
        ORDER BY c.name, ga.kelas
    ");
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

// SQL fragment: filter materi yang bisa dikelola oleh guru (atau semua jika admin)
function getGuruMaterialCondition(string $alias = 'm'): string {
    if (isAdmin()) return '1=1';
    $uid = (int)$_SESSION['user_id'];
    return "EXISTS (
        SELECT 1 FROM guru_assignments ga
        WHERE ga.guru_id = {$uid}
          AND ga.category_id = {$alias}.category_id
          AND ({$alias}.kelas = 'Semua' OR ga.kelas = {$alias}.kelas OR ga.kelas = 'Semua')
    )";
}

// SQL fragment: kelas yang diajar oleh guru (untuk rekap nilai)
function getGuruKelasCondition(string $alias = 'u'): string {
    if (isAdmin()) return '1=1';
    $uid = (int)$_SESSION['user_id'];
    return "{$alias}.kelas IN (
        SELECT DISTINCT kelas FROM guru_assignments WHERE guru_id = {$uid}
    )";
}

// Cek apakah guru boleh mengelola material tertentu
function canGuruManageMaterial(int $material_id): bool {
    if (isAdmin()) return true;
    $db  = getDB(); $uid = (int)$_SESSION['user_id']; $mid = (int)$material_id;
    $res = $db->query("
        SELECT 1 FROM materials m
        WHERE m.id = $mid
          AND EXISTS (
              SELECT 1 FROM guru_assignments ga
              WHERE ga.guru_id = $uid
                AND ga.category_id = m.category_id
                AND (m.kelas = 'Semua' OR ga.kelas = m.kelas OR ga.kelas = 'Semua')
          )
        LIMIT 1
    ");
    return $res && $res->num_rows > 0;
}
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB(); $id = (int)$_SESSION['user_id'];
    $r  = $db->query("SELECT * FROM users WHERE id=$id LIMIT 1");
    return $r ? $r->fetch_assoc() : null;
}
function clean($v)    { return htmlspecialchars(strip_tags(trim($v))); }
function safeInt($v)  { return (int)$v; }

// ── Streak ──────────────────────────────
function updateStreak($uid) {
    $db=$getDB=getDB(); $uid=(int)$uid; $today=date('Y-m-d');
    $db->query("INSERT IGNORE INTO streak_log(user_id,log_date) VALUES($uid,'$today')");
    $s=calculateStreak($uid);
    $db->query("UPDATE users SET streak=$s,last_active='$today' WHERE id=$uid");
    return $s;
}
function calculateStreak($uid) {
    $db=getDB(); $uid=(int)$uid; $streak=0; $d=date('Y-m-d');
    while(true){
        $r=$db->query("SELECT id FROM streak_log WHERE user_id=$uid AND log_date='$d' LIMIT 1");
        if($r&&$r->num_rows>0){$streak++;$d=date('Y-m-d',strtotime($d.' -1 day'));}else break;
    }
    return $streak;
}

// ── Kelas helpers ────────────────────────
// Ambil tingkat dari kelas (misal "X IPA 1" -> "X")
function getTingkat(?string $kelas): string {
    if(!$kelas) return '';
    return explode(' ', trim($kelas))[0] ?? '';
}

// SQL fragment: siswa hanya lihat materi sesuai kelasnya
// Aturan: tampil jika kelas materi = 'Semua' ATAU = kelas penuh user ATAU = tingkat user
function getKelasFilter(?string $userKelas, string $col = 'm.kelas'): string {
    if(!$userKelas) return "{$col} = 'Semua'";
    $db   = getDB();
    $full = $db->real_escape_string($userKelas);
    $tkt  = $db->real_escape_string(getTingkat($userKelas));
    $cond = "{$col} = 'Semua' OR {$col} = '{$full}'";
    if($tkt && $tkt !== $full) $cond .= " OR {$col} = '{$tkt}'";
    return "($cond)";
}

// Daftar opsi kelas untuk dropdown
function getKelasOptions(): array {
    $db   = getDB();
    $opts = [];
    $res  = $db->query("SELECT nama FROM kelas ORDER BY nama");
    if ($res) while ($row = $res->fetch_assoc()) $opts[] = $row['nama'];
    return $opts;
}

// ── Utilities ────────────────────────────
function getInitials($name) {
    $w=explode(' ',trim($name)); $i='';
    foreach(array_slice($w,0,2) as $word) $i.=strtoupper(substr($word,0,1));
    return $i;
}
function getGrade($score) {
    if($score>=90) return ['label'=>'A','color'=>'text-emerald-600'];
    if($score>=80) return ['label'=>'B','color'=>'text-blue-600'];
    if($score>=70) return ['label'=>'C','color'=>'text-yellow-600'];
    if($score>=60) return ['label'=>'D','color'=>'text-orange-500'];
    return ['label'=>'E','color'=>'text-red-600'];
}
function timeAgo($dt) {
    $d=time()-strtotime($dt);
    if($d<60)     return 'Baru saja';
    if($d<3600)   return floor($d/60).' menit lalu';
    if($d<86400)  return floor($d/3600).' jam lalu';
    if($d<604800) return floor($d/86400).' hari lalu';
    return date('d M Y',strtotime($dt));
}
function getRankBadge($rank) {
    $b=[1=>['emoji'=>icon('medal-gold'),'color'=>'bg-yellow-100 text-yellow-800'],
        2=>['emoji'=>icon('medal-silver'),'color'=>'bg-gray-100 text-gray-700'],
        3=>['emoji'=>icon('medal-bronze'),'color'=>'bg-orange-100 text-orange-700']];
    return $b[$rank]??['emoji'=>"#{$rank}",'color'=>'bg-indigo-50 text-indigo-700'];
}

// ── Tabler Icons ─────────────────────────
// Semua ikon menggunakan Tabler Icons webfont (dimuat di header.php via CDN)
function icon(string $name, string $size='1.1em', string $extra=''): string {
    static $map = [
        'bar-chart'=>'chart-bar','books'=>'books','bulb'=>'bulb',
        'check-circle'=>'circle-check','check-simple'=>'check','check'=>'check',
        'clipboard'=>'clipboard','close'=>'x','file-tabs'=>'files',
        'filter'=>'filter','fire'=>'flame','flashcard'=>'cards',
        'gear'=>'settings','graduation'=>'school','home'=>'home',
        'mailbox'=>'mail','medal-bronze'=>'medal-2','medal-gold'=>'medal',
        'medal-silver'=>'medal-2','memo'=>'notes','muscle'=>'barbell',
        'open-book'=>'book-open','pencil'=>'pencil','red-x'=>'x',
        'refresh'=>'refresh','search'=>'search','shuffle'=>'arrows-shuffle',
        'sparkles'=>'sparkles','star'=>'star-filled','stopwatch'=>'clock-hour-4',
        'target'=>'target','thinking'=>'brain','thumbs-up'=>'thumb-up',
        'trophy'=>'trophy','users'=>'users','warning'=>'alert-triangle',
        'wave'=>'hand-stop','x-mark'=>'x',
    ];
    $ti=$map[$name]??$name;
    $st="font-size:{$size};vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;";
    if($extra) $st.=$extra;
    return "<i class=\"ti ti-{$ti}\" style=\"{$st}\"></i>";
}
function icon_lg(string $name, string $size='64px', string $extra=''): string {
    return icon($name,$size,'opacity:0.18;'.$extra);
}

// Render ikon kategori dari nama Tabler yang disimpan di DB
function catIcon(string $icon, string $size='1.3em', string $extra=''): string {
    return ''; // icons disabled
}
