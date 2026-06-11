<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$streak = updateStreak($uid);
echo json_encode(['success' => true, 'streak' => $streak]);
