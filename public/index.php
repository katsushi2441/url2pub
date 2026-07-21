<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Tokyo');

function u2p_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        return array('status' => 502, 'data' => array('ok' => false, 'detail' => $error ?: 'url2brain接続に失敗しました'));
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return array('status' => 502, 'data' => array('ok' => false, 'detail' => 'url2brainから不正な応答'));
    }
    return array('status' => $status ?: 502, 'data' => $decoded);
}

// 株式会社エクスブリッジが運営する5メディア。post/*の順に対応。
$MEDIA = array(
    array('key' => 'bluesky', 'label' => 'Bluesky', 'note' => '@bittensorman.bsky.social'),
    array('key' => 'hatena-bookmark', 'label' => 'はてなブックマーク', 'note' => ''),
    array('key' => 'aixsns', 'label' => 'AIxSNS', 'note' => 'aixec.exbridge.jp'),
    array('key' => 'bludit', 'label' => 'Kurageブログ', 'note' => 'kurage.exbridge.jp/blog (url2pubカテゴリ)'),
    array('key' => 'hatena-blog', 'label' => 'はてなブログ', 'note' => 'xb-bittensor.hatenablog.com'),
);

$error = '';
$source = null;
$announcement = null;
$blog = null;
$posted = array();
$submitted_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_url = trim((string)(isset($_POST['url']) ? $_POST['url'] : ''));
    if ($submitted_url === '' || !filter_var($submitted_url, FILTER_VALIDATE_URL)) {
        $error = '有効なURLを入力してください。';
    } else {
        $gen = u2p_api('/v1/generate/from-url', array(
            'url' => $submitted_url, 'language' => 'ja', 'tone' => 'neutral',
        ));
        if ($gen['status'] !== 200 || empty($gen['data']['result'])) {
            $detail_msg = isset($gen['data']['detail']) ? $gen['data']['detail'] : (isset($gen['data']['error']) ? $gen['data']['error'] : '不明なエラー');
            $error = '解析に失敗しました: ' . u2p_h($detail_msg);
        } else {
            $result = $gen['data']['result'];
            $source = $result['source'];
            $announcement = $result['announcement'];
            $blog = $result['blog_article'];

            // Kurageさんが5メディアへ配信する(自動実投稿)。
            $posted[] = array('key' => 'bluesky', 'label' => 'Bluesky') + u2p_post_bluesky($announcement['text']);
            $posted[] = array('key' => 'hatena-bookmark', 'label' => 'はてなブックマーク') + u2p_post_hatena_bookmark($source['url'], $announcement['text']);
            $posted[] = array('key' => 'aixsns', 'label' => 'AIxSNS') + u2p_post_aixsns($announcement['text']);
            $posted[] = array('key' => 'bludit', 'label' => 'Kurageブログ') + u2p_post_bludit($blog['title'], $blog['body_markdown']);
            $posted[] = array('key' => 'hatena-blog', 'label' => 'はてなブログ') + u2p_post_hatena_blog($blog['title'], $blog['body_markdown']);
        }
    }
}

function u2p_post_bluesky($text) {
    $r = u2p_api('/v1/post/bluesky', array('text' => $text, 'url' => '', 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_hatena_bookmark($url, $text) {
    $r = u2p_api('/v1/post/hatena-bookmark', array('url' => $url, 'comment' => mb_substr($text, 0, 90), 'tags' => array(), 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_aixsns($text) {
    $r = u2p_api('/v1/post/aixsns', array('content' => $text, 'author' => 'url2pub', 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_bludit($title, $body) {
    $r = u2p_api('/v1/post/bludit', array('title' => $title, 'body_markdown' => $body, 'category' => 'url2pub', 'tags' => 'url2pub', 'confirm_post' => true));
    return u2p_post_result($r);
}
function u2p_post_hatena_blog($title, $body) {
    $r = u2p_api('/v1/post/hatena-blog', array('title' => $title, 'body_markdown' => $body, 'confirm_post' => true));
    return u2p_post_result($r);
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
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurage URL2AI Publisher — KurageさんがURLを解析して5メディアに配信</title>
<meta name="description" content="URLを渡すとKurageさんが記事を読み、考察と告知文を書き、株式会社エクスブリッジが運営する5メディア(Bluesky/はてなブックマーク/はてなブログ/AIxSNS/Kurageブログ)へ配信します。">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://url2ai.exbridge.jp/">
<style>
:root {
  --ink: #241a4d; --muted: #6d679a; --sea: #8f7ae8; --line: #ded4f6;
  --accent: #6c4fd4; --accent2: #5136b0; --soft: #f2edfd;
  --paper: rgba(255,255,255,.92); --up: #1baf7a; --down: #d6453d;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  color: var(--ink);
  font-family: -apple-system, "Segoe UI", Roboto, "Hiragino Sans", "Yu Gothic", Meiryo, sans-serif;
  background: linear-gradient(160deg, #fff 0%, #f1edff 50%, #f8f5ff 100%);
  min-height: 100vh; line-height: 1.75;
}
a { color: var(--accent); }
header { max-width: 900px; margin: 0 auto; padding: 40px 24px 10px; display: flex; gap: 22px; align-items: center; }
header img { width: 92px; height: 92px; border-radius: 50%; box-shadow: 0 10px 30px rgba(108,79,212,.22); }
header h1 { font-size: 24px; font-weight: 900; letter-spacing: -.01em; }
header p { font-size: 13.5px; color: var(--muted); margin-top: 6px; max-width: 560px; }
main { max-width: 900px; margin: 0 auto; padding: 10px 24px 60px; }
.intro { background: var(--paper); border: 1.5px solid var(--line); border-radius: 20px; padding: 22px 26px; margin: 18px 0 26px; font-size: 14.5px; color: #45406a; box-shadow: 0 12px 34px rgba(30,22,61,.06); }
.intro b { color: var(--accent2); }
.media { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.media span { background: var(--soft); color: var(--accent2); border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; }
form { background: var(--paper); border: 1.5px solid var(--line); border-radius: 20px; padding: 24px; box-shadow: 0 12px 34px rgba(30,22,61,.06); }
form label { font-size: 13px; font-weight: 800; color: var(--accent2); display: block; margin-bottom: 8px; }
form input[type=url] { width: 100%; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 12px; font-size: 14px; margin-bottom: 14px; }
form button {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; border: none;
  border-radius: 999px; padding: 12px 26px; font-weight: 900; font-size: 14px; cursor: pointer;
  box-shadow: 0 8px 20px rgba(108,79,212,.26);
}
.error { background: #fde2e1; color: #a4201b; border-radius: 14px; padding: 14px 18px; margin: 18px 0; font-size: 13.5px; font-weight: 700; }
.result { margin-top: 30px; }
.result h2 { font-size: 15px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin: 26px 0 10px; }
.card { background: var(--paper); border: 1.5px solid var(--line); border-radius: 18px; padding: 20px 22px; box-shadow: 0 12px 34px rgba(30,22,61,.06); }
.card.announcement { white-space: pre-wrap; font-size: 14.5px; }
.card.blog h3 { font-size: 16px; margin-bottom: 10px; }
.card.blog { font-size: 14px; color: #3a3560; }
.card.blog p { margin: 10px 0; }
table { width: 100%; border-collapse: collapse; background: var(--paper); border: 1.5px solid var(--line); border-radius: 14px; overflow: hidden; font-size: 13.5px; }
th, td { padding: 10px 14px; border-bottom: 1px solid var(--line); text-align: left; }
th { background: var(--soft); font-size: 12px; }
td.ok { color: var(--up); font-weight: 700; } td.ng { color: var(--down); font-weight: 700; }
footer { text-align: center; color: var(--muted); font-size: 12.5px; padding: 40px 20px 46px; border-top: 1px solid var(--line); margin-top: 30px; }
footer a { font-weight: 700; }
</style>
</head>
<body>

<header>
  <img src="assets/kurage_avatar.webp" alt="Kurage — jellyfish AI VTuber">
  <div>
    <h1>Kurage URL2AI Publisher</h1>
    <p>Kurageさんが記事を読み、考察と告知文を書き、5つのメディアへ配信します。</p>
  </div>
</header>

<main>
  <div class="intro">
    URLを1つ渡すだけで、<b>Kurageさん</b>がその記事を解析して考察し、告知文とブログ記事を書き上げ、
    <b>株式会社エクスブリッジ</b>が運営する5つのメディアへ自動で配信します。
    <div class="media">
      <?php foreach ($MEDIA as $m): ?>
        <span><?php echo u2p_h($m['label']); ?><?php echo $m['note'] !== '' ? ' — ' . u2p_h($m['note']) : ''; ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="post">
    <label for="url">配信したいページのURL</label>
    <input type="url" id="url" name="url" placeholder="https://example.com/article" required
           value="<?php echo u2p_h($submitted_url); ?>">
    <button type="submit">Kurageさんに配信してもらう</button>
  </form>

  <?php if ($error !== ''): ?>
    <div class="error"><?php echo $error; ?></div>
  <?php endif; ?>

  <?php if ($announcement !== null): ?>
    <div class="result">
      <h2>告知用記事</h2>
      <div class="card announcement"><?php echo u2p_h($announcement['text']); ?></div>

      <h2>考察・ブログ用記事</h2>
      <div class="card blog">
        <h3><?php echo u2p_h($blog['title']); ?></h3>
        <?php foreach (preg_split('/\n{2,}/', trim($blog['body_markdown'])) as $para): ?>
          <p><?php echo nl2br(u2p_h($para)); ?></p>
        <?php endforeach; ?>
      </div>

      <h2>投稿URL一覧</h2>
      <table>
        <thead><tr><th>メディア</th><th>状態</th><th>URL</th></tr></thead>
        <tbody>
        <?php foreach ($posted as $p): ?>
          <tr>
            <td><?php echo u2p_h($p['label']); ?></td>
            <td class="<?php echo $p['ok'] ? 'ok' : 'ng'; ?>"><?php echo $p['ok'] ? '配信済み' : '失敗'; ?></td>
            <td>
              <?php if ($p['ok'] && $p['url'] !== ''): ?>
                <a href="<?php echo u2p_h($p['url']); ?>" target="_blank" rel="noopener"><?php echo u2p_h($p['url']); ?></a>
              <?php elseif (!$p['ok']): ?>
                <?php echo u2p_h($p['error']); ?>
              <?php else: ?>
                &mdash;
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>

<footer>
  Kurage URL2AI Publisher — <a href="https://exbridge.jp/">株式会社エクスブリッジ</a>のプロダクト ·
  頭脳は <a href="https://github.com/katsushi2441/url2brain">url2brain</a>(OSS)が担当 ·
  <a href="https://kfreqai.exbridge.jp/">kfreqai</a> · <a href="https://kfxai.exbridge.jp/">kfxai</a> ·
  <a href="https://kcbrain.exbridge.jp/">kcbrain</a> · <a href="https://kfxbrain.exbridge.jp/">kfxbrain</a>
</footer>

</body>
</html>
