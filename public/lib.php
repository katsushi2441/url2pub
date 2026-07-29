<?php
// url2pub共通ヘルパー。url2brain APIの薄いラッパーと、Xユーザー単位の履歴保存。
// url2pub.php(画面描画)とajax.php(段階的な生成/配信API)の両方から使う。

function u2p_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ペルソナ別の枠付け(LLM再生成なし、決定的なテンプレート処理)。同一文面がそのまま
// 複数媒体に並ぶ重複コンテンツを緩和しつつ、媒体ごとにキャラクター性を出す。
// Bluesky(announcement)は280字制限があるため枠を最小限にする。
function u2p_persona_frame($text, $persona, $kind, $lang = 'ja') {
    $text = trim((string)$text);
    $en = ($lang === 'en');
    if ($persona === 'kurage') {
        if ($kind === 'announcement') {
            return '🪼 ' . $text;
        }
        if ($en) {
            return "🪼 Hi, I'm Kurage. Let me introduce this today.\n\n" . $text . "\n\n---\n*— Kurage*";
        }
        return "🪼 Kurageです。今日はこちらをご紹介しますね。\n\n" . $text . "\n\n---\n*— Kurage*";
    }
    if ($persona === 'bittensorman') {
        if ($kind === 'announcement') {
            return ($en ? "[From the developer]" : '【開発者より】') . "\n" . $text . "\n— bittensorman";
        }
        if ($en) {
            return "From a developer & founder's perspective.\n\n" . $text . "\n\n---\n*— bittensorman (developer & founder)*";
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

// --- URLAI利用特典 ---

define('U2P_REWARD_LEDGER', __DIR__ . '/storage/rewards.json');

function u2p_reward_enabled() {
    return defined('URL2PUB_REWARD_ENABLED') && URL2PUB_REWARD_ENABLED;
}

function u2p_reward_wallet($value) {
    $wallet = strtolower(trim((string)$value));
    return preg_match('/^0x[a-f0-9]{40}$/', $wallet) ? $wallet : '';
}

function u2p_reward_user($value) {
    return strtolower(ltrim(trim((string)$value), '@'));
}

function u2p_reward_with_ledger($callback) {
    $dir = dirname(U2P_REWARD_LEDGER);
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $fp = fopen(U2P_REWARD_LEDGER, 'c+');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        if ($fp !== false) { fclose($fp); }
        throw new RuntimeException('報酬台帳を開けません');
    }
    rewind($fp);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = array('version' => 1, 'claims' => array()); }
    if (!isset($data['claims']) || !is_array($data['claims'])) { $data['claims'] = array(); }
    $result = call_user_func_array($callback, array(&$data));
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function u2p_reward_reserve($username, $wallet, $history_id) {
    $user_key = u2p_reward_user($username);
    $wallet_key = u2p_reward_wallet($wallet);
    if ($user_key === '' || $wallet_key === '') {
        return array('ok' => false, 'status' => 'invalid_wallet', 'message' => 'Baseウォレットを確認できません');
    }
    return u2p_reward_with_ledger(function (&$data) use ($username, $user_key, $wallet_key, $history_id) {
        foreach ($data['claims'] as $claim) {
            if (isset($claim['user_key']) && $claim['user_key'] === $user_key) {
                return array('ok' => true, 'eligible' => false, 'status' => 'already_claimed', 'claim' => $claim, 'message' => 'このXアカウントは利用特典を申請済みです');
            }
            if (isset($claim['wallet']) && strtolower($claim['wallet']) === $wallet_key) {
                return array('ok' => true, 'eligible' => false, 'status' => 'already_claimed', 'message' => 'このウォレットは利用特典を申請済みです');
            }
        }
        $limit = defined('URL2PUB_REWARD_LIMIT') ? (int)URL2PUB_REWARD_LIMIT : 1000;
        if (count($data['claims']) >= $limit) {
            return array('ok' => true, 'eligible' => false, 'status' => 'closed', 'message' => 'URLAI利用特典は上限に達しました');
        }
        $claim = array(
            'id' => 'urlai-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'username' => (string)$username,
            'user_key' => $user_key,
            'wallet' => $wallet_key,
            'history_id' => (string)$history_id,
            'amount' => defined('URL2PUB_REWARD_AMOUNT') ? (string)URL2PUB_REWARD_AMOUNT : '10000',
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        );
        $data['claims'][] = $claim;
        return array('ok' => true, 'eligible' => true, 'status' => 'pending', 'claim' => $claim, 'remaining' => $limit - count($data['claims']));
    });
}

function u2p_reward_find($claim_id) {
    if (!file_exists(U2P_REWARD_LEDGER)) { return null; }
    $data = json_decode(file_get_contents(U2P_REWARD_LEDGER), true);
    if (!is_array($data) || empty($data['claims'])) { return null; }
    foreach ($data['claims'] as $claim) {
        if (isset($claim['id']) && hash_equals((string)$claim['id'], (string)$claim_id)) { return $claim; }
    }
    return null;
}

function u2p_reward_update($claim_id, $changes, $allowed_statuses = null) {
    return u2p_reward_with_ledger(function (&$data) use ($claim_id, $changes, $allowed_statuses) {
        foreach ($data['claims'] as &$claim) {
            if (!isset($claim['id']) || !hash_equals((string)$claim['id'], (string)$claim_id)) { continue; }
            if (is_array($allowed_statuses) && !in_array(isset($claim['status']) ? $claim['status'] : '', $allowed_statuses, true)) {
                return array('ok' => false, 'claim' => $claim, 'message' => 'status conflict');
            }
            foreach ($changes as $key => $value) { $claim[$key] = $value; }
            $claim['updated_at'] = date('c');
            return array('ok' => true, 'claim' => $claim);
        }
        return array('ok' => false, 'message' => 'claim not found');
    });
}

function u2p_reward_public($claim) {
    if (!is_array($claim)) { return null; }
    $wallet = isset($claim['wallet']) ? $claim['wallet'] : '';
    return array(
        'id' => isset($claim['id']) ? $claim['id'] : '',
        'amount' => isset($claim['amount']) ? $claim['amount'] : '10000',
        'status' => isset($claim['status']) ? $claim['status'] : 'pending',
        'wallet' => strlen($wallet) === 42 ? substr($wallet, 0, 6) . '...' . substr($wallet, -4) : '',
        'tx_hash' => isset($claim['tx_hash']) ? $claim['tx_hash'] : '',
        'tx_url' => !empty($claim['tx_hash']) ? 'https://basescan.org/tx/' . rawurlencode($claim['tx_hash']) : '',
        'message' => isset($claim['message']) ? $claim['message'] : '',
    );
}

function u2p_reward_enqueue($claim) {
    if (!defined('RQDB4AI_API_BASE') || !defined('RQDB4AI_OPERATE_TOKEN') || RQDB4AI_OPERATE_TOKEN === '') {
        return array('ok' => false, 'error' => '報酬キューが未設定です');
    }
    $payload = array(
        'queue' => defined('URL2PUB_REWARD_QUEUE') ? URL2PUB_REWARD_QUEUE : 'url2pub-reward',
        'function' => defined('URL2PUB_REWARD_FUNCTION') ? URL2PUB_REWARD_FUNCTION : 'url2pub_reward_jobs.send_urlai_reward',
        'kwargs' => array(
            'claim_id' => $claim['id'],
            'username' => $claim['username'],
            'wallet' => $claim['wallet'],
            'history_id' => $claim['history_id'],
            'amount' => $claim['amount'],
        ),
        'meta' => array(
            'project' => 'url2pub', 'kind' => 'urlai_reward', 'source' => 'web',
            'description' => 'URL2Pub利用特典 ' . $claim['amount'] . ' URLAI -> ' . substr($claim['wallet'], 0, 8) . '...',
        ),
        'timeout' => 180,
        'result_ttl' => 2592000,
        'failure_ttl' => 2592000,
    );
    $ch = curl_init(rtrim(RQDB4AI_API_BASE, '/') . '/api/enqueue');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . RQDB4AI_OPERATE_TOKEN, 'Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode($body, true);
    if ($status !== 200 || !is_array($decoded) || empty($decoded['ok'])) {
        return array('ok' => false, 'error' => $error !== '' ? $error : 'RQDB4AI enqueue failed (' . $status . ')');
    }
    $job_id = isset($decoded['job']['id']) ? $decoded['job']['id'] : '';
    u2p_reward_update($claim['id'], array('status' => 'queued', 'job_id' => $job_id), array('pending', 'enqueue_failed', 'failed'));
    return array('ok' => true, 'job_id' => $job_id);
}

// ---------------------------------------------------------------------------
// サーバー側ジョブ実行(2026-07-29): ブラウザを閉じても配信が完走する「kurage方式」。
// startでジョブを作成→同一リクエスト内で全ステップを実行(ignore_user_abort)。
// ブラウザはjob-statusをポーリングして進捗表示するだけ。ランナーが死んだ場合は
// job-statusのtick(90秒無更新で1ステップ前進)が救済する。
// ---------------------------------------------------------------------------
define('U2P_JOBS_DIR', __DIR__ . '/storage/jobs');

function u2p_job_path($id) {
    return U2P_JOBS_DIR . '/' . preg_replace('/[^a-f0-9]/', '', $id) . '.json';
}

function u2p_job_load($id) {
    $p = u2p_job_path($id);
    if (!is_file($p)) { return null; }
    $j = json_decode((string)@file_get_contents($p), true);
    return is_array($j) ? $j : null;
}

function u2p_job_save($job) {
    if (!is_dir(U2P_JOBS_DIR)) { @mkdir(U2P_JOBS_DIR, 0705, true); }
    $job['updated_at'] = time();
    file_put_contents(u2p_job_path($job['id']), json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $job;
}

function u2p_job_new($username, $url, $wallet, $lang) {
    $steps = array('analyze', 'announcement', 'blog',
                   'post-bluesky', 'post-hatena-bookmark', 'post-aixsns', 'post-bludit', 'post-hatena-blog',
                   'reward');
    $state = array();
    foreach ($steps as $s) { $state[$s] = 'pending'; }
    $job = array(
        'id' => bin2hex(random_bytes(16)),
        'username' => (string)$username,
        'url' => (string)$url,
        'wallet' => (string)$wallet,
        'lang' => (string)$lang,
        'status' => 'running',
        'steps' => $state,
        'errors' => array(),
        'posted' => array(),
        'source' => null, 'announcement' => null, 'blog' => null,
        'history_id' => '', 'reward' => null,
        'created_at' => time(), 'updated_at' => time(),
    );
    return u2p_job_save($job);
}

/** 次のpendingステップを1つ実行して保存。全完了でstatus=done。 */
function u2p_job_advance($job) {
    $labels = array('bluesky' => 'Bluesky', 'hatena-bookmark' => 'はてなブックマーク',
                    'aixsns' => 'AIxSNS', 'bludit' => 'Kurageブログ', 'hatena-blog' => 'はてなブログ');
    foreach ($job['steps'] as $step => $st) {
        if ($st !== 'pending') { continue; }
        $job['steps'][$step] = 'running';
        u2p_job_save($job);
        try {
            if ($step === 'analyze') {
                $r = u2p_api('/analyze/url', array('url' => $job['url'], 'depth' => 'full'));
                if ($r['status'] !== 200 || empty($r['data']['result'])) {
                    throw new Exception(isset($r['data']['detail']) ? $r['data']['detail'] : '解析に失敗しました');
                }
                $job['source'] = $r['data']['result'];
            } elseif ($step === 'announcement') {
                $r = u2p_api('/generate/announcement', array('source' => $job['source'], 'language' => $job['lang'], 'tone' => 'neutral'));
                if ($r['status'] !== 200 || empty($r['data']['result'])) {
                    throw new Exception(isset($r['data']['detail']) ? $r['data']['detail'] : '告知文の生成に失敗しました');
                }
                $job['announcement'] = $r['data']['result'];
            } elseif ($step === 'blog') {
                $r = u2p_api('/generate/blog-article', array('source' => $job['source'], 'language' => $job['lang'], 'tone' => 'neutral'));
                if ($r['status'] !== 200 || empty($r['data']['result'])) {
                    throw new Exception(isset($r['data']['detail']) ? $r['data']['detail'] : 'ブログ記事の生成に失敗しました');
                }
                $job['blog'] = $r['data']['result'];
            } elseif (strpos($step, 'post-') === 0) {
                $platform = substr($step, 5);
                $text = isset($job['announcement']['text']) ? $job['announcement']['text'] : '';
                $title = isset($job['blog']['title']) ? $job['blog']['title'] : '';
                $body = isset($job['blog']['body_markdown']) ? $job['blog']['body_markdown'] : '';
                if ($platform === 'bluesky') {
                    $result = u2p_post_bluesky(u2p_persona_frame($text, 'kurage', 'announcement', $job['lang']));
                } elseif ($platform === 'hatena-bookmark') {
                    $result = u2p_post_hatena_bookmark($job['url'], $text);
                } elseif ($platform === 'aixsns') {
                    $result = u2p_post_aixsns(u2p_persona_frame($text, 'bittensorman', 'announcement', $job['lang']));
                } elseif ($platform === 'bludit') {
                    $result = u2p_post_bludit($title, u2p_persona_frame($body, 'kurage', 'blog', $job['lang']));
                } else {
                    $result = u2p_post_hatena_blog($title, u2p_persona_frame($body, 'bittensorman', 'blog', $job['lang']));
                }
                // 媒体障害は利用者の責任ではない(従来方針): 失敗もngとして記録して先へ進む
                $job['posted'][] = array('key' => $platform,
                    'label' => isset($labels[$platform]) ? $labels[$platform] : $platform,
                    'ok' => !empty($result['ok']), 'url' => isset($result['url']) ? $result['url'] : '',
                    'error' => isset($result['error']) ? $result['error'] : '');
                $job['steps'][$step] = !empty($result['ok']) ? 'ok' : 'ng';
                if (empty($result['ok'])) { $job['errors'][$step] = isset($result['error']) ? $result['error'] : '失敗'; }
                return u2p_job_save($job);
            } elseif ($step === 'reward') {
                $history_id = u2p_history_new_id();
                $reward_result = array('ok' => true, 'eligible' => false, 'status' => 'disabled', 'message' => '利用特典は現在停止中です');
                if (u2p_reward_enabled()) {
                    if ($job['wallet'] === '') {
                        $reward_result = array('ok' => true, 'eligible' => false, 'status' => 'no_wallet',
                            'message' => 'ウォレット未接続のため特典申請はありません（配信は完了しています）');
                    } else {
                        $reward_result = u2p_reward_reserve($job['username'], $job['wallet'], $history_id);
                    }
                }
                $record = array(
                    'id' => $history_id, 'created_at' => date('c'),
                    'source' => $job['source'], 'announcement' => $job['announcement'], 'blog' => $job['blog'],
                    'posted' => $job['posted'],
                    'reward_claim_id' => !empty($reward_result['claim']['id']) ? $reward_result['claim']['id'] : '',
                    'reward_status' => isset($reward_result['status']) ? $reward_result['status'] : '',
                );
                u2p_history_save($job['username'], $record);
                if (!empty($reward_result['eligible']) && !empty($reward_result['claim'])) {
                    $queued = u2p_reward_enqueue($reward_result['claim']);
                    if (empty($queued['ok'])) {
                        u2p_reward_update($reward_result['claim']['id'], array('status' => 'enqueue_failed', 'message' => $queued['error']), array('pending'));
                    }
                    $reward_result['claim'] = u2p_reward_find($reward_result['claim']['id']);
                }
                $job['history_id'] = $history_id;
                $job['reward'] = !empty($reward_result['claim']) ? u2p_reward_public($reward_result['claim']) : array(
                    'status' => isset($reward_result['status']) ? $reward_result['status'] : 'unavailable',
                    'message' => isset($reward_result['message']) ? $reward_result['message'] : '');
                $job['steps'][$step] = 'ok';
                $job['status'] = 'done';
                return u2p_job_save($job);
            }
            $job['steps'][$step] = 'ok';
            return u2p_job_save($job);
        } catch (Exception $e) {
            // 生成系ステップの失敗はジョブ全体を失敗として止める(従来のfailと同じ挙動)
            $job['steps'][$step] = 'ng';
            $job['errors'][$step] = $e->getMessage();
            $job['status'] = 'failed';
            return u2p_job_save($job);
        }
    }
    if ($job['status'] === 'running') { $job['status'] = 'done'; }
    return u2p_job_save($job);
}

/** ジョブを最後まで実行(startのランナー用)。 */
function u2p_job_run_all($id) {
    for ($i = 0; $i < 20; $i++) {
        $job = u2p_job_load($id);
        if ($job === null || $job['status'] !== 'running') { return; }
        u2p_job_advance($job);
    }
}

/** UI向けの公開状態。 */
function u2p_job_public($job) {
    return array('ok' => true, 'job_id' => $job['id'], 'status' => $job['status'],
        'steps' => $job['steps'], 'errors' => $job['errors'],
        'history_id' => $job['history_id'], 'reward' => $job['reward']);
}
