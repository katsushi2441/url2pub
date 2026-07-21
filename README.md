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

## デプロイ

```bash
bash scripts/deploy.sh
```

`public/config.php`(url2brainのAPIトークンを含む、git管理外)を先に用意すること。
`public/config.php.example`を参考に、`URL2BRAIN_API_BASE`(既定 `http://exbridge.ddns.net:18332`)と
`URL2BRAIN_API_TOKEN`を設定する。

## 実測(2026-07-21)

本番(https://url2ai.exbridge.jp/)でURL投入→解析→5媒体配信のEnd-to-Endを確認済み:
- `https://kcbrain.exbridge.jp/kcbrain.html` を投入 → 16秒で告知文・ブログ記事を生成
- Bluesky/AIxSNS/Kurageブログ/はてなブログの4媒体で実投稿・実確認済み
- はてなブックマークのみ、OAuth認証情報が未設定のため失敗(既知の状態、url2brain側の課題)
