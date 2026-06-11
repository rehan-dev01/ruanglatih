<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Leaderboard';

$lb   = $db->query("SELECT u.id,u.name,u.kelas,u.total_points,u.streak,COUNT(qr.id) as tq,COALESCE(AVG(qr.score),0) as avg
    FROM users u LEFT JOIN quiz_results qr ON u.id=qr.user_id
    WHERE u.role='user' GROUP BY u.id ORDER BY u.total_points DESC,avg DESC LIMIT 20");
$rows = []; $rank = 0; $myRank = 0;
while ($r = $lb->fetch_assoc()) { $rank++; $r['rank'] = $rank; if ($r['id'] == $uid) $myRank = $rank; $rows[] = $r; }

// Filter kelas
$fKelas = trim($_GET['kelas'] ?? '');
$kr = $db->query("SELECT DISTINCT kelas FROM users WHERE role='user' AND kelas IS NOT NULL AND kelas!='' ORDER BY kelas");
$kList = [];
while ($kk = $kr->fetch_assoc()) $kList[] = $kk['kelas'];

if ($fKelas) {
    $fkEsc = $db->real_escape_string($fKelas);
    $lb2   = $db->query("SELECT u.id,u.name,u.kelas,u.total_points,u.streak,COUNT(qr.id) as tq,COALESCE(AVG(qr.score),0) as avg
        FROM users u LEFT JOIN quiz_results qr ON u.id=qr.user_id
        WHERE u.role='user' AND u.kelas='$fkEsc' GROUP BY u.id ORDER BY u.total_points DESC,avg DESC LIMIT 20");
    $rows  = []; $rank = 0; $myRank = 0;
    while ($r = $lb2->fetch_assoc()) { $rank++; $r['rank'] = $rank; if ($r['id'] == $uid) $myRank = $rank; $rows[] = $r; }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner" style="margin-bottom:20px">
    <div class="pb-label">Kompetisi Belajar</div>
    <div class="pb-title">Leaderboard</div>
    <div class="pb-sub">Peringkat berdasarkan total poin dan aktivitas belajar</div>
</div>

<!-- Filter Kelas -->
<form method="GET" style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <select name="kelas" class="form-input form-select" style="width:auto;min-width:160px;font-size:13px">
        <option value="">Semua Kelas</option>
        <?php foreach ($kList as $kk): ?>
        <option value="<?= htmlspecialchars($kk) ?>" <?= $fKelas === $kk ? 'selected' : '' ?>><?= htmlspecialchars($kk) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-navy btn-sm">Filter</button>
    <?php if ($fKelas): ?><a href="leaderboard.php" class="btn btn-outline btn-sm">Semua</a><?php endif; ?>
</form>

<!-- Peringkatku -->
<?php if ($myRank): ?>
<div style="background:linear-gradient(135deg,var(--navy),var(--navy-lt));border-radius:var(--r);padding:16px 20px;margin-bottom:16px;color:#fff;display:flex;align-items:center;gap:14px">
    <div style="width:52px;height:52px;background:rgba(255,255,255,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:20px;flex-shrink:0">
        #<?= $myRank ?>
    </div>
    <div>
        <div style="font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px">Peringkatmu</div>
        <div style="font-weight:800;font-size:15px"><?= htmlspecialchars($user['name']) ?></div>
        <div style="font-size:12px;color:rgba(255,255,255,.65);margin-top:2px">
            <?= number_format((int)$user['total_points']) ?> poin
            <?php if ($user['streak'] > 0): ?> &nbsp;&middot;&nbsp; Streak: <?= $user['streak'] ?> hari<?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Podium Top 3 — angka saja, tanpa ikon medali -->
<?php if (count($rows) >= 3): ?>
<div class="card card-pad" style="margin-bottom:16px">
    <div style="text-align:center;font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:20px">Top 3 Terbaik</div>
    <div style="display:flex;align-items:flex-end;justify-content:center;gap:16px">

        <!-- Juara 2 -->
        <div style="text-align:center;flex:1">
            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#c4b5fd,#7c3aed);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:14px"><?= getInitials($rows[1]['name']) ?></div>
            <div style="font-size:20px;font-weight:900;color:#94a3b8;margin-bottom:6px">2</div>
            <div style="background:#f8fafc;border-radius:10px;padding:10px 6px;height:64px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:11px;font-weight:700;color:var(--text)"><?= htmlspecialchars(explode(' ', $rows[1]['name'])[0]) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= number_format($rows[1]['total_points']) ?></div>
            </div>
        </div>

        <!-- Juara 1 -->
        <div style="text-align:center;flex:1;margin-bottom:-10px">
            <div style="width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,var(--gold),#d97706);color:#1a1a1a;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:16px;box-shadow:0 4px 14px rgba(245,158,11,.4)"><?= getInitials($rows[0]['name']) ?></div>
            <div style="font-size:26px;font-weight:900;color:#d97706;margin-bottom:6px">1</div>
            <div style="background:var(--gold-pale);border:1px solid var(--gold-lt);border-radius:10px;padding:12px 8px;height:82px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:13px;font-weight:800;color:var(--navy)"><?= htmlspecialchars(explode(' ', $rows[0]['name'])[0]) ?></div>
                <div style="font-size:13px;font-weight:800;color:var(--gold-dk)"><?= number_format($rows[0]['total_points']) ?></div>
                <div style="font-size:9px;color:var(--muted)">poin</div>
            </div>
        </div>

        <!-- Juara 3 -->
        <div style="text-align:center;flex:1">
            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#fdba74,#ea580c);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:13px"><?= getInitials($rows[2]['name']) ?></div>
            <div style="font-size:18px;font-weight:900;color:#b45309;margin-bottom:6px">3</div>
            <div style="background:#fff7ed;border-radius:10px;padding:8px 6px;height:50px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:11px;font-weight:700;color:var(--text)"><?= htmlspecialchars(explode(' ', $rows[2]['name'])[0]) ?></div>
                <div style="font-size:10px;color:var(--muted)"><?= number_format($rows[2]['total_points']) ?></div>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- Tabel Semua Peringkat — angka saja -->
<div class="card" style="overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px;color:var(--navy)">Semua Peringkat</div>
    <?php foreach ($rows as $r): $isMe = $r['id'] == $uid; ?>
    <div style="display:grid;grid-template-columns:40px 1fr auto auto;gap:10px;align-items:center;padding:12px 20px;border-bottom:1px solid #f8fafc;<?= $isMe ? 'background:var(--navy-pale);border-left:4px solid var(--navy);' : '' ?>">

        <!-- Nomor urut — angka saja, tanpa ikon medali -->
        <div style="text-align:center;font-weight:800;font-size:13px;color:<?= $r['rank'] <= 3 ? 'var(--gold-dk)' : 'var(--muted)' ?>">
            #<?= $r['rank'] ?>
        </div>

        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--navy-lt),var(--navy-dk));color:#fff;font-weight:700;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= getInitials($r['name']) ?></div>
            <div>
                <div style="font-weight:600;font-size:13px;color:var(--text);display:flex;align-items:center;gap:6px">
                    <?= htmlspecialchars($r['name']) ?>
                    <?php if ($isMe): ?>
                    <span style="background:var(--navy);color:#fff;font-size:9px;padding:2px 7px;border-radius:4px;font-weight:700">Kamu</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:10.5px;color:var(--muted);margin-top:1px">
                    <?= $r['tq'] ?> quiz
                    <?php if ($r['kelas']): ?> &middot; <?= htmlspecialchars($r['kelas']) ?><?php endif; ?>
                    <?php if ($r['streak'] > 0): ?> &middot; Streak: <?= $r['streak'] ?> hari<?php endif; ?>
                </div>
            </div>
        </div>

        <div style="text-align:right">
            <span style="font-size:12px;font-weight:600;color:var(--muted)"><?= round($r['avg'], 1) ?></span>
            <div style="font-size:9px;color:var(--muted)">avg</div>
        </div>

        <div style="text-align:right">
            <div style="font-weight:900;font-size:15px;color:var(--navy)"><?= number_format($r['total_points']) ?></div>
            <div style="font-size:9px;color:var(--muted)">poin</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
