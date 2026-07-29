<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/lib.php';
date_default_timezone_set('Asia/Tokyo');

// 言語判定: ?lang=en/ja で切替＆Cookieに保存。以降はCookieで維持（リンクにlangを付けなくてよい）。
$lang = 'ja';
if (isset($_GET['lang'])) {
    $lang = ($_GET['lang'] === 'en') ? 'en' : 'ja';
    setcookie('u2p_lang', $lang, time() + 31536000, '/');
    $_COOKIE['u2p_lang'] = $lang;
} elseif (isset($_COOKIE['u2p_lang']) && $_COOKIE['u2p_lang'] === 'en') {
    $lang = 'en';
}

// Xログインは既存のKurage共通ログイン基盤に委譲。*.exbridge.jp共通クッキーで自動ログイン扱い。
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

// UI文言（日本語 / English）。ロジックは1本、文言だけ差し替える。
$T_ALL = array(
'ja' => array(
  'og_locale' => 'ja_JP',
  'title' => 'Kurage URL2AI Publisher — KurageさんがURLを解析して5メディアに配信',
  'meta_desc' => 'URLを渡すとKurageさんが記事を読み、考察と告知文を書き、株式会社エクスブリッジが運営する5メディア(Bluesky/はてなブックマーク/はてなブログ/AIxSNS/Kurageブログ)へ自動配信します。無料・Xログインで利用可能。',
  'meta_keywords' => 'Kurage,URL2AI,AI VTuber,自動配信,ブログ自動生成,SNS自動投稿,Bluesky,はてな,AIxSNS,exbridge',
  'og_title' => 'Kurage URL2AI Publisher — URLを渡すだけで5メディアへ自動配信',
  'og_desc' => 'KurageさんがURLを解析して考察・告知文・ブログ記事を書き、Bluesky/はてな/AIxSNS/Kurageブログへ自動配信します。',
  'tw_desc' => 'URLを渡すだけでKurageさんが5メディアへ自動配信します。',
  'ld_desc' => 'URLを解析し、告知文とブログ記事を自動生成して5つのメディアへ配信するAIパブリッシングツール。',
  'tagline' => 'Kurageさんが記事を読み、考察と告知文を書き、5つのメディアへ配信します。',
  'loggedin_suffix' => ' でログイン中',
  'nav_history' => '履歴',
  'nav_logout' => 'ログアウト',
  'm_hatena_bookmark' => 'はてなブックマーク',
  'm_bludit' => 'Kurageブログ',
  'm_hatena_blog' => 'はてなブログ',
  'lp_eyebrow' => 'Kurageを一緒に育てる ・ URLAI テスト期間',
  'lp_title' => 'Kurageを広める人が、<br>Kurageと一緒に<em>育つ</em>。',
  'lp_lead' => 'URLを1つ渡すだけ。<b>Kurageさん</b>がその記事を読んで考察し、告知文とブログ記事を書き上げ、<b>5つのメディアへ自動で配信</b>。さらに<b>あなた自身もXでシェア</b>すれば、その一言が <b>Kurageを世界へ広げる拡散の力</b>になります。広めてくれたあなたへ、感謝を込めて <b>URLAIトークン</b>をお渡しします。',
  'lp_token_kicker' => 'いま協力してくれた方へ',
  'lp_token_sub' => '1回の配信を最後まで完了で配布 ／ 先着1,000人 ・ Xとウォレットにつき1回 ・ <b>無料</b>',
  'lp_token_soon_kicker' => 'URLAI 配布',
  'lp_token_soon_amt' => 'まもなく開始',
  'lp_token_soon_sub' => '協力してくれた方へURLAIトークンをお配りします。',
  'lp_cta' => '𝕏 でログインして、いますぐ協力する',
  'lp_cta_note' => 'Xログイン＋Baseウォレット接続だけ。費用はかかりません。',
  'fw_kicker' => 'これが、経済が回る仕組み',
  'fw_title' => 'あなたの拡散が、<em>Kurageの実需</em>に変わる。',
  'fw1_b' => 'あなたが広める', 'fw1_s' => 'url2pubで5媒体へ配信＋Xでシェア',
  'fw2_b' => 'Kurageの知名度が上がる',
  'fw3_b' => '有料サービスの利用者が増える', 'fw3_s' => 'kfreqai・kcbrain・kfxbrain…',
  'fw4_b' => '利益が生まれる',
  'fw5_b' => 'URLAI と貢献者へ還元',
  'fw_text' => '使うほどKurageが広まり、広まるほど実需が生まれ、生まれた価値が<b>育ててくれたあなた</b>へ還ってくる。URLAIは、その循環の<b>当事者になるための仕組み</b>です。',
  'eco_title' => 'あなたが広げる、Kurageの“実体”',
  'eco_lead' => 'Kurageは1つのボットではなく、<b>本番稼働しているAIプロダクト群</b>。あなたの拡散は、この全部の知名度になります。',
  'eco_kfreqai' => '暗号資産の判断AI', 'eco_kfxai' => 'FXの判断AI',
  'eco_kcbrain' => '暗号ジャッジAPI', 'eco_kfxbrain' => 'FXジャッジAPI',
  'eco_kvtuber' => 'AI VTuber', 'eco_url2brain' => '配信エンジン(OSS)',
  'philo_kicker' => '思想 — inspired by Bittensor',
  'philo_title' => 'あなたは“利用者”ではなく、<em>共に育てる人</em>。',
  'philo_text' => '私たちは <b>Bittensor</b> の考え方を支持しています。AIが生み出す価値を運営が独り占めするのではなく、使い・広め・支えてくれた人へ <b>トークンで還元する</b>。それが URLAI です。あなたの1回が、この分配ネットワークの一部になります。',
  'steps_title' => '使って、Xでシェアして、Kurageを広める',
  'step1_b' => 'Xでログイン', 'step1_s' => 'Baseウォレットを接続します。',
  'step2_b' => 'URLを1つ入力', 'step2_s' => 'Kurageさんが読んで告知文とブログを執筆。',
  'step3_b' => 'Kurageが5媒体へ自動配信', 'step3_s' => 'Bluesky・はてな・AIxSNS等へKurageさんが投稿。',
  'step4_b' => 'あなたもXでシェア', 'step4_s' => 'その一言の拡散がKurageの力に。完了であなたへURLAIを配布。',
  'econ_kicker' => 'テスト完了後の姿',
  'econ_title' => '1回のご利用は <em>200円</em>、または <em>20,000 URLAI</em>。',
  'econ_text' => 'テストが終わったら、1回のご利用を <b>200円でも、20,000 URLAIでも</b>お支払いいただける<b>選択制</b>になります。テスト期間に受け取ったURLAIは、そのまま利用料としてお使いいただけます。使わずに持ち続ける人は、<b>Kurageが育てば、その上振れを分かち合う</b>——約束ではなく、早く支えてくれた人への“取り分”として。',
  'flow1' => 'URLAIを受け取る', 'flow2' => '使って支払う／売る', 'flow3' => '欲しい人が買う', 'flow4' => '経済が回る',
  'econ_em' => 'いまはその仕組みを検証する<b>テスト期間</b>。<b>あなたの協力が、この経済の最初の一歩</b>になります。',
  'econ_cta' => '𝕏 でログインして始める',
  'reach_kicker' => '5媒体の外へ',
  'reach_title' => '自動配信の5媒体に加えて、<em>𝕏 と YouTube</em> へ広がることもあります。',
  'reach_x' => '<b>𝕏（手動）:</b> Xへの自動投稿は誤爆リスクがあるため行っていませんが、内容を確認したうえで手動で告知することがあります。ご希望の方は <a href="https://x.com/xb_bittensor" target="_blank" rel="noopener">@xb_bittensor</a> をフォローしてコメントをください。確認後に、そのURLの告知を投稿します。',
  'reach_yt' => '<b>YouTube・考察ショート動画:</b> 質の高いコンテンツは、<a href="https://kurage.exbridge.jp/kmontage.php" target="_blank" rel="noopener">kmontage.php</a> で考察系のショート動画を生成し、<a href="https://kurage.exbridge.jp/kuragev.php" target="_blank" rel="noopener">kuragev.php</a> や YouTubeチャンネル <a href="https://www.youtube.com/@xb-bittensor" target="_blank" rel="noopener">@xb-bittensor</a> で配信することもあります。',
  'intro' => 'URLを1つ渡すだけで、<b>Kurageさん</b>がその記事を解析して考察し、告知文とブログ記事を書き上げ、<b>株式会社エクスブリッジ</b>が運営する5つのメディアへ自動で配信します。',
  'reward_banner_desc' => '先着1,000人・XアカウントとBaseウォレットにつき1回。5媒体への配信は、媒体側で失敗しても特典対象です。',
  'wallet_optional_note' => 'ウォレット接続は任意です（キャンペーン中につき配信は無料）。10,000 URLAIの特典を受け取りたい場合のみ接続してください。既に特典を受け取り済みの方は接続不要です。',
  'form_label' => '配信したいページのURL',
  'form_submit' => 'Kurageさんに配信してもらう',
  'progress_working' => 'Kurageさんが作業中です',
  'share_done_h2' => '配信が完了しました',
  'share_desc' => '無料でのご利用にあたり、下の内容でXへ一言シェアをお願いします。投稿後、下のボタンから結果画面へ進んでください。',
  'reward_use_title' => '10,000 URLAI 利用特典',
  'reward_generic_title' => 'URLAI 利用特典',
  'reward_applied' => '申請済み',
  'reward_unit_suffix' => ' URLAI 利用特典',
  'btn_post_x' => '𝕏 で投稿する',
  'btn_copy' => 'コピー',
  'btn_posted_next' => '投稿しました → 結果を見る',
  'basescan' => 'Basescanで確認',
  'hist_label' => '履歴',
  'hist_srcurl' => '元URL',
  'hist_back' => '履歴一覧へ戻る',
  'h2_ann' => '告知用記事',
  'btn_x_post' => '𝕏 投稿',
  'h2_blog' => '考察・ブログ用記事',
  'h2_posted' => '配信先URL一覧',
  'status_ok' => '配信済み', 'status_ng' => '失敗',
  'btn_another' => '別のURLを配信する',
  'btn_history' => '履歴一覧',
  'admin_h2' => '管理者ビュー: 全ユーザー利用履歴',
  'th_user' => 'ユーザー', 'th_url' => 'URL', 'th_time' => '日時',
  'admin_empty' => 'まだ利用履歴がありません。',
  'footer_product' => 'のプロダクト',
  'footer_brain' => 'が担当',
  'footer_contact' => 'お問い合わせ',
  'share_text' => 'Kurage URL2AI Publisherを試してみました。URLを渡すだけでKurageさんが記事を読んで告知文とブログ記事を書き、5つのメディアへ自動配信。利用者向けの10,000 URLAI特典もあります。',
  'js' => array(
    'copied' => 'コピーしました',
    'st_analyze' => '記事を解析中…', 'st_announcement' => '告知文を生成中…', 'st_blog' => 'ブログ記事を生成中…',
    'st_bluesky' => 'Blueskyへ配信中…', 'st_hbm' => 'はてなブックマークへ配信中…', 'st_aixsns' => 'AIxSNSへ配信中…',
    'st_bludit' => 'Kurageブログへ配信中…', 'st_hblog' => 'はてなブログへ配信中…', 'st_reward' => 'URLAI利用特典を受付中…',
    'pl_bluesky' => 'Bluesky', 'pl_hbm' => 'はてなブックマーク', 'pl_aixsns' => 'AIxSNS', 'pl_bludit' => 'Kurageブログ', 'pl_hblog' => 'はてなブログ',
    'err_analyze' => '解析に失敗しました', 'err_announcement' => '告知文の生成に失敗しました', 'err_blog' => 'ブログ記事の生成に失敗しました', 'err_save' => '結果の保存に失敗しました', 'err_failed' => '失敗',
    'wallet_need' => 'MetaMaskなどのEVMウォレットが必要です。', 'wallet_notfound' => 'ウォレットを確認できませんでした。',
    'wallet_unconnected' => '未接続', 'wallet_connected' => '接続済み', 'wallet_connect' => 'Baseウォレットを接続',
    'rw_sent' => '送金済み', 'rw_processing' => '送金処理中', 'rw_confirming' => '送金確認中', 'rw_waiting' => '送金待機中', 'basescan' => 'Basescanで確認',
  ),
),
'en' => array(
  'og_locale' => 'en_US',
  'title' => 'Kurage URL2AI Publisher — Kurage reads your URL and publishes to 5 media',
  'meta_desc' => 'Hand over one URL and Kurage reads the article, writes an announcement and a blog post, and auto-publishes to 5 media (Bluesky / Hatena Bookmark / Hatena Blog / AIxSNS / Kurage Blog) run by EXBRIDGE, Inc. Free, sign in with X.',
  'meta_keywords' => 'Kurage,URL2AI,AI VTuber,auto publishing,blog generation,social posting,Bluesky,Hatena,AIxSNS,exbridge',
  'og_title' => 'Kurage URL2AI Publisher — one URL, auto-published to 5 media',
  'og_desc' => 'Kurage reads your URL, writes analysis / announcement / blog post, and auto-publishes to Bluesky / Hatena / AIxSNS / Kurage Blog.',
  'tw_desc' => 'Hand over one URL and Kurage auto-publishes to 5 media.',
  'ld_desc' => 'An AI publishing tool that analyzes a URL, generates an announcement and blog post, and distributes them to 5 media.',
  'tagline' => 'Kurage reads your article, writes analysis and an announcement, and publishes to 5 media.',
  'loggedin_suffix' => ' — signed in',
  'nav_history' => 'History',
  'nav_logout' => 'Sign out',
  'm_hatena_bookmark' => 'Hatena Bookmark',
  'm_bludit' => 'Kurage Blog',
  'm_hatena_blog' => 'Hatena Blog',
  'lp_eyebrow' => 'Grow Kurage together ・ URLAI test period',
  'lp_title' => 'Those who spread Kurage<br>grow <em>together with</em> Kurage.',
  'lp_lead' => 'Just hand over one URL. <b>Kurage</b> reads the article, writes an announcement and a blog post, and <b>auto-publishes to 5 media</b>. And when <b>you share it on X too</b>, that one post becomes <b>the reach that spreads Kurage to the world</b>. As a thank-you, we hand you <b>URLAI tokens</b>.',
  'lp_token_kicker' => 'For those who help now',
  'lp_token_sub' => 'Granted when you complete one full run ／ First 1,000 people ・ Once per X account and wallet ・ <b>Free</b>',
  'lp_token_soon_kicker' => 'URLAI distribution',
  'lp_token_soon_amt' => 'Coming soon',
  'lp_token_soon_sub' => 'We will hand URLAI tokens to those who help.',
  'lp_cta' => 'Sign in with 𝕏 and help now',
  'lp_cta_note' => 'Just sign in with X and connect a Base wallet. It is free.',
  'fw_kicker' => 'How the economy comes full circle',
  'fw_title' => 'Your reach turns into <em>real demand for Kurage</em>.',
  'fw1_b' => 'You spread it', 'fw1_s' => 'Publish to 5 media + share on X',
  'fw2_b' => 'Kurage gets known',
  'fw3_b' => 'Paid services gain users', 'fw3_s' => 'kfreqai・kcbrain・kfxbrain…',
  'fw4_b' => 'Revenue is created',
  'fw5_b' => 'Returned to URLAI &amp; contributors',
  'fw_text' => 'The more it is used, the more Kurage spreads; the more it spreads, the more real demand is created; and that value flows back to <b>you, who helped grow it</b>. URLAI is <b>the way to become part of that loop</b>.',
  'eco_title' => 'The “real thing” you help spread',
  'eco_lead' => 'Kurage is not a single bot but <b>a suite of AI products running in production</b>. Your reach becomes awareness for all of them.',
  'eco_kfreqai' => 'Crypto judgment AI', 'eco_kfxai' => 'FX judgment AI',
  'eco_kcbrain' => 'Crypto judgment API', 'eco_kfxbrain' => 'FX judgment API',
  'eco_kvtuber' => 'AI VTuber', 'eco_url2brain' => 'Publishing engine (OSS)',
  'philo_kicker' => 'Philosophy — inspired by Bittensor',
  'philo_title' => 'You are not a “user,” but <em>someone who grows it with us</em>.',
  'philo_text' => 'We support the ideas of <b>Bittensor</b>. Instead of the operator monopolizing the value AI creates, we <b>return it in tokens</b> to those who use, spread, and support it. That is URLAI. Your single run becomes part of this distribution network.',
  'steps_title' => 'Use it, share it on X, spread Kurage',
  'step1_b' => 'Sign in with X', 'step1_s' => 'Connect a Base wallet.',
  'step2_b' => 'Enter one URL', 'step2_s' => 'Kurage reads it and writes the announcement and blog.',
  'step3_b' => 'Kurage auto-publishes to 5 media', 'step3_s' => 'Posts to Bluesky, Hatena, AIxSNS and more.',
  'step4_b' => 'You share it on X too', 'step4_s' => 'That one share powers Kurage. URLAI is granted to you on completion.',
  'econ_kicker' => 'After the test period',
  'econ_title' => 'Each run is <em>200 JPY</em>, or <em>20,000 URLAI</em>.',
  'econ_text' => 'After the test, each run becomes payable <b>either with 200 JPY or with 20,000 URLAI</b> — <b>your choice</b>. URLAI you received during the test can be used directly as the fee. Those who keep holding it <b>share in the upside if Kurage grows</b> — not a promise, but a “stake” for those who supported it early.',
  'flow1' => 'Receive URLAI', 'flow2' => 'Use to pay / sell', 'flow3' => 'Others buy it', 'flow4' => 'The economy circulates',
  'econ_em' => 'Right now is the <b>test period</b> to validate this mechanism. <b>Your help is the first step of this economy.</b>',
  'econ_cta' => 'Sign in with 𝕏 to start',
  'reach_kicker' => 'Beyond the 5 media',
  'reach_title' => 'Beyond the 5 auto-publish channels, it can also reach <em>𝕏 and YouTube</em>.',
  'reach_x' => '<b>𝕏 (manual):</b> We do not auto-post to X because of the risk, but we do post announcements manually after review. If you would like that, follow <a href="https://x.com/xb_bittensor" target="_blank" rel="noopener">@xb_bittensor</a> and leave a comment — after we confirm it, we will post an announcement for your URL.',
  'reach_yt' => '<b>YouTube · analysis shorts:</b> For high-quality content, we may generate an analysis short video with <a href="https://kurage.exbridge.jp/kmontage.php" target="_blank" rel="noopener">kmontage.php</a> and distribute it on <a href="https://kurage.exbridge.jp/kuragev.php" target="_blank" rel="noopener">kuragev.php</a> and our YouTube channel <a href="https://www.youtube.com/@xb-bittensor" target="_blank" rel="noopener">@xb-bittensor</a>.',
  'intro' => 'Just hand over one URL and <b>Kurage</b> analyzes the article, writes an announcement and a blog post, and auto-publishes to 5 media run by <b>EXBRIDGE, Inc.</b>',
  'reward_banner_desc' => 'First 1,000 people, once per X account and Base wallet. You qualify even if some of the 5 media fail on their side.',
  'wallet_optional_note' => 'Connecting a wallet is optional (publishing is free during the campaign). Connect only if you want the 10,000 URLAI reward. Already claimed? No need to connect.',
  'form_label' => 'URL of the page to publish',
  'form_submit' => 'Have Kurage publish it',
  'progress_working' => 'Kurage is working',
  'share_done_h2' => 'Publishing complete',
  'share_desc' => 'For free use, please share a quick word on X with the text below. After posting, continue to the result screen with the button below.',
  'reward_use_title' => '10,000 URLAI reward',
  'reward_generic_title' => 'URLAI reward',
  'reward_applied' => 'Requested',
  'reward_unit_suffix' => ' URLAI reward',
  'btn_post_x' => 'Post on 𝕏',
  'btn_copy' => 'Copy',
  'btn_posted_next' => 'I posted → see the result',
  'basescan' => 'View on Basescan',
  'hist_label' => 'History',
  'hist_srcurl' => 'Source URL',
  'hist_back' => 'Back to history',
  'h2_ann' => 'Announcement',
  'btn_x_post' => 'Post 𝕏',
  'h2_blog' => 'Analysis / Blog post',
  'h2_posted' => 'Published URLs',
  'status_ok' => 'Published', 'status_ng' => 'Failed',
  'btn_another' => 'Publish another URL',
  'btn_history' => 'History',
  'admin_h2' => 'Admin view: all users’ history',
  'th_user' => 'User', 'th_url' => 'URL', 'th_time' => 'Date',
  'admin_empty' => 'No usage history yet.',
  'footer_product' => ' — a product by ',
  'footer_brain' => 'powers the brains',
  'footer_contact' => 'Contact',
  'share_text' => 'I tried Kurage URL2AI Publisher. Hand over a URL and Kurage reads it, writes an announcement and a blog post, and auto-publishes to 5 media. There is also a 10,000 URLAI reward for users.',
  'js' => array(
    'copied' => 'Copied',
    'st_analyze' => 'Analyzing the article…', 'st_announcement' => 'Generating the announcement…', 'st_blog' => 'Generating the blog post…',
    'st_bluesky' => 'Publishing to Bluesky…', 'st_hbm' => 'Publishing to Hatena Bookmark…', 'st_aixsns' => 'Publishing to AIxSNS…',
    'st_bludit' => 'Publishing to Kurage Blog…', 'st_hblog' => 'Publishing to Hatena Blog…', 'st_reward' => 'Registering your URLAI reward…',
    'pl_bluesky' => 'Bluesky', 'pl_hbm' => 'Hatena Bookmark', 'pl_aixsns' => 'AIxSNS', 'pl_bludit' => 'Kurage Blog', 'pl_hblog' => 'Hatena Blog',
    'err_analyze' => 'Analysis failed', 'err_announcement' => 'Announcement generation failed', 'err_blog' => 'Blog generation failed', 'err_save' => 'Failed to save the result', 'err_failed' => 'Failed',
    'wallet_need' => 'An EVM wallet like MetaMask is required.', 'wallet_notfound' => 'Could not detect a wallet.',
    'wallet_unconnected' => 'Not connected', 'wallet_connected' => 'Connected', 'wallet_connect' => 'Connect Base wallet',
    'rw_sent' => 'Sent', 'rw_processing' => 'Processing', 'rw_confirming' => 'Confirming', 'rw_waiting' => 'Queued', 'basescan' => 'View on Basescan',
  ),
),
);
$T = $T_ALL[$lang];

// 株式会社エクスブリッジが運営する5メディア（ラベルは言語で差し替え）。
$MEDIA = array(
    array('key' => 'bluesky', 'label' => 'Bluesky', 'note' => '@bittensorman.bsky.social'),
    array('key' => 'hatena-bookmark', 'label' => $T['m_hatena_bookmark'], 'note' => ''),
    array('key' => 'aixsns', 'label' => 'AIxSNS', 'note' => 'aixec.exbridge.jp'),
    array('key' => 'bludit', 'label' => $T['m_bludit'], 'note' => 'kurage.exbridge.jp/blog (url2pub)'),
    array('key' => 'hatena-blog', 'label' => $T['m_hatena_blog'], 'note' => 'xb-bittensor.hatenablog.com'),
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

$share_text = $T['share_text'];
$share_url = 'https://url2ai.exbridge.jp/';
$other_lang = $lang === 'en' ? 'ja' : 'en';
$reward_sent_label = $lang === 'en' ? 'Sent' : '送金済み';
$reward_processing_label = $lang === 'en' ? 'Processing' : '送金処理中';
?>
<!doctype html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo u2p_h($T['title']); ?></title>
<meta name="description" content="<?php echo u2p_h($T['meta_desc']); ?>">
<meta name="keywords" content="<?php echo u2p_h($T['meta_keywords']); ?>">
<meta name="robots" content="index,follow">
<meta name="author" content="EXBRIDGE, Inc.">
<link rel="canonical" href="https://url2ai.exbridge.jp/url2pub.php">
<link rel="alternate" hreflang="ja" href="https://url2ai.exbridge.jp/url2pub.php?lang=ja">
<link rel="alternate" hreflang="en" href="https://url2ai.exbridge.jp/url2pub.php?lang=en">
<link rel="icon" href="assets/kurage_avatar_square.png" type="image/png">
<link rel="apple-touch-icon" href="assets/kurage_avatar_square.png">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Kurage URL2AI Publisher">
<meta property="og:title" content="<?php echo u2p_h($T['og_title']); ?>">
<meta property="og:description" content="<?php echo u2p_h($T['og_desc']); ?>">
<meta property="og:url" content="https://url2ai.exbridge.jp/url2pub.php">
<meta property="og:image" content="https://url2ai.exbridge.jp/assets/ogp.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="<?php echo $T['og_locale']; ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Kurage URL2AI Publisher">
<meta name="twitter:description" content="<?php echo u2p_h($T['tw_desc']); ?>">
<meta name="twitter:image" content="https://url2ai.exbridge.jp/assets/ogp.png">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebApplication",
  "name": "Kurage URL2AI Publisher",
  "url": "https://url2ai.exbridge.jp/url2pub.php",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web",
  "description": <?php echo json_encode($T['ld_desc'], JSON_UNESCAPED_UNICODE); ?>,
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "JPY" },
  "provider": { "@type": "Organization", "name": "EXBRIDGE, Inc.", "url": "https://exbridge.jp/" }
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic:wght@500;700;900&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-BP0650KDFR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-BP0650KDFR');
</script>
<script>
(function () {
    var s = document.createElement('script');
    s.src = 'https://aiknowledgecms.exbridge.jp/simpletrack.php'
        + '?url=' + encodeURIComponent(location.href)
        + '&ref=' + encodeURIComponent(document.referrer);
    document.head.appendChild(s);
})();
</script>
</head>
<body>

<header class="site"><div class="wrap">
  <div class="hbrand">
    <div class="avatar-ring"><img src="assets/kurage_avatar_square.webp" alt="Kurage — jellyfish AI VTuber" width="96" height="96"></div>
    <div>
      <h1>Kurage URL2AI Publisher</h1>
      <p class="tagline"><?php echo u2p_h($T['tagline']); ?></p>
    </div>
  </div>
  <div class="topright">
    <div class="langswitch">
      <a href="?lang=ja"<?php echo $lang === 'ja' ? ' class="on"' : ''; ?>>日本語</a>
      <a href="?lang=en"<?php echo $lang === 'en' ? ' class="on"' : ''; ?>>English</a>
    </div>
    <?php if ($logged_in): ?>
      <div class="whoami"><strong>@<?php echo u2p_h($auth['session_user']); ?></strong><?php echo u2p_h($T['loggedin_suffix']); ?><br>
        <a href="history.php"><?php echo u2p_h($T['nav_history']); ?></a> · <a href="?logout=1"><?php echo u2p_h($T['nav_logout']); ?></a></div>
    <?php endif; ?>
  </div>
</div></header>

<main><div class="wrap">

<?php if ($view === 'login'): ?>
  <section class="lp-hero">
    <span class="lp-eyebrow"><?php echo u2p_h($T['lp_eyebrow']); ?></span>
    <h2 class="lp-title"><?php echo $T['lp_title']; ?></h2>
    <p class="lp-lead"><?php echo $T['lp_lead']; ?></p>

    <?php if (u2p_reward_enabled()): ?>
      <div class="lp-token">
        <span class="lp-token-kicker"><?php echo u2p_h($T['lp_token_kicker']); ?></span>
        <strong class="lp-token-amt">10,000<span class="lp-token-unit">URLAI</span></strong>
        <span class="lp-token-sub"><?php echo $T['lp_token_sub']; ?></span>
      </div>
    <?php else: ?>
      <div class="lp-token lp-token--soon">
        <span class="lp-token-kicker"><?php echo u2p_h($T['lp_token_soon_kicker']); ?></span>
        <strong class="lp-token-amt"><?php echo u2p_h($T['lp_token_soon_amt']); ?></strong>
        <span class="lp-token-sub"><?php echo u2p_h($T['lp_token_soon_sub']); ?></span>
      </div>
    <?php endif; ?>

    <a class="btn btn-x lp-cta" href="?login=1"><?php echo u2p_h($T['lp_cta']); ?></a>
    <p class="lp-cta-note"><?php echo u2p_h($T['lp_cta_note']); ?></p>

    <div class="media lp-media">
      <?php foreach ($MEDIA as $m): ?>
        <span><?php echo u2p_h($m['label']); ?><?php echo $m['note'] !== '' ? ' — ' . u2p_h($m['note']) : ''; ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="lp-band lp-reach">
    <span class="lp-band-kicker"><?php echo u2p_h($T['reach_kicker']); ?></span>
    <h3 class="lp-band-title"><?php echo $T['reach_title']; ?></h3>
    <p class="lp-band-text"><?php echo $T['reach_x']; ?></p>
    <p class="lp-band-text"><?php echo $T['reach_yt']; ?></p>
  </section>

  <section class="lp-band lp-flywheel">
    <span class="lp-band-kicker"><?php echo u2p_h($T['fw_kicker']); ?></span>
    <h3 class="lp-band-title"><?php echo $T['fw_title']; ?></h3>
    <div class="fw">
      <div class="fw-node"><span class="fw-n">1</span><b><?php echo u2p_h($T['fw1_b']); ?></b><small><?php echo u2p_h($T['fw1_s']); ?></small></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node"><span class="fw-n">2</span><b><?php echo u2p_h($T['fw2_b']); ?></b></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node"><span class="fw-n">3</span><b><?php echo u2p_h($T['fw3_b']); ?></b><small><?php echo u2p_h($T['fw3_s']); ?></small></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node"><span class="fw-n">4</span><b><?php echo u2p_h($T['fw4_b']); ?></b></div>
      <i class="fw-arrow">→</i>
      <div class="fw-node fw-node--gold"><span class="fw-n">5</span><b><?php echo $T['fw5_b']; ?></b></div>
      <i class="fw-arrow fw-arrow--loop">↻</i>
    </div>
    <p class="lp-band-text"><?php echo $T['fw_text']; ?></p>
  </section>

  <section class="lp-eco">
    <h3 class="lp-eco-title"><?php echo u2p_h($T['eco_title']); ?></h3>
    <p class="lp-eco-lead"><?php echo $T['eco_lead']; ?></p>
    <div class="lp-eco-grid">
      <span class="lp-eco-item"><b>kfreqai</b><?php echo u2p_h($T['eco_kfreqai']); ?></span>
      <span class="lp-eco-item"><b>kfxai</b><?php echo u2p_h($T['eco_kfxai']); ?></span>
      <span class="lp-eco-item"><b>kcbrain</b><?php echo u2p_h($T['eco_kcbrain']); ?></span>
      <span class="lp-eco-item"><b>kfxbrain</b><?php echo u2p_h($T['eco_kfxbrain']); ?></span>
      <span class="lp-eco-item"><b>kvtuber</b><?php echo u2p_h($T['eco_kvtuber']); ?></span>
      <span class="lp-eco-item"><b>url2brain</b><?php echo u2p_h($T['eco_url2brain']); ?></span>
    </div>
  </section>

  <section class="lp-band lp-philo">
    <span class="lp-band-kicker"><?php echo u2p_h($T['philo_kicker']); ?></span>
    <h3 class="lp-band-title"><?php echo $T['philo_title']; ?></h3>
    <p class="lp-band-text"><?php echo $T['philo_text']; ?></p>
  </section>

  <section class="lp-steps">
    <h3 class="lp-steps-title"><?php echo u2p_h($T['steps_title']); ?></h3>
    <ol class="lp-steps-list">
      <li><span class="lp-step-n">1</span><b><?php echo u2p_h($T['step1_b']); ?></b><small><?php echo u2p_h($T['step1_s']); ?></small></li>
      <li><span class="lp-step-n">2</span><b><?php echo u2p_h($T['step2_b']); ?></b><small><?php echo u2p_h($T['step2_s']); ?></small></li>
      <li><span class="lp-step-n">3</span><b><?php echo u2p_h($T['step3_b']); ?></b><small><?php echo u2p_h($T['step3_s']); ?></small></li>
      <li><span class="lp-step-n">4</span><b><?php echo u2p_h($T['step4_b']); ?></b><small><?php echo u2p_h($T['step4_s']); ?></small></li>
    </ol>
  </section>

  <section class="lp-band lp-econ">
    <span class="lp-band-kicker"><?php echo u2p_h($T['econ_kicker']); ?></span>
    <h3 class="lp-band-title"><?php echo $T['econ_title']; ?></h3>
    <p class="lp-band-text"><?php echo $T['econ_text']; ?></p>
    <div class="lp-flow">
      <span><?php echo u2p_h($T['flow1']); ?></span><i>→</i><span><?php echo u2p_h($T['flow2']); ?></span><i>→</i><span><?php echo u2p_h($T['flow3']); ?></span><i>→</i><span><?php echo u2p_h($T['flow4']); ?></span>
    </div>
    <p class="lp-band-text lp-band-text--em"><?php echo $T['econ_em']; ?></p>
    <a class="btn btn-x lp-cta" href="?login=1"><?php echo u2p_h($T['econ_cta']); ?></a>
  </section>

<?php elseif ($view === 'form'): ?>
  <div class="intro">
    <?php echo $T['intro']; ?>
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
        <p><?php echo u2p_h($T['reward_banner_desc']); ?></p>
      </div>
      <div class="wallet-row">
        <button type="button" id="u2pConnectWallet" class="btn btn-violet"><?php echo u2p_h($T['js']['wallet_connect']); ?></button>
        <span id="u2pWalletState"><?php echo u2p_h($T['js']['wallet_unconnected']); ?></span>
      </div>
      <p style="margin:6px 0 0;font-size:12px;opacity:.8"><?php echo u2p_h($T['wallet_optional_note']); ?></p>
      <input type="hidden" id="u2pWallet" value="">
    <?php endif; ?>
    <label for="url"><?php echo u2p_h($T['form_label']); ?></label>
    <input type="url" id="url" name="url" placeholder="https://example.com/article" required>
    <button type="submit" class="btn"><?php echo u2p_h($T['form_submit']); ?></button>
  </form>
  <div id="u2pError" class="error" style="display:none"></div>
  <div id="u2pProgress" class="card" style="display:none">
    <h2 style="font-size:16px;margin-bottom:6px;text-transform:none;letter-spacing:0;color:var(--abyss)"><?php echo u2p_h($T['progress_working']); ?></h2>
    <ul id="u2pSteps"></ul>
  </div>

<?php elseif ($view === 'share' && $pending): ?>
  <div class="card">
    <h2 style="font-size:16px;margin-bottom:10px;text-transform:none;letter-spacing:0;color:var(--abyss)"><?php echo u2p_h($T['share_done_h2']); ?></h2>
    <p style="font-size:13.5px;color:var(--abyss-soft);margin-bottom:16px">
      <?php echo u2p_h($T['share_desc']); ?>
    </p>
    <?php if ($reward_claim): ?>
      <div class="reward-status" data-claim-id="<?php echo u2p_h($reward_claim['id']); ?>">
        <strong><?php echo u2p_h($T['reward_use_title']); ?></strong>
        <span class="reward-state"><?php echo $reward_claim['status'] === 'sent' ? $reward_sent_label : $reward_processing_label; ?></span>
        <small><?php echo u2p_h(substr($reward_claim['wallet'], 0, 6) . '...' . substr($reward_claim['wallet'], -4)); ?></small>
      </div>
    <?php elseif (!empty($pending['reward_status']) && $pending['reward_status'] !== 'disabled'): ?>
      <div class="reward-status"><strong><?php echo u2p_h($T['reward_generic_title']); ?></strong><span class="reward-state"><?php echo u2p_h($T['reward_applied']); ?></span></div>
    <?php endif; ?>
    <textarea class="sharebox" id="shareText" readonly><?php echo u2p_h($share_text . ' ' . $share_url); ?></textarea>
    <div class="item actions">
      <a class="btn btn-x" href="<?php echo u2p_h(u2p_tweet_intent($share_text, $share_url)); ?>" target="_blank" rel="noopener"><?php echo u2p_h($T['btn_post_x']); ?></a>
      <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopy('shareText')"><?php echo u2p_h($T['btn_copy']); ?></button>
    </div>
    <form method="post" style="margin-top:18px">
      <button type="submit" name="confirm_share" value="1" class="btn"><?php echo u2p_h($T['btn_posted_next']); ?></button>
    </form>
  </div>

<?php elseif ($view === 'result' && $pending): ?>
  <?php $announcement = $pending['announcement']; $blog = $pending['blog']; $posted = $pending['posted']; ?>
  <div class="result">
    <?php if ($reward_claim): ?>
      <div class="reward-status" data-claim-id="<?php echo u2p_h($reward_claim['id']); ?>">
        <strong><?php echo u2p_h($reward_claim['amount']); ?><?php echo u2p_h($T['reward_unit_suffix']); ?></strong>
        <span class="reward-state"><?php echo $reward_claim['status'] === 'sent' ? $reward_sent_label : $reward_processing_label; ?></span>
        <small><?php echo u2p_h(substr($reward_claim['wallet'], 0, 6) . '...' . substr($reward_claim['wallet'], -4)); ?></small>
        <?php if (!empty($reward_claim['tx_hash'])): ?>
          <a href="https://basescan.org/tx/<?php echo rawurlencode($reward_claim['tx_hash']); ?>" target="_blank" rel="noopener"><?php echo u2p_h($T['basescan']); ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($is_history_view): ?>
      <p style="font-size:12.5px;color:var(--abyss-soft);margin-bottom:10px">
        <?php echo u2p_h($T['hist_label']); ?>: <?php echo u2p_h(isset($pending['created_at']) ? $pending['created_at'] : ''); ?> ·
        <?php echo u2p_h($T['hist_srcurl']); ?>: <a href="<?php echo u2p_h($pending['source']['url']); ?>" target="_blank" rel="noopener"><?php echo u2p_h($pending['source']['url']); ?></a> ·
        <a href="history.php"><?php echo u2p_h($T['hist_back']); ?></a>
      </p>
    <?php endif; ?>
    <h2><?php echo u2p_h($T['h2_ann']); ?></h2>
    <div class="item">
      <div class="text" id="ann-text"><?php echo u2p_h($announcement['text']); ?></div>
      <div class="actions">
        <a class="btn btn-x btn-sm" href="<?php echo u2p_h(u2p_tweet_intent($announcement['text'])); ?>" target="_blank" rel="noopener"><?php echo u2p_h($T['btn_x_post']); ?></a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopy('ann-text')"><?php echo u2p_h($T['btn_copy']); ?></button>
      </div>
    </div>

    <h2><?php echo u2p_h($T['h2_blog']); ?></h2>
    <div class="item card blog">
      <h3 id="blog-title"><?php echo u2p_h($blog['title']); ?></h3>
      <div id="blog-body">
        <?php foreach (preg_split('/\n{2,}/', trim($blog['body_markdown'])) as $para): ?>
          <p><?php echo nl2br(u2p_h($para)); ?></p>
        <?php endforeach; ?>
      </div>
      <div class="actions" style="margin-top:12px">
        <a class="btn btn-x btn-sm" href="<?php echo u2p_h(u2p_tweet_intent($blog['title'])); ?>" target="_blank" rel="noopener"><?php echo u2p_h($T['btn_x_post']); ?></a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopyText('blog-copy-src')"><?php echo u2p_h($T['btn_copy']); ?></button>
        <textarea id="blog-copy-src" style="position:absolute;left:-9999px"><?php echo u2p_h($blog['title'] . "\n\n" . $blog['body_markdown']); ?></textarea>
      </div>
    </div>

    <h2><?php echo u2p_h($T['h2_posted']); ?></h2>
    <?php foreach ($posted as $i => $p): ?>
      <div class="item">
        <div class="actions">
          <strong style="min-width:150px;display:inline-block"><?php echo u2p_h($p['label']); ?></strong>
          <span class="status <?php echo !empty($p['ok']) ? 'ok' : 'ng'; ?>"><?php echo !empty($p['ok']) ? u2p_h($T['status_ok']) : u2p_h($T['status_ng']); ?></span>
          <?php if (!empty($p['ok']) && $p['url'] !== ''): ?>
            <a href="<?php echo u2p_h($p['url']); ?>" target="_blank" rel="noopener" id="url-<?php echo $i; ?>"><?php echo u2p_h($p['url']); ?></a>
            <a class="btn btn-x btn-sm" href="<?php echo u2p_h(u2p_tweet_intent($p['label'] . ': ', $p['url'])); ?>" target="_blank" rel="noopener"><?php echo u2p_h($T['btn_x_post']); ?></a>
            <button type="button" class="btn btn-ghost btn-sm" onclick="u2pCopy('url-<?php echo $i; ?>')"><?php echo u2p_h($T['btn_copy']); ?></button>
          <?php elseif (empty($p['ok'])): ?>
            <span style="font-size:12.5px;color:var(--abyss-soft)"><?php echo u2p_h(isset($p['error']) ? $p['error'] : ''); ?></span>
          <?php else: ?>
            &mdash;
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p style="margin-top:24px">
      <a href="url2pub.php?reset=1" class="btn btn-ghost"><?php echo u2p_h($T['btn_another']); ?></a>
      <a href="history.php" class="btn btn-ghost"><?php echo u2p_h($T['btn_history']); ?></a>
    </p>
  </div>
<?php endif; ?>

<?php if (!empty($auth['is_admin'])): ?>
  <section class="block" style="margin-top:40px">
    <h2><?php echo u2p_h($T['admin_h2']); ?></h2>
    <div class="hist-card" style="padding:0;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--panel)">
            <th style="text-align:left;padding:10px 14px"><?php echo u2p_h($T['th_user']); ?></th>
            <th style="text-align:left;padding:10px 14px"><?php echo u2p_h($T['th_url']); ?></th>
            <th style="text-align:left;padding:10px 14px"><?php echo u2p_h($T['th_time']); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $all_history = u2p_history_all_users(200); ?>
          <?php if (empty($all_history)): ?>
            <tr><td colspan="3" style="padding:14px;color:var(--abyss-soft)"><?php echo u2p_h($T['admin_empty']); ?></td></tr>
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
  Kurage URL2AI Publisher<?php echo u2p_h($T['footer_product']); ?><a href="https://exbridge.jp/">EXBRIDGE, Inc.</a> ·
  <a href="https://github.com/katsushi2441/url2brain">url2brain</a>(OSS) <?php echo u2p_h($T['footer_brain']); ?> ·
  <a href="https://kfreqai.exbridge.jp/">kfreqai</a> · <a href="https://kfxai.exbridge.jp/">kfxai</a> ·
  <a href="https://kcbrain.exbridge.jp/">kcbrain</a> · <a href="https://kfxbrain.exbridge.jp/">kfxbrain</a>
  <br><br>
  &copy; <?php echo date('Y'); ?> EXBRIDGE, Inc. Developed by <a href="https://x.com/xb_bittensor" target="_blank" rel="noopener">bittensorman</a> ·
  <a href="https://exbridge.jp/contact.php"><?php echo u2p_h($T['footer_contact']); ?></a>
</div></footer>

<script>
var T = <?php echo json_encode($T['js'], JSON_UNESCAPED_UNICODE); ?>;
function u2pCopy(id) {
  var el = document.getElementById(id);
  var text = el.tagName === 'TEXTAREA' ? el.value : el.textContent;
  navigator.clipboard.writeText(text).then(function () { alert(T.copied); });
}
function u2pCopyText(id) {
  var el = document.getElementById(id);
  navigator.clipboard.writeText(el.value).then(function () { alert(T.copied); });
}

var u2pForm = document.getElementById('u2pForm');
if (u2pForm) {
  var STEPS = [
    { key: 'analyze', label: T.st_analyze },
    { key: 'announcement', label: T.st_announcement },
    { key: 'blog', label: T.st_blog },
    { key: 'post-bluesky', label: T.st_bluesky },
    { key: 'post-hatena-bookmark', label: T.st_hbm },
    { key: 'post-aixsns', label: T.st_aixsns },
    { key: 'post-bludit', label: T.st_bludit },
    { key: 'post-hatena-blog', label: T.st_hblog },
    { key: 'reward', label: T.st_reward }
  ];
  var PLATFORMS = [
    { key: 'bluesky', label: T.pl_bluesky },
    { key: 'hatena-bookmark', label: T.pl_hbm },
    { key: 'aixsns', label: T.pl_aixsns },
    { key: 'bludit', label: T.pl_bludit },
    { key: 'hatena-blog', label: T.pl_hblog }
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
    if (walletState) walletState.textContent = address ? shortWallet(address) + ' / Base' : T.wallet_unconnected;
    if (walletButton) walletButton.textContent = address ? T.wallet_connected : T.wallet_connect;
  }
  async function connectWallet() {
    if (!window.ethereum) throw new Error(T.wallet_need);
    var accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    if (!accounts || !accounts[0]) throw new Error(T.wallet_notfound);
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
    // ウォレットは任意(2026-07-29): 特典を受け取りたい人だけ接続。未検出・拒否でも配信は進める。
    if (walletInput && !wallet && window.ethereum) {
      try { wallet = await connectWallet(); }
      catch (walletError) { wallet = ''; }
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
      if (!d.ok) { throw new Error(d.error || T.err_analyze); }
      u2pMark('analyze', 'ok');
      runId = d.run_id;
      var source = d.source;
      return callAjax('announcement', null, { run_id: runId, source: source }).then(function (d2) {
        if (!d2.ok) { throw new Error(d2.error || T.err_announcement); }
        u2pMark('announcement', 'ok');
        var announcement = d2.announcement;
        return callAjax('blog', null, { run_id: runId, source: source }).then(function (d3) {
          if (!d3.ok) { throw new Error(d3.error || T.err_blog); }
          u2pMark('blog', 'ok');
          var blog = d3.blog;
          var posted = [];
          var chain = Promise.resolve();
          PLATFORMS.forEach(function (p) {
            chain = chain.then(function () {
              return callAjax('post', p.key, { run_id: runId, source: source, announcement: announcement, blog: blog }).then(function (dp) {
                u2pMark('post-' + p.key, dp.ok ? 'ok' : 'ng', dp.ok ? '' : (dp.error || T.err_failed));
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
      if (!dfin || !dfin.ok) { throw new Error(T.err_save); }
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
      var labels = { sent: T.rw_sent, failed: T.rw_confirming, enqueue_failed: T.rw_waiting, queued: T.rw_waiting, processing: T.rw_processing, pending: T.rw_waiting };
      if (state) state.textContent = labels[data.reward.status] || data.reward.status;
      if (data.reward.status === 'sent') {
        clearInterval(timer);
        if (data.reward.tx_url && !el.querySelector('a')) {
          var link = document.createElement('a'); link.href = data.reward.tx_url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = T.basescan; el.appendChild(link);
        }
      }
    }).catch(function () {});
  }, 5000);
});
</script>

</body>
</html>
