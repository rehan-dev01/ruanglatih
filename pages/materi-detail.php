<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin(); $user = getCurrentUser(); $db = getDB();
$id = (int)($_GET['id'] ?? 0);
if(!$id){header('Location: materi.php');exit;}
$mat = $db->query("SELECT m.*,c.name as cn,c.icon as ci FROM materials m JOIN categories c ON m.category_id=c.id WHERE m.id=$id LIMIT 1")->fetch_assoc();
if(!$mat){header('Location: materi.php');exit;}
// Kelas access check
if(!isAdmin() && !isGuru()){
    $userKelas=$user['kelas']??'';
    // Jika murid tidak punya kelas, izinkan lihat semua materi
    if($userKelas){
        $kf=getKelasFilter($userKelas,'m.kelas');
        $ck=$db->query("SELECT m.id FROM materials m WHERE m.id=$id AND $kf LIMIT 1");
        if(!$ck||$ck->num_rows===0){header('Location: materi.php');exit;}
    }
}
$pageTitle = $mat['title'];
$quizzes   = $db->query("SELECT q.*,(SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) as qc FROM quizzes q WHERE q.material_id=$id");
$fcCount   = $db->query("SELECT COUNT(*) as c FROM flashcards WHERE material_id=$id")->fetch_assoc()['c'];
$related   = $db->query("SELECT m.id,m.title,c.icon FROM materials m JOIN categories c ON m.category_id=c.id WHERE m.category_id={$mat['category_id']} AND m.id!=$id LIMIT 4");
include __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="<?=APP_URL?>/dashboard.php">Dashboard</a>
    <span class="breadcrumb-sep">›</span>
    <a href="materi.php">Materi</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current"><?=htmlspecialchars($mat['title'])?></span>
</div>

<!-- Banner -->
<div class="page-banner" style="margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:14px">
        <div>
            <div style="font-size:11px;color:rgba(255,255,255,.6);font-weight:600;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px"><?=htmlspecialchars($mat['cn'])?></div>
            <div style="font-size:20px;font-weight:800;color:#fff"><?=htmlspecialchars($mat['title'])?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:3px">Dipublikasikan <?=date('d F Y',strtotime($mat['created_at']))?></div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:16px">

    <!-- Konten Materi -->
    <div class="card card-pad">
        <style>
        .prose h2{font-size:1.2rem;font-weight:700;color:var(--navy);margin:18px 0 8px;padding-bottom:6px;border-bottom:2px solid var(--gold-lt)}
        .prose h3{font-size:1rem;font-weight:700;color:var(--navy);margin:14px 0 6px}
        .prose p{margin-bottom:10px;color:#374151;line-height:1.75;font-size:14px}
        .prose ul,.prose ol{margin:8px 0 12px 20px;color:#374151;font-size:14px}
        .prose li{margin-bottom:5px;line-height:1.65}
        .prose strong{color:var(--navy);font-weight:700}
        .prose blockquote{border-left:4px solid var(--gold);padding:10px 16px;background:var(--gold-pale);border-radius:0 8px 8px 0;margin:14px 0;font-style:italic;color:#6b5000}
        </style>
        <div class="prose"><?=$mat['content']?></div>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:14px">

        <!-- Quizzes -->
        <div class="card card-pad">
            <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:14px">Quiz Tersedia</div>
            <?php if($quizzes->num_rows===0): ?>
            <div style="text-align:center;color:var(--muted);font-size:13px;padding:12px 0">Belum ada quiz</div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px">
            <?php while($q=$quizzes->fetch_assoc()): ?>
                <div style="background:var(--navy-pale);border-radius:12px;padding:14px">
                    <div style="font-weight:700;font-size:13px;color:var(--navy);margin-bottom:4px"><?=htmlspecialchars($q['title'])?></div>
                    <div style="font-size:11px;color:var(--muted);margin-bottom:10px"><?=$q['qc']?> soal &nbsp;•&nbsp; <?=$q['duration_minutes']?> menit</div>
                    <a href="quiz-play.php?id=<?=$q['id']?>" class="btn btn-gold btn-sm btn-full">Mulai Quiz →</a>
                </div>
            <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Flashcard -->
        <?php if($fcCount>0): ?>
        <div class="card card-pad" style="background:linear-gradient(135deg,#0e7490,#0891b2);color:#fff">
            <div style="font-weight:700;font-size:14px;margin-bottom:6px">Flashcard</div>
            <div style="font-size:12px;opacity:.8;margin-bottom:12px"><?=$fcCount?> kartu hafalan tersedia</div>
            <a href="flashcard.php?material_id=<?=$id?>" class="btn btn-sm btn-full" style="background:rgba(255,255,255,.2);color:#fff;font-weight:700">Buka Flashcard</a>
        </div>
        <?php endif; ?>

        <!-- Related -->
        <?php if($related->num_rows>0): ?>
        <div class="card card-pad">
            <div style="font-weight:700;font-size:14px;color:var(--navy);margin-bottom:12px">Materi Serupa</div>
            <?php while($rel=$related->fetch_assoc()): ?>
            <a href="materi-detail.php?id=<?=$rel['id']?>" style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:9px;margin-bottom:4px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                <span style="font-size:12.5px;font-weight:600;color:var(--text)"><?=htmlspecialchars($rel['title'])?></span>
            </a>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <a href="materi.php" class="btn btn-outline btn-full">← Kembali ke Daftar</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
