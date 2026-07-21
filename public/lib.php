<?php
// url2pub共通ヘルパー。url2brain APIの薄いラッパーと、Xユーザー単位の履歴保存。
// url2pub.php(画面描画)とajax.php(段階的な生成/配信API)の両方から使う。

function u2p_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ペルソナ別の枠付け(LLM再生成なし、決定的なテンプレート処理)。同一文面がそのまま
// 複数媒体に並ぶ重複コンテンツを緩和しつつ、媒体ごとにキャラクター性を出す。
// Bluesky(announcement)は280字制限があるため枠を最小限にする。
function u2p_persona_frame($text, $persona, $kind) {
    $text = trim((string)$text);
    if ($persona === 'kurage') {
        if ($kind === 'announcement') {
            return '🪼 ' . $text;
        }
        return "🪼 Kurageです。今日はこちらをご紹介しますね。\n\n" . $text . "\n\n---\n*— Kurage*";
    }
    if ($persona === 'bittensorman') {
        if ($kind === 'announcement') {
            return '【開発者より】' . "\n" . $text . "\n— bittensorman";
        }
        return "開発者・経営者の視点から。\n\n" . $text . "\n\n---\n*— bittensorman（開発者・経営者）*";
    }
    return $text;
}

function u2p_api($path, $payload, $timeout = 180) {
    $base = rtrim(URL2BRAIN_API_BASE, '/');
    $headers = array('Accept: application/json', 'Content-Type: application/json');
    if (URL2BRAIN_API_TOKEN !== '') {
        $headers[] = 'X-URL2BRAIN-Token: ' . URL2BRAIN_API_TOKEN;
    }
    $ch = curl_init($base . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $error !== '') {
        return array('status' => 502, 'data' => array('ok' => false, 'detail' => $error !== '' ? $error : 'url2brain接続に失敗しました'));
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return array('status' => 502, 'data' => array('ok' => false, 'detail' => 'url2brainから不正な応答'));
    }
    return array('status' => $status ? $status : 502, 'data' => $decoded);
}

function u2p_post_result($r) {
    $detail = isset($r['data']['result']['detail']) ? $r['data']['result']['detail'] : array();
    $ok = !empty($r['data']['result']['ok']);
    $url = isset($detail['post_url']) ? $detail['post_url'] : (isset($detail['permalink']) ? $detail['permalink'] : '');
    if ($url === '' && !empty($detail['item']['id'])) {
        $url = 'https://aixec.exbridge.jp/#post-' . $detail['item']['id'];
    }
    if ($ok) {
        $err = '';
    } else {
        $err = isset($r['data']['detail']) ? $r['data']['detail'] : (isset($detail['error']) ? $detail['error'] : '投稿に失敗しました');
    }
    return array('ok' => $ok, 'url' => $url, 'error' => $err);
}

function u2p_post_bluesky($text) {
    $r = u2p_api('/post/bluesky', array('text' => $text, 'url' => '', 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_hatena_bookmark($url, $text) {
    $r = u2p_api('/post/hatena-bookmark', array('url' => $url, 'comment' => mb_substr($text, 0, 90), 'tags' => array(), 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_aixsns($text) {
    $r = u2p_api('/post/aixsns', array('content' => $text, 'author' => 'url2pub', 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_bludit($title, $body) {
    $r = u2p_api('/post/bludit', array('title' => $title, 'body_markdown' => $body, 'category' => 'url2pub', 'tags' => 'url2pub', 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_hatena_blog($title, $body) {
    $r = u2p_api('/post/hatena-blog', array('title' => $title, 'body_markdown' => $body, 'confirm_post' => true));
    return u2p_post_result($r);
}

function u2p_tweet_intent($text, $url = '') {
    $params = array('text' => $text);
    if ($url !== '') { $params['url'] = $url; }
    return 'https://twitter.com/intent/tweet?' . http_build_query($params);
}

// --- 履歴保存(Xユーザー単位のJSONファイル。DB不要でOSS配布しやすい形) ---

define('U2P_HISTORY_DIR', __DIR__ . '/storage/history');

function u2p_history_path($username) {
    $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$username);
    return U2P_HISTORY_DIR . '/' . $safe . '.json';
}

function u2p_history_load($username) {
    $path = u2p_history_path($username);
    if (!file_exists($path)) { return array(); }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : array();
}

function u2p_history_save($username, $record) {
    if (!is_dir(U2P_HISTORY_DIR)) { mkdir(U2P_HISTORY_DIR, 0775, true); }
    $records = u2p_history_load($username);
    array_unshift($records, $record);
    // 1ユーザーあたり最新200件まで
    if (count($records) > 200) { $records = array_slice($records, 0, 200); }
    file_put_contents(u2p_history_path($username), json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function u2p_history_find($username, $id) {
    foreach (u2p_history_load($username) as $r) {
        if (isset($r['id']) && $r['id'] === $id) { return $r; }
    }
    return null;
}

function u2p_history_new_id() {
    return date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 6);
}

// 管理者用: 全ユーザーの利用履歴を横断して新しい順に返す(username, url, created_at, id)。
// Xのユーザー名は英数字/アンダースコアのみなのでファイル名=ユーザー名としてそのまま使える。
function u2p_history_all_users($limit = 200) {
    if (!is_dir(U2P_HISTORY_DIR)) { return array(); }
    $all = array();
    foreach (glob(U2P_HISTORY_DIR . '/*.json') as $path) {
        $username = basename($path, '.json');
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) { continue; }
        foreach ($data as $r) {
            $all[] = array(
                'username' => $username,
                'id' => isset($r['id']) ? $r['id'] : '',
                'url' => isset($r['source']['url']) ? $r['source']['url'] : '',
                'title' => isset($r['blog']['title']) ? $r['blog']['title'] : '',
                'created_at' => isset($r['created_at']) ? $r['created_at'] : '',
            );
        }
    }
    usort($all, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
    return array_slice($all, 0, $limit);
}
