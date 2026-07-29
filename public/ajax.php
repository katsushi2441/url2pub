<?php
// url2pub.phpのJS(進捗チェックリスト)から段階的に呼ばれるAPI。解析→告知文→ブログ記事→
// 5媒体配信→履歴保存、をそれぞれ1コールずつ実行し、都度結果を返す。
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

// 出力言語: url2pub.php が設定する Cookie u2p_lang に従う（en/ja）。生成物とエラー文言に反映。
$u2p_lang = (isset($_COOKIE['u2p_lang']) && $_COOKIE['u2p_lang'] === 'en') ? 'en' : 'ja';
function am($ja, $en) { global $u2p_lang; return $u2p_lang === 'en' ? $en : $ja; }

$auth = url2ai_auth_bootstrap();
if (empty($auth['logged_in'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => am('ログインが必要です', 'Sign-in required')), JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { $input = array(); }
$action = isset($_GET['action']) ? $_GET['action'] : '';

function ajax_fail($msg) {
    echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
    exit;
}

function ajax_payload_hash($value) {
    return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ajax_require_run($run_id) {
    if ($run_id === '' || empty($_SESSION['u2p_run']['id']) || !hash_equals((string)$_SESSION['u2p_run']['id'], (string)$run_id)) {
        ajax_fail(am('配信セッションが無効です。最初からやり直してください。', 'Your session is invalid. Please start over.'));
    }
    return $_SESSION['u2p_run'];
}

function ajax_verify_hash($run, $key, $value) {
    if (empty($run[$key]) || !hash_equals((string)$run[$key], ajax_payload_hash($value))) {
        ajax_fail(am('配信データを確認できません。最初からやり直してください。', 'Could not verify the run data. Please start over.'));
    }
}

if ($action === 'analyze') {
    $url = isset($input['url']) ? trim((string)$input['url']) : '';
    $wallet = u2p_reward_wallet(isset($input['wallet']) ? $input['wallet'] : '');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) { ajax_fail(am('有効なURLを入力してください。', 'Please enter a valid URL.')); }
    $r = u2p_api('/analyze/url', array('url' => $url, 'depth' => 'full'));
    if ($r['status'] !== 200 || empty($r['data']['result'])) {
        ajax_fail(isset($r['data']['detail']) ? $r['data']['detail'] : am('解析に失敗しました', 'Analysis failed'));
    }
    $run_id = bin2hex(random_bytes(16));
    $_SESSION['u2p_run'] = array(
        'id' => $run_id,
        'username' => $auth['session_user'],
        'wallet' => $wallet,
        'url' => $url,
        'lang' => $u2p_lang,
        'source_hash' => ajax_payload_hash($r['data']['result']),
        'stages' => array('analyze' => date('c')),
        'created_at' => date('c'),
    );
    echo json_encode(array('ok' => true, 'source' => $r['data']['result'], 'run_id' => $run_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'announcement') {
    $run_id = isset($input['run_id']) ? (string)$input['run_id'] : '';
    $run = ajax_require_run($run_id);
    if (empty($input['source'])) { ajax_fail('source is required'); }
    ajax_verify_hash($run, 'source_hash', $input['source']);
    $r = u2p_api('/generate/announcement', array('source' => $input['source'], 'language' => (isset($run['lang']) ? $run['lang'] : $u2p_lang), 'tone' => 'neutral'));
    if ($r['status'] !== 200 || empty($r['data']['result'])) {
        ajax_fail(isset($r['data']['detail']) ? $r['data']['detail'] : am('告知文の生成に失敗しました', 'Failed to generate the announcement'));
    }
    $_SESSION['u2p_run']['announcement_hash'] = ajax_payload_hash($r['data']['result']);
    $_SESSION['u2p_run']['stages']['announcement'] = date('c');
    echo json_encode(array('ok' => true, 'announcement' => $r['data']['result']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'blog') {
    $run_id = isset($input['run_id']) ? (string)$input['run_id'] : '';
    $run = ajax_require_run($run_id);
    if (empty($input['source'])) { ajax_fail('source is required'); }
    ajax_verify_hash($run, 'source_hash', $input['source']);
    if (empty($run['stages']['announcement'])) { ajax_fail(am('告知文の生成が完了していません', 'The announcement has not been generated yet')); }
    $r = u2p_api('/generate/blog-article', array('source' => $input['source'], 'language' => (isset($run['lang']) ? $run['lang'] : $u2p_lang), 'tone' => 'neutral'));
    if ($r['status'] !== 200 || empty($r['data']['result'])) {
        ajax_fail(isset($r['data']['detail']) ? $r['data']['detail'] : am('ブログ記事の生成に失敗しました', 'Failed to generate the blog post'));
    }
    $_SESSION['u2p_run']['blog_hash'] = ajax_payload_hash($r['data']['result']);
    $_SESSION['u2p_run']['stages']['blog'] = date('c');
    echo json_encode(array('ok' => true, 'blog' => $r['data']['result']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'post') {
    $run_id = isset($input['run_id']) ? (string)$input['run_id'] : '';
    $run = ajax_require_run($run_id);
    $platform = isset($_GET['platform']) ? $_GET['platform'] : '';
    $announcement = isset($input['announcement']) ? $input['announcement'] : array('text' => '');
    $blog = isset($input['blog']) ? $input['blog'] : array('title' => '', 'body_markdown' => '');
    $source = isset($input['source']) ? $input['source'] : array('url' => '');
    $text = isset($announcement['text']) ? $announcement['text'] : '';
    $allowed_platforms = array('bluesky', 'hatena-bookmark', 'aixsns', 'bludit', 'hatena-blog');
    if (!in_array($platform, $allowed_platforms, true)) { ajax_fail('unknown platform: ' . $platform); }
    ajax_verify_hash($run, 'source_hash', $source);
    ajax_verify_hash($run, 'announcement_hash', $announcement);
    ajax_verify_hash($run, 'blog_hash', $blog);
    // 媒体障害は利用者の責任ではないため、成功ではなく「試行」を報酬条件にする。
    $_SESSION['u2p_run']['stages']['post-' . $platform] = date('c');

    // 重複コンテンツ対策+媒体ごとの人格分け(LLM再生成なし・決定的な枠付け、2026-07-21方針):
    // Bluesky/Kurageブログ = Kurageペルソナ、AIxSNS/はてなブログ = bittensormanペルソナ。
    // はてなブックマークは短いコメントのため枠なし。
    $run_lang = isset($run['lang']) ? $run['lang'] : $u2p_lang;
    if ($platform === 'bluesky') {
        $result = u2p_post_bluesky(u2p_persona_frame($text, 'kurage', 'announcement', $run_lang));
    } elseif ($platform === 'hatena-bookmark') {
        $result = u2p_post_hatena_bookmark(isset($source['url']) ? $source['url'] : '', $text);
    } elseif ($platform === 'aixsns') {
        $result = u2p_post_aixsns(u2p_persona_frame($text, 'bittensorman', 'announcement', $run_lang));
    } elseif ($platform === 'bludit') {
        $body = isset($blog['body_markdown']) ? $blog['body_markdown'] : '';
        $result = u2p_post_bludit(isset($blog['title']) ? $blog['title'] : '', u2p_persona_frame($body, 'kurage', 'blog', $run_lang));
    } elseif ($platform === 'hatena-blog') {
        $body2 = isset($blog['body_markdown']) ? $blog['body_markdown'] : '';
        $result = u2p_post_hatena_blog(isset($blog['title']) ? $blog['title'] : '', u2p_persona_frame($body2, 'bittensorman', 'blog', $run_lang));
    } else {
        ajax_fail('unknown platform: ' . $platform);
        exit;
    }
    echo json_encode(array('ok' => $result['ok'], 'url' => $result['url'], 'error' => $result['error']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'finish') {
    $run_id = isset($input['run_id']) ? (string)$input['run_id'] : '';
    $run = ajax_require_run($run_id);
    if (!empty($run['finished_history_id'])) {
        $claim = !empty($run['reward_claim_id']) ? u2p_reward_find($run['reward_claim_id']) : null;
        echo json_encode(array('ok' => true, 'id' => $run['finished_history_id'], 'reward' => u2p_reward_public($claim)), JSON_UNESCAPED_UNICODE);
        exit;
    }
    // JS側の最終結果に加え、同じsessionで全工程が実行されたことを検証して保存する。
    if (empty($input['source']) || empty($input['announcement']) || empty($input['blog'])) {
        ajax_fail('incomplete result');
    }
    ajax_verify_hash($run, 'source_hash', $input['source']);
    ajax_verify_hash($run, 'announcement_hash', $input['announcement']);
    ajax_verify_hash($run, 'blog_hash', $input['blog']);
    foreach (array('bluesky', 'hatena-bookmark', 'aixsns', 'bludit', 'hatena-blog') as $required_platform) {
        if (empty($run['stages']['post-' . $required_platform])) { ajax_fail(am($required_platform . 'への配信が未試行です', 'Publishing to ' . $required_platform . ' was not attempted')); }
    }
    $posted = isset($input['posted']) ? $input['posted'] : array();
    $history_id = u2p_history_new_id();
    $reward_result = array('ok' => true, 'eligible' => false, 'status' => 'disabled', 'message' => '利用特典は現在停止中です');
    if (u2p_reward_enabled()) {
        if ($run['wallet'] === '') {
            // ウォレットは任意(2026-07-29): 未接続でも配信は完了扱い。特典申請だけ無し。
            $reward_result = array('ok' => true, 'eligible' => false, 'status' => 'no_wallet',
                'message' => am('ウォレット未接続のため特典申請はありません（配信は完了しています）',
                                'No wallet connected — publishing completed, no reward claim.'));
        } else {
            $reward_result = u2p_reward_reserve($auth['session_user'], $run['wallet'], $history_id);
        }
    }
    $record = array(
        'id' => $history_id,
        'created_at' => date('c'),
        'source' => $input['source'],
        'announcement' => $input['announcement'],
        'blog' => $input['blog'],
        'posted' => $posted,
        'reward_claim_id' => !empty($reward_result['claim']['id']) ? $reward_result['claim']['id'] : '',
        'reward_status' => isset($reward_result['status']) ? $reward_result['status'] : '',
    );
    u2p_history_save($auth['session_user'], $record);

    if (!empty($reward_result['eligible']) && !empty($reward_result['claim'])) {
        $queued = u2p_reward_enqueue($reward_result['claim']);
        if (empty($queued['ok'])) {
            u2p_reward_update($reward_result['claim']['id'], array('status' => 'enqueue_failed', 'message' => $queued['error']), array('pending'));
        }
        $reward_result['claim'] = u2p_reward_find($reward_result['claim']['id']);
    }

    $_SESSION['pending_result'] = array(
        'source' => $input['source'], 'announcement' => $input['announcement'],
        'blog' => $input['blog'], 'posted' => $posted, 'history_id' => $record['id'],
        'reward_claim_id' => $record['reward_claim_id'], 'reward_status' => $record['reward_status'],
    );
    $_SESSION['u2p_run']['finished_history_id'] = $record['id'];
    $_SESSION['u2p_run']['reward_claim_id'] = $record['reward_claim_id'];
    unset($_SESSION['shared_confirmed']);
    $public_reward = !empty($reward_result['claim']) ? u2p_reward_public($reward_result['claim']) : array(
        'status' => isset($reward_result['status']) ? $reward_result['status'] : 'unavailable',
        'message' => isset($reward_result['message']) ? $reward_result['message'] : '',
    );
    echo json_encode(array('ok' => true, 'id' => $record['id'], 'reward' => $public_reward), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'reward-status') {
    $claim_id = isset($input['claim_id']) ? (string)$input['claim_id'] : '';
    $claim = u2p_reward_find($claim_id);
    if (!$claim || u2p_reward_user($claim['username']) !== u2p_reward_user($auth['session_user'])) {
        ajax_fail('報酬申請を確認できません');
    }
    echo json_encode(array('ok' => true, 'reward' => u2p_reward_public($claim)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode(array('ok' => false, 'error' => 'unknown action'), JSON_UNESCAPED_UNICODE);
