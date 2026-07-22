<?php
// Local reward worker callback. This endpoint never holds the Bankr key; it only
// coordinates idempotent claim state with a shared secret.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

$secret = defined('URL2PUB_REWARD_CALLBACK_SECRET') ? URL2PUB_REWARD_CALLBACK_SECRET : '';
$provided = isset($_SERVER['HTTP_X_URL2PUB_REWARD_SECRET']) ? (string)$_SERVER['HTTP_X_URL2PUB_REWARD_SECRET'] : '';
if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'forbidden'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { $input = array(); }
$action = isset($input['action']) ? (string)$input['action'] : '';
$claim_id = isset($input['claim_id']) ? (string)$input['claim_id'] : '';

if ($action === 'start') {
    $result = u2p_reward_update($claim_id, array(
        'status' => 'processing',
        'attempts' => isset($input['attempts']) ? (int)$input['attempts'] : 1,
        'worker_started_at' => date('c'),
    ), array('queued', 'pending', 'failed', 'enqueue_failed'));
} elseif ($action === 'sent') {
    $tx_hash = isset($input['tx_hash']) ? strtolower(trim((string)$input['tx_hash'])) : '';
    if (!preg_match('/^0x[a-f0-9]{64}$/', $tx_hash)) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'invalid transaction hash'));
        exit;
    }
    $result = u2p_reward_update($claim_id, array(
        'status' => 'sent', 'tx_hash' => $tx_hash, 'sent_at' => date('c'), 'message' => '',
    ), array('processing'));
} elseif ($action === 'failed') {
    $message = isset($input['message']) ? mb_substr((string)$input['message'], 0, 500) : '送金に失敗しました';
    $result = u2p_reward_update($claim_id, array('status' => 'failed', 'message' => $message), array('processing'));
} elseif ($action === 'status') {
    $claim = u2p_reward_find($claim_id);
    $result = $claim ? array('ok' => true, 'claim' => $claim) : array('ok' => false, 'message' => 'claim not found');
} else {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => 'unknown action'));
    exit;
}

if (empty($result['ok'])) { http_response_code(409); }
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
