# NExT Exif Display Block

ページ内の画像を指定して、その画像の Exif 情報を表示する WordPress ブロックプラグインです。仕様は [SPEC.md](SPEC.md) を参照してください。

- ブロック名: `next/exif-display-block`
- 動作要件: WordPress 6.5 以上 / PHP 7.4 以上
- GPS（位置情報）は扱いません

## 使い方

1. 画像ブロックの直後に「Exif情報」ブロックを追加すると、その画像の Exif が表示されます。
2. 設定パネルの「対象の画像」で「ページ内の画像から選択」を選ぶと、ページ内の任意の画像を指定できます。
3. ツールバーでレイアウト（テーブル / インライン / アイコン付きリスト）を、設定パネルで表示項目・ラベル・区切り文字を切り替えます。

## 開発

```bash
npm install
composer install
npm run build          # ビルド（build/ に出力）
npm run start          # 監視ビルド
npm run lint:js
npm run lint:css
composer run phpcs
```

### テスト

```bash
# Unit（WordPress 不要）
composer run test:unit

# Integration（wp-env の tests コンテナで実行。Integration 用 bootstrap を使う）
npx wp-env start
npx wp-env run tests-cli --env-cwd=wp-content/plugins/NExT-Exif-Display-Block composer run test:integration

# E2E（Playwright）
npx playwright install chromium
npm run test:e2e
```

- Unit と Integration は**別々のプロセスで**実行してください。Integration 用 bootstrap で Unit テストを動かすと、WordPress が定義済みの `__()` などを Brain Monkey が差し替えられず失敗します（CI も分けて実行しています）。
- Integration テストは wp-env の tests サイトと同じ DB を初期化します。続けて E2E を実行する場合は、先に `npx wp-env clean tests` を実行してください。
- E2E のログイン状態は `artifacts/storage-states/admin.json` に保存され、実行のたびに作り直されます（wp-env を作り直しても古い Cookie で止まりません）。

> **注意（macOS）**: macOS のファイルシステムは大文字・小文字を区別しないため、wp-env のコンテナ内で `wp-content/plugins/next-exif-display-block` のように**大文字・小文字だけが違うパス**を操作すると、ホスト側のこのフォルダ（`NExT-Exif-Display-Block`）を操作したことになります。コンテナ内でこのようなパスを `rm` しないでください。

テスト用画像（`tests/fixtures/*.jpg`）は `php tests/fixtures/generate-fixtures.php` で再生成できます（GD 拡張が必要）。

## 構成

| パス | 内容 |
|---|---|
| `next-exif-display-block.php` | プラグイン本体（ブロック登録・キャッシュ破棄） |
| `includes/class-exif-reader.php` | Exif の取得・正規化・閲覧権限の判定・キャッシュ |
| `includes/class-exif-formatter.php` | 表示用の整形 |
| `src/` | ブロックのソース（`block.json`, `edit.js`, `render.php`, スタイル） |
| `uninstall.php` | アンインストール時のキャッシュ削除 |
