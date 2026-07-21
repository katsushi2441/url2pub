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
<style>
:root { --ink:#241a4d; --muted:#6d679a; --line:#ded4f6; --accent:#6c4fd4; --accent2:#5136b0; --soft:#f2edfd; --paper:rgba(255,255,255,.92); }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { color: var(--ink); font-family: -apple-system,"Segoe UI",Roboto,"Hiragino Sans","Yu Gothic",Meiryo,sans-serif; background: linear-gradient(160deg,#fff 0%,#f1edff 50%,#f8f5ff 100%); min-height: 100vh; line-height: 1.75; }
a { color: var(--accent); }
header { max-width: 900px; margin: 0 auto; padding: 30px 24px 10px; display: flex; justify-content: space-between; align-items: center; }
header h1 { font-size: 20px; font-weight: 900; }
main { max-width: 900px; margin: 0 auto; padding: 10px 24px 60px; }
.card { background: var(--paper); border: 1.5px solid var(--line); border-radius: 16px; padding: 16px 20px; margin-bottom: 12px; box-shadow: 0 8px 24px rgba(30,22,61,.05); }
.card a.title { font-weight: 800; font-size: 14.5px; }
.card .meta { font-size: 12px; color: var(--muted); margin-top: 4px; }
.empty { color: var(--muted); font-size: 14px; padding: 30px 0; text-align: center; }
</style>
</head>
<body>
<header>
  <h1>配信履歴</h1>
  <div><a href="index.php">← 新しいURLを配信する</a></div>
</header>
<main>
<?php if (empty($records)): ?>
  <p class="empty">まだ配信履歴がありません。</p>
<?php else: ?>
  <?php foreach ($records as $r): ?>
    <div class="card">
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
</main>
</body>
</html>
