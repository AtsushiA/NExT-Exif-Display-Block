<?php
/**
 * アンインストール時の後処理。Exif のキャッシュ用 postmeta をすべて削除する。
 *
 * マルチサイトではアンインストールがネットワーク全体で一度だけ実行されるため、全サイト分を削除する。
 *
 * @package NextExifDisplayBlock
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $next_exif_display_block_site_id ) {
		switch_to_blog( $next_exif_display_block_site_id );
		delete_post_meta_by_key( '_next_exif_display_block_data' );
		restore_current_blog();
	}
} else {
	delete_post_meta_by_key( '_next_exif_display_block_data' );
}
