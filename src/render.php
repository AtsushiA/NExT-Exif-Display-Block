<?php
/**
 * next/exif-display-block のサーバーサイドレンダリング。
 *
 * 画像未選択・画像が存在しない・表示できる Exif がない場合は何も出力しない。
 *
 * @package NextExifDisplayBlock
 *
 * @var array    $attributes ブロック属性.
 * @var string   $content    ブロックの内容（未使用）.
 * @var WP_Block $block      ブロックインスタンス.
 */

use NextExifDisplayBlock\Exif_Formatter;
use NextExifDisplayBlock\Exif_Reader;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセスされた場合は終了する.
}

$attachment_id = isset( $attributes['attachmentId'] ) ? absint( $attributes['attachmentId'] ) : 0;
if ( ! Exif_Reader::is_viewable( $attachment_id ) ) {
	return;
}
$data = Exif_Reader::get( $attachment_id );
if ( null === $data ) {
	return;
}

$items = Exif_Formatter::build_items(
	$data,
	isset( $attributes['fields'] ) ? $attributes['fields'] : array(),
	array( 'date_format' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
);
if ( empty( $items ) ) {
	return;
}

$layout      = isset( $attributes['layout'] ) && in_array( $attributes['layout'], array( 'table', 'inline', 'list' ), true ) ? $attributes['layout'] : 'table';
$show_labels = ! isset( $attributes['showLabels'] ) || (bool) $attributes['showLabels'];
// 区切り文字は前後の空白も意味を持つ。wp_strip_all_tags() は trim するため、前後の空白を退避して付け直す.
$separator = ' / ';
if ( isset( $attributes['separator'] ) ) {
	$raw_separator = str_replace( array( "\r", "\n", "\t" ), ' ', (string) $attributes['separator'] );
	$separator     = wp_strip_all_tags( $raw_separator );
	if ( '' !== $separator && preg_match( '/^( *).*?( *)$/s', $raw_separator, $spaces ) ) {
		$separator = $spaces[1] . $separator . $spaces[2];
	}
}

$base_class = 'wp-block-next-exif-display-block';
$classes    = array( $base_class . '--' . $layout );
if ( ! $show_labels ) {
	$classes[] = 'is-labels-hidden';
}

// アイコン（list レイアウト用）。装飾目的のため読み上げ対象外とする.
$icon_paths   = array(
	'camera'          => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
	'lens'            => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/>',
	'focalLength'     => '<path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/>',
	'focalLength35'   => '<rect x="3" y="6" width="18" height="12" rx="1"/><path d="M7 6v12M17 6v12"/>',
	'aperture'        => '<circle cx="12" cy="12" r="9"/><path d="M12 3l4 7M21 12l-8 1M16 20l-5-6M5 18l5-6M4 8l8 3"/>',
	'shutterSpeed'    => '<circle cx="12" cy="13" r="8"/><path d="M12 13V9M10 3h4M12 3v2"/>',
	'iso'             => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10v4M10 14h2a1 1 0 0 0 0-2h-1a1 1 0 0 1 0-2h2M16 10a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/>',
	'exposureBias'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 21L21 3M7 7h4M9 5v4M13 17h4"/>',
	'exposureProgram' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
	'flash'           => '<path d="M13 2L5 14h6l-1 8 8-12h-6z"/>',
	'whiteBalance'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/>',
	'dateTime'        => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
);
$icon_allowed = array(
	'svg'    => array(
		'class'           => true,
		'xmlns'           => true,
		'viewbox'         => true,
		'width'           => true,
		'height'          => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
		'aria-hidden'     => true,
		'focusable'       => true,
	),
	'path'   => array( 'd' => true ),
	'circle' => array(
		'cx' => true,
		'cy' => true,
		'r'  => true,
	),
	'rect'   => array(
		'x'      => true,
		'y'      => true,
		'width'  => true,
		'height' => true,
		'rx'     => true,
	),
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() はエスケープ済み. ?>>
<?php if ( 'table' === $layout ) : ?>
	<dl class="<?php echo esc_attr( $base_class . '__list' ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<div class="<?php echo esc_attr( $base_class . '__item ' . $base_class . '__item--' . $item['key'] ); ?>">
				<dt class="<?php echo esc_attr( $base_class . '__label' . ( $show_labels ? '' : ' screen-reader-text' ) ); ?>"><?php echo esc_html( $item['label'] ); ?></dt>
				<dd class="<?php echo esc_attr( $base_class . '__value' ); ?>"><?php echo esc_html( $item['value'] ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
<?php elseif ( 'inline' === $layout ) : ?>
	<p class="<?php echo esc_attr( $base_class . '__list' ); ?>">
		<?php
		$parts = array();
		foreach ( $items as $item ) {
			$label   = $show_labels
				? '<span class="' . esc_attr( $base_class . '__label' ) . '">' . esc_html( $item['label'] ) . ': </span>'
				: '<span class="' . esc_attr( $base_class . '__label screen-reader-text' ) . '">' . esc_html( $item['label'] ) . ': </span>';
			$parts[] = '<span class="' . esc_attr( $base_class . '__item ' . $base_class . '__item--' . $item['key'] ) . '">'
				. $label
				. '<span class="' . esc_attr( $base_class . '__value' ) . '">' . esc_html( $item['value'] ) . '</span>'
				. '</span>';
		}
		$separator_html = '<span class="' . esc_attr( $base_class . '__separator' ) . '" aria-hidden="true">' . esc_html( $separator ) . '</span>';
		echo implode( $separator_html, $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 各要素は上でエスケープ済み.
		?>
	</p>
<?php else : ?>
	<ul class="<?php echo esc_attr( $base_class . '__list' ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<li class="<?php echo esc_attr( $base_class . '__item ' . $base_class . '__item--' . $item['key'] ); ?>">
				<?php
				echo wp_kses(
					'<svg class="' . esc_attr( $base_class . '__icon' ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icon_paths[ $item['key'] ] . '</svg>',
					$icon_allowed
				);
				?>
				<span class="<?php echo esc_attr( $base_class . '__label' . ( $show_labels ? '' : ' screen-reader-text' ) ); ?>"><?php echo esc_html( $item['label'] ); ?></span>
				<span class="<?php echo esc_attr( $base_class . '__value' ); ?>"><?php echo esc_html( $item['value'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
</div>
<?php
