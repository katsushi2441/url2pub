<?php
// index.phpのJS(進捗チェックリスト)から段階的に呼ばれるAPI。解析→告知文→ブログ記事→
// 5媒体配信→履歴保存、をそれぞれ1コールずつ実行し、都度結果を返す。
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

$auth = url2ai_auth_bootstrap();
if (empty($auth['logged_in'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'ログインが必要です'), JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { $input = array(); }
$action = isset($_GET['action']) ? $_GET['action'] : '';

function ajax_fail($msg) {
    echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'analyze') {
    $url = isset($input['url']) ? trim((string)$input['url']) : '';
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) { ajax_fail('有効なURLを入力してください。'); }
    $r = u2p_api('/v1/analyze/url', array('url' => $url, 'depth' => 'full'));
    if ($r['status'] !== 200 || empty($r['data']['result'])) {
        ajax_fail(isset($r['data']['detail']) ? $r['data']['detail'] : '解析に失敗しました');
    }
    echo json_encode(array('ok' => true, 'source' => $r['data']['result']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'announcement') {
    if (empty($input['source'])) { ajax_fail('source is required'); }
    $r = u2p_api('/v1/generate/announcement', array('source' => $input['source'], 'language' => 'ja', 'tone' => 'neutral'));
    if ($r['status'] !== 200 || empty($r['data']['result'])) {
        ajax_fail(isset($r['data']['detail']) ? $r['data']['detail'] : '告知文の生成に失敗しました');
    }
    echo json_encode(array('ok' => true, 'announcement' => $r['data']['result']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'blog') {
    if (empty($input['source'])) { ajax_fail('source is required'); }
    $r = u2p_api('/v1/generate/blog-article', array('source' => $input['source'], 'language' => 'ja', 'tone' => 'neutral'));
    if ($r['status'] !== 200 || empty($r['data']['result'])) {
        ajax_fail(isset($r['data']['detail']) ? $r['data']['detail'] : 'ブログ記事の生成に失敗しました');
    }
    echo json_encode(array('ok' => true, 'blog' => $r['data']['result']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'post') {
    $platform = isset($_GET['platform']) ? $_GET['platform'] : '';
    $announcement = isset($input['announcement']) ? $input['announcement'] : array('text' => '');
    $blog = isset($input['blog']) ? $input['blog'] : array('title' => '', 'body_markdown' => '');
    $source = isset($input['source']) ? $input['source'] : array('url' => '');
    $text = isset($announcement['text']) ? $announcement['text'] : '';

    // 重複コンテンツ対策+媒体ごとの人格分け(LLM再生成なし・決定的な枠付け、2026-07-21方針):
    // Bluesky/Kurageブログ = Kurageペルソナ、AIxSNS/はてなブログ = bittensormanペルソナ。
    // はてなブックマークは短いコメントのため枠なし。
    if ($platform === 'bluesky') {
        $result = u2p_post_bluesky(u2p_persona_frame($text, 'kurage', 'announcement'));
    } elseif ($platform === 'hatena-bookmark') {
        $result = u2p_post_hatena_bookmark(isset($source['url']) ? $source['url'] : '', $text);
    } elseif ($platform === 'aixsns') {
        $result = u2p_post_aixsns(u2p_persona_frame($text, 'bittensorman', 'announcement'));
    } elseif ($platform === 'bludit') {
        $body = isset($blog['body_markdown']) ? $blog['body_markdown'] : '';
        $result = u2p_post_bludit(isset($blog['title']) ? $blog['title'] : '', u2p_persona_frame($body, 'kurage', 'blog'));
    } elseif ($platform === 'hatena-blog') {
        $body2 = isset($blog['body_markdown']) ? $blog['body_markdown'] : '';
        $result = u2p_post_hatena_blog(isset($blog['title']) ? $blog['title'] : '', u2p_persona_frame($body2, 'bittensorman', 'blog'));
    } else {
        ajax_fail('unknown platform: ' . $platform);
        exit;
    }
    echo json_encode(array('ok' => $result['ok'], 'url' => $result['url'], 'error' => $result['error']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'finish') {
    // JS側で組み立てた最終結果を受け取り、session(共有画面用)と履歴(ユーザーごと)に保存する。
    if (empty($input['source']) || empty($input['announcement']) || empty($input['blog'])) {
        ajax_fail('incomplete result');
    }
    $posted = isset($input['posted']) ? $input['posted'] : array();
    $record = array(
        'id' => u2p_history_new_id(),
        'created_at' => date('c'),
        'source' => $input['source'],
        'announcement' => $input['announcement'],
        'blog' => $input['blog'],
        'posted' => $posted,
    );
    u2p_history_save($auth['session_user'], $record);

    $_SESSION['pending_result'] = array(
        'source' => $input['source'], 'announcement' => $input['announcement'],
        'blog' => $input['blog'], 'posted' => $posted, 'history_id' => $record['id'],
    );
    unset($_SESSION['shared_confirmed']);
    echo json_encode(array('ok' => true, 'id' => $record['id']), JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(array('ok' => false, 'error' => 'unknown action'), JSON_UNESCAPED_UNICODE);
