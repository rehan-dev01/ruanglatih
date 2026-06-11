<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: quiz.php');exit;}
$db=$db=getDB();$uid=(int)$_SESSION['user_id'];
$quiz_id=(int)($_POST['quiz_id']??0);$answers=$_POST['answers']??[];
if(!$quiz_id){header('Location: quiz.php');exit;}
$quiz=$db->query("SELECT q.*,m.title as mt FROM quizzes q JOIN materials m ON q.material_id=m.id WHERE q.id=$quiz_id LIMIT 1")->fetch_assoc();
if(!$quiz){header('Location: quiz.php');exit;}
$qres=$db->query("SELECT * FROM questions WHERE quiz_id=$quiz_id ORDER BY order_num,id");
$correct=0;$total=0;$details=[];
while($q=$qres->fetch_assoc()){
    $total++;$qId=$q['id'];$selId=(int)($answers[$qId]??0);$ok=false;
    if($selId){$chk=$db->query("SELECT is_correct FROM options WHERE id=$selId LIMIT 1")->fetch_assoc();if($chk&&$chk['is_correct']){$correct++;$ok=true;}}
    $corrOpt=$db->query("SELECT * FROM options WHERE question_id=$qId AND is_correct=1 LIMIT 1")->fetch_assoc();
    $selOpt=$selId?$db->query("SELECT * FROM options WHERE id=$selId LIMIT 1")->fetch_assoc():null;
    $details[]=['q'=>$q,'sel'=>$selOpt,'corr'=>$corrOpt,'ok'=>$ok];
}
$score=$total>0?round(($correct/$total)*100):0;
$pts=$correct*10;
$stmt=$db->prepare("INSERT INTO quiz_results(user_id,quiz_id,score,total_questions) VALUES(?,?,?,?)");
$stmt->bind_param('iiii',$uid,$quiz_id,$score,$total);$stmt->execute();
$rid=$db->insert_id;
foreach($answers as $qid=>$oid){
    $qid=(int)$qid;$oid=(int)$oid;$ic=0;
    foreach($details as $d){if($d['q']['id']==$qid&&$d['ok']){$ic=1;break;}}
    $db->query("INSERT INTO user_answers(result_id,question_id,selected_option_id,is_correct) VALUES($rid,$qid,$oid,$ic)");
}
$db->query("UPDATE users SET total_points=total_points+$pts WHERE id=$uid");
updateStreak($uid);
$user=getCurrentUser();$grade=getGrade($score);
$emoji=$score>=90?icon('trophy'):($score>=80?icon('star'):($score>=60?icon('thumbs-up'):icon('muscle')));
$msg=$score>=90?'Sempurna! Luar biasa!':($score>=80?'Bagus sekali!':($score>=60?'Lumayan, terus berlatih!':'Jangan menyerah!'));
$pageTitle='Hasil Quiz';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:560px;margin:0 auto">

    <!-- Score Card -->
    <div style="background:linear-gradient(135deg,var(--navy) 0%,var(--navy-lt) 100%);border-radius:var(--r-xl);padding:32px;text-align:center;margin-bottom:16px;color:#fff">
        <div style="font-size:52px;margin-bottom:10px"><?=$emoji?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.6);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px"><?=$msg?></div>
        <div style="font-size:72px;font-weight:900;line-height:1;color:var(--gold)"><?=$score?></div>
        <div style="font-size:16px;color:rgba(255,255,255,.6);margin:4px 0 14px">/ 100</div>
        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);padding:7px 20px;border-radius:99px;font-weight:800;font-size:14px;margin-bottom:24px">
            Grade <span style="font-size:20px;color:var(--gold)"><?=$grade['label']?></span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-top:1px solid rgba(255,255,255,.15);padding-top:20px">
            <div><div style="font-size:24px;font-weight:800;color:var(--gold)"><?=$correct?></div><div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:2px">Benar</div></div>
            <div><div style="font-size:24px;font-weight:800"><?=$total-$correct?></div><div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:2px">Salah</div></div>
            <div><div style="font-size:24px;font-weight:800;color:var(--gold)">+<?=$pts?></div><div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:2px">Poin</div></div>
        </div>
    </div>

    <!-- Review -->
    <div class="card card-pad" style="margin-bottom:16px">
        <div style="font-weight:800;font-size:14px;color:var(--navy);margin-bottom:16px"><i class="ti ti-clipboard" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> Review Jawaban</div>
        <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach($details as $i=>$d): ?>
        <div style="border-radius:12px;border:2px solid <?=$d['ok']?'#a7f3d0':'#fca5a5'?>;overflow:hidden">
            <div style="display:flex;align-items:start;gap:10px;padding:11px 14px;background:<?=$d['ok']?'#ecfdf5':'#fef2f2'?>">
                <span style="font-size:18px;flex-shrink:0"><?=$d['ok']?icon('check-circle'):icon('red-x')?></span>
                <div>
                    <span style="font-size:10px;color:var(--muted)">Soal <?=$i+1?> &nbsp;</span>
                    <span style="font-size:13px;font-weight:600;color:var(--text)"><?=htmlspecialchars($d['q']['question_text'])?></span>
                </div>
            </div>
            <?php if(!$d['ok']): ?>
            <div style="padding:12px 14px;background:#fff;font-size:12px">
                <?php if($d['sel']): ?><div style="color:#dc2626;margin-bottom:5px"><strong>Jawabanmu:</strong> <?=htmlspecialchars($d['sel']['option_text'])?></div><?php endif; ?>
                <?php if($d['corr']): ?><div style="color:#059669;margin-bottom:5px"><strong>Jawaban Benar:</strong> <?=htmlspecialchars($d['corr']['option_text'])?></div><?php endif; ?>
                <?php if(!empty($d['q']['explanation'])): ?><div style="background:var(--gold-pale);border:1px solid var(--gold-lt);border-radius:8px;padding:9px 12px;color:#92400e;margin-top:6px"><strong><i class="ti ti-bulb" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> Penjelasan:</strong> <?=htmlspecialchars($d['q']['explanation'])?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Actions -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:24px">
        <a href="quiz.php" class="btn btn-outline btn-full">← Quiz Lain</a>
        <a href="quiz-play.php?id=<?=$quiz_id?>" class="btn btn-navy btn-full"><i class="ti ti-refresh" style="font-size:1.1em;vertical-align:middle;display:inline-block;line-height:1;flex-shrink:0;"></i> Ulangi</a>
        <a href="<?=APP_URL?>/dashboard.php" class="btn btn-gold btn-full">Dashboard →</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
