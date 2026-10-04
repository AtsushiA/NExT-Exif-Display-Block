<?php
/**
 * Unit テスト用の PHPUnit bootstrap ファイル。
 *
 * WordPress をロードしない軽量な bootstrap。WordPress 関数は Brain\Monkey でモックする。
 *
 * @package NextExifDisplayBlock
 */

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

require_once dirname( __DIR__, 3 ) . '/includes/class-exif-formatter.php';
