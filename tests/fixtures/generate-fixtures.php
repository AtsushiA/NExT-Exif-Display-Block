<?php
/**
 * テスト用の画像を生成するスクリプト（GD 拡張が必要）。
 *
 * 実行: php tests/fixtures/generate-fixtures.php
 *
 * - exif-sample.jpg : Exif（GPS 含む）付き JPEG
 * - no-exif.jpg     : Exif なし JPEG
 *
 * @package NextExifDisplayBlock
 */

// phpcs:disable

const TYPE_ASCII     = 2;
const TYPE_SHORT     = 3;
const TYPE_LONG      = 4;
const TYPE_RATIONAL  = 5;
const TYPE_SRATIONAL = 10;

/**
 * IFD の 1 エントリの値をバイト列にする.
 */
function encode_value( int $type, $value ): array {
	switch ( $type ) {
		case TYPE_ASCII:
			$bytes = $value . "\0";
			return array( strlen( $bytes ), $bytes );
		case TYPE_SHORT:
			return array( 1, pack( 'v', $value ) );
		case TYPE_LONG:
			return array( 1, pack( 'V', $value ) );
		case TYPE_RATIONAL:
		case TYPE_SRATIONAL:
			$pairs = is_array( $value[0] ) ? $value : array( $value );
			$bytes = '';
			foreach ( $pairs as $pair ) {
				$bytes .= TYPE_RATIONAL === $type ? pack( 'VV', $pair[0], $pair[1] ) : pack( 'll', $pair[0], $pair[1] );
			}
			return array( count( $pairs ), $bytes );
	}
	throw new InvalidArgumentException( 'unsupported type' );
}

/**
 * IFD を組み立てる。$offset は TIFF ヘッダ先頭からの IFD 開始位置.
 *
 * @param array $entries tag => [type, value].
 */
function build_ifd( array $entries, int $offset ): string {
	ksort( $entries );
	$count     = count( $entries );
	$data_base = $offset + 2 + $count * 12 + 4;
	$head      = pack( 'v', $count );
	$data      = '';
	foreach ( $entries as $tag => list( $type, $value ) ) {
		list( $n, $bytes ) = encode_value( $type, $value );
		$head             .= pack( 'vvV', $tag, $type, $n );
		if ( strlen( $bytes ) <= 4 ) {
			$head .= str_pad( $bytes, 4, "\0" );
		} else {
			$head .= pack( 'V', $data_base + strlen( $data ) );
			$data .= $bytes;
			if ( strlen( $data ) % 2 ) {
				$data .= "\0";
			}
		}
	}
	return $head . pack( 'V', 0 ) . $data;
}

/**
 * IFD のバイト長（値領域を含む）.
 */
function ifd_length( array $entries ): int {
	return strlen( build_ifd( $entries, 0 ) );
}

function build_exif_app1(): string {
	$exif = array(
		0x829A => array( TYPE_RATIONAL, array( 1, 200 ) ),         // ExposureTime.
		0x829D => array( TYPE_RATIONAL, array( 18, 10 ) ),         // FNumber.
		0x8822 => array( TYPE_SHORT, 3 ),                          // ExposureProgram: 絞り優先.
		0x8827 => array( TYPE_SHORT, 100 ),                        // ISOSpeedRatings.
		0x9003 => array( TYPE_ASCII, '2026:10:03 14:32:00' ),      // DateTimeOriginal.
		0x9204 => array( TYPE_SRATIONAL, array( 2, 3 ) ),          // ExposureBiasValue.
		0x9209 => array( TYPE_SHORT, 16 ),                         // Flash: 非発光.
		0x920A => array( TYPE_RATIONAL, array( 50, 1 ) ),          // FocalLength.
		0xA403 => array( TYPE_SHORT, 0 ),                          // WhiteBalance: オート.
		0xA405 => array( TYPE_SHORT, 75 ),                         // FocalLengthIn35mmFilm.
		0xA434 => array( TYPE_ASCII, 'RF50mm F1.8 STM' ),          // LensModel.
	);
	$gps  = array(
		0x0001 => array( TYPE_ASCII, 'N' ),
		0x0002 => array( TYPE_RATIONAL, array( array( 35, 1 ), array( 39, 1 ), array( 3012, 100 ) ) ),
		0x0003 => array( TYPE_ASCII, 'E' ),
		0x0004 => array( TYPE_RATIONAL, array( array( 139, 1 ), array( 44, 1 ), array( 2856, 100 ) ) ),
	);
	$ifd0 = array(
		0x010F => array( TYPE_ASCII, 'Canon' ),
		0x0110 => array( TYPE_ASCII, 'Canon EOS R5' ),
		0x8769 => array( TYPE_LONG, 0 ),
		0x8825 => array( TYPE_LONG, 0 ),
	);

	$ifd0_offset       = 8;
	$exif_offset       = $ifd0_offset + ifd_length( $ifd0 );
	$gps_offset        = $exif_offset + ifd_length( $exif );
	$ifd0[0x8769][1]   = $exif_offset;
	$ifd0[0x8825][1]   = $gps_offset;

	$tiff = 'II' . pack( 'vV', 42, $ifd0_offset )
		. build_ifd( $ifd0, $ifd0_offset )
		. build_ifd( $exif, $exif_offset )
		. build_ifd( $gps, $gps_offset );

	$payload = "Exif\0\0" . $tiff;
	return "\xFF\xE1" . pack( 'n', strlen( $payload ) + 2 ) . $payload;
}

function base_jpeg(): string {
	$image = imagecreatetruecolor( 64, 48 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 90, 140, 200 ) );
	ob_start();
	imagejpeg( $image, null, 80 );
	return (string) ob_get_clean();
}

$dir  = __DIR__;
$jpeg = base_jpeg();
file_put_contents( $dir . '/no-exif.jpg', $jpeg );
// SOI（先頭 2 バイト）の直後に APP1 を差し込む.
file_put_contents( $dir . '/exif-sample.jpg', substr( $jpeg, 0, 2 ) . build_exif_app1() . substr( $jpeg, 2 ) );

echo "generated\n";
