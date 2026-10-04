# NExT Exif Display Block 仕様書

- 種別: WordPress プラグイン（参考: [Exif Details](https://ja.wordpress.org/plugins/exif-details/)）
- プラグイン名称: **NExT Exif Display Block**
- 概要: **挿入されたページにある画像を指定して、その画像の Exif 情報を表示するブロック**

## 実装仕様（決定事項）

- ブロック名: `next/exif-display-block`（既存 NExT プラグインの `next/` 名前空間に合わせる）
- テキストドメイン: `next-exif-display-block`
- 動作要件: WordPress 6.5 以上 / PHP 7.4 以上
- 動的ブロック（`render.php` で描画し、`save` は `null`）
- 構成は `NExT-Custom-Field-link-Button-Block` を踏襲する（`@wordpress/scripts`、phpcs、PHPUnit、Playwright、wp-env、husky、GitHub Actions）

| 項目 | 決定内容 |
|---|---|
| Exif 取得元 | WP が保存済みの `image_meta` を基本にし、オリジナル画像を `exif_read_data` で読んで補完（結果は postmeta にキャッシュ） |
| 画像の指定 | 既定は「直前の画像」を自動で対象にする。サイドバーでページ内の任意の画像に切り替えられる |
| 表示項目 | 項目ごとに ON/OFF できる（並び順は固定）。ラベルの表示切替あり |
| レイアウト | テーブル / インライン / アイコン付きリスト の3種から選択 |
| GPS（位置情報） | **取得も表示もしない**（プライバシー保護） |
| Exif なし・未選択・削除済み | フロントでは何も出力しない。エディタ上でのみ警告を表示する |
| 値の手動上書き | 行わない（取得した値のみを表示する） |

### 属性

| 属性 | 型 | 既定値 | 説明 |
|---|---|---|---|
| `source` | string (`auto` \| `manual`) | `auto` | 直前の画像を自動で選ぶ / 手動で選択する |
| `attachmentId` | number | `0` | 対象画像の添付ファイル ID。`auto` のときもエディタが自動で書き込む |
| `layout` | string (`table` \| `inline` \| `list`) | `table` | 表示レイアウト |
| `showLabels` | boolean | `true` | 項目ラベルを表示するか |
| `separator` | string | `" / "` | `inline` レイアウトの区切り文字 |
| `fields` | object | 下表 | 項目ごとの表示 ON/OFF |

#### 表示項目（`fields`、この順で表示）

| キー | ラベル | 既定 | 取得元 | 表示例 |
|---|---|---|---|---|
| `camera` | カメラ | ON | `image_meta.camera` / Exif `Make`＋`Model` | Canon EOS R5 |
| `lens` | レンズ | ON | Exif `LensModel`（無い場合は `UndefinedTag:0xA434`） | RF50mm F1.8 STM |
| `focalLength` | 焦点距離 | ON | `image_meta.focal_length` | 50mm |
| `focalLength35` | 35mm換算焦点距離 | OFF | Exif `FocalLengthIn35mmFilm` | 75mm（35mm換算） |
| `aperture` | F値 | ON | `image_meta.aperture` | f/1.8 |
| `shutterSpeed` | シャッター速度 | ON | `image_meta.shutter_speed` | 1/200s |
| `iso` | ISO感度 | ON | `image_meta.iso` | ISO 100 |
| `exposureBias` | 露出補正 | OFF | Exif `ExposureBiasValue` | +0.7EV |
| `exposureProgram` | 撮影モード | OFF | Exif `ExposureProgram` | 絞り優先 |
| `flash` | フラッシュ | OFF | Exif `Flash` | 非発光 |
| `whiteBalance` | ホワイトバランス | OFF | Exif `WhiteBalance` | オート |
| `dateTime` | 撮影日時 | ON | Exif `DateTimeOriginal` / `image_meta.created_timestamp` | 2026年10月3日 14:32 |

ブロックサポート: `anchor`, `className`, `color`, `typography`, `spacing`, `__experimentalBorder`

### 画像の解決（エディタ）

- **ページ内の画像一覧**: `core/block-editor` の `getBlocks()` を再帰的にたどり、`id` を持つ `core/image`（`core/gallery` 内の画像も含む）を集める。
- **auto（直前の画像）**: 同じ親ブロックの中で、自ブロックより前にある兄弟を後ろから順に探す。最初に見つかった `core/image`、または `core/gallery` の最後の画像を対象にする。ブロックの移動や画像の差し替えを監視し、対象が変わったら `attachmentId` を自動で更新する（フロントの描画では兄弟ブロックを参照できないため、ID を属性に保存しておく）。
- **manual（手動選択）**: サイドバーに、ページ内の画像だけをサムネイル付きで一覧表示して選ばせる。選んだ画像がページから消えた場合はエディタで警告する（ID が有効な画像であればフロントでの表示は続ける）。
- **プレビュー**: コアの block-renderer エンドポイントで `render.php` の出力を取得し、エディタに表示する（`src/preview.js`）。送る属性はこのブロック自身の属性（`source`, `attachmentId`, `layout`, `showLabels`, `separator`, `fields`）に限る。コアの `ServerSideRender` は使わない。`ServerSideRender` は送信前に JS 側の全属性の既定値を補うため、他のプラグインが JS 側だけで全ブロックに追加した属性（例: wp-ambientcss の `ambient`、wp-data-anim の `dataAnim` など、サーバー未登録）まで送ってしまい、block-renderer が `rest_invalid_param` で拒否するため。また WordPress 7.1 の `ServerSideRender` は REST のエラーを「[object Object]」と表示してしまうので、自前のプレビューでは実際のエラー文を表示する。出力が空のときは、理由に応じてプレースホルダー（「対象の画像が見つかりません」/「Exif 情報がありません」）を表示する。
- **UI**: ツールバーでレイアウトを切り替える。サイドバーには画像の指定（auto / manual、`RadioControl`）、項目トグル、ラベル表示、区切り文字（inline のときだけ）を置く。

### Exif の取得

1. `wp_attachment_is_image()` で画像の添付ファイルか確認し、画像でなければ取得しない。
2. 基本データとして `wp_get_attachment_metadata()['image_meta']` を読む。
3. 補完として、`wp_get_original_image_path()`（`-scaled` 画像対策）のファイルが存在し、`exif_read_data` が使える JPEG/TIFF であれば、`exif_read_data( $path, null, true )` で上表の追加項目を読む。警告は抑止する。
4. GPS セクションは読み捨て、キャッシュにも保存しない。
5. Exif から取り出した文字列は、NUL 文字・不正な UTF-8・タグを除去し、200 文字までに切り詰める。
6. 正規化した結果は postmeta `_next_exif_display_block_data` に `version` 付きでキャッシュする。`wp_update_attachment_metadata`（画像編集・サムネイル再生成時）でキャッシュを破棄する。
7. Exif 拡張がない環境では、`image_meta` だけで表示を続ける。

### 値の整形

- カメラ: Make＋Model。Model が Make の先頭語で始まる場合は Make を付けない（例: `Canon` + `Canon EOS R5` → `Canon EOS R5`）。
- F値は `f/1.8`。シャッター速度は 1 秒未満なら `1/200s`、1 秒以上なら `2s` や `1.3s`。焦点距離は `50mm`。ISO は `ISO 100`。
- 露出補正は符号付きで `+0.7EV` / `-1EV`（0 のときは `0EV`）。
- 撮影モード・フラッシュ・ホワイトバランスは、Exif の値を日本語ラベルに変換する。
- 撮影日時は、撮影時のローカル時刻をタイムゾーン変換せずに、サイトの日付形式＋時刻形式で表示する。
- 整形処理は副作用のない関数にまとめ、Unit テストできるようにする。
- `fields` の値は真偽値として解釈する（エディタのプレビューでは `"false"` などの文字列で届くため）。

### 出力マークアップとスタイル方針

ラッパーは `get_block_wrapper_attributes()` で出力し、`wp-block-next-exif-display-block--{layout}` の修飾クラスを付ける。

- **table**: `<dl>` 内に `<div><dt>ラベル</dt><dd>値</dd></div>` を並べ、CSS grid で 2 列にする。
- **inline**: `<p>` 内に `<span class="wp-block-next-exif-display-block__item">` を区切り文字でつなぐ。ラベルが ON なら「ラベル: 値」、OFF なら値だけを出す（ラベルは `screen-reader-text` で残す）。項目の途中では折り返さない。
- **list**: `<ul>` の各 `<li>` に、インライン SVG アイコン（`aria-hidden="true"`）＋ラベル＋値を出す。ラベルが OFF のときも、ラベルは `screen-reader-text` で読み上げ用に残す。
- アイコン SVG はプラグイン内に持ち、外部に依存しない。
- 同梱 CSS はレイアウトに必要な最小限にとどめ、色・文字・余白・枠線はブロックサポートに任せる。

### 空値時の挙動

- 値が空の項目は出力しない。
- 次のいずれかに当たる場合、フロントではブロック全体を出力しない（空文字を返す）。
  - `attachmentId` が 0（画像が未選択、または auto で直前に画像がない）
  - 添付ファイルが存在しない、または画像ではない
  - 閲覧者にその画像の閲覧権限がない（下記「閲覧権限」）
  - 表示対象の項目に値が 1 件もない
- エディタでは、上記の理由をプレースホルダーで表示する。

### 閲覧権限

`attachmentId` は投稿者が自由に指定でき、エディタのプレビュー（コアの block-renderer エンドポイント）は `edit_posts` 権限だけで呼べる。他人の非公開画像の撮影情報が漏れないよう、次の条件を満たす場合だけ表示する。

| 画像の状態 | 表示する相手 |
|---|---|
| 親投稿がパスワード保護されていて、閲覧者がパスワードを入力していない | 親投稿を編集できる人（`edit_post`）のみ |
| 親投稿が公開済み、またはどの投稿にも属さない | 全員 |
| 親投稿が非公開・下書き・ゴミ箱など | その画像を読める人（`read_post`）のみ |

公開済み投稿の `read_post` はログインユーザー全員が持つため、パスワード保護の判定には使わない。

### セキュリティ

- 出力はすべて `esc_html` / `esc_attr` / `wp_kses` でエスケープする。
- `attachmentId` は `absint` で整数にし、画像の添付ファイルであることと閲覧権限を確認してから使う。
- `layout` は許可リストで検証する。
- `separator` はタグと改行を取り除き（前後の空白は残す）、エスケープしてから出力する。
- GPS 情報は扱わない（取得・保存・出力のいずれもしない）。
- 独自の REST エンドポイントは追加しない（プレビューはコアの block-renderer を使う）。
- PHP ファイルは `ABSPATH` を確認し、直接アクセスされた場合は終了する。

### アンインストール

- `uninstall.php` で、キャッシュ用の postmeta `_next_exif_display_block_data` をすべて削除する。マルチサイトでは全サイト分を削除する。

### テスト

- PHPUnit Unit: 整形処理（シャッター速度、F値、カメラ名の重複除去、露出補正、日時、文字列の真偽値）
- PHPUnit Integration: Exif（GPS 含む）付きの fixture JPEG で描画結果を確認する。あわせて、GPS が出力されないこと、Exif なし・ID 0・削除済み ID のとき空文字になること、非公開・下書き・パスワード保護の画像が権限のない人に出力されないこと、メタデータ更新でキャッシュが破棄されることを確認する
- Playwright: 画像の直後にブロックを挿入すると自動で対象になること、他のプラグインが JS 側だけで追加した属性があってもプレビューできること、manual への切り替え、3 レイアウトの切り替え、項目トグルがプレビューに反映されることを確認する

### 対象外（スコープ外）

- GPS・位置情報の表示や地図連携
- Exif 値の手動入力・上書き
- 表示項目の並び替え
- ページ外の画像（メディアライブラリから直接選ぶ画像）の指定
- 外部メディア（オフロード先ストレージ）のファイルからの Exif 読み取り（`image_meta` のみで表示する）
- 画像ブロック以外（カバー、メディアとテキスト等）の画像の自動検出
