<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = getCurrentUser(); $db = getDB();
$pageTitle = 'Flashcard';
$mid     = (int)($_GET['material_id'] ?? 0);
$fKelas  = trim($_GET['kelas'] ?? '');

// Semua flashcard — tanpa filter kelas
$sql = "SELECT f.*, m.title as mt, m.kelas as mkelas
        FROM flashcards f JOIN materials m ON f.material_id=m.id WHERE 1=1";
if ($fKelas) $sql .= " AND m.kelas='" . $db->real_escape_string($fKelas) . "'";
if ($mid)    $sql .= " AND f.material_id=$mid";
$sql .= " ORDER BY RAND()";
$fcRes = $db->query($sql);
$cards = [];
while ($c = $fcRes->fetch_assoc()) $cards[] = $c;

// Dropdown: semua materi (urut X→XI→XII)
$matSql = "SELECT DISTINCT m.id, m.title, m.kelas FROM flashcards f
           JOIN materials m ON f.material_id=m.id WHERE 1=1";
if ($fKelas) $matSql .= " AND m.kelas='" . $db->real_escape_string($fKelas) . "'";
$matSql .= " ORDER BY CASE WHEN m.kelas LIKE 'XII%' THEN 3 WHEN m.kelas LIKE 'XI%' THEN 2
                            WHEN m.kelas LIKE 'X%' THEN 1 ELSE 9 END, m.kelas, m.title";
$mats = $db->query($matSql);

// Dropdown kelas
$kelasRes  = $db->query("SELECT DISTINCT m.kelas FROM flashcards f JOIN materials m ON f.material_id=m.id
    ORDER BY CASE WHEN m.kelas LIKE 'XII%' THEN 3 WHEN m.kelas LIKE 'XI%' THEN 2
                  WHEN m.kelas LIKE 'X%' THEN 1 ELSE 9 END, m.kelas");
$kelasOpts = [];
while ($k = $kelasRes->fetch_assoc()) $kelasOpts[] = $k['kelas'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner" style="margin-bottom:20px">
    <div class="pb-label">Hafalan Cepat</div>
    <div class="pb-title">Flashcard</div>
    <div class="pb-sub">Klik kartu untuk membalik &mdash; gunakan &larr; &rarr; atau Spasi di keyboard</div>
</div>

<div style="max-width:540px;margin:0 auto">

    <!-- Counter + Filter -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:10px;flex-wrap:wrap">
        <div style="font-weight:700;font-size:14px;color:var(--navy)" id="fcCounter">
            Kartu 1 dari <?= count($cards) ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <select onchange="location.href='flashcard.php?kelas=<?= urlencode($fKelas) ?>'+(this.value?'&material_id='+this.value:'')"
                class="form-input" style="width:auto;font-size:12px;padding:7px 28px 7px 10px">
                <option value="">Semua Materi</option>
                <?php while ($m = $mats->fetch_assoc()): ?>
                <option value="<?= $m['id'] ?>" <?= $mid == $m['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['title']) ?>
                    <?php $mkelas = $m['mkelas'] ?? ($m['kelas'] ?? ''); ?>
                    <?php if ($mkelas && $mkelas !== 'Semua'): ?>
                    (<?= htmlspecialchars($mkelas) ?>)
                    <?php endif; ?>
                </option>
                <?php endwhile; ?>
            </select>
            <select onchange="location.href='flashcard.php?kelas='+this.value<?= $mid ? "+'&material_id=$mid'" : '' ?>"
                class="form-input" style="width:auto;font-size:12px;padding:7px 28px 7px 10px">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasOpts as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $fKelas===$k?'selected':'' ?>>
                    <?= htmlspecialchars($k) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button onclick="fcShuffle()" class="btn btn-outline btn-sm">Acak</button>
        </div>
    </div>

    <?php if (empty($cards)): ?>
    <div class="card card-pad" style="text-align:center;padding:60px 20px">
        <div style="font-weight:700;font-size:16px;color:var(--navy)">Belum ada flashcard</div>
        <div style="color:var(--muted);margin-top:6px">Pilih materi lain</div>
    </div>
    <?php else: ?>

    <!-- 3D Flip Card -->
    <div style="perspective:1400px;height:240px;margin-bottom:16px;cursor:pointer" onclick="fcFlip()">
        <div id="fcInner" style="width:100%;height:100%;transform-style:preserve-3d;transition:transform .65s cubic-bezier(.4,0,.2,1);position:relative">
            <!-- Depan: Pertanyaan -->
            <div style="position:absolute;inset:0;backface-visibility:hidden;border-radius:var(--r-xl);background:linear-gradient(135deg,var(--navy) 0%,var(--navy-lt) 100%);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;box-shadow:var(--shadow-lg)">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.5);margin-bottom:16px">Pertanyaan</div>
                <div id="fcQ" style="font-size:16px;font-weight:700;text-align:center;line-height:1.6"></div>
                <div style="position:absolute;bottom:14px;font-size:11px;color:rgba(255,255,255,.35)">Klik untuk lihat jawaban</div>
            </div>
            <!-- Belakang: Jawaban -->
            <div style="position:absolute;inset:0;backface-visibility:hidden;transform:rotateY(180deg);border-radius:var(--r-xl);background:#fff;border:2px solid var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;box-shadow:var(--shadow-lg)">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-bottom:16px">Jawaban</div>
                <div id="fcA" style="font-size:15px;font-weight:600;text-align:center;color:var(--navy);line-height:1.6"></div>
                <div style="position:absolute;bottom:14px;font-size:11px;color:var(--muted)">Klik untuk balik</div>
            </div>
        </div>
    </div>

    <!-- Dots -->
    <div style="display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-bottom:16px" id="fcDots">
        <?php for ($i = 0; $i < min(count($cards), 15); $i++): ?>
        <button onclick="fcGo(<?= $i ?>)" style="width:8px;height:8px;border-radius:50%;border:none;cursor:pointer;transition:all .2s;background:<?= $i === 0 ? 'var(--navy)' : '#cbd5e1' ?>" class="fc-dot"></button>
        <?php endfor; ?>
        <?php if (count($cards) > 15): ?>
        <span style="font-size:11px;color:var(--muted);align-self:center">+<?= count($cards) - 15 ?></span>
        <?php endif; ?>
    </div>

    <!-- Navigasi -->
    <div style="display:flex;gap:10px;justify-content:center">
        <button onclick="fcPrev()" class="btn btn-outline btn-lg">&larr; Sebelumnya</button>
        <button onclick="fcNext()" class="btn btn-navy btn-lg">Selanjutnya &rarr;</button>
    </div>
    <?php endif; ?>
</div>

<script>
let cards = <?= json_encode($cards) ?>, cur = 0, flipped = false;
function fcShow(i) {
    flipped = false;
    document.getElementById('fcInner').style.transform = 'rotateY(0deg)';
    setTimeout(() => {
        document.getElementById('fcQ').textContent = cards[i]?.question || '';
        document.getElementById('fcA').textContent = cards[i]?.answer   || '';
        document.getElementById('fcCounter').textContent = 'Kartu ' + (i+1) + ' dari ' + cards.length;
        document.querySelectorAll('.fc-dot').forEach((d,di) => {
            d.style.background = di === i ? 'var(--navy)' : '#cbd5e1';
            d.style.transform  = di === i ? 'scale(1.4)' : 'scale(1)';
        });
    }, 200);
}
function fcFlip() { flipped = !flipped; document.getElementById('fcInner').style.transform = flipped ? 'rotateY(180deg)' : 'rotateY(0deg)'; }
function fcNext() { cur = (cur + 1) % cards.length; fcShow(cur); }
function fcPrev() { cur = (cur - 1 + cards.length) % cards.length; fcShow(cur); }
function fcGo(i)  { cur = i; fcShow(i); }
function fcShuffle() {
    for (let i = cards.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [cards[i], cards[j]] = [cards[j], cards[i]];
    }
    cur = 0; fcShow(0);
}
document.addEventListener('keydown', e => {
    if      (e.key === 'ArrowRight') fcNext();
    else if (e.key === 'ArrowLeft')  fcPrev();
    else if (e.key === ' ')          { e.preventDefault(); fcFlip(); }
});
fcShow(0);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
