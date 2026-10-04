<?php
/**
 * Exif の正規化済みデータを表示用の文字列に整形するクラス。
 *
 * 副作用を持たない静的メソッドのみで構成し、Unit テストできるようにする。
 *
 * @package NextExifDisplayBlock
 */

namespace NextExifDisplayBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセスされた場合は終了する.
}

/**
 * Exif 値の整形処理.
 */
class Exif_Formatter {

	/**
	 * 表示項目のキー（表示順）.
	 *
	 * @var string[]
	 */
	const FIELDS = array(
		'camera',
		'lens',
		'focalLength',
		'focalLength35',
		'aperture',
		'shutterSpeed',
		'iso',
		'exposureBias',
		'exposureProgram',
		'flash',
		'whiteBalance',
		'dateTime',
	);

	/**
	 * 既定で表示する項目.
	 *
	 * @var string[]
	 */
	const DEFAULT_ENABLED = array(
		'camera',
		'lens',
		'focalLength',
		'aperture',
		'shutterSpeed',
		'iso',
		'dateTime',
	);

	/**
	 * 項目ラベルを返す.
	 *
	 * @param string $key 項目キー.
	 * @return string
	 */
	public static function label( $key ) {
		$labels = array(
			'camera'          => __( 'カメラ', 'next-exif-display-block' ),
			'lens'            => __( 'レンズ', 'next-exif-display-block' ),
			'focalLength'     => __( '焦点距離', 'next-exif-display-block' ),
			'focalLength35'   => __( '35mm換算焦点距離', 'next-exif-display-block' ),
			'aperture'        => __( 'F値', 'next-exif-display-block' ),
			'shutterSpeed'    => __( 'シャッター速度', 'next-exif-display-block' ),
			'iso'             => __( 'ISO感度', 'next-exif-display-block' ),
			'exposureBias'    => __( '露出補正', 'next-exif-display-block' ),
			'exposureProgram' => __( '撮影モード', 'next-exif-display-block' ),
			'flash'           => __( 'フラッシュ', 'next-exif-display-block' ),
			'whiteBalance'    => __( 'ホワイトバランス', 'next-exif-display-block' ),
			'dateTime'        => __( '撮影日時', 'next-exif-display-block' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}

	/**
	 * ブロック属性の fields を、既定値で補った「キー => 表示するか」の配列にする.
	 *
	 * @param mixed $fields ブロック属性の fields.
	 * @return array<string, bool>
	 */
	public static function resolve_enabled_fields( $fields ) {
		$fields   = is_array( $fields ) ? $fields : array();
		$resolved = array();
		foreach ( self::FIELDS as $key ) {
			// エディタのプレビュー（ServerSideRender）ではクエリ文字列経由で "false" などの文字列が届くため、真偽値として解釈する.
			$resolved[ $key ] = array_key_exists( $key, $fields )
				? (bool) filter_var( $fields[ $key ], FILTER_VALIDATE_BOOLEAN )
				: in_array( $key, self::DEFAULT_ENABLED, true );
		}
		return $resolved;
	}

	/**
	 * 表示する項目の一覧を組み立てる。値が空の項目は含めない.
	 *
	 * @param array $data   Exif_Reader が返す正規化済みデータ.
	 * @param mixed $fields ブロック属性の fields.
	 * @param array $args   date_format: 撮影日時の書式.
	 * @return array<int, array{key: string, label: string, value: string}>
	 */
	public static function build_items( array $data, $fields, array $args = array() ) {
		$items = array();
		foreach ( self::resolve_enabled_fields( $fields ) as $key => $enabled ) {
			if ( ! $enabled ) {
				continue;
			}
			$value = self::format_field( $key, $data, $args );
			if ( '' === $value ) {
				continue;
			}
			$items[] = array(
				'key'   => $key,
				'label' => self::label( $key ),
				'value' => $value,
			);
		}
		return $items;
	}

	/**
	 * 1 項目を整形する.
	 *
	 * @param string $key  項目キー.
	 * @param array  $data 正規化済みデータ.
	 * @param array  $args 追加引数.
	 * @return string
	 */
	public static function format_field( $key, array $data, array $args = array() ) {
		$get = static function ( $name ) use ( $data ) {
			return isset( $data[ $name ] ) ? $data[ $name ] : null;
		};

		switch ( $key ) {
			case 'camera':
				return self::camera( (string) $get( 'make' ), (string) $get( 'model' ) );
			case 'lens':
				return trim( (string) $get( 'lens' ) );
			case 'focalLength':
				return self::focal_length( $get( 'focal_length' ) );
			case 'focalLength35':
				$value = self::focal_length( $get( 'focal_length_35' ) );
				/* translators: %s: 35mm 換算の焦点距離（例: 75mm）. */
				return '' === $value ? '' : sprintf( __( '%s（35mm換算）', 'next-exif-display-block' ), $value );
			case 'aperture':
				return self::aperture( $get( 'aperture' ) );
			case 'shutterSpeed':
				return self::shutter_speed( $get( 'shutter_speed' ) );
			case 'iso':
				return self::iso( $get( 'iso' ) );
			case 'exposureBias':
				return self::exposure_bias( $get( 'exposure_bias' ) );
			case 'exposureProgram':
				return self::exposure_program( $get( 'exposure_program' ) );
			case 'flash':
				return self::flash( $get( 'flash' ) );
			case 'whiteBalance':
				return self::white_balance( $get( 'white_balance' ) );
			case 'dateTime':
				$format = isset( $args['date_format'] ) ? (string) $args['date_format'] : 'Y-m-d H:i';
				return self::date_time( $get( 'created_timestamp' ), $format );
		}
		return '';
	}

	/**
	 * カメラ名。Model が Make の先頭語で始まる場合は Make を重ねない.
	 *
	 * @param string $make  メーカー.
	 * @param string $model 機種.
	 * @return string
	 */
	public static function camera( $make, $model ) {
		$make  = trim( $make );
		$model = trim( $model );
		if ( '' === $make ) {
			return $model;
		}
		if ( '' === $model ) {
			return $make;
		}
		$first_word = strtok( $make, ' ' );
		if ( false !== $first_word && 0 === stripos( $model, $first_word ) ) {
			return $model;
		}
		return $make . ' ' . $model;
	}

	/**
	 * 焦点距離（例: 50mm）.
	 *
	 * @param mixed $value 焦点距離（mm）.
	 * @return string
	 */
	public static function focal_length( $value ) {
		if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
			return '';
		}
		return self::trim_number( (float) $value, 1 ) . 'mm';
	}

	/**
	 * F値（例: f/1.8）.
	 *
	 * @param mixed $value F値.
	 * @return string
	 */
	public static function aperture( $value ) {
		if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
			return '';
		}
		return 'f/' . self::trim_number( (float) $value, 1 );
	}

	/**
	 * シャッター速度。1 秒未満は 1/N 秒、1 秒以上は小数 1 桁まで（例: 1/200s, 2s, 1.3s）.
	 *
	 * @param mixed $value 露出時間（秒）.
	 * @return string
	 */
	public static function shutter_speed( $value ) {
		if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
			return '';
		}
		$seconds = (float) $value;
		if ( $seconds < 1 ) {
			$denominator = (int) round( 1 / $seconds );
			// 1/N で表せる値（誤差 5% 以内）は分数で、それ以外（0.4 秒など）は小数で表示する.
			if ( $denominator > 0 && abs( ( 1 / $denominator ) - $seconds ) / $seconds <= 0.05 ) {
				return '1/' . $denominator . 's';
			}
		}
		return self::trim_number( $seconds, 1 ) . 's';
	}

	/**
	 * ISO 感度（例: ISO 100）.
	 *
	 * @param mixed $value ISO 感度.
	 * @return string
	 */
	public static function iso( $value ) {
		if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
			return '';
		}
		return 'ISO ' . (int) $value;
	}

	/**
	 * 露出補正（例: +0.7EV, -1EV, 0EV）.
	 *
	 * @param mixed $value 露出補正値（EV）.
	 * @return string
	 */
	public static function exposure_bias( $value ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		$rounded = round( (float) $value, 1 );
		if ( 0.0 === $rounded || -0.0 === $rounded ) {
			return '0EV';
		}
		return ( $rounded > 0 ? '+' : '-' ) . self::trim_number( abs( $rounded ), 1 ) . 'EV';
	}

	/**
	 * 撮影モード（Exif ExposureProgram）.
	 *
	 * @param mixed $value ExposureProgram の値.
	 * @return string
	 */
	public static function exposure_program( $value ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		$map   = array(
			1 => __( 'マニュアル', 'next-exif-display-block' ),
			2 => __( 'プログラムAE', 'next-exif-display-block' ),
			3 => __( '絞り優先', 'next-exif-display-block' ),
			4 => __( 'シャッター優先', 'next-exif-display-block' ),
			5 => __( 'クリエイティブ', 'next-exif-display-block' ),
			6 => __( 'アクション', 'next-exif-display-block' ),
			7 => __( 'ポートレート', 'next-exif-display-block' ),
			8 => __( '風景', 'next-exif-display-block' ),
		);
		$value = (int) $value;
		return isset( $map[ $value ] ) ? $map[ $value ] : '';
	}

	/**
	 * フラッシュ（Exif Flash のビット 0 = 発光）.
	 *
	 * @param mixed $value Flash の値.
	 * @return string
	 */
	public static function flash( $value ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		return ( (int) $value & 1 )
			? __( '発光', 'next-exif-display-block' )
			: __( '非発光', 'next-exif-display-block' );
	}

	/**
	 * ホワイトバランス（Exif WhiteBalance）.
	 *
	 * @param mixed $value WhiteBalance の値.
	 * @return string
	 */
	public static function white_balance( $value ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		switch ( (int) $value ) {
			case 0:
				return __( 'オート', 'next-exif-display-block' );
			case 1:
				return __( 'マニュアル', 'next-exif-display-block' );
		}
		return '';
	}

	/**
	 * 撮影日時。撮影時のローカル時刻をタイムゾーン変換せずに表示する.
	 *
	 * Exif の日時はタイムゾーンを持たないため、UTC の timestamp として保持し UTC のまま書式化する.
	 *
	 * @param mixed  $timestamp UTC として扱う timestamp.
	 * @param string $format    日付書式.
	 * @return string
	 */
	public static function date_time( $timestamp, $format ) {
		if ( ! is_numeric( $timestamp ) || (int) $timestamp <= 0 ) {
			return '';
		}
		$formatted = wp_date( $format, (int) $timestamp, new \DateTimeZone( 'UTC' ) );
		return is_string( $formatted ) ? $formatted : '';
	}

	/**
	 * Exif の有理数表記（"1/200" など）や数値を float にする.
	 *
	 * @param mixed $value 値.
	 * @return float|null
	 */
	public static function parse_rational( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( preg_match( '#^(-?\d+(?:\.\d+)?)/(-?\d+(?:\.\d+)?)$#', $value, $m ) ) {
			return 0.0 === (float) $m[2] ? null : (float) $m[1] / (float) $m[2];
		}
		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * 小数点以下を指定桁で丸め、末尾の 0 を取り除く.
	 *
	 * @param float $value     値.
	 * @param int   $precision 小数点以下の桁数.
	 * @return string
	 */
	private static function trim_number( $value, $precision ) {
		$formatted = number_format( round( $value, $precision ), $precision, '.', '' );
		return false !== strpos( $formatted, '.' ) ? rtrim( rtrim( $formatted, '0' ), '.' ) : $formatted;
	}
}
