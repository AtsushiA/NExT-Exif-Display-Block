# NExT Exif Display Block

ページ内の画像を指定して、その画像の撮影情報（Exif）を表示する WordPress ブロックプラグインです。

| 項目 | 内容 |
|------|------|
| Contributors | NExT-Season |
| Tags | block, exif, photo, camera, image |
| Requires at least | 6.5 |
| Tested up to | 7.1 |
| Requires PHP | 7.4 |
| Stable tag | 0.1.0 |
| License | GPLv2 or later |

## Description

投稿・固定ページに配置した画像の Exif（カメラ・レンズ・F値・シャッター速度など）を、ブロックとして表示します。画像ブロックの直後に置くだけで、その画像の撮影情報が表示されます。

### 主な機能

- **画像の指定**: 既定では直前の画像ブロック（ギャラリーの場合は最後の画像）を自動で対象にします。設定パネルで、ページ内の任意の画像に切り替えることもできます。
- **表示項目**: 次の 12 項目から、表示するものを項目ごとに選べます。
- **レイアウト**: テーブル / インライン（1 行表示）/ アイコン付きリスト の 3 種類から選べます。
- **表示設定**: ラベルの表示・非表示、インライン表示の区切り文字を設定できます。
- **デザイン**: 色・タイポグラフィ・余白・枠線は、ブロックの標準機能でカスタマイズできます。

| 項目 | 既定 | 表示例 |
|------|------|--------|
| カメラ | 表示 | Canon EOS R5 |
| レンズ | 表示 | RF50mm F1.8 STM |
| 焦点距離 | 表示 | 50mm |
| 35mm換算焦点距離 | 非表示 | 75mm（35mm換算） |
| F値 | 表示 | f/1.8 |
| シャッター速度 | 表示 | 1/200s |
| ISO感度 | 表示 | ISO 100 |
| 露出補正 | 非表示 | +0.7EV |
| 撮影モード | 非表示 | 絞り優先 |
| フラッシュ | 非表示 | 非発光 |
| ホワイトバランス | 非表示 | オート |
| 撮影日時 | 表示 | 2026年10月3日 14:32 |

### 使い方

1. 投稿またはページの編集画面で、画像ブロックの直後に「Exif情報」ブロックを追加します。
2. 直前の画像の Exif が自動で表示されます。
3. 別の画像を対象にしたい場合は、設定パネルの「対象の画像」で「ページ内の画像から選択」を選び、サムネイルから画像を選びます。
4. ツールバーでレイアウトを、設定パネルで表示項目・ラベル・区切り文字を切り替えます。

### プライバシー

- **GPS（位置情報）は取得・保存・表示のいずれも行いません。** 撮影場所が公開されることはありません。
- 非公開・下書き・パスワード保護された投稿に属する画像の撮影情報は、その投稿を閲覧・編集できる人にだけ表示されます。

### 注意事項

- 画像が未選択の場合や、画像に Exif 情報がない場合、フロントエンドでは何も表示されません（エディタ上では理由を表示します）。
- 画像編集ソフトや SNS 向けの書き出しで Exif が削除された画像では、情報を表示できません。
- PHP の Exif 拡張が無効な環境では、WordPress がアップロード時に保存した情報（カメラ・F値・シャッター速度・ISO・焦点距離・撮影日時）のみ表示します。
- 撮影日時は、撮影時のカメラの時刻をそのまま表示します（タイムゾーンの変換はしません）。書式はサイトの「設定 → 一般」の日付・時刻形式に従います。

## Installation

1. [Releases](https://github.com/AtsushiA/NExT-Exif-Display-Block/releases) から最新の `next-exif-display-block-x.y.z.zip` をダウンロードします。
2. WordPress の「プラグイン → 新規追加 → プラグインのアップロード」から zip をアップロードします（または `/wp-content/plugins/next-exif-display-block` に展開します）。
3. 「プラグイン」画面で有効化します。
4. 投稿またはページの編集画面で「Exif情報」ブロックを使用します。

## Frequently Asked Questions

### 表示されない項目があります

その画像の Exif に該当する情報が含まれていない可能性があります。値がない項目は自動的に表示されません。レンズ名などは、カメラや書き出し方法によって記録されないことがあります。

### 位置情報を表示することはできますか？

できません。プライバシー保護のため、GPS 情報は取得・保存・表示のいずれも行わない仕様です。

### 他のプラグインと一緒に使えますか？

エディタのプレビューは、このブロック自身の属性だけをサーバーに送るため、他のプラグインがすべてのブロックに独自の属性を追加する場合（例: wp-ambientcss、wp-data-anim）でも正しく表示されます。

### アンインストールすると何が削除されますか？

このプラグインが画像ごとに保存している Exif のキャッシュ（postmeta `_next_exif_display_block_data`）だけを削除します。マルチサイトでは全サイト分を削除します。投稿内のブロックや画像そのものは削除されません。

## 開発

仕様の詳細は [SPEC.md](SPEC.md) を参照してください。

### 必要なもの

- Node.js 20 以上
- PHP 8.3 以上 + Composer（開発ツールの実行用。プラグイン自体は PHP 7.4 以上で動作します）
- Docker（wp-env を使う場合）

### セットアップ・ビルド

```bash
npm install
composer install
npm run build
```

### 開発時（ファイル変更を監視）

```bash
npm run start
```

### ローカル環境（wp-env）

```bash
npx wp-env start   # 開発: http://localhost:8888 / テスト: http://localhost:8889（admin / password）
npx wp-env stop
```

ポートや PHP バージョンなどの個人設定は `.wp-env.override.json`（Git 管理外）に書きます。

> **注意（macOS）**: macOS のファイルシステムは大文字・小文字を区別しません。wp-env のコンテナ内で `wp-content/plugins/next-exif-display-block` のように**大文字・小文字だけが違うパス**を操作すると、ホスト側のこのフォルダ（`NExT-Exif-Display-Block`）を操作したことになります。コンテナ内でこのようなパスを `rm` しないでください。

### コーディング規約チェック

```bash
composer run phpcs    # PHP（WordPress Coding Standards）
composer run phpcbf   # PHP の自動修正
npm run lint:js
npm run lint:css
```

コミット時には husky + lint-staged により、ステージされた PHP ファイルに phpcs が実行されます。

### PHPUnit テスト

```bash
# Unit テスト（WordPress 非依存、ローカルでそのまま実行可能）
composer run test:unit

# Integration テスト（wp-env の tests コンテナで実行）
npx wp-env run tests-cli --env-cwd=wp-content/plugins/NExT-Exif-Display-Block composer run test:integration
```

- Unit と Integration は**別々のプロセスで**実行してください。Integration 用の bootstrap で Unit テストを動かすと、WordPress が定義済みの `__()` などを Brain Monkey が差し替えられず失敗します（CI も分けて実行しています）。
- Integration テストは wp-env の tests サイトと同じ DB を初期化します。続けて E2E を実行する場合は、先に `npx wp-env clean tests` を実行してください。

### E2E テスト（Playwright）

```bash
npx playwright install chromium
npm run test:e2e
```

ログイン状態は `artifacts/storage-states/admin.json` に保存され、実行のたびに作り直されます。

テスト用画像（`tests/fixtures/*.jpg`、GPS を含む Exif 付き）は `php tests/fixtures/generate-fixtures.php` で再生成できます（GD 拡張が必要）。

### CI / リリース

- **CI**（`.github/workflows/ci.yml`）: `main` への push と pull request で、phpcs・PHPUnit（WordPress 最新 + 1 世代前 × PHP 8.3 / 8.4）・Plugin Check・E2E を実行します。
- **リリース**（`.github/workflows/release.yml`）: `0.1.0` 形式のタグを push すると、プラグインヘッダーのバージョンと一致するか確認したうえで、配布用 zip を作成して GitHub Release に添付します。

```bash
git tag 0.1.0
git push origin 0.1.0
```

### ファイル構成

```
NExT-Exif-Display-Block/
├── next-exif-display-block.php      # プラグイン本体（ブロック登録・キャッシュ破棄）
├── uninstall.php                    # アンインストール時のキャッシュ削除
├── includes/
│   ├── class-exif-reader.php        # Exif の取得・正規化・閲覧権限の判定・キャッシュ
│   └── class-exif-formatter.php     # 表示用の整形
├── src/                             # ブロックのソース
│   ├── block.json
│   ├── index.js
│   ├── edit.js                      # エディタ UI・画像の自動判定
│   ├── preview.js                   # エディタ内プレビュー
│   ├── render.php                   # フロント・プレビューの描画
│   ├── style.scss
│   └── editor.scss
├── build/                           # ビルド成果物（npm run build）
├── tests/
│   ├── phpunit/                     # Unit / Integration テスト
│   ├── e2e/                         # Playwright テスト
│   └── fixtures/                    # テスト用画像と生成スクリプト
├── bin/install-wp-tests.sh          # CI 用 WordPress テストスイートのインストーラー
├── SPEC.md                          # 仕様書
└── readme.txt                       # WordPress.org 形式の readme
```

### 実装方針

- **動的ブロック**: 表示は `render.php` でサーバー側に描画します。Exif の値は画像の差し替えや再生成に追従します。
- **Exif の取得**: WordPress がアップロード時に保存した `image_meta` を基本にします。レンズ名などの不足分はオリジナル画像から `exif_read_data` で補い、結果は postmeta にキャッシュします。画像の編集・再生成時にはキャッシュを破棄します。
- **画像の自動判定**: フロントの描画では隣のブロックを参照できないため、エディタで直前の画像を判定し、その ID をブロックの属性に保存します。
- **プレビュー**: コアの `ServerSideRender` は使いません。他のプラグインが追加した属性まで送ってしまいエラーになるため、このブロック自身の属性だけを block-renderer エンドポイントに送る独自のプレビューを使います。

## Changelog

### 0.1.0

- 初回リリース

## License

GPLv2 or later. 詳細は [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html) を参照してください。
