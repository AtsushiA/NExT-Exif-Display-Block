import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Placeholder, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { RawHTML, useEffect, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { capturePhoto } from '@wordpress/icons';

/**
 * サーバー側の render.php の出力をエディタに表示するプレビュー。
 *
 * ServerSideRender は送信前に sanitizeBlockAttributes() で JS 側の全属性の既定値を補うため、
 * 他のプラグインが JS 側だけで全ブロックに追加した属性（wp-data-anim の dataAnim など、サーバー未登録）まで
 * 送ってしまい、block-renderer が rest_invalid_param で拒否する。ここではこのブロック自身の属性だけを送る。
 *
 * @param {Object} props            Props.
 * @param {string} props.block      ブロック名.
 * @param {Object} props.attributes 送信する属性（このブロック自身のもののみ）.
 * @param {string} props.emptyText  出力が空のときの説明.
 * @return {Element} プレビュー.
 */
export default function ExifPreview( { block, attributes, emptyText } ) {
	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.() ?? null,
		[]
	);
	const path = addQueryArgs( `/wp/v2/block-renderer/${ block }`, {
		context: 'edit',
		attributes,
		...( postId ? { post_id: postId } : {} ),
	} );

	const [ state, setState ] = useState( { status: 'loading' } );

	useEffect( () => {
		const controller = new AbortController();
		// 入力中の連続した変更でリクエストが重ならないよう、少し待ってから取得する.
		const timer = setTimeout( () => {
			setState( ( prev ) =>
				prev.status === 'success'
					? { ...prev, isUpdating: true }
					: { status: 'loading' }
			);
			apiFetch( { path, signal: controller.signal } )
				.then( ( res ) =>
					setState( {
						status: 'success',
						content: res?.rendered ?? '',
					} )
				)
				.catch( ( error ) => {
					if ( error?.name === 'AbortError' ) {
						return;
					}
					setState( {
						status: 'error',
						// apiFetch は REST のエラー（{ code, message }）を Error ではないオブジェクトで返す.
						message:
							error?.message ||
							__( '不明なエラー', 'next-exif-display-block' ),
					} );
				} );
		}, 300 );
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ path ] );

	if ( state.status === 'loading' ) {
		return (
			<Placeholder icon={ capturePhoto }>
				<Spinner />
			</Placeholder>
		);
	}

	if ( state.status === 'error' ) {
		return (
			<Placeholder
				icon={ capturePhoto }
				label={ __( 'Exif情報', 'next-exif-display-block' ) }
				instructions={ sprintf(
					/* translators: %s: エラーメッセージ */
					__(
						'プレビューを読み込めませんでした: %s',
						'next-exif-display-block'
					),
					state.message
				) }
			/>
		);
	}

	if ( ! state.content.trim() ) {
		return (
			<Placeholder
				icon={ capturePhoto }
				label={ __( 'Exif情報', 'next-exif-display-block' ) }
				instructions={ emptyText }
			/>
		);
	}

	return (
		<div style={ state.isUpdating ? { opacity: 0.6 } : undefined }>
			<RawHTML>{ state.content }</RawHTML>
		</div>
	);
}
