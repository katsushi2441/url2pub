<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/lib.php';
date_default_timezone_set('Asia/Tokyo');

$auth = url2ai_auth_bootstrap();
if (empty($auth['logged_in'])) {
    header('Location: index.php');
    exit;
}
$records = u2p_history_load($auth['session_user']);
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>配信履歴 | Kurage URL2AI Publisher</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="assets/kurage_avatar_square.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic:wght@500;700;900&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site"><div class="wrap">
  <div class="hbrand">
    <div class="avatar-ring"><img src="assets/kurage_avatar_square.webp" alt="Kurage" width="96" height="96"></div>
    <div>
      <h1>配信履歴</h1>
      <p class="tagline">@<?php echo htmlspecialchars($auth['session_user'], ENT_QUOTES, 'UTF-8'); ?> がKurageさんに配信してもらった記事の一覧です。</p>
    </div>
  </div>
  <div class="whoami"><a href="index.php">← 新しいURLを配信する</a></div>
</div></header>
<main><div class="wrap">
<?php if (empty($records)): ?>
  <p class="empty">まだ配信履歴がありません。</p>
<?php else: ?>
  <?php foreach ($records as $r): ?>
    <div class="hist-card">
      <a class="title" href="index.php?step=result&id=<?php echo urlencode($r['id']); ?>">
        <?php echo htmlspecialchars(isset($r['blog']['title']) ? $r['blog']['title'] : $r['id'], ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <div class="meta">
        <?php echo htmlspecialchars(isset($r['created_at']) ? $r['created_at'] : '', ENT_QUOTES, 'UTF-8'); ?> ·
        <?php echo htmlspecialchars(isset($r['source']['url']) ? $r['source']['url'] : '', ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div></main>
<footer class="site"><div class="wrap">
  Kurage URL2AI Publisher — <a href="https://exbridge.jp/">株式会社エクスブリッジ</a>のプロダクト
</div></footer>
</body>
</html>
