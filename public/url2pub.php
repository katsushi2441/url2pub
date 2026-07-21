<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/lib.php';
date_default_timezone_set('Asia/Tokyo');

// Xログインは既存のKurage共通ログイン基盤(aiknowledgecms.exbridge.jp/aiknowledgesns.php)に
// 委譲する。*.exbridge.jp共通クッキーのため、他のexbridge.jpサイトで既にログイン済みなら
// url2ai.exbridge.jpでも自動的にログイン扱いになる。
if (isset($_GET['login'])) {
    header('Location: ' . url2ai_auth_login_url('/url2pub.php'));
    exit;
}
if (isset($_GET['logout'])) {
    header('Location: ' . url2ai_auth_logout_url('/url2pub.php'));
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
    header('Location: url2pub.php?step=result');
    exit;
}

// 最初からやり直す
if (isset($_GET['reset'])) {
    unset($_SESSION['pending_result'], $_SESSION['shared_confirmed']);
    header('Location: url2pub.php');
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
<meta name="description" content="URLを渡すとKurageさんが記事を読み、考察と告知文を書き、株式会社エクスブリッジが運営する5メディア(Bluesky/はてなブックマーク/はてなブログ/AIxSNS/Kurageブログ)へ自動配信します。無料・Xログインで利用可能。">
<meta name="keywords" content="Kurage,URL2AI,AI VTuber,自動配信,ブログ自動生成,SNS自動投稿,Bluesky,はてな,AIxSNS,exbridge">
<meta name="robots" content="index,follow">
<meta name="author" content="EXBRIDGE, Inc.">
<link rel="canonical" href="https://url2ai.exbridge.jp/url2pub.php">
<link rel="icon" href="assets/kurage_avatar_square.png" type="image/png">
<link rel="apple-touch-icon" href="assets/kurage_avatar_square.png">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Kurage URL2AI Publisher">
<meta property="og:title" content="Kurage URL2AI Publisher — URLを渡すだけで5メディアへ自動配信">
<meta property="og:description" content="KurageさんがURLを解析して考察・告知文・ブログ記事を書き、Bluesky/はてな/AIxSNS/Kurageブログへ自動配信します。">
<meta property="og:url" content="https://url2ai.exbridge.jp/url2pub.php">
<meta property="og:image" content="https://url2ai.exbridge.jp/assets/ogp.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="ja_JP">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Kurage URL2AI Publisher">
<meta name="twitter:description" content="URLを渡すだけでKurageさんが5メディアへ自動配信します。">
<meta name="twitter:image" content="https://url2ai.exbridge.jp/assets/ogp.png">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebApplication",
  "name": "Kurage URL2AI Publisher",
  "url": "https://url2ai.exbridge.jp/url2pub.php",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web",
  "description": "URLを解析し、告知文とブログ記事を自動生成して5つのメディアへ配信するAIパブリッシングツール。",
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "JPY" },
  "provider": { "@type": "Organization", "name": "EXBRIDGE, Inc.", "url": "https://exbridge.jp/" }
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic:wght@500;700;900&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="site"><div class="wrap">
  <div class="hbrand">
    <div class="avatar-ring"><img src="assets/kurage_avatar_square.webp" alt="Kurage — jellyfish AI VTuber" width="96" height="96"></div>
    <div>
      <h1>Kurage URL2AI Publisher</h1>
      <p class="tagline">Kurageさんが記事を読み、考察と告知文を書き、5つのメディアへ配信します。</p>
    </div>
  </div>
  <?php if ($logged_in): ?>
    <div class="whoami"><strong>@<?php echo u2p_h($auth['session_user']); ?></strong> でログイン中<br>
      <a href="history.php">履歴</a> · <a href="?logout=1">ログアウト</a></div>
  <?php endif; ?>
</div></header>

<main><div class="wrap">

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
    <p style="font-size:13.5px;color:var(--abyss-soft);margin-bottom:16px">
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
    <h2 style="font-size:16px;margin-bottom:6px;text-transform:none;letter-spacing:0;color:var(--abyss)">Kurageさんが作業中です</h2>
    <ul id="u2pSteps"></ul>
  </div>

<?php elseif ($view === 'share' && $pending): ?>
  <div class="card">
    <h2 style="font-size:16px;margin-bottom:10px;text-transform:none;letter-spacing:0;color:var(--abyss)">配信が完了しました</h2>
    <p style="font-size:13.5px;color:var(--abyss-soft);margin-bottom:16px">
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
      <p style="font-size:12.5px;color:var(--abyss-soft);margin-bottom:10px">
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
            <span style="font-size:12.5px;color:var(--abyss-soft)"><?php echo u2p_h(isset($p['error']) ? $p['error'] : ''); ?></span>
          <?php else: ?>
            &mdash;
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p style="margin-top:24px">
      <a href="url2pub.php?reset=1" class="btn btn-ghost">別のURLを配信する</a>
      <a href="history.php" class="btn btn-ghost">履歴一覧</a>
    </p>
  </div>
<?php endif; ?>

<?php if (!empty($auth['is_admin'])): ?>
  <section class="block" style="margin-top:40px">
    <h2>管理者ビュー: 全ユーザー利用履歴</h2>
    <div class="hist-card" style="padding:0;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--panel)">
            <th style="text-align:left;padding:10px 14px">ユーザー</th>
            <th style="text-align:left;padding:10px 14px">URL</th>
            <th style="text-align:left;padding:10px 14px">日時</th>
          </tr>
        </thead>
        <tbody>
          <?php $all_history = u2p_history_all_users(200); ?>
          <?php if (empty($all_history)): ?>
            <tr><td colspan="3" style="padding:14px;color:var(--abyss-soft)">まだ利用履歴がありません。</td></tr>
          <?php else: ?>
            <?php foreach ($all_history as $h): ?>
              <tr style="border-top:1px solid var(--panel-line)">
                <td style="padding:10px 14px">@<?php echo u2p_h($h['username']); ?></td>
                <td style="padding:10px 14px">
                  <a href="url2pub.php?step=result&id=<?php echo urlencode($h['id']); ?>"><?php echo u2p_h($h['url']); ?></a>
                </td>
                <td style="padding:10px 14px;white-space:nowrap;color:var(--abyss-soft)"><?php echo u2p_h($h['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

</div></main>

<footer class="site"><div class="wrap">
  Kurage URL2AI Publisher — <a href="https://exbridge.jp/">株式会社エクスブリッジ</a>のプロダクト ·
  頭脳は <a href="https://github.com/katsushi2441/url2brain">url2brain</a>(OSS)が担当 ·
  <a href="https://kfreqai.exbridge.jp/">kfreqai</a> · <a href="https://kfxai.exbridge.jp/">kfxai</a> ·
  <a href="https://kcbrain.exbridge.jp/">kcbrain</a> · <a href="https://kfxbrain.exbridge.jp/">kfxbrain</a>
  <br><br>
  &copy; <?php echo date('Y'); ?> EXBRIDGE, Inc. Developed by <a href="https://x.com/xb_bittensor" target="_blank" rel="noopener">bittensorman</a> ·
  <a href="https://exbridge.jp/contact.php">お問い合わせ</a>
</div></footer>

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
      window.location.href = 'url2pub.php?step=share';
    }).catch(function (err) {
      fail(err.message || String(err));
    });
  });
}
</script>

</body>
</html>
