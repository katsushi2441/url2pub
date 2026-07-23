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
    unset($_SESSION['pending_result'], $_SESSION['shared_confirmed'], $_SESSION['u2p_run']);
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
$reward_claim = null;
if ($pending && !empty($pending['reward_claim_id'])) {
    $reward_claim = u2p_reward_find($pending['reward_claim_id']);
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

$share_text = 'Kurage URL2AI Publisherを試してみました。URLを渡すだけでKurageさんが記事を読んで告知文とブログ記事を書き、5つのメディアへ自動配信。利用者向けの10,000 URLAI特典もあります。';
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
  <section class="lp-hero">
    <span class="lp-eyebrow">Kurageを一緒に育てる ・ URLAI テスト期間</span>
    <h2 class="lp-title">Kurageを広める人が、<br>Kurageと一緒に<em>育つ</em>。</h2>
    <p class="lp-lead">
      URLを1つ渡すだけ。<b>Kurageさん</b>がその記事を読んで考察し、告知文とブログ記事を書き上げ、
      <b>5つのメディアへ自動で配信</b>します。＝あなたが <b>Kurageを世に広げる</b>ということ。
      広めてくれたあなたへ、感謝を込めて <b>URLAIトークン</b>をお渡しします。
    </p>

    <?php if (u2p_reward_enabled()): ?>
      <div class="lp-token">
        <span class="lp-token-kicker">いま協力してくれた方へ</span>
        <strong class="lp-token-amt">10,000<span class="lp-token-unit">URLAI</span></strong>
        <span class="lp-token-sub">1回の配信を最後まで完了で配布 ／ 先着1,000人 ・ Xとウォレットにつき1回 ・ <b>無料</b></span>
      </div>
    <?php else: ?>
      <div class="lp-token lp-token--soon">
        <span class="lp-token-kicker">URLAI 配布</span>
        <strong class="lp-token-amt">まもなく開始</strong>
        <span class="lp-token-sub">協力してくれた方へURLAIトークンをお配りします。</span>
      </div>
    <?php endif; ?>

    <a class="btn btn-x lp-cta" href="?login=1">𝕏 でログインして、いますぐ協力する</a>
    <p class="lp-cta-note">Xログイン＋Baseウォレット接続だけ。費用はかかりません。</p>

    <div class="media lp-media">
      <?php foreach ($MEDIA as $m): ?>
        <span><?php echo u2p_h($m['label']); ?><?php echo $m['note'] !== '' ? ' — ' . u2p_h($m['note']) : ''; ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="lp-band lp-flywheel">
    <span class="lp-band-kicker">これが、経済が回る仕組み</span>
    <h3 class="lp-band-title">あなたの拡散が、<em>Kurageの実需</em>に変わる。</h3>
    <div class="fw">
      <div class="fw-node"><span class="fw-n">1</span><b>あなたが広める</b><small>url2pubで5媒体へ配信</small></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node"><span class="fw-n">2</span><b>Kurageの知名度が上がる</b></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node"><span class="fw-n">3</span><b>有料サービスの利用者が増える</b><small>kfreqai・kcbrain・kfxbrain…</small></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node"><span class="fw-n">4</span><b>利益が生まれる</b></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node fw-node--gold"><span class="fw-n">5</span><b>URLAI と貢献者へ還元</b></div>
      <i class="fw-arrow fw-arrow--loop">↻</i>
    </div>
    <p class="lp-band-text">
      使うほどKurageが広まり、広まるほど実需が生まれ、生まれた価値が
      <b>育ててくれたあなた</b>へ還ってくる。URLAIは、その循環の<b>当事者になるための仕組み</b>です。
    </p>
  </section>

  <section class="lp-eco">
    <h3 class="lp-eco-title">あなたが広げる、Kurageの“実体”</h3>
    <p class="lp-eco-lead">
      Kurageは1つのボットではなく、<b>本番稼働しているAIプロダクト群</b>。あなたの拡散は、この全部の知名度になります。
    </p>
    <div class="lp-eco-grid">
      <span class="lp-eco-item"><b>kfreqai</b>暗号資産の判断AI</span>
      <span class="lp-eco-item"><b>kfxai</b>FXの判断AI</span>
      <span class="lp-eco-item"><b>kcbrain</b>暗号ジャッジAPI</span>
      <span class="lp-eco-item"><b>kfxbrain</b>FXジャッジAPI</span>
      <span class="lp-eco-item"><b>kvtuber</b>AI VTuber</span>
      <span class="lp-eco-item"><b>url2brain</b>配信エンジン(OSS)</span>
    </div>
  </section>

  <section class="lp-band lp-philo">
    <span class="lp-band-kicker">思想 — inspired by Bittensor</span>
    <h3 class="lp-band-title">あなたは“利用者”ではなく、<em>共に育てる人</em>。</h3>
    <p class="lp-band-text">
      私たちは <b>Bittensor</b> の考え方を支持しています。AIが生み出す価値を運営が独り占めするのではなく、
      使い・広め・支えてくれた人へ <b>トークンで還元する</b>。それが URLAI です。
      あなたの1回が、この分配ネットワークの一部になります。
    </p>
  </section>

  <section class="lp-steps">
    <h3 class="lp-steps-title">3ステップ、数分で完了</h3>
    <ol class="lp-steps-list">
      <li><span class="lp-step-n">1</span><b>Xでログイン</b><small>Baseウォレットを接続します。</small></li>
      <li><span class="lp-step-n">2</span><b>URLを1つ入力</b><small>Kurageさんが読んで告知文とブログを執筆。</small></li>
      <li><span class="lp-step-n">3</span><b>5媒体へ自動配信</b><small>完了したあなたへ URLAI を配布。</small></li>
    </ol>
  </section>

  <section class="lp-band lp-econ">
    <span class="lp-band-kicker">テスト完了後の姿</span>
    <h3 class="lp-band-title">1回のご利用は <em>200円</em>、または <em>20,000 URLAI</em>。</h3>
    <p class="lp-band-text">
      テストが終わったら、1回のご利用を <b>200円でも、20,000 URLAIでも</b>お支払いいただける
      <b>選択制</b>になります。テスト期間に受け取ったURLAIは、そのまま利用料としてお使いいただけます。
      使わずに持ち続ける人は、<b>Kurageが育てば、その上振れを分かち合う</b>——約束ではなく、
      早く支えてくれた人への“取り分”として。
    </p>
    <div class="lp-flow">
      <span>URLAIを受け取る</span><i>→</i><span>使って支払う／売る</span><i>→</i><span>欲しい人が買う</span><i>→</i><span>経済が回る</span>
    </div>
    <p class="lp-band-text lp-band-text--em">
      いまはその仕組みを検証する<b>テスト期間</b>。<b>あなたの協力が、この経済の最初の一歩</b>になります。
    </p>
    <a class="btn btn-x lp-cta" href="?login=1">𝕏 でログインして始める</a>
  </section>

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
    <?php if (u2p_reward_enabled()): ?>
      <div class="reward-banner">
        <div><span class="reward-kicker">URLAI USER REWARD</span><strong>10,000 URLAI</strong></div>
        <p>先着1,000人・XアカウントとBaseウォレットにつき1回。5媒体への配信は、媒体側で失敗しても特典対象です。</p>
      </div>
      <div class="wallet-row">
        <button type="button" id="u2pConnectWallet" class="btn btn-violet">Baseウォレットを接続</button>
        <span id="u2pWalletState">未接続</span>
      </div>
      <input type="hidden" id="u2pWallet" value="">
    <?php endif; ?>
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
    <?php if ($reward_claim): ?>
      <div class="reward-status" data-claim-id="<?php echo u2p_h($reward_claim['id']); ?>">
        <strong>10,000 URLAI 利用特典</strong>
        <span class="reward-state"><?php echo $reward_claim['status'] === 'sent' ? '送金済み' : '送金処理中'; ?></span>
        <small><?php echo u2p_h(substr($reward_claim['wallet'], 0, 6) . '...' . substr($reward_claim['wallet'], -4)); ?></small>
      </div>
    <?php elseif (!empty($pending['reward_status']) && $pending['reward_status'] !== 'disabled'): ?>
      <div class="reward-status"><strong>URLAI 利用特典</strong><span class="reward-state">申請済み</span></div>
    <?php endif; ?>
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
    <?php if ($reward_claim): ?>
      <div class="reward-status" data-claim-id="<?php echo u2p_h($reward_claim['id']); ?>">
        <strong><?php echo u2p_h($reward_claim['amount']); ?> URLAI 利用特典</strong>
        <span class="reward-state"><?php echo $reward_claim['status'] === 'sent' ? '送金済み' : '送金処理中'; ?></span>
        <small><?php echo u2p_h(substr($reward_claim['wallet'], 0, 6) . '...' . substr($reward_claim['wallet'], -4)); ?></small>
        <?php if (!empty($reward_claim['tx_hash'])): ?>
          <a href="https://basescan.org/tx/<?php echo rawurlencode($reward_claim['tx_hash']); ?>" target="_blank" rel="noopener">Basescanで確認</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
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
    { key: 'post-hatena-blog', label: 'はてなブログへ配信中…' },
    { key: 'reward', label: 'URLAI利用特典を受付中…' }
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

  var walletInput = document.getElementById('u2pWallet');
  var walletState = document.getElementById('u2pWalletState');
  var walletButton = document.getElementById('u2pConnectWallet');

  function shortWallet(address) { return address.slice(0, 6) + '...' + address.slice(-4); }
  function setWallet(address) {
    if (walletInput) walletInput.value = address || '';
    if (walletState) walletState.textContent = address ? shortWallet(address) + ' / Base' : '未接続';
    if (walletButton) walletButton.textContent = address ? '接続済み' : 'Baseウォレットを接続';
  }
  async function connectWallet() {
    if (!window.ethereum) throw new Error('MetaMaskなどのEVMウォレットが必要です。');
    var accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    if (!accounts || !accounts[0]) throw new Error('ウォレットを確認できませんでした。');
    try {
      await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: '0x2105' }] });
    } catch (switchError) {
      if (switchError && switchError.code === 4902) {
        await window.ethereum.request({ method: 'wallet_addEthereumChain', params: [{
          chainId: '0x2105', chainName: 'Base', nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 },
          rpcUrls: ['https://mainnet.base.org'], blockExplorerUrls: ['https://basescan.org']
        }] });
      } else { throw switchError; }
    }
    setWallet(accounts[0].toLowerCase());
    return accounts[0].toLowerCase();
  }
  if (walletButton) walletButton.addEventListener('click', function () {
    connectWallet().catch(function (err) {
      var el = document.getElementById('u2pError');
      el.textContent = err.message || String(err); el.style.display = 'block';
    });
  });
  if (window.ethereum) {
    window.ethereum.request({ method: 'eth_accounts' }).then(function (accounts) {
      if (accounts && accounts[0]) setWallet(accounts[0].toLowerCase());
    });
    if (window.ethereum.on) window.ethereum.on('accountsChanged', function (accounts) {
      setWallet(accounts && accounts[0] ? accounts[0].toLowerCase() : '');
    });
  }

  u2pForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    var url = document.getElementById('url').value;
    var errEl = document.getElementById('u2pError');
    errEl.style.display = 'none';
    var wallet = walletInput ? walletInput.value : '';
    if (walletInput && !wallet) {
      try { wallet = await connectWallet(); }
      catch (walletError) {
        errEl.textContent = walletError.message || String(walletError); errEl.style.display = 'block'; return;
      }
    }
    u2pForm.style.display = 'none';
    var progressEl = document.getElementById('u2pProgress');
    progressEl.style.display = 'block';
    var stepsEl = document.getElementById('u2pSteps');
    stepsEl.innerHTML = STEPS.map(function (s) {
      return '<li id="step-' + s.key + '" data-label="' + s.label + '">⏳ ' + s.label + '</li>';
    }).join('');

    function callAjax(action, platform, body) {
      var qs = 'ajax.php?action=' + action + (platform ? '&platform=' + platform : '');
      return fetch(qs, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
    }

    function fail(msg) {
      progressEl.style.display = 'none';
      u2pForm.style.display = 'block';
      errEl.textContent = msg;
      errEl.style.display = 'block';
    }

    var runId = '';
    callAjax('analyze', null, { url: url, wallet: wallet }).then(function (d) {
      if (!d.ok) { throw new Error(d.error || '解析に失敗しました'); }
      u2pMark('analyze', 'ok');
      runId = d.run_id;
      var source = d.source;
      return callAjax('announcement', null, { run_id: runId, source: source }).then(function (d2) {
        if (!d2.ok) { throw new Error(d2.error || '告知文の生成に失敗しました'); }
        u2pMark('announcement', 'ok');
        var announcement = d2.announcement;
        return callAjax('blog', null, { run_id: runId, source: source }).then(function (d3) {
          if (!d3.ok) { throw new Error(d3.error || 'ブログ記事の生成に失敗しました'); }
          u2pMark('blog', 'ok');
          var blog = d3.blog;
          var posted = [];
          var chain = Promise.resolve();
          PLATFORMS.forEach(function (p) {
            chain = chain.then(function () {
              return callAjax('post', p.key, { run_id: runId, source: source, announcement: announcement, blog: blog }).then(function (dp) {
                u2pMark('post-' + p.key, dp.ok ? 'ok' : 'ng', dp.ok ? '' : (dp.error || '失敗'));
                posted.push({ key: p.key, label: p.label, ok: !!dp.ok, url: dp.url || '', error: dp.error || '' });
              }).catch(function (postError) {
                var message = postError.message || String(postError);
                u2pMark('post-' + p.key, 'ng', message);
                posted.push({ key: p.key, label: p.label, ok: false, url: '', error: message });
              });
            });
          });
          return chain.then(function () {
            return callAjax('finish', null, { run_id: runId, source: source, announcement: announcement, blog: blog, posted: posted });
          });
        });
      });
    }).then(function (dfin) {
      if (!dfin || !dfin.ok) { throw new Error('結果の保存に失敗しました'); }
      u2pMark('reward', dfin.reward && dfin.reward.status !== 'closed' ? 'ok' : 'ng', dfin.reward ? (dfin.reward.message || '') : '');
      window.location.href = 'url2pub.php?step=share';
    }).catch(function (err) {
      fail(err.message || String(err));
    });
  });
}

document.querySelectorAll('.reward-status[data-claim-id]').forEach(function (el) {
  var claimId = el.getAttribute('data-claim-id');
  var timer = setInterval(function () {
    fetch('ajax.php?action=reward-status', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ claim_id: claimId })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data.ok || !data.reward) return;
      var state = el.querySelector('.reward-state');
      var labels = { sent: '送金済み', failed: '送金確認中', enqueue_failed: '送金待機中', queued: '送金待機中', processing: '送金処理中', pending: '送金待機中' };
      if (state) state.textContent = labels[data.reward.status] || data.reward.status;
      if (data.reward.status === 'sent') {
        clearInterval(timer);
        if (data.reward.tx_url && !el.querySelector('a')) {
          var link = document.createElement('a'); link.href = data.reward.tx_url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = 'Basescanで確認'; el.appendChild(link);
        }
      }
    }).catch(function () {});
  }, 5000);
});
</script>

</body>
</html>
