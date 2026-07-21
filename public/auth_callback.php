<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/x_auth.php';
session_start();

$code = isset($_GET['code']) ? (string)$_GET['code'] : '';
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';

if ($code === '' || $state === '' || empty($_SESSION['x_oauth_state']) || !hash_equals($_SESSION['x_oauth_state'], $state)) {
    http_response_code(400);
    echo 'ログインに失敗しました(state不一致)。<a href="index.php">やり直す</a>';
    exit;
}

$tok = xa_exchange_code($code);
unset($_SESSION['x_pkce_verifier'], $_SESSION['x_oauth_state']);
if (!$tok['ok']) {
    http_response_code(400);
    echo 'ログインに失敗しました: ' . htmlspecialchars($tok['error'], ENT_QUOTES, 'UTF-8') . ' <a href="index.php">やり直す</a>';
    exit;
}

$me = xa_fetch_me($tok['access_token']);
if (!$me['ok']) {
    http_response_code(400);
    echo 'ユーザー情報の取得に失敗しました。<a href="index.php">やり直す</a>';
    exit;
}

$_SESSION['x_user'] = array('username' => $me['username'], 'name' => $me['name'], 'id' => $me['id']);
header('Location: index.php');
exit;
