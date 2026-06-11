<?php
ob_start();
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$user = getCurrentUser();
$db   = getDB();
$pageTitle = 'Kelola Quiz';
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_quiz') {
        $mid   = (int)$_POST['material_id'];
        $title = $db->real_escape_string(trim($_POST['title'] ?? ''));
        $desc  = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $dur   = max(1, (int)($_POST['duration_minutes'] ?? 10));
        if (!$mid || !$title) {
            $msg = 'Materi dan judul wajib diisi!'; $msgType = 'error';
        } else {
            $db->query("INSERT INTO quizzes(material_id,title,description,duration_minutes) VALUES($mid,'$title','$desc',$dur)");
            $newQzid = (int)$db->insert_id;
            ob_end_clean();
            header("Location: quiz.php?quiz_id=$newQzid&msg=Quiz+berhasil+ditambahkan%21");
            exit;
        }

    } elseif ($act === 'edit_quiz') {
        $id    = (int)$_POST['id'];
        $mid   = (int)$_POST['material_id'];
        $title = $db->real_escape_string(trim($_POST['title'] ?? ''));
        $desc  = $db->real_escape_string(trim($_POST['description'] ?? ''));
        $dur   = max(1, (int)($_POST['duration_minutes'] ?? 10));
        $db->query("UPDATE quizzes SET material_id=$mid,title='$title',description='$desc',duration_minutes=$dur WHERE id=$id");
        ob_end_clean();
        header("Location: quiz.php?msg=Quiz+berhasil+diperbarui%21");
        exit;

    } elseif ($act === 'delete_quiz') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM quizzes WHERE id=$id");
        ob_end_clean();
        header("Location: quiz.php?msg=Quiz+berhasil+dihapus.");
        exit;

    } elseif ($act === 'add_question') {
        $qzid = (int)$_POST['quiz_id'];
        $qt   = $db->real_escape_string(trim($_POST['question_text'] ?? ''));
        $exp  = $db->real_escape_string(trim($_POST['explanation'] ?? ''));
        $corr = (int)($_POST['correct_option'] ?? 0);
        $opts = $_POST['options'] ?? [];
        if (!$qt) {
            $msg = 'Teks pertanyaan wajib diisi!'; $msgType = 'error';
        } elseif (!$qzid) {
            $msg = 'Quiz tidak valid!'; $msgType = 'error';
        } else {
            $db->query("INSERT INTO questions(quiz_id,question_text,explanation) VALUES($qzid,'$qt','$exp')");
            $newQid = (int)$db->insert_id;
            foreach ($opts as $i => $ot) {
                $ot = $db->real_escape_string(trim($ot));
                $ic = ($i == $corr) ? 1 : 0;
                if ($ot) $db->query("INSERT INTO options(question_id,option_text,is_correct) VALUES($newQid,'$ot',$ic)");
            }
            ob_end_clean();
            header("Location: quiz.php?quiz_id=$qzid&msg=Soal+berhasil+ditambahkan%21");
            exit;
        }

    } elseif ($act === 'edit_question') {
        $id   = (int)$_POST['id'];
        $qzid = (int)$_POST['quiz_id'];
        $qt   = $db->real_escape_string(trim($_POST['question_text'] ?? ''));
        $exp  = $db->real_escape_string(trim($_POST['explanation'] ?? ''));
        $corr = (int)($_POST['correct_option'] ?? 0);
        $opts = $_POST['options'] ?? [];
        $db->query("UPDATE questions SET question_text='$qt',explanation='$exp' WHERE id=$id");
        $db->query("DELETE FROM options WHERE question_id=$id");
        foreach ($opts as $i => $ot) {
            $ot = $db->real_escape_string(trim($ot));
            $ic = ($i == $corr) ? 1 : 0;
            if ($ot) $db->query("INSERT INTO options(question_id,option_text,is_correct) VALUES($id,'$ot',$ic)");
        }
        ob_end_clean();
        header("Location: quiz.php?quiz_id=$qzid&msg=Soal+berhasil+diperbarui%21");
        exit;

    } elseif ($act === 'delete_question') {
        $id   = (int)$_POST['id'];
        $qzid = (int)$_POST['quiz_id'];
        $db->query("DELETE FROM questions WHERE id=$id");
        ob_end_clean();
        header("Location: quiz.php?quiz_id=$qzid&msg=Soal+berhasil+dihapus.");
        exit;
    }
}

// Ambil pesan dari GET (redirect)
if (!$msg && isset($_GET['msg'])) { $msg = htmlspecialchars($_GET['msg']); }

$vqid = (int)($_GET['quiz_id'] ?? 0);

// Daftar semua quiz
$quizzes = $db->query("
    SELECT q.*, m.title AS mt,
           (SELECT COUNT(*) FROM questions WHERE quiz_id=q.id) AS qc
    FROM quizzes q
    JOIN materials m ON q.material_id = m.id
    ORDER BY q.created_at DESC
");

// Daftar materi untuk dropdown
$mats   = $db->query("SELECT id, title, kelas FROM materials ORDER BY title");
$matArr = [];
if ($mats) while ($m = $mats->fetch_assoc()) $matArr[] = $m;

// Data quiz yang sedang dikelola soalnya
$vQuiz = null;
$qRows = [];
if ($vqid) {
    $aqRes = $db->query("
        SELECT q.*, m.title AS mt
        FROM quizzes q JOIN materials m ON q.material_id = m.id
        WHERE q.id = $vqid LIMIT 1
    ");
    $vQuiz = ($aqRes && $aqRes->num_rows > 0) ? $aqRes->fetch_assoc() : null;

    if ($vQuiz) {
        $qRes = $db->query("
            SELECT q.*,
                   GROUP_CONCAT(o.option_text ORDER BY o.id SEPARATOR '|||') AS opts,
                   GROUP_CONCAT(o.is_correct  ORDER BY o.id SEPARATOR ',')   AS corrects
            FROM questions q
            LEFT JOIN options o ON q.id = o.question_id
            WHERE q.quiz_id = $vqid
            GROUP BY q.id
            ORDER BY q.order_num, q.id
        ");
        if ($qRes) while ($row = $qRes->fetch_assoc()) $qRows[] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <?= icon($msgType === 'success' ? 'check' : 'warning') ?> <?= $msg ?>
</div>
<?php endif; ?>

<!-- ── Header halaman ── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="font-size:20px;font-weight:800;color:var(--navy)">Kelola Quiz</div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px">Buat dan kelola quiz beserta soal-soalnya</div>
    </div>
    <?php if (!$vqid): ?>
    <button onclick="openModal('addQuizModal')" class="btn btn-navy">+ Tambah Quiz</button>
    <?php endif; ?>
</div>

<?php if (!$vqid): ?>
<!-- ── Tabel daftar quiz ── -->
<div class="card" style="overflow:hidden;margin-bottom:20px">
    <div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Quiz</th>
                <th>Materi</th>
                <th style="text-align:center">Soal</th>
                <th style="text-align:center">Durasi</th>
                <th style="text-align:center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$quizzes || $quizzes->num_rows === 0): ?>
        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">Belum ada quiz. Klik "+ Tambah Quiz".</td></tr>
        <?php else: while ($q = $quizzes->fetch_assoc()): ?>
        <tr>
            <td>
                <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($q['title']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($q['description'] ?? '') ?></div>
            </td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($q['mt']) ?></td>
            <td style="text-align:center;font-weight:700"><?= $q['qc'] ?> soal</td>
            <td style="text-align:center;color:var(--muted);font-size:12px"><?= $q['duration_minutes'] ?> menit</td>
            <td>
                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                    <a href="quiz.php?quiz_id=<?= (int)$q['id'] ?>" class="btn btn-sm" style="background:#ecfdf5;color:#059669">
                        <?= icon('clipboard', '0.9em') ?> Kelola Soal
                    </a>
                    <button onclick='openEditQuiz(<?= json_encode([
                        "id"               => (int)$q["id"],
                        "material_id"      => (int)$q["material_id"],
                        "title"            => $q["title"],
                        "description"      => $q["description"] ?? "",
                        "duration_minutes" => (int)$q["duration_minutes"]
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                        class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">
                        <?= icon('pencil', '0.9em') ?> Edit
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Hapus quiz ini beserta semua soalnya?')">
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
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

<!-- ── Panel kelola soal ── -->
<?php if ($vqid && $vQuiz): ?>
<div class="card" style="overflow:hidden;margin-bottom:20px;border:2px solid var(--navy-pale)">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--navy-pale);flex-wrap:wrap;gap:10px">
        <div>
            <div style="font-weight:800;font-size:14px;color:var(--navy)">
                <?= icon('clipboard') ?> Soal — <?= htmlspecialchars($vQuiz['title']) ?>
            </div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                Materi: <?= htmlspecialchars($vQuiz['mt']) ?> &nbsp;·&nbsp;
                <?= count($qRows) ?> soal &nbsp;·&nbsp;
                <?= (int)$vQuiz['duration_minutes'] ?> menit
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <a href="quiz.php" class="btn btn-sm btn-outline">← Kembali ke Daftar Quiz</a>
            <button onclick="openModal('addQModal')" class="btn btn-sm" style="background:#059669;color:#fff">
                + Tambah Soal
            </button>
        </div>
    </div>

    <div style="padding:20px">
    <?php if (empty($qRows)): ?>
        <div style="text-align:center;padding:40px;color:var(--muted)">
            <div style="font-size:40px;margin-bottom:10px"><?= icon('memo', '40px') ?></div>
            <div style="font-weight:600">Belum ada soal</div>
            <div style="font-size:12px;margin-top:4px">Klik "+ Tambah Soal" untuk mulai</div>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
        <?php $no = 1; foreach ($qRows as $q):
            $opts     = $q['opts']     ? explode('|||', $q['opts'])    : [];
            $corrects = $q['corrects'] ? explode(',',   $q['corrects']) : [];
            while (count($opts) < 4)     $opts[]     = '';
            while (count($corrects) < 4) $corrects[] = '0';
            $corrIdx = 0;
            foreach ($corrects as $ci => $cv) { if ((string)$cv === '1') { $corrIdx = (int)$ci; break; } }
        ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:16px">
            <div style="display:flex;align-items:start;justify-content:space-between;gap:12px">
                <div style="flex:1">
                    <div style="display:flex;align-items:start;gap:10px;margin-bottom:12px">
                        <div class="stat-circle" style="width:26px;height:26px;font-size:12px;flex-shrink:0;margin-top:2px"><?= $no++ ?></div>
                        <p style="font-weight:600;font-size:13px;color:var(--text);line-height:1.5;margin:0">
                            <?= htmlspecialchars($q['question_text']) ?>
                        </p>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-left:36px">
                    <?php foreach ($opts as $i => $opt): if (!trim($opt)) continue;
                          $isCorr = isset($corrects[$i]) && (string)$corrects[$i] === '1'; ?>
                        <div style="display:flex;align-items:center;gap:6px;font-size:12px;
                                    color:<?= $isCorr ? '#059669' : 'var(--muted)' ?>;
                                    font-weight:<?= $isCorr ? '700' : '400' ?>">
                            <span style="width:22px;height:22px;border-radius:6px;
                                         background:<?= $isCorr ? '#d1fae5' : '#f1f5f9' ?>;
                                         display:flex;align-items:center;justify-content:center;
                                         font-size:10px;font-weight:800;flex-shrink:0">
                                <?= chr(65 + $i) ?>
                            </span>
                            <?= htmlspecialchars($opt) ?>
                            <?= $isCorr ? icon('check', '0.9em') : '' ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <?php if (!empty($q['explanation'])): ?>
                    <div style="margin-top:10px;margin-left:36px;background:var(--gold-pale);border:1px solid var(--gold-lt);border-radius:8px;padding:8px 12px;font-size:12px;color:#92400e">
                        <?= icon('bulb') ?> <?= htmlspecialchars($q['explanation']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
                    <button onclick='openEditQ(<?= json_encode([
                        "id"             => (int)$q["id"],
                        "quiz_id"        => $vqid,
                        "question_text"  => $q["question_text"],
                        "explanation"    => $q["explanation"] ?? "",
                        "options"        => array_values($opts),
                        "correct_option" => $corrIdx
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                        class="btn btn-sm" style="background:var(--navy-pale);color:var(--navy)">
                        <?= icon('pencil', '0.85em') ?> Edit
                    </button>
                    <form method="POST" onsubmit="return confirm('Hapus soal ini?')">
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                        <input type="hidden" name="quiz_id" value="<?= $vqid ?>">
                        <button type="submit" class="btn btn-red btn-sm" style="width:100%">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($vqid && !$vQuiz): ?>
<div class="alert alert-error">Quiz tidak ditemukan. <a href="quiz.php">← Kembali</a></div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════
     MODAL: Tambah Quiz
     ════════════════════════════════════════════════════ -->
<div id="addQuizModal" class="modal-backdrop hidden">
<div class="modal-box">
    <div class="modal-header">
        <div class="modal-title"><?= icon('clipboard') ?> Tambah Quiz Baru</div>
        <button onclick="closeModal('addQuizModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" action="quiz.php">
        <input type="hidden" name="action" value="add_quiz">
        <div class="form-group">
            <label class="form-label">Materi <span style="color:red">*</span></label>
            <select name="material_id" required class="form-input form-select">
                <option value="">-- Pilih Materi --</option>
                <?php foreach ($matArr as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($matArr)): ?>
            <div style="color:#dc2626;font-size:12px;margin-top:4px">⚠ Belum ada materi. Tambah materi terlebih dahulu.</div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label">Judul Quiz <span style="color:red">*</span></label>
            <input type="text" name="title" required placeholder="Contoh: Quiz Aljabar Dasar" class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="description" placeholder="Deskripsi singkat..." class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Durasi (menit) <span style="color:red">*</span></label>
            <input type="number" name="duration_minutes" value="10" min="1" max="120" required class="form-input">
        </div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('addQuizModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full"<?= empty($matArr) ? ' disabled' : '' ?>>Simpan Quiz</button>
        </div>
    </form>
    </div>
</div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL: Edit Quiz
     ════════════════════════════════════════════════════ -->
<div id="editQuizModal" class="modal-backdrop hidden">
<div class="modal-box">
    <div class="modal-header">
        <div class="modal-title"><?= icon('pencil') ?> Edit Quiz</div>
        <button onclick="closeModal('editQuizModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" action="quiz.php">
        <input type="hidden" name="action" value="edit_quiz">
        <input type="hidden" name="id" id="eqId">
        <div class="form-group">
            <label class="form-label">Materi <span style="color:red">*</span></label>
            <select name="material_id" id="eqMat" required class="form-input form-select">
                <?php foreach ($matArr as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['kelas']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Judul Quiz <span style="color:red">*</span></label>
            <input type="text" name="title" id="eqTitle" required class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="description" id="eqDesc" class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Durasi (menit)</label>
            <input type="number" name="duration_minutes" id="eqDur" min="1" max="120" class="form-input">
        </div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('editQuizModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full">Simpan Perubahan</button>
        </div>
    </form>
    </div>
</div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL: Tambah Soal
     ════════════════════════════════════════════════════ -->
<div id="addQModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:560px">
    <div class="modal-header">
        <div>
            <div class="modal-title"><?= icon('memo') ?> Tambah Soal Baru</div>
            <div class="modal-sub">Pilih radio (●) di kiri untuk menandai jawaban yang benar</div>
        </div>
        <button onclick="closeModal('addQModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" action="quiz.php?quiz_id=<?= $vqid ?>">
        <input type="hidden" name="action"  value="add_question">
        <input type="hidden" name="quiz_id" value="<?= $vqid ?>">
        <div class="form-group">
            <label class="form-label">Pertanyaan <span style="color:red">*</span></label>
            <textarea name="question_text" rows="3" required placeholder="Tulis pertanyaan..." class="form-input" style="resize:vertical"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Pilihan Jawaban <span class="form-hint">● = tandai yang benar</span></label>
            <div style="display:flex;flex-direction:column;gap:8px">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="radio" name="correct_option" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>
                           style="width:16px;height:16px;accent-color:var(--navy);flex-shrink:0">
                    <span style="width:26px;height:26px;background:var(--navy-pale);border-radius:7px;
                                 display:flex;align-items:center;justify-content:center;
                                 font-size:11px;font-weight:800;color:var(--navy);flex-shrink:0">
                        <?= chr(65 + $i) ?>
                    </span>
                    <input type="text" name="options[]" required placeholder="Pilihan <?= chr(65 + $i) ?>" class="form-input" style="flex:1">
                </div>
            <?php endfor; ?>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Penjelasan <span class="form-hint">Opsional</span></label>
            <textarea name="explanation" rows="2" placeholder="Penjelasan jawaban benar..." class="form-input" style="resize:vertical"></textarea>
        </div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('addQModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-full" style="background:#059669;color:#fff;font-weight:800;padding:10px;border-radius:10px">
                Simpan Soal
            </button>
        </div>
    </form>
    </div>
</div>
</div>

<!-- ════════════════════════════════════════════════════
     MODAL: Edit Soal
     ════════════════════════════════════════════════════ -->
<div id="editQModal" class="modal-backdrop hidden">
<div class="modal-box" style="max-width:560px">
    <div class="modal-header">
        <div>
            <div class="modal-title"><?= icon('pencil') ?> Edit Soal</div>
            <div class="modal-sub">Ubah pertanyaan, pilihan, dan jawaban benar</div>
        </div>
        <button onclick="closeModal('editQModal')" class="modal-close"><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
    <form method="POST" action="quiz.php?quiz_id=<?= $vqid ?>">
        <input type="hidden" name="action"  value="edit_question">
        <input type="hidden" name="id"      id="eqQId">
        <input type="hidden" name="quiz_id" id="eqQQuizId" value="<?= $vqid ?>">
        <div class="form-group">
            <label class="form-label">Pertanyaan <span style="color:red">*</span></label>
            <textarea name="question_text" id="eqQText" rows="3" required
                      class="form-input" style="resize:vertical"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Pilihan Jawaban <span class="form-hint">● = tandai yang benar</span></label>
            <div style="display:flex;flex-direction:column;gap:8px">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="radio" name="correct_option" id="eqRad<?= $i ?>" value="<?= $i ?>"
                           style="width:16px;height:16px;accent-color:var(--navy);flex-shrink:0">
                    <span style="width:26px;height:26px;background:var(--navy-pale);border-radius:7px;
                                 display:flex;align-items:center;justify-content:center;
                                 font-size:11px;font-weight:800;color:var(--navy);flex-shrink:0">
                        <?= chr(65 + $i) ?>
                    </span>
                    <input type="text" name="options[]" id="eqOpt<?= $i ?>" required
                           placeholder="Pilihan <?= chr(65 + $i) ?>" class="form-input" style="flex:1">
                </div>
            <?php endfor; ?>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Penjelasan <span class="form-hint">Opsional</span></label>
            <textarea name="explanation" id="eqQExp" rows="2"
                      placeholder="Penjelasan jawaban benar..." class="form-input" style="resize:vertical"></textarea>
        </div>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="closeModal('editQModal')" class="btn btn-outline btn-full">Batal</button>
            <button type="submit" class="btn btn-navy btn-full">Simpan Perubahan</button>
        </div>
    </form>
    </div>
</div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.add('hidden');    document.body.style.overflow=''; }

function openEditQuiz(q) {
    document.getElementById('eqId').value    = q.id;
    document.getElementById('eqTitle').value = q.title;
    document.getElementById('eqDesc').value  = q.description || '';
    document.getElementById('eqDur').value   = q.duration_minutes;
    // Set selected option
    var sel = document.getElementById('eqMat');
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            sel.options[i].selected = (parseInt(sel.options[i].value) === q.material_id);
        }
    }
    openModal('editQuizModal');
}

function openEditQ(q) {
    document.getElementById('eqQId').value     = q.id;
    document.getElementById('eqQQuizId').value = q.quiz_id;
    document.getElementById('eqQText').value   = q.question_text;
    document.getElementById('eqQExp').value    = q.explanation || '';
    for (var i = 0; i < 4; i++) {
        var inp = document.getElementById('eqOpt' + i);
        var rad = document.getElementById('eqRad' + i);
        if (inp) inp.value   = (q.options && q.options[i] !== undefined) ? q.options[i] : '';
        if (rad) rad.checked = (q.correct_option === i);
    }
    openModal('editQModal');
}

// Tutup modal saat klik backdrop
['addQuizModal','editQuizModal','addQModal','editQModal'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('click', function(e) { if (e.target === this) closeModal(id); });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
