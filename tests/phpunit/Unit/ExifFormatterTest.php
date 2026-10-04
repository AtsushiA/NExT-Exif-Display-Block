<?php
/**
 * Exif_Formatter の Unit テスト。
 *
 * @package NextExifDisplayBlock
 */

namespace NextExifDisplayBlock\Tests\Unit;

use Brain\Monkey\Functions;
use NextExifDisplayBlock\Exif_Formatter;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * 値の整形処理を検証するテスト.
 */
class ExifFormatterTest extends TestCase {

	/**
	 * 翻訳関数と wp_date をスタブする.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\when( 'wp_date' )->alias(
			static function ( $format, $timestamp ) {
				return gmdate( $format, $timestamp );
			}
		);
	}

	/**
	 * シャッター速度の整形.
	 *
	 * @dataProvider shutter_speed_provider
	 *
	 * @param mixed  $input    入力（秒）.
	 * @param string $expected 期待値.
	 * @return void
	 */
	public function test_shutter_speed( $input, $expected ): void {
		$this->assertSame( $expected, Exif_Formatter::shutter_speed( $input ) );
	}

	/**
	 * シャッター速度のデータ.
	 *
	 * @return array
	 */
	public static function shutter_speed_provider(): array {
		return array(
			'1/200'      => array( 0.005, '1/200s' ),
			'1/8000'     => array( 0.000125, '1/8000s' ),
			'1/3'        => array( 0.3333, '1/3s' ),
			'0.4 は小数' => array( 0.4, '0.4s' ),
			'1 秒'       => array( 1, '1s' ),
			'1.3 秒'     => array( 1.3, '1.3s' ),
			'30 秒'      => array( '30', '30s' ),
			'0'          => array( 0, '' ),
			'null'       => array( null, '' ),
		);
	}

	/**
	 * F値・焦点距離・ISO の整形.
	 *
	 * @return void
	 */
	public function test_aperture_focal_length_iso(): void {
		$this->assertSame( 'f/1.8', Exif_Formatter::aperture( 1.8 ) );
		$this->assertSame( 'f/8', Exif_Formatter::aperture( '8.00' ) );
		$this->assertSame( 'f/5.6', Exif_Formatter::aperture( 5.6 ) );
		$this->assertSame( '', Exif_Formatter::aperture( 0 ) );
		$this->assertSame( '50mm', Exif_Formatter::focal_length( 50 ) );
		$this->assertSame( '4.3mm', Exif_Formatter::focal_length( 4.25 ) );
		$this->assertSame( 'ISO 100', Exif_Formatter::iso( '100' ) );
		$this->assertSame( '', Exif_Formatter::iso( 0 ) );
	}

	/**
	 * カメラ名の重複除去.
	 *
	 * @return void
	 */
	public function test_camera(): void {
		$this->assertSame( 'Canon EOS R5', Exif_Formatter::camera( 'Canon', 'Canon EOS R5' ) );
		$this->assertSame( 'NIKON Z 6', Exif_Formatter::camera( 'NIKON CORPORATION', 'NIKON Z 6' ) );
		$this->assertSame( 'SONY ILCE-7M4', Exif_Formatter::camera( 'SONY', 'ILCE-7M4' ) );
		$this->assertSame( 'ILCE-7M4', Exif_Formatter::camera( '', 'ILCE-7M4' ) );
		$this->assertSame( 'SONY', Exif_Formatter::camera( 'SONY', '' ) );
		$this->assertSame( '', Exif_Formatter::camera( '', '' ) );
	}

	/**
	 * 露出補正の整形.
	 *
	 * @return void
	 */
	public function test_exposure_bias(): void {
		$this->assertSame( '+0.7EV', Exif_Formatter::exposure_bias( 2 / 3 ) );
		$this->assertSame( '-1EV', Exif_Formatter::exposure_bias( -1 ) );
		$this->assertSame( '0EV', Exif_Formatter::exposure_bias( 0 ) );
		$this->assertSame( '0EV', Exif_Formatter::exposure_bias( -0.01 ) );
		$this->assertSame( '', Exif_Formatter::exposure_bias( null ) );
	}

	/**
	 * 撮影モード・フラッシュ・ホワイトバランスの変換.
	 *
	 * @return void
	 */
	public function test_enumerations(): void {
		$this->assertSame( '絞り優先', Exif_Formatter::exposure_program( 3 ) );
		$this->assertSame( '', Exif_Formatter::exposure_program( 0 ) );
		$this->assertSame( '発光', Exif_Formatter::flash( 0x19 ) );
		$this->assertSame( '非発光', Exif_Formatter::flash( 16 ) );
		$this->assertSame( 'オート', Exif_Formatter::white_balance( 0 ) );
		$this->assertSame( 'マニュアル', Exif_Formatter::white_balance( 1 ) );
		$this->assertSame( '', Exif_Formatter::white_balance( null ) );
	}

	/**
	 * 撮影日時はタイムゾーン変換せずに書式化する.
	 *
	 * @return void
	 */
	public function test_date_time(): void {
		$timestamp = gmmktime( 14, 32, 0, 10, 3, 2026 );
		$this->assertSame( '2026-10-03 14:32', Exif_Formatter::date_time( $timestamp, 'Y-m-d H:i' ) );
		$this->assertSame( '', Exif_Formatter::date_time( 0, 'Y-m-d H:i' ) );
	}

	/**
	 * 有理数表記の解釈.
	 *
	 * @return void
	 */
	public function test_parse_rational(): void {
		$this->assertSame( 0.005, Exif_Formatter::parse_rational( '1/200' ) );
		$this->assertSame( -1.0, Exif_Formatter::parse_rational( '-3/3' ) );
		$this->assertSame( 2.5, Exif_Formatter::parse_rational( '2.5' ) );
		$this->assertNull( Exif_Formatter::parse_rational( '1/0' ) );
		$this->assertNull( Exif_Formatter::parse_rational( 'abc' ) );
	}

	/**
	 * build_items は既定の項目を表示順に返し、空の値と OFF の項目を除く.
	 *
	 * @return void
	 */
	public function test_build_items_uses_defaults_and_order(): void {
		$data  = array(
			'make'          => 'Canon',
			'model'         => 'Canon EOS R5',
			'lens'          => '',
			'aperture'      => 1.8,
			'shutter_speed' => 0.005,
			'iso'           => 100,
			'flash'         => 16,
		);
		$items = Exif_Formatter::build_items( $data, array() );

		$this->assertSame( array( 'camera', 'aperture', 'shutterSpeed', 'iso' ), array_column( $items, 'key' ) );
		$this->assertSame( 'カメラ', $items[0]['label'] );
		$this->assertSame( 'Canon EOS R5', $items[0]['value'] );

		$items = Exif_Formatter::build_items(
			$data,
			array(
				'camera' => false,
				'flash'  => true,
			)
		);
		$this->assertSame( array( 'aperture', 'shutterSpeed', 'iso', 'flash' ), array_column( $items, 'key' ) );
	}

	/**
	 * エディタのプレビューから文字列で届く真偽値を正しく解釈する.
	 *
	 * @return void
	 */
	public function test_resolve_enabled_fields_accepts_string_booleans(): void {
		$resolved = Exif_Formatter::resolve_enabled_fields(
			array(
				'camera' => 'false',
				'lens'   => '0',
				'flash'  => 'true',
				'iso'    => '1',
			)
		);

		$this->assertFalse( $resolved['camera'] );
		$this->assertFalse( $resolved['lens'] );
		$this->assertTrue( $resolved['flash'] );
		$this->assertTrue( $resolved['iso'] );
		$this->assertTrue( $resolved['aperture'] );
		$this->assertFalse( $resolved['whiteBalance'] );
	}
}
