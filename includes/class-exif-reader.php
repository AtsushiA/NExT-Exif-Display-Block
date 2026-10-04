<?php
/**
 * 添付画像の Exif を取得・正規化・キャッシュするクラス。
 *
 * @package NextExifDisplayBlock
 */

namespace NextExifDisplayBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセスされた場合は終了する.
}

/**
 * Exif の取得処理.
 *
 * WordPress がアップロード時に保存する image_meta を基本とし、レンズ名など image_meta に
 * 含まれない項目はオリジナル画像から exif_read_data で補完する。GPS は一切扱わない。
 */
class Exif_Reader {

	/**
	 * キャッシュ用の postmeta キー.
	 *
	 * @var string
	 */
	const META_KEY = '_next_exif_display_block_data';

	/**
	 * キャッシュ形式のバージョン。取得項目を変えたら上げる.
	 *
	 * @var int
	 */
	const CACHE_VERSION = 1;

	/**
	 * Exif を読み取る対象の MIME タイプ.
	 *
	 * @var string[]
	 */
	const READABLE_MIME_TYPES = array( 'image/jpeg', 'image/tiff' );

	/**
	 * Exif から取り出す文字列（カメラ名・レンズ名）の最大文字数.
	 *
	 * @var int
	 */
	const MAX_STRING_LENGTH = 200;

	/**
	 * 添付画像の正規化済み Exif データを返す.
	 *
	 * @param int $attachment_id 添付ファイル ID.
	 * @return array|null 画像の添付ファイルでない場合は null.
	 */
	public static function get( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$cached = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( is_array( $cached ) && isset( $cached['version'], $cached['data'] ) && self::CACHE_VERSION === $cached['version'] && is_array( $cached['data'] ) ) {
			return $cached['data'];
		}

		$data = self::read( $attachment_id );
		update_post_meta(
			$attachment_id,
			self::META_KEY,
			array(
				'version' => self::CACHE_VERSION,
				'data'    => $data,
			)
		);
		return $data;
	}

	/**
	 * 現在の閲覧者に、この画像の Exif を表示してよいかを判定する.
	 *
	 * ブロックの attachmentId は投稿者が自由に指定でき、エディタのプレビュー（block-renderer）は
	 * edit_posts 権限だけで呼べる。非公開・下書き・パスワード保護された投稿に属する画像の撮影情報が
	 * 漏れないよう、公開状態の画像か、閲覧者が読み取り権限を持つ画像に限る.
	 *
	 * @param int $attachment_id 添付ファイル ID.
	 * @return bool
	 */
	public static function is_viewable( $attachment_id ) {
		$attachment = get_post( absint( $attachment_id ) );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		// パスワード保護された投稿に属する画像は、パスワード入力済みか、その投稿を編集できる人に限る.
		// 公開済み投稿の read_post はログインユーザー全員が持つため、ここでは使わない.
		$parent = $attachment->post_parent ? get_post( $attachment->post_parent ) : null;
		if ( $parent && post_password_required( $parent ) ) {
			return current_user_can( 'edit_post', $parent->ID );
		}

		// inherit の添付ファイルは親投稿のステータスに解決される.
		$status = get_post_status( $attachment );
		if ( $status && is_post_status_viewable( $status ) ) {
			return true;
		}

		return current_user_can( 'read_post', $attachment->ID );
	}

	/**
	 * キャッシュを破棄する.
	 *
	 * @param int $attachment_id 添付ファイル ID.
	 * @return void
	 */
	public static function delete_cache( $attachment_id ) {
		if ( $attachment_id > 0 ) {
			delete_post_meta( $attachment_id, self::META_KEY );
		}
	}

	/**
	 * image_meta とファイルの Exif から正規化済みデータを組み立てる.
	 *
	 * @param int $attachment_id 添付ファイル ID.
	 * @return array
	 */
	public static function read( $attachment_id ) {
		$data = self::from_image_meta( $attachment_id );

		foreach ( self::from_file( $attachment_id ) as $key => $value ) {
			if ( null !== $value && '' !== $value ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}

	/**
	 * WordPress が保存済みの image_meta から取得する.
	 *
	 * @param int $attachment_id 添付ファイル ID.
	 * @return array
	 */
	private static function from_image_meta( $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$meta     = is_array( $metadata ) && isset( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] )
			? $metadata['image_meta']
			: array();

		$number = static function ( $key ) use ( $meta ) {
			return isset( $meta[ $key ] ) && is_numeric( $meta[ $key ] ) && (float) $meta[ $key ] > 0 ? (float) $meta[ $key ] : null;
		};

		return array(
			'make'              => '',
			'model'             => isset( $meta['camera'] ) ? self::clean_string( $meta['camera'] ) : '',
			'lens'              => '',
			'focal_length'      => $number( 'focal_length' ),
			'focal_length_35'   => null,
			'aperture'          => $number( 'aperture' ),
			'shutter_speed'     => $number( 'shutter_speed' ),
			'iso'               => null !== $number( 'iso' ) ? (int) $meta['iso'] : null,
			'exposure_bias'     => null,
			'exposure_program'  => null,
			'flash'             => null,
			'white_balance'     => null,
			'created_timestamp' => null !== $number( 'created_timestamp' ) ? (int) $meta['created_timestamp'] : null,
		);
	}

	/**
	 * オリジナル画像の Exif から補完用の値を取得する.
	 *
	 * @param int $attachment_id 添付ファイル ID.
	 * @return array
	 */
	private static function from_file( $attachment_id ) {
		if ( ! function_exists( 'exif_read_data' ) ) {
			return array();
		}
		if ( ! in_array( get_post_mime_type( $attachment_id ), self::READABLE_MIME_TYPES, true ) ) {
			return array();
		}

		// -scaled などに縮小される前のオリジナル画像を対象にする.
		$path = wp_get_original_image_path( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return array();
		}

		// 壊れた Exif を含むファイルでは警告が出るため抑止する.
		$exif = @exif_read_data( $path, null, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $exif ) ) {
			return array();
		}

		// GPS は取得しない。必要なセクション以外はここで捨てる.
		$ifd0 = isset( $exif['IFD0'] ) && is_array( $exif['IFD0'] ) ? $exif['IFD0'] : array();
		$tags = isset( $exif['EXIF'] ) && is_array( $exif['EXIF'] ) ? $exif['EXIF'] : array();
		unset( $exif );

		$lens = '';
		foreach ( array( 'LensModel', 'UndefinedTag:0xA434' ) as $lens_key ) {
			if ( isset( $tags[ $lens_key ] ) && is_string( $tags[ $lens_key ] ) ) {
				$lens = self::clean_string( $tags[ $lens_key ] );
				if ( '' !== $lens ) {
					break;
				}
			}
		}

		$int = static function ( $key ) use ( $tags ) {
			return isset( $tags[ $key ] ) && is_numeric( $tags[ $key ] ) ? (int) $tags[ $key ] : null;
		};

		$focal_35 = $int( 'FocalLengthIn35mmFilm' );

		return array(
			'make'              => isset( $ifd0['Make'] ) && is_string( $ifd0['Make'] ) ? self::clean_string( $ifd0['Make'] ) : '',
			'model'             => isset( $ifd0['Model'] ) && is_string( $ifd0['Model'] ) ? self::clean_string( $ifd0['Model'] ) : '',
			'lens'              => $lens,
			'focal_length_35'   => null !== $focal_35 && $focal_35 > 0 ? (float) $focal_35 : null,
			'exposure_bias'     => isset( $tags['ExposureBiasValue'] ) ? Exif_Formatter::parse_rational( $tags['ExposureBiasValue'] ) : null,
			'exposure_program'  => $int( 'ExposureProgram' ),
			'flash'             => $int( 'Flash' ),
			'white_balance'     => $int( 'WhiteBalance' ),
			'created_timestamp' => isset( $tags['DateTimeOriginal'] ) ? self::exif_date_to_timestamp( $tags['DateTimeOriginal'] ) : null,
		);
	}

	/**
	 * Exif の日時（"YYYY:MM:DD HH:MM:SS"）を UTC として timestamp にする.
	 *
	 * @param mixed $value 日時文字列.
	 * @return int|null
	 */
	private static function exif_date_to_timestamp( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})/', trim( $value ), $m ) ) {
			return null;
		}
		$timestamp = gmmktime( (int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1] );
		return false !== $timestamp && $timestamp > 0 ? $timestamp : null;
	}

	/**
	 * Exif の文字列から NUL 文字・不正な UTF-8・タグを取り除く.
	 *
	 * @param mixed $value 値.
	 * @return string
	 */
	private static function clean_string( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = str_replace( "\0", '', $value );
		$value = wp_check_invalid_utf8( $value, true );
		$value = sanitize_text_field( $value );
		// Exif の ASCII 値は最大 64KB まで入りうるため、表示・保存する長さに上限を設ける.
		return mb_substr( $value, 0, self::MAX_STRING_LENGTH );
	}
}
