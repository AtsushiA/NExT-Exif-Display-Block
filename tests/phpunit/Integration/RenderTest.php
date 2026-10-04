<?php
/**
 * next/exif-display-block のブロック登録・Exif 取得・レンダリングの Integration テスト。
 *
 * @package NextExifDisplayBlock
 */

namespace NextExifDisplayBlock\Tests\Integration;

use NextExifDisplayBlock\Exif_Reader;
use WP_UnitTestCase;

/**
 * render.php の出力と Exif_Reader のキャッシュ動作を検証するテスト.
 */
class RenderTest extends WP_UnitTestCase {

	/**
	 * Exif 付き画像の添付ファイル ID.
	 *
	 * @var int
	 */
	private $exif_id;

	/**
	 * Exif なし画像の添付ファイル ID.
	 *
	 * @var int
	 */
	private $no_exif_id;

	/**
	 * Fixture 画像をアップロードする.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );

		$fixtures         = dirname( __DIR__, 2 ) . '/fixtures';
		$this->exif_id    = self::factory()->attachment->create_upload_object( $fixtures . '/exif-sample.jpg' );
		$this->no_exif_id = self::factory()->attachment->create_upload_object( $fixtures . '/no-exif.jpg' );
	}

	/**
	 * アップロードした画像を削除する.
	 *
	 * @return void
	 */
	public function tear_down() {
		wp_delete_attachment( $this->exif_id, true );
		wp_delete_attachment( $this->no_exif_id, true );
		parent::tear_down();
	}

	/**
	 * ブロックをレンダリングする.
	 *
	 * @param array $attrs ブロック属性.
	 * @return string
	 */
	private function render( array $attrs ) {
		return render_block(
			array(
				'blockName'    => 'next/exif-display-block',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * ブロックが登録されていることを確認する.
	 *
	 * @return void
	 */
	public function test_block_is_registered(): void {
		$this->assertTrue(
			\WP_Block_Type_Registry::get_instance()->is_registered( 'next/exif-display-block' )
		);
	}

	/**
	 * image_meta と exif_read_data の両方から取得し、GPS は含まないことを確認する.
	 *
	 * @return void
	 */
	public function test_reader_merges_image_meta_and_file_exif_without_gps(): void {
		$data = Exif_Reader::get( $this->exif_id );

		$this->assertSame( 'Canon', $data['make'] );
		$this->assertSame( 'Canon EOS R5', $data['model'] );
		$this->assertSame( 'RF50mm F1.8 STM', $data['lens'] );
		$this->assertEquals( 1.8, $data['aperture'] );
		$this->assertEquals( 50, $data['focal_length'] );
		$this->assertEquals( 75, $data['focal_length_35'] );
		$this->assertSame( 100, $data['iso'] );
		$this->assertSame( 3, $data['exposure_program'] );
		$this->assertSame( gmmktime( 14, 32, 0, 10, 3, 2026 ), $data['created_timestamp'] );

		$serialized = wp_json_encode( get_post_meta( $this->exif_id, Exif_Reader::META_KEY, true ) );
		$this->assertStringNotContainsStringIgnoringCase( 'gps', $serialized );
		$this->assertStringNotContainsString( '139', $serialized );
	}

	/**
	 * 既定の項目がテーブルレイアウトで出力されることを確認する.
	 *
	 * @return void
	 */
	public function test_render_table_layout_with_default_fields(): void {
		$html = $this->render( array( 'attachmentId' => $this->exif_id ) );

		$this->assertStringContainsString( 'wp-block-next-exif-display-block--table', $html );
		$this->assertStringContainsString( '<dl', $html );
		$this->assertStringContainsString( 'Canon EOS R5', $html );
		$this->assertStringNotContainsString( 'Canon Canon', $html );
		$this->assertStringContainsString( 'RF50mm F1.8 STM', $html );
		$this->assertStringContainsString( '50mm', $html );
		$this->assertStringContainsString( 'f/1.8', $html );
		$this->assertStringContainsString( '1/200s', $html );
		$this->assertStringContainsString( 'ISO 100', $html );
		$this->assertStringContainsString( '2026-10-03 14:32', $html );
		// 既定 OFF の項目は出力されない.
		$this->assertStringNotContainsString( '絞り優先', $html );
		$this->assertStringNotContainsString( '35mm換算', $html );
	}

	/**
	 * 位置情報が出力に含まれないことを確認する（全項目 ON でも）.
	 *
	 * @return void
	 */
	public function test_render_never_outputs_gps(): void {
		$fields = array_fill_keys( \NextExifDisplayBlock\Exif_Formatter::FIELDS, true );
		$html   = $this->render(
			array(
				'attachmentId' => $this->exif_id,
				'fields'       => $fields,
			)
		);

		$this->assertStringContainsString( '絞り優先', $html );
		$this->assertStringContainsString( '+0.7EV', $html );
		$this->assertStringContainsString( '75mm（35mm換算）', $html );
		$this->assertStringNotContainsString( '139', $html );
		$this->assertStringNotContainsString( '35/1', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'gps', $html );
	}

	/**
	 * インラインレイアウトでは区切り文字でつなぎ、ラベル OFF のときは読み上げ用に残す.
	 *
	 * @return void
	 */
	public function test_render_inline_layout_with_separator_and_hidden_labels(): void {
		$html = $this->render(
			array(
				'attachmentId' => $this->exif_id,
				'layout'       => 'inline',
				'showLabels'   => false,
				'separator'    => ' | ',
			)
		);

		$this->assertStringContainsString( 'wp-block-next-exif-display-block--inline', $html );
		$this->assertStringContainsString( 'is-labels-hidden', $html );
		$this->assertStringContainsString( '>' . ' | ' . '</span>', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
	}

	/**
	 * アイコン付きリストでは SVG アイコンが装飾として出力される.
	 *
	 * @return void
	 */
	public function test_render_list_layout_outputs_icons(): void {
		$html = $this->render(
			array(
				'attachmentId' => $this->exif_id,
				'layout'       => 'list',
			)
		);

		$this->assertStringContainsString( '<ul', $html );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	/**
	 * 区切り文字に含まれるタグはエスケープ・除去される.
	 *
	 * @return void
	 */
	public function test_render_strips_tags_from_separator(): void {
		$html = $this->render(
			array(
				'attachmentId' => $this->exif_id,
				'layout'       => 'inline',
				'separator'    => '<script>alert(1)</script>/',
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * 何も出力しないケース.
	 *
	 * @return void
	 */
	public function test_render_outputs_nothing_when_unavailable(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame( '', trim( $this->render( array( 'attachmentId' => 0 ) ) ) );
		$this->assertSame( '', trim( $this->render( array( 'attachmentId' => $this->no_exif_id ) ) ) );
		$this->assertSame( '', trim( $this->render( array( 'attachmentId' => $post_id ) ) ) );
		$this->assertSame( '', trim( $this->render( array( 'attachmentId' => 999999 ) ) ) );
		// 表示対象の項目をすべて OFF にした場合.
		$this->assertSame(
			'',
			trim(
				$this->render(
					array(
						'attachmentId' => $this->exif_id,
						'fields'       => array_fill_keys( \NextExifDisplayBlock\Exif_Formatter::FIELDS, false ),
					)
				)
			)
		);
	}

	/**
	 * 非公開・下書き・パスワード保護された投稿に属する画像の Exif は、閲覧権限のない人には出力しない.
	 *
	 * @dataProvider protected_parent_provider
	 *
	 * @param array $parent_args 親投稿の引数.
	 * @return void
	 */
	public function test_render_hides_exif_of_images_in_non_public_posts( array $parent_args ): void {
		$parent_id = self::factory()->post->create( $parent_args );
		wp_update_post(
			array(
				'ID'          => $this->exif_id,
				'post_parent' => $parent_id,
			)
		);

		// 未ログインの閲覧者.
		wp_set_current_user( 0 );
		$this->assertSame( '', trim( $this->render( array( 'attachmentId' => $this->exif_id ) ) ) );

		// エディタのプレビューを呼べる寄稿者でも、他人の非公開画像は読めない.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertSame( '', trim( $this->render( array( 'attachmentId' => $this->exif_id ) ) ) );

		// 親投稿を読める管理者には表示する.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'Canon EOS R5', $this->render( array( 'attachmentId' => $this->exif_id ) ) );
	}

	/**
	 * 公開されていない親投稿のデータ.
	 *
	 * @return array
	 */
	public static function protected_parent_provider(): array {
		return array(
			'非公開'         => array( array( 'post_status' => 'private' ) ),
			'下書き'         => array( array( 'post_status' => 'draft' ) ),
			'パスワード保護' => array(
				array(
					'post_status'   => 'publish',
					'post_password' => 'secret',
				),
			),
		);
	}

	/**
	 * 公開済みの投稿に属する画像と、どの投稿にも属さない画像は、未ログインでも表示する.
	 *
	 * @return void
	 */
	public function test_render_shows_exif_of_public_images_to_anonymous(): void {
		wp_set_current_user( 0 );
		$this->assertStringContainsString( 'Canon EOS R5', $this->render( array( 'attachmentId' => $this->exif_id ) ) );

		$parent_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_update_post(
			array(
				'ID'          => $this->exif_id,
				'post_parent' => $parent_id,
			)
		);
		$this->assertStringContainsString( 'Canon EOS R5', $this->render( array( 'attachmentId' => $this->exif_id ) ) );
	}

	/**
	 * 添付ファイルのメタデータ更新でキャッシュが破棄される.
	 *
	 * @return void
	 */
	public function test_cache_is_flushed_on_metadata_update(): void {
		Exif_Reader::get( $this->exif_id );
		$this->assertNotEmpty( get_post_meta( $this->exif_id, Exif_Reader::META_KEY, true ) );

		wp_update_attachment_metadata( $this->exif_id, wp_get_attachment_metadata( $this->exif_id ) );

		$this->assertSame( '', get_post_meta( $this->exif_id, Exif_Reader::META_KEY, true ) );
	}
}
