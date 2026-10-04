import { __, sprintf } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	Notice,
	RadioControl,
	TextControl,
	ToggleControl,
	ToolbarDropdownMenu,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';
import { capturePhoto, table, paragraph, list } from '@wordpress/icons';
import ExifPreview from './preview';

/**
 * 表示項目（表示順）。PHP 側の Exif_Formatter::FIELDS と揃える。
 */
const FIELDS = [
	{ key: 'camera', label: __( 'カメラ', 'next-exif-display-block' ) },
	{ key: 'lens', label: __( 'レンズ', 'next-exif-display-block' ) },
	{ key: 'focalLength', label: __( '焦点距離', 'next-exif-display-block' ) },
	{
		key: 'focalLength35',
		label: __( '35mm換算焦点距離', 'next-exif-display-block' ),
	},
	{ key: 'aperture', label: __( 'F値', 'next-exif-display-block' ) },
	{
		key: 'shutterSpeed',
		label: __( 'シャッター速度', 'next-exif-display-block' ),
	},
	{ key: 'iso', label: __( 'ISO感度', 'next-exif-display-block' ) },
	{ key: 'exposureBias', label: __( '露出補正', 'next-exif-display-block' ) },
	{
		key: 'exposureProgram',
		label: __( '撮影モード', 'next-exif-display-block' ),
	},
	{ key: 'flash', label: __( 'フラッシュ', 'next-exif-display-block' ) },
	{
		key: 'whiteBalance',
		label: __( 'ホワイトバランス', 'next-exif-display-block' ),
	},
	{ key: 'dateTime', label: __( '撮影日時', 'next-exif-display-block' ) },
];

const LAYOUTS = [
	{
		value: 'table',
		label: __( 'テーブル', 'next-exif-display-block' ),
		icon: table,
	},
	{
		value: 'inline',
		label: __( 'インライン', 'next-exif-display-block' ),
		icon: paragraph,
	},
	{
		value: 'list',
		label: __( 'アイコン付きリスト', 'next-exif-display-block' ),
		icon: list,
	},
];

/**
 * 画像ブロック（ID を持つもの）を表示用の情報に変換する。
 *
 * @param {Object} block ブロック.
 * @return {Object|null} 画像情報.
 */
function toImage( block ) {
	if ( block?.name !== 'core/image' || ! block.attributes?.id ) {
		return null;
	}
	return {
		id: block.attributes.id,
		url: block.attributes.url,
		alt: block.attributes.alt,
		clientId: block.clientId,
	};
}

/**
 * ブロックツリーを再帰的にたどり、ページ内の画像を文書順に集める。
 *
 * @param {Object[]} blocks ブロック一覧.
 * @param {Object[]} result 収集結果.
 * @return {Object[]} 画像一覧.
 */
function collectImages( blocks, result = [] ) {
	for ( const block of blocks ) {
		const image = toImage( block );
		if ( image ) {
			result.push( image );
		}
		if ( block.innerBlocks?.length ) {
			collectImages( block.innerBlocks, result );
		}
	}
	return result;
}

/**
 * 自ブロックより前にある兄弟から直前の画像を探す。
 * core/image はその画像、core/gallery はギャラリー内の最後の画像を対象にする。
 *
 * @param {Object[]} siblings 兄弟ブロック一覧.
 * @param {string}   clientId 自ブロックの clientId.
 * @return {Object|null} 画像情報.
 */
function findPrecedingImage( siblings, clientId ) {
	const index = siblings.findIndex( ( b ) => b.clientId === clientId );
	for ( let i = index - 1; i >= 0; i-- ) {
		const block = siblings[ i ];
		const image = toImage( block );
		if ( image ) {
			return image;
		}
		if ( block.name === 'core/gallery' ) {
			const images = collectImages( block.innerBlocks ?? [] );
			if ( images.length ) {
				return images[ images.length - 1 ];
			}
		}
	}
	return null;
}

/**
 * 画像の表示名（代替テキスト、なければファイル名）。
 *
 * @param {Object} image 画像情報.
 * @return {string} 表示名.
 */
function imageName( image ) {
	if ( image.alt ) {
		return image.alt;
	}
	const file = image.url ? image.url.split( '/' ).pop() : '';
	return (
		file ||
		sprintf(
			/* translators: %d: 添付ファイル ID */
			__( '画像 #%d', 'next-exif-display-block' ),
			image.id
		)
	);
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { source, attachmentId, layout, showLabels, separator, fields } =
		attributes;

	const { allBlocks, siblings } = useSelect(
		( select ) => {
			const { getBlocks, getBlockRootClientId } =
				select( blockEditorStore );
			return {
				allBlocks: getBlocks(),
				siblings: getBlocks( getBlockRootClientId( clientId ) ),
			};
		},
		[ clientId ]
	);

	const pageImages = useMemo(
		() => collectImages( allBlocks ),
		[ allBlocks ]
	);
	const precedingImage = useMemo(
		() => findPrecedingImage( siblings, clientId ),
		[ siblings, clientId ]
	);

	// auto のときは直前の画像の ID を属性に保存する（フロントでは兄弟ブロックを参照できないため）。
	const { __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );
	const autoId = precedingImage?.id ?? 0;
	useEffect( () => {
		if ( source === 'auto' && autoId !== attachmentId ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { attachmentId: autoId } );
		}
	}, [
		source,
		autoId,
		attachmentId,
		setAttributes,
		__unstableMarkNextChangeAsNotPersistent,
	] );

	const isMissingFromPage =
		source === 'manual' &&
		attachmentId > 0 &&
		! pageImages.some( ( image ) => image.id === attachmentId );

	const setField = ( key, value ) =>
		setAttributes( { fields: { ...fields, [ key ]: value } } );

	// プレビューにはこのブロック自身の属性だけを送る。他のプラグインが JS 側でだけ追加した属性
	// （サーバー未登録）を送ると、block-renderer が rest_invalid_param で拒否するため。
	const previewAttributes = useMemo(
		() => ( {
			source,
			attachmentId,
			layout,
			showLabels,
			separator,
			fields,
		} ),
		[ source, attachmentId, layout, showLabels, separator, fields ]
	);

	const blockProps = useBlockProps();
	const currentLayout =
		LAYOUTS.find( ( item ) => item.value === layout ) ?? LAYOUTS[ 0 ];

	const noImageMessage =
		source === 'auto'
			? __(
					'このブロックの直前に画像ブロックがありません。画像ブロックの直後に配置するか、設定で画像を選択してください。',
					'next-exif-display-block'
			  )
			: __(
					'設定パネルで、Exif情報を表示する画像を選択してください。',
					'next-exif-display-block'
			  );

	return (
		<>
			<BlockControls group="block">
				<ToolbarDropdownMenu
					icon={ currentLayout.icon }
					label={ __( 'レイアウト', 'next-exif-display-block' ) }
					controls={ LAYOUTS.map( ( item ) => ( {
						title: item.label,
						icon: item.icon,
						isActive: item.value === layout,
						onClick: () => setAttributes( { layout: item.value } ),
					} ) ) }
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( '画像', 'next-exif-display-block' ) }>
					<RadioControl
						label={ __( '対象の画像', 'next-exif-display-block' ) }
						selected={ source }
						options={ [
							{
								value: 'auto',
								label: __(
									'直前の画像',
									'next-exif-display-block'
								),
							},
							{
								value: 'manual',
								label: __(
									'ページ内の画像から選択',
									'next-exif-display-block'
								),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { source: value } )
						}
					/>
					{ source === 'manual' && (
						<div className="next-exif-display-block__picker">
							{ pageImages.length === 0 && (
								<p>
									{ __(
										'このページには画像ブロックがありません。',
										'next-exif-display-block'
									) }
								</p>
							) }
							{ pageImages.length > 0 && (
								<div
									className="next-exif-display-block__picker-grid"
									role="group"
									aria-label={ __(
										'ページ内の画像',
										'next-exif-display-block'
									) }
								>
									{ pageImages.map( ( image ) => (
										<button
											key={ image.clientId }
											type="button"
											className="next-exif-display-block__picker-item"
											aria-pressed={
												image.id === attachmentId
											}
											aria-label={ imageName( image ) }
											title={ imageName( image ) }
											onClick={ () =>
												setAttributes( {
													attachmentId: image.id,
												} )
											}
										>
											<img src={ image.url } alt="" />
										</button>
									) ) }
								</div>
							) }
							{ isMissingFromPage && (
								<Notice
									status="warning"
									isDismissible={ false }
								>
									{ __(
										'選択中の画像はこのページ内にありません。',
										'next-exif-display-block'
									) }
								</Notice>
							) }
						</div>
					) }
				</PanelBody>
				<PanelBody
					title={ __( '表示項目', 'next-exif-display-block' ) }
				>
					{ FIELDS.map( ( field ) => (
						<ToggleControl
							__nextHasNoMarginBottom
							key={ field.key }
							label={ field.label }
							checked={ !! fields?.[ field.key ] }
							onChange={ ( value ) =>
								setField( field.key, value )
							}
						/>
					) ) }
				</PanelBody>
				<PanelBody
					title={ __( '表示設定', 'next-exif-display-block' ) }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'ラベルを表示',
							'next-exif-display-block'
						) }
						checked={ showLabels }
						onChange={ ( value ) =>
							setAttributes( { showLabels: value } )
						}
					/>
					{ layout === 'inline' && (
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'区切り文字',
								'next-exif-display-block'
							) }
							value={ separator }
							onChange={ ( value ) =>
								setAttributes( { separator: value } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ attachmentId > 0 ? (
					<ExifPreview
						block="next/exif-display-block"
						attributes={ previewAttributes }
						emptyText={ __(
							'この画像には表示できるExif情報がありません（または画像が削除されています）。フロントでは何も表示されません。',
							'next-exif-display-block'
						) }
					/>
				) : (
					<Placeholder
						icon={ capturePhoto }
						label={ __( 'Exif情報', 'next-exif-display-block' ) }
						instructions={ noImageMessage }
					/>
				) }
			</div>
		</>
	);
}
