# url2pub — Kurage URL2AI Publisher

**株式会社エクスブリッジ**が運営する Kurage URL2AI Publisher のWebサイト本体(OSS body)。
https://url2ai.exbridge.jp/

URLを1つ渡すと、**Kurageさん**がその記事を読んで考察し、告知文とブログ記事を書き、
5つのメディアへ自動配信します:

- Bluesky (@bittensorman.bsky.social)
- はてなブックマーク
- AIxSNS (aixec.exbridge.jp)
- Kurageブログ (kurage.exbridge.jp/blog, `url2pub`カテゴリ)
- はてなブログ (xb-bittensor.hatenablog.com)

解析・記事生成・各媒体への投稿ロジックはすべて[url2brain](https://github.com/katsushi2441/url2brain)
が担う。url2pub自身はURL入力フォーム・結果表示・url2brainへのAPI呼び出しのみを行う薄いPHPページ。

### ページ構成

- `/`(`index.html`)・`/url2pub.html` — プロジェクト説明のランディングページ(英語/日本語)
- `/url2pub.php` — 実際に使うツール本体(Xログイン必須。旧`index.php`から改名)
- `/history.php` — ログインユーザー本人の配信履歴一覧

## デプロイ

```bash
bash scripts/deploy.sh
```

`public/config.php`(git管理外)を先に用意すること。`public/config.php.example`をコピーして使う。

### url2brainへの接続モード

`URL2BRAIN_API_BASE`の向け先で2通り選べる:

1. **OSS既定: 公開x402エンドポイント経由**(`https://bittensorman.xyz/url2brain`、トークン不要)
   1コール$1.00 USDC(Base network)の従量課金。url2pub自身はx402の署名/支払いロジックを
   持たないので、手前にx402対応のHTTPクライアント/プロキシ(例:
   [x402-fetch](https://github.com/coinbase/x402)、`bankr x402 call`)を立てて、
   あなた自身のウォレットで支払う必要がある。解析・告知文・ブログ記事生成に加えて
   `/post/*`(Bluesky/はてな/AIxSNS/Bludit/はてなブログ)も公開されているが、これは
   **Kurage/EXBRIDGE自身のアカウント**へ実際に投稿するもの(x402決済を投稿の許可として扱う設計、
   投稿文はKurage/bittensormanペルソナで自動枠付け)。あなた自身のアカウントへの投稿ではない。
2. **自前運用: 自分のurl2brainインスタンス**(ローカルGemma4等、無料)
   [url2brain](https://github.com/katsushi2441/url2brain)を自分で立て、
   `URL2BRAIN_API_BASE`と`URL2BRAIN_API_TOKEN`をそちらに向ける。x402は経由しないので無料。
   `/post/*`も含めフル機能が使える(各媒体の認証情報は別途自分で設定が必要)。

本番(https://url2ai.exbridge.jp/)はモード2で運用しており、EXBRIDGEが自前運用する
url2brain(ローカルGemma4)に直結している。

## 実測(2026-07-21)

本番(https://url2ai.exbridge.jp/)でURL投入→解析→5媒体配信のEnd-to-Endを確認済み:
- `https://kcbrain.exbridge.jp/kcbrain.html` を投入 → 16秒で告知文・ブログ記事を生成
- Bluesky/AIxSNS/Kurageブログ/はてなブログの4媒体で実投稿・実確認済み
- はてなブックマークのみ、OAuth認証情報が未設定のため失敗(既知の状態、url2brain側の課題)
