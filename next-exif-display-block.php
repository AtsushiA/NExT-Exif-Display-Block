<?php
/**
 * Plugin Name:       NExT Exif Display Block
 * Description:       ページ内の画像を指定して、その画像の Exif 情報（カメラ・レンズ・F値・シャッター速度など）を表示するブロックを追加します。
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            NExT-Season
 * Author URI:        https://next-season.net/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       next-exif-display-block
 *
 * @package NextExifDisplayBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセスされた場合は終了する.
}

require_once __DIR__ . '/includes/class-exif-formatter.php';
require_once __DIR__ . '/includes/class-exif-reader.php';

/**
 * ブロックを登録する。
 *
 * @return void
 */
function next_exif_display_block_init() {
	register_block_type( __DIR__ . '/build/' );
}
add_action( 'init', 'next_exif_display_block_init' );

/**
 * 添付ファイルのメタデータ更新時（画像編集・サムネイル再生成など）に Exif キャッシュを破棄する。
 *
 * @param array $data          添付ファイルのメタデータ.
 * @param int   $attachment_id 添付ファイル ID.
 * @return array
 */
function next_exif_display_block_flush_cache( $data, $attachment_id ) {
	\NextExifDisplayBlock\Exif_Reader::delete_cache( (int) $attachment_id );
	return $data;
}
add_filter( 'wp_update_attachment_metadata', 'next_exif_display_block_flush_cache', 10, 2 );
