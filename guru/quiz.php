<?php
require_once __DIR__ . '/../includes/functions.php';
requireGuruOrAdmin();
$user = getCurrentUser(); $db = getDB(); $uid = (int)$_SESSION['user_id'];
$pageTitle = 'Kelola Quiz';
$msg = ''; $msgType = 'success';

$matCond = getGuruMaterialCondition('m');

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_quiz') {
        $mid   = (int)$_POST['material_id'];
        $title = $db->real_escape_string(trim($_POST['title']       ?? ''));
        $desc  = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $dur   = max(1,(int)$_POST['duration_minutes']);
        if (!canGuruManageMaterial($mid)) { $msg='Akses ditolak!'; $msgType='error'; goto render; }
        $db->query("INSERT INTO quizzes(material_id,title,description,duration_minutes) VALUES($mid,'$title','$desc',$dur)");
        $msg = 'Quiz berhasil ditambahkan!';

    } elseif ($act === 'edit_quiz') {
        $id  = (int)$_POST['id'];
        $mid = (int)$_POST['material_id'];
        if (!canGuruManageMaterial($mid)) { $msg='Akses ditolak!'; $msgType='error'; goto render; }
        $title = $db->real_escape_string(trim($_POST['title']       ?? ''));
        $desc  = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $dur   = max(1,(int)$_POST['duration_minutes']);
        $db->query("UPDATE quizzes SET material_id=$mid,title='$title',description='$desc',duration_minutes=$dur WHERE id=$id");
        $msg = 'Quiz berhasil diperbarui!';

    } elseif ($act === 'delete_quiz') {
        $id = (int)$_POST['id'];
        // verifikasi ownership lewat material
        $chk = $db->query("SELECT m.id FROM quizzes q JOIN materials m ON q.material_id=m.id WHERE q.id=$id LIMIT 1");
        if ($chk && ($row=$chk->fetch_assoc()) && canGuruManageMaterial((int)$row['id'])) {
            $db->query("DELETE FROM quizzes WHERE id=$id");
            $msg = 'Quiz dihapus.';
        } else { $msg='Akses ditolak!'; $msgType='error'; }
        header("Location: quiz.php"); exit;

    } elseif ($act === 'add_question') {
        $qzid = (int)$_POST['quiz_id'];
        $qt   = $db->real_escape_string(trim($_POST['question_text'] ?? ''));
        $exp  = $db->real_escape_string(trim($_POST['explanation']   ?? ''));
        $corr = (int)$_POST['correct_option'];
        $opts = $_POST['options'] ?? [];
        $db->query("INSERT INTO questions(quiz_id,question_text,explanation) VALUES($qzid,'$qt','$exp')");
        $newQid = $db->insert_id;
        foreach ($opts as $i => $ot) {
            $ot = $db->real_escape_string(trim($ot));
            $ic = ($i == $corr) ? 1 : 0;
            if ($ot) $db->query("INSERT INTO options(question_id,option_text,is_correct) VALUES($newQid,'$ot',$ic)");
        }
        $msg = 'Soal berhasil ditambahkan!';
        header("Location: ?quiz_id=$qzid"); exit;

    } elseif ($act === 'edit_question') {
        $id   = (int)$_POST['id'];
        $qzid = (int)$_POST['quiz_id'];
        $qt   = $db->real_escape_string(trim($_POST['question_text'] ?? ''));
        $exp  = $db->real_escape_string(trim($_POST['explanation']   ?? ''));
        $corr = (int)$_POST['correct_option'];
        $opts = $_POST['options'] ?? [];
        $db->query("UPDATE questions SET question_text='$qt',explanation='$exp' WHERE id=$id");
        $db->query("DELETE FROM options WHERE question_id=$id");
        foreach ($opts as $i => $ot) {
            $ot = $db->real_escape_string(trim($ot));
            $ic = ($i == $corr) ? 1 : 0;
            if ($ot) $db->query("INSERT INTO options(question_id,option_text,is_correct) VALUES($id,'$ot',$ic)");
        }
        $msg = 'Soal diperbarui!';
        header("Location: ?quiz_id=$qzid"); exit;

    } elseif ($act === 'delete_question') {
        $id   = (int)$_POST['id'];
        $qzid = (int)$_POST['quiz_id'];
        $db->query("DELETE FROM questions WHERE id=$id");
        header("Location: ?quiz_id=$qzid"); exit;
    }
}

render:
$vqid    = (int)($_GET['quiz_id'] ?? 0);
$quizzes = $db->query("
    SELECT q.*, m.title AS mt, m.kelas AS mkelas,
           (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) AS qc
    FROM quizzes q JOIN materials m ON q.material_id=m.id
    WHERE $matCond
    ORDER BY q.created_at DESC
");

// Material list untuk dropdown
$mats = $db->query("
    SELECT m.id, m.title, m.kelas, c.name AS cn
    FROM materials m JOIN categories c ON m.category_id=c.id
    WHERE $matCond ORDER BY c.name, m.title
");
$matArr = [];
while ($m = $mats->fetch_assoc()) $matArr[] = $m;

// Jika ada quiz_id, load soal-soalnya
$activeQuiz = null; $questions = null;
if ($vqid) {
    $aq = $db->query("SELECT q.*,m.title AS mt FROM quizzes q JOIN materials m ON q.material_id=m.id WHERE q.id=$vqid LIMIT 1");
    if ($aq && ($activeQuiz = $aq->fetch_assoc())) {
        if (!canGuruManageMaterial((int)$activeQuiz['material_id'])) { $activeQuiz=null; $vqid=0; }
        else {
            $questions = $db->query("
                SELECT q.*, GROUP_CONCAT(o.id ORDER BY o.id SEPARATOR '||') as opt_ids,
                       GROUP_CONCAT(o.option_text ORDER BY o.id SEPARATOR '||') as opt_texts,
                       GROUP_CONCAT(o.is_correct  ORDER BY o.id SEPARATOR '||') as opt_corrects
                FROM questions q LEFT JOIN options o ON o.question_id=q.id
                WHERE q.quiz_id=$vqid GROUP BY q.id ORDER BY q.order_num
            ");
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($activeQuiz): ?>
<!-- ── Tampilan soal quiz ── -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap">
    <a href="quiz.php" style="color:var(--muted);text-decoration:none;font-size:13px">← Daftar Quiz</a>
    <span style="color:var(--muted)">/</span>
    <span style="font-weight:700;font-size:14px;color:var(--navy)"><?= htmlspecialchars($activeQuiz['title']) ?></span>
    <span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($activeQuiz['mt']) ?></span>
    <button onclick="openModal('addQModal')" class="btn btn-sm" style="margin-left:auto;background:#047857;color:#fff">+ Tambah Soal</button>
</div>

<div class="card" style="overflow:hidden">
<div style="overflow-x:auto">
<table class="data-table">
    <thead><tr><th>#</th><th>Pertanyaan</th><th>Pilihan</th><th style="text-align:center">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$questions || $questions->num_rows === 0): ?>
    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--muted)">Belum ada soal. Klik "+ Tambah Soal".</td></tr>
    <?php else: $no=0; while ($q = $questions->fetch_assoc()): $no++;
        $optIds     = $q['opt_ids']     ? explode('||', $q['opt_ids'])     : [];
        $optTexts   = $q['opt_texts']   ? explode('||', $q['opt_texts'])   : [];
        $optCorrects= $q['opt_corrects']? explode('||', $q['opt_corrects']): [];
    ?>
    <tr>
        <td style="font-weight:700;color:var(--muted);width:40px"><?= $no ?></td>
        <td>
            <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($q['question_text']) ?></div>
            <?php if ($q['explanation']): ?><div style="font-size:11px;color:var(--muted);margin-top:3px">Penjelasan: <?= htmlspecialchars($q['explanation']) ?></div><?php endif; ?>
        </td>
        <td>
            <?php foreach ($optTexts as $i => $ot): if(!trim($ot)) continue; ?>
            <span style="display:inline-block;margin:2px;padding:2px 8px;border-radius:5px;font-size:11px;<?= ($optCorrects[$i]??0)?'background:#dcfce7;color:#166534;font-weight:700':'background:#f1f5f9;color:var(--muted)' ?>">
                <?= htmlspecialchars($ot) ?>
            </span>
            <?php endforeach; ?>
        </td>
        <td style="text-align:center">
            <div style="display:flex;gap:5px;justify-content:center">
                <button onclick='openEditQModal(<?= json_encode([
                    "id"=>$q["id"],"quiz_id"=>$vqid,"question_text"=>$q["question_text"],
                    "explanation"=>$q["explanation"],
                    "opt_texts"=>$optTexts,"opt_corrects"=>$optCorrects
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus soal?')">
                    <input type="hidden" name="action"  value="delete_question">
                    <input type="hidden" name="id"      value="<?= $q['id'] ?>">
                    <input type="hidden" name="quiz_id" value="<?= $vqid ?>">
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

<?php else: ?>
<!-- ── Daftar Quiz ── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Quiz</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px"><?= isAdmin() ? 'Semua quiz' : 'Quiz dari materi Anda' ?></div>
    </div>
    <?php if ($matArr): ?>
    <button onclick="openModal('addQzModal')" class="btn" style="background:#047857;color:#fff">+ Tambah Quiz</button>
    <?php elseif (!isAdmin()): ?>
    <div style="font-size:12px;color:var(--muted);background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:8px 14px">
        Belum ada materi dalam penugasan Anda. Tambahkan materi terlebih dahulu di menu <a href="materi.php" style="color:var(--navy);font-weight:700">Kelola Materi</a>.
    </div>
    <?php endif; ?>
</div>

<div class="card" style="overflow:hidden">
<div style="overflow-x:auto">
<table class="data-table">
    <thead><tr><th>Quiz</th><th>Materi / Kelas</th><th style="text-align:center">Soal</th><th style="text-align:center">Durasi</th><th style="text-align:center">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$quizzes || $quizzes->num_rows === 0): ?>
    <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">Belum ada quiz.</td></tr>
    <?php else: while ($q = $quizzes->fetch_assoc()): ?>
    <tr>
        <td><div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($q['title']) ?></div>
            <?php if ($q['description']): ?><div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($q['description']) ?></div><?php endif; ?></td>
        <td>
            <div style="font-size:12px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($q['mt']) ?></div>
            <div style="font-size:11px;color:var(--muted)">Kelas <?= htmlspecialchars($q['mkelas']) ?></div>
        </td>
        <td style="text-align:center;font-weight:700"><?= $q['qc'] ?></td>
        <td style="text-align:center;font-size:12px"><?= $q['duration_minutes'] ?> mnt</td>
        <td>
            <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                <a href="?quiz_id=<?= $q['id'] ?>" class="btn btn-sm" style="background:#f0fdf4;color:#047857">Soal</a>
                <button onclick='openEditQzModal(<?= json_encode(["id"=>$q["id"],"material_id"=>$q["material_id"],"title"=>$q["title"],"description"=>$q["description"]??"","duration_minutes"=>$q["duration_minutes"]]) ?>)'
                    class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">Edit</button>
                <form method="POST" onsubmit="return confirm('Hapus quiz ini?')">
                    <input type="hidden" name="action" value="delete_quiz">
                    <input type="hidden" name="id"     value="<?= $q['id'] ?>">
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
<?php endif; ?>

<!-- Modal Tambah Quiz -->
<div id="addQzModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:460px">
    <div class="modal-header"><div class="modal-title">Tambah Quiz</div>
        <button onclick="closeModal('addQzModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="add_quiz">
        <div class="form-group"><label class="form-label">Materi</label>
            <select name="material_id" class="form-input form-select" required>
                <option value="">-- Pilih Materi --</option>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group"><label class="form-label">Judul Quiz</label>
            <input type="text" name="title" class="form-input" required></div>
        <div class="form-group"><label class="form-label">Deskripsi</label>
            <input type="text" name="description" class="form-input"></div>
        <div class="form-group"><label class="form-label">Durasi (menit)</label>
            <input type="number" name="duration_minutes" class="form-input" value="10" min="1" required></div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('addQzModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan</button>
        </div>
    </form></div>
</div></div>

<!-- Modal Edit Quiz -->
<div id="editQzModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:460px">
    <div class="modal-header"><div class="modal-title">Edit Quiz</div>
        <button onclick="closeModal('editQzModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action" value="edit_quiz">
        <input type="hidden" name="id"     id="eQzId">
        <div class="form-group"><label class="form-label">Materi</label>
            <select name="material_id" id="eQzMat" class="form-input form-select" required>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group"><label class="form-label">Judul Quiz</label>
            <input type="text" name="title" id="eQzTitle" class="form-input" required></div>
        <div class="form-group"><label class="form-label">Deskripsi</label>
            <input type="text" name="description" id="eQzDesc" class="form-input"></div>
        <div class="form-group"><label class="form-label">Durasi (menit)</label>
            <input type="number" name="duration_minutes" id="eQzDur" class="form-input" min="1" required></div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('editQzModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan</button>
        </div>
    </form></div>
</div></div>

<!-- Modal Tambah Soal -->
<div id="addQModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:560px">
    <div class="modal-header"><div class="modal-title">Tambah Soal</div>
        <button onclick="closeModal('addQModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST">
        <input type="hidden" name="action"  value="add_question">
        <input type="hidden" name="quiz_id" value="<?= $vqid ?>">
        <div class="form-group"><label class="form-label">Teks Pertanyaan</label>
            <textarea name="question_text" class="form-input" rows="3" required></textarea></div>
        <div class="form-group"><label class="form-label">Penjelasan (opsional)</label>
            <input type="text" name="explanation" class="form-input"></div>
        <div class="form-group"><label class="form-label">Pilihan Jawaban <span style="color:var(--muted);font-weight:400">(centang = benar)</span></label>
            <?php for($i=0;$i<4;$i++): ?>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                <input type="radio"  name="correct_option" value="<?= $i ?>" <?= $i===0?'checked':'' ?> required>
                <input type="text"   name="options[]"      class="form-input" placeholder="Pilihan <?= chr(65+$i) ?>" required>
            </div>
            <?php endfor; ?>
        </div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('addQModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan Soal</button>
        </div>
    </form></div>
</div></div>

<!-- Modal Edit Soal -->
<div id="editQModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:560px">
    <div class="modal-header"><div class="modal-title">Edit Soal</div>
        <button onclick="closeModal('editQModal')" class="modal-close"><?= icon('close') ?></button></div>
    <div class="modal-body"><form method="POST" id="editQForm">
        <input type="hidden" name="action"  value="edit_question">
        <input type="hidden" name="id"      id="eQId">
        <input type="hidden" name="quiz_id" id="eQQuizId">
        <div class="form-group"><label class="form-label">Teks Pertanyaan</label>
            <textarea name="question_text" id="eQText" class="form-input" rows="3" required></textarea></div>
        <div class="form-group"><label class="form-label">Penjelasan</label>
            <input type="text" name="explanation" id="eQExp" class="form-input"></div>
        <div class="form-group" id="eQOptsWrap"><label class="form-label">Pilihan Jawaban</label></div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('editQModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#047857;color:#fff">Simpan</button>
        </div>
    </form></div>
</div></div>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow=''; }

function openEditQzModal(q) {
    document.getElementById('eQzId').value    = q.id;
    document.getElementById('eQzMat').value   = q.material_id;
    document.getElementById('eQzTitle').value = q.title;
    document.getElementById('eQzDesc').value  = q.description;
    document.getElementById('eQzDur').value   = q.duration_minutes;
    openModal('editQzModal');
}
function openEditQModal(q) {
    document.getElementById('eQId').value     = q.id;
    document.getElementById('eQQuizId').value = q.quiz_id;
    document.getElementById('eQText').value   = q.question_text;
    document.getElementById('eQExp').value    = q.explanation || '';
    const wrap = document.getElementById('eQOptsWrap');
    // Remove old option rows
    while (wrap.children.length > 1) wrap.removeChild(wrap.lastChild);
    q.opt_texts.forEach((t,i) => {
        if (!t.trim()) return;
        const div = document.createElement('div');
        div.style = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
        const corr = parseInt(q.opt_corrects[i]) === 1;
        div.innerHTML = `<input type="radio" name="correct_option" value="${i}" ${corr?'checked':''}>`
                       +`<input type="text"  name="options[]" class="form-input" value="${t.replace(/"/g,'&quot;')}" required>`;
        wrap.appendChild(div);
    });
    openModal('editQModal');
}
['addQzModal','editQzModal','addQModal','editQModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) closeModal(id); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
