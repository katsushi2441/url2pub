<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/lib.php';
date_default_timezone_set('Asia/Tokyo');

// Xログインは既存のKurage共通ログイン基盤(aiknowledgecms.exbridge.jp/aiknowledgesns.php)に
// 委譲する。*.exbridge.jp共通クッキーのため、他のexbridge.jpサイトで既にログイン済みなら
// url2ai.exbridge.jpでも自動的にログイン扱いになる。
if (isset($_GET['login'])) {
    header('Location: ' . url2ai_auth_login_url('/index.php'));
    exit;
}
if (isset($_GET['logout'])) {
    header('Location: ' . url2ai_auth_logout_url('/index.php'));
    exit;
}
$auth = url2ai_auth_bootstrap();
$logged_in = !empty($auth['logged_in']);

// 株式会社エクスブリッジが運営する5メディア。
$MEDIA = array(
    array('key' => 'bluesky', 'label' => 'Bluesky', 'note' => '@bittensorman.bsky.social'),
    array('key' => 'hatena-bookmark', 'label' => 'はてなブックマーク', 'note' => ''),
    array('key' => 'aixsns', 'label' => 'AIxSNS', 'note' => 'aixec.exbridge.jp'),
    array('key' => 'bludit', 'label' => 'Kurageブログ', 'note' => 'kurage.exbridge.jp/blog (url2pubカテゴリ)'),
    array('key' => 'hatena-blog', 'label' => 'はてなブログ', 'note' => 'xb-bittensor.hatenablog.com'),
);

// Xでシェア完了(自己申告) → 結果画面へ
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_share'])) {
    $_SESSION['shared_confirmed'] = true;
    header('Location: index.php?step=result');
    exit;
}

// 最初からやり直す
if (isset($_GET['reset'])) {
    unset($_SESSION['pending_result'], $_SESSION['shared_confirmed']);
    header('Location: index.php');
    exit;
}

$history_id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$pending = null;
$is_history_view = false;

if ($logged_in && $history_id !== '') {
    // 履歴詳細: 本人の履歴からのみ読む
    $record = u2p_history_find($auth['session_user'], $history_id);
    if ($record !== null) {
        $pending = $record;
        $is_history_view = true;
    }
} elseif (!empty($_SESSION['pending_result'])) {
    $pending = $_SESSION['pending_result'];
}

if (!$logged_in) {
    $view = 'login';
} elseif ($is_history_view) {
    $view = 'result';
} elseif ($pending && empty($_SESSION['shared_confirmed'])) {
    $view = 'share';
} elseif ($pending && !empty($_SESSION['shared_confirmed'])) {
    $view = 'result';
} else {
    $view = 'form';
}

$share_text = 'Kurage URL2AI Publisherを試してみました。URLを渡すだけでKurageさんが記事を読んで告知文とブログ記事を書き、5つのメディアへ自動配信してくれます。';
$share_url = 'https://url2ai.exbridge.jp/';
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
header { max-width: 900px; margin: 0 auto; padding: 40px 24px 10px; display: flex; gap: 22px; align-items: center; justify-content: space-between; }
.hbrand { display: flex; gap: 22px; align-items: center; }
header img { width: 92px; height: 92px; border-radius: 50%; box-shadow: 0 10px 30px rgba(108,79,212,.22); }
header h1 { font-size: 24px; font-weight: 900; letter-spacing: -.01em; }
header p { font-size: 13.5px; color: var(--muted); margin-top: 6px; max-width: 560px; }
.whoami { font-size: 12.5px; color: var(--muted); text-align: right; }
.whoami a { font-weight: 700; }
main { max-width: 900px; margin: 0 auto; padding: 10px 24px 60px; }
.intro { background: var(--paper); border: 1.5px solid var(--line); border-radius: 20px; padding: 22px 26px; margin: 18px 0 26px; font-size: 14.5px; color: #45406a; box-shadow: 0 12px 34px rgba(30,22,61,.06); }
.intro b { color: var(--accent2); }
.media { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.media span { background: var(--soft); color: var(--accent2); border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; }
.card { background: var(--paper); border: 1.5px solid var(--line); border-radius: 20px; padding: 24px; box-shadow: 0 12px 34px rgba(30,22,61,.06); }
form input[type=url] { width: 100%; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 12px; font-size: 14px; margin-bottom: 14px; }
label { font-size: 13px; font-weight: 800; color: var(--accent2); display: block; margin-bottom: 8px; }
.btn { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; border: none; border-radius: 999px; padding: 12px 26px; font-weight: 900; font-size: 14px; cursor: pointer; text-decoration: none; box-shadow: 0 8px 20px rgba(108,79,212,.26); }
.btn-x { background: #000; }
.btn-ghost { background: #fff; color: var(--accent2); border: 1.5px solid var(--line); box-shadow: none; }
.btn-sm { padding: 7px 14px; font-size: 12.5px; }
.error { background: #fde2e1; color: #a4201b; border-radius: 14px; padding: 14px 18px; margin: 18px 0; font-size: 13.5px; font-weight: 700; }
.result h2 { font-size: 15px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin: 26px 0 10px; }
.item { margin-bottom: 14px; }
.item .text { white-space: pre-wrap; font-size: 14px; background: var(--soft); border-radius: 12px; padding: 14px 16px; margin-bottom: 8px; }
.item .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.item .actions .status { font-size: 12.5px; font-weight: 700; }
.item .actions .status.ok { color: var(--up); } .item .actions .status.ng { color: var(--down); }
.blog h3 { font-size: 16px; margin-bottom: 10px; }
.blog p { margin: 10px 0; font-size: 14px; color: #3a3560; }
textarea.sharebox { width: 100%; min-height: 90px; border: 1.5px solid var(--line); border-radius: 12px; padding: 12px 14px; font-size: 14px; font-family: inherit; margin-bottom: 14px; }
#u2pSteps { list-style: none; }
#u2pSteps li { padding: 8px 0; font-size: 14px; border-bottom: 1px solid var(--line); }
#u2pSteps li:last-child { border-bottom: none; }
footer { text-align: center; color: var(--muted); font-size: 12.5px; padding: 40px 20px 46px; border-top: 1px solid var(--line); margin-top: 30px; }
footer a { font-weight: 700; }
</style>
</head>
<body>

<header>
  <div class="hbrand">
    <img src="assets/kurage_avatar.webp" alt="Kurage — jellyfish AI VTuber">
    <div>
      <h1>Kurage URL2AI Publisher</h1>
      <p>Kurageさんが記事を読み、考察と告知文を書き、5つのメディアへ配信します。</p>
    </div>
  </div>
  <?php if ($logged_in): ?>
    <div class="whoami">@<?php echo u2p_h($auth['session_user']); ?> でログイン中<br>
      <a href="history.php">履歴</a> · <a href="?logout=1">ログアウト</a></div>
  <?php endif; ?>
</header>

<main>

<?php if ($view === 'login'): ?>
  <div class="intro">
    URLを1つ渡すだけで、<b>Kurageさん</b>がその記事を解析して考察し、告知文とブログ記事を書き上げ、
    <b>株式会社エクスブリッジ</b>が運営する5つのメディアへ自動で配信します。
    <div class="media">
      <?php foreach ($MEDIA as $m): ?>
        <span><?php echo u2p_h($m['label']); ?><?php echo $m['note'] !== '' ? ' — ' . u2p_h($m['note']) : ''; ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card" style="text-align:center">
    <p style="font-size:13.5px;color:var(--muted);margin-bottom:16px">
      現在無料でご利用いただけます。ご利用の条件として、配信後にXへ一言シェアをお願いしています。<br>
      利用を始めるにはXでログインしてください。
    </p>
    <a class="btn btn-x" href="?login=1">𝕏 でログインして始める</a>
  </div>

<?php elseif ($view === 'form'): ?>
  <div class="intro">
    URLを1つ渡すだけで、<b>Kurageさん</b>がその記事を解析して考察し、告知文とブログ記事を書き上げ、
    <b>株式会社エクスブリッジ</b>が運営する5つのメディアへ自動で配信します。
    <div class="media">
      <?php foreach ($MEDIA as $m): ?>
        <span><?php echo u2p_h($m['label']); ?><?php echo $m['note'] !== '' ? ' — ' . u2p_h($m['note']) : ''; ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <form id="u2pForm" class="card">
    <label for="url">配信したいページのURL</label>
    <input type="url" id="url" name="url" placeholder="https://example.com/article" required>
    <button type="submit" class="btn">Kurageさんに配信してもらう</button>
  </form>
  <div id="u2pError" class="error" style="display:none"></div>
  <div id="u2pProgress" class="card" style="display:none">
    <h2 style="font-size:16px;margin-bottom:6px">Kurageさんが作業中です🪼</h2>
    <ul id="u2pSteps"></ul>
  </div>

<?php elseif ($view === 'share' && $pending): ?>
  <div class="card">
    <h2 style="font-size:16px;margin-bottom:10px">配信が完了しました🪼</h2>
    <p style="font-size:13.5px;color:var(--muted);margin-bottom:16px">
      無料でのご利用にあたり、下の内容でXへ一言シェアをお願いします。投稿後、下のボタンから結果画面へ進んでください。
    </p>
    <textarea class="sharebox" id="shareText" readonly><?php echo u2p_h($share_text . ' ' . $share_url); ?></textarea>
    <div class="item actions">
      <a class="btn btn-x" href="<?php echo u2p_h(u2p_tweet_intent($share_text, $share_url)); ?>" target="_blank" rel="noopener">𝕏 で投稿する</a>
      <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopy('shareText')">コピー</button>
    </div>
    <form method="post" style="margin-top:18px">
      <button type="submit" name="confirm_share" value="1" class="btn">投稿しました → 結果を見る</button>
    </form>
  </div>

<?php elseif ($view === 'result' && $pending): ?>
  <?php $announcement = $pending['announcement']; $blog = $pending['blog']; $posted = $pending['posted']; ?>
  <div class="result">
    <?php if ($is_history_view): ?>
      <p style="font-size:12.5px;color:var(--muted);margin-bottom:10px">
        履歴: <?php echo u2p_h(isset($pending['created_at']) ? $pending['created_at'] : ''); ?> ·
        元URL: <a href="<?php echo u2p_h($pending['source']['url']); ?>" target="_blank" rel="noopener"><?php echo u2p_h($pending['source']['url']); ?></a> ·
        <a href="history.php">履歴一覧へ戻る</a>
      </p>
    <?php endif; ?>
    <h2>告知用記事</h2>
    <div class="item">
      <div class="text" id="ann-text"><?php echo u2p_h($announcement['text']); ?></div>
      <div class="actions">
        <a class="btn btn-x btn-sm" href="<?php echo u2p_h(u2p_tweet_intent($announcement['text'])); ?>" target="_blank" rel="noopener">𝕏 投稿</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopy('ann-text')">コピー</button>
      </div>
    </div>

    <h2>考察・ブログ用記事</h2>
    <div class="item card blog">
      <h3 id="blog-title"><?php echo u2p_h($blog['title']); ?></h3>
      <div id="blog-body">
        <?php foreach (preg_split('/\n{2,}/', trim($blog['body_markdown'])) as $para): ?>
          <p><?php echo nl2br(u2p_h($para)); ?></p>
        <?php endforeach; ?>
      </div>
      <div class="actions" style="margin-top:12px">
        <a class="btn btn-x btn-sm" href="<?php echo u2p_h(u2p_tweet_intent($blog['title'])); ?>" target="_blank" rel="noopener">𝕏 投稿</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopyText('blog-copy-src')">コピー</button>
        <textarea id="blog-copy-src" style="position:absolute;left:-9999px"><?php echo u2p_h($blog['title'] . "\n\n" . $blog['body_markdown']); ?></textarea>
      </div>
    </div>

    <h2>配信先URL一覧</h2>
    <?php foreach ($posted as $i => $p): ?>
      <div class="item">
        <div class="actions">
          <strong style="min-width:150px;display:inline-block"><?php echo u2p_h($p['label']); ?></strong>
          <span class="status <?php echo !empty($p['ok']) ? 'ok' : 'ng'; ?>"><?php echo !empty($p['ok']) ? '配信済み' : '失敗'; ?></span>
          <?php if (!empty($p['ok']) && $p['url'] !== ''): ?>
            <a href="<?php echo u2p_h($p['url']); ?>" target="_blank" rel="noopener" id="url-<?php echo $i; ?>"><?php echo u2p_h($p['url']); ?></a>
            <a class="btn btn-x btn-sm" href="<?php echo u2p_h(u2p_tweet_intent($p['label'] . ': ', $p['url'])); ?>" target="_blank" rel="noopener">𝕏 投稿</a>
            <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopy('url-<?php echo $i; ?>')">コピー</button>
          <?php elseif (empty($p['ok'])): ?>
            <span style="font-size:12.5px;color:var(--muted)"><?php echo u2p_h(isset($p['error']) ? $p['error'] : ''); ?></span>
          <?php else: ?>
            &mdash;
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p style="margin-top:24px">
      <a href="index.php?reset=1" class="btn btn-ghost">別のURLを配信する</a>
      <a href="history.php" class="btn btn-ghost">履歴一覧</a>
    </p>
  </div>
<?php endif; ?>

</main>

<footer>
  Kurage URL2AI Publisher — <a href="https://exbridge.jp/">株式会社エクスブリッジ</a>のプロダクト ·
  頭脳は <a href="https://github.com/katsushi2441/url2brain">url2brain</a>(OSS)が担当 ·
  <a href="https://kfreqai.exbridge.jp/">kfreqai</a> · <a href="https://kfxai.exbridge.jp/">kfxai</a> ·
  <a href="https://kcbrain.exbridge.jp/">kcbrain</a> · <a href="https://kfxbrain.exbridge.jp/">kfxbrain</a>
</footer>

<script>
function u2pCopy(id) {
  var el = document.getElementById(id);
  var text = el.tagName === 'TEXTAREA' ? el.value : el.textContent;
  navigator.clipboard.writeText(text).then(function () { alert('コピーしました'); });
}
function u2pCopyText(id) {
  var el = document.getElementById(id);
  navigator.clipboard.writeText(el.value).then(function () { alert('コピーしました'); });
}

var u2pForm = document.getElementById('u2pForm');
if (u2pForm) {
  var STEPS = [
    { key: 'analyze', label: '記事を解析中…' },
    { key: 'announcement', label: '告知文を生成中…' },
    { key: 'blog', label: 'ブログ記事を生成中…' },
    { key: 'post-bluesky', label: 'Blueskyへ配信中…' },
    { key: 'post-hatena-bookmark', label: 'はてなブックマークへ配信中…' },
    { key: 'post-aixsns', label: 'AIxSNSへ配信中…' },
    { key: 'post-bludit', label: 'Kurageブログへ配信中…' },
    { key: 'post-hatena-blog', label: 'はてなブログへ配信中…' }
  ];
  var PLATFORMS = [
    { key: 'bluesky', label: 'Bluesky' },
    { key: 'hatena-bookmark', label: 'はてなブックマーク' },
    { key: 'aixsns', label: 'AIxSNS' },
    { key: 'bludit', label: 'Kurageブログ' },
    { key: 'hatena-blog', label: 'はてなブログ' }
  ];

  function u2pMark(key, state, note) {
    var li = document.getElementById('step-' + key);
    if (!li) return;
    var icon = state === 'ok' ? '✅' : (state === 'ng' ? '❌' : '⏳');
    li.textContent = icon + ' ' + li.getAttribute('data-label') + (note ? '（' + note + '）' : '');
  }

  u2pForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var url = document.getElementById('url').value;
    var errEl = document.getElementById('u2pError');
    errEl.style.display = 'none';
    u2pForm.style.display = 'none';
    var progressEl = document.getElementById('u2pProgress');
    progressEl.style.display = 'block';
    var stepsEl = document.getElementById('u2pSteps');
    stepsEl.innerHTML = STEPS.map(function (s) {
      return '<li id="step-' + s.key + '" data-label="' + s.label + '">⏳ ' + s.label + '</li>';
    }).join('');

    function callAjax(action, platform, body) {
      var qs = 'ajax.php?action=' + action + (platform ? '&platform=' + platform : '');
      return fetch(qs, { method: 'POST', body: JSON.stringify(body) }).then(function (r) { return r.json(); });
    }

    function fail(msg) {
      progressEl.style.display = 'none';
      u2pForm.style.display = 'block';
      errEl.textContent = msg;
      errEl.style.display = 'block';
    }

    callAjax('analyze', null, { url: url }).then(function (d) {
      if (!d.ok) { throw new Error(d.error || '解析に失敗しました'); }
      u2pMark('analyze', 'ok');
      var source = d.source;
      return callAjax('announcement', null, { source: source }).then(function (d2) {
        if (!d2.ok) { throw new Error(d2.error || '告知文の生成に失敗しました'); }
        u2pMark('announcement', 'ok');
        var announcement = d2.announcement;
        return callAjax('blog', null, { source: source }).then(function (d3) {
          if (!d3.ok) { throw new Error(d3.error || 'ブログ記事の生成に失敗しました'); }
          u2pMark('blog', 'ok');
          var blog = d3.blog;
          var posted = [];
          var chain = Promise.resolve();
          PLATFORMS.forEach(function (p) {
            chain = chain.then(function () {
              return callAjax('post', p.key, { source: source, announcement: announcement, blog: blog }).then(function (dp) {
                u2pMark('post-' + p.key, dp.ok ? 'ok' : 'ng', dp.ok ? '' : (dp.error || '失敗'));
                posted.push({ key: p.key, label: p.label, ok: !!dp.ok, url: dp.url || '', error: dp.error || '' });
              });
            });
          });
          return chain.then(function () {
            return callAjax('finish', null, { source: source, announcement: announcement, blog: blog, posted: posted });
          });
        });
      });
    }).then(function (dfin) {
      if (!dfin || !dfin.ok) { throw new Error('結果の保存に失敗しました'); }
      window.location.href = 'index.php?step=share';
    }).catch(function (err) {
      fail(err.message || String(err));
    });
  });
}
</script>

</body>
</html>
