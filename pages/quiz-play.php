<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin(); $user = getCurrentUser(); $db = getDB();
$quiz_id = (int)($_GET['id'] ?? 0);
if(!$quiz_id){header('Location: quiz.php');exit;}
$quiz = $db->query("SELECT q.*,m.title as mt FROM quizzes q JOIN materials m ON q.material_id=m.id WHERE q.id=$quiz_id LIMIT 1")->fetch_assoc();
if(!$quiz){header('Location: quiz.php');exit;}
// Kelas access check
if(!isAdmin()){
    $userKelas=$user['kelas']??'';
    $kf=getKelasFilter($userKelas,'m.kelas');
    $ck=$db->query("SELECT q.id FROM quizzes q JOIN materials m ON q.material_id=m.id WHERE q.id=$quiz_id AND $kf LIMIT 1");
    if(!$ck||$ck->num_rows===0){header('Location: quiz.php');exit;}
}
$qres = $db->query("SELECT * FROM questions WHERE quiz_id=$quiz_id ORDER BY order_num,id");
$qData = [];
while($q=$qres->fetch_assoc()){
    $opts=$db->query("SELECT * FROM options WHERE question_id={$q['id']} ORDER BY id");
    $q['opts']=[]; while($o=$opts->fetch_assoc()) $q['opts'][]=$o;
    $qData[]=$q;
}
if(empty($qData)){header('Location: quiz.php');exit;}
$pageTitle = $quiz['title'];
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:640px;margin:0 auto">

    <!-- Info Bar -->
    <div class="card card-pad" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <div>
                <div style="font-weight:800;font-size:15px;color:var(--navy)"><?=htmlspecialchars($quiz['title'])?></div>
                <div style="font-size:12px;color:var(--muted);margin-top:2px"><?=htmlspecialchars($quiz['mt'])?></div>
            </div>
            <div id="timerBox" style="display:flex;align-items:center;gap:6px;background:var(--navy-pale);color:var(--navy);font-weight:800;font-size:18px;padding:8px 16px;border-radius:10px;transition:all .3s">
                <i class="ti ti-clock-hour-4" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> <span id="timerDisplay"><?=str_pad($quiz['duration_minutes'],2,'0',STR_PAD_LEFT)?>:00</span>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px">
            <span id="progressLabel">Soal 1 dari <?=count($qData)?></span>
            <span id="progressPct">0% selesai</span>
        </div>
        <div class="prog-wrap" style="height:8px"><div class="prog-bar" id="progressBar" style="width:0%"></div></div>
    </div>

    <!-- Questions -->
    <div id="quizContainer">
    <?php foreach($qData as $i=>$q): ?>
    <div class="question-slide <?=$i>0?'hidden':''?>" data-index="<?=$i?>">
        <div class="card card-pad" style="margin-bottom:12px">
            <div style="display:flex;align-items:start;gap:12px;margin-bottom:18px">
                <div class="stat-circle stat-circle-sm" style="width:30px;height:30px;font-size:13px;flex-shrink:0;margin-top:2px"><?=$i+1?></div>
                <p style="font-weight:600;font-size:14px;color:var(--text);line-height:1.6"><?=htmlspecialchars($q['question_text'])?></p>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach($q['opts'] as $j=>$opt): $ltr=chr(65+$j); ?>
                <button type="button" class="option-btn"
                    style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:11px;border:2px solid var(--border);background:#fff;cursor:pointer;text-align:left;font-size:13px;font-weight:500;color:var(--text);transition:all .15s;width:100%"
                    data-question-id="<?=$q['id']?>" data-option-id="<?=$opt['id']?>"
                    data-is-correct="<?=(int)$opt['is_correct']?>" data-explanation="<?=htmlspecialchars($q['explanation']??'')?>"
                    data-slide-idx="<?=$i?>"
                    onmouseover="if(!this.disabled)this.style.borderColor='#2d4a7a'"
                    onmouseout="if(!this.disabled&&!this.classList.contains('correct')&&!this.classList.contains('wrong'))this.style.borderColor='var(--border)'">
                    <span style="width:28px;height:28px;border-radius:7px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0"><?=$ltr?></span>
                    <?=htmlspecialchars($opt['option_text'])?>
                </button>
            <?php endforeach; ?>
            </div>
            <div id="feedback-<?=$q['id']?>" style="display:none;margin-top:12px"></div>
        </div>
        <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:24px">
            <?php if($i>0): ?>
            <button onclick="goTo(<?=$i-1?>)" class="btn btn-outline">← Sebelumnya</button>
            <?php else: ?><div></div><?php endif; ?>
            <?php if($i<count($qData)-1): ?>
            <button id="nextBtn-<?=$i?>" onclick="goTo(<?=$i+1?>)" disabled class="btn btn-navy" style="opacity:.35">Selanjutnya →</button>
            <?php else: ?>
            <button id="submitBtn" onclick="submitQuiz()" disabled class="btn btn-gold" style="opacity:.35"><i class="ti ti-check" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> Selesaikan Quiz</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <form id="quizForm" action="quiz-result.php" method="POST" style="display:none">
        <input type="hidden" name="quiz_id" value="<?=$quiz_id?>">
        <div id="answersContainer"></div>
    </form>
</div>

<style>
.hidden{display:none!important}
.option-btn.correct{border-color:#10b981!important;background:#ecfdf5!important;color:#065f46!important;font-weight:700!important}
.option-btn.wrong{border-color:#f87171!important;background:#fef2f2!important;color:#991b1b!important}
</style>
<script>
const totalQ=<?=count($qData)?>,totalSecs=<?=$quiz['duration_minutes']*60?>;
let cur=0,answered={},timeLeft=totalSecs;
const timerEl=document.getElementById('timerDisplay');
const timerBox=document.getElementById('timerBox');
const timerInt=setInterval(()=>{
    timeLeft--;
    const m=String(Math.floor(timeLeft/60)).padStart(2,'0');
    const s=String(timeLeft%60).padStart(2,'0');
    timerEl.textContent=m+':'+s;
    if(timeLeft<=60){timerBox.style.background='#fef2f2';timerBox.style.color='#dc2626';}
    if(timeLeft<=0){clearInterval(timerInt);submitQuiz();}
},1000);

function goTo(idx){
    document.querySelectorAll('.question-slide')[cur].classList.add('hidden');
    cur=idx;
    document.querySelectorAll('.question-slide')[cur].classList.remove('hidden');
    updateProg();
    window.scrollTo({top:0,behavior:'smooth'});
}
function updateProg(){
    const n=Object.keys(answered).length,pct=Math.round(n/totalQ*100);
    document.getElementById('progressBar').style.width=pct+'%';
    document.getElementById('progressLabel').textContent='Soal '+(cur+1)+' dari '+totalQ;
    document.getElementById('progressPct').textContent=pct+'% selesai';
}
document.querySelectorAll('.option-btn').forEach(btn=>{
    btn.addEventListener('click',function(){
        const qId=this.dataset.questionId,optId=this.dataset.optionId;
        const ok=this.dataset.isCorrect==='1',exp=this.dataset.explanation,si=parseInt(this.dataset.slideIdx);
        if(answered[qId])return;
        document.querySelectorAll(`.option-btn[data-question-id="${qId}"]`).forEach(b=>{
            b.disabled=true;
            if(b.dataset.isCorrect==='1') b.classList.add('correct');
        });
        if(!ok) this.classList.add('wrong');
        const fb=document.getElementById('feedback-'+qId);
        fb.style.display='block';
        if(ok){
            fb.innerHTML='<div style="padding:10px 14px;border-radius:10px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:13px;font-weight:600"><i class="ti ti-check" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> Jawaban benar! Pertahankan!</div>';
        } else {
            fb.innerHTML='<div style="padding:10px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;font-size:13px"><strong><i class="ti ti-x" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> Salah.</strong>'+(exp?'<div style="margin-top:6px;color:#374151;font-size:12px"><i class="ti ti-bulb" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> '+exp+'</div>':'')+'</div>';
        }
        answered[qId]=optId;
        const inp=document.createElement('input');
        inp.type='hidden';inp.name='answers['+qId+']';inp.value=optId;
        document.getElementById('answersContainer').appendChild(inp);
        const nb=document.getElementById('nextBtn-'+si);
        if(nb){nb.disabled=false;nb.style.opacity='1';}
        const sb=document.getElementById('submitBtn');
        if(sb){sb.disabled=false;sb.style.opacity='1';}
        updateProg();
    });
});
function submitQuiz(){
    clearInterval(timerInt);
    const el=document.createElement('input');el.type='hidden';el.name='elapsed';el.value=totalSecs-timeLeft;
    document.getElementById('answersContainer').appendChild(el);
    document.getElementById('quizForm').submit();
}
updateProg();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
