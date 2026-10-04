import path from 'path';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const BLOCK_NAME = 'next/exif-display-block';
const FIXTURES = path.join( __dirname, '..', 'fixtures' );

type Media = { id: number; source_url: string };

test.describe( 'NExT Exif Display Block', () => {
	let exifImage: Media;
	let plainImage: Media;

	test.beforeAll( async ( { requestUtils } ) => {
		exifImage = await requestUtils.uploadMedia(
			path.join( FIXTURES, 'exif-sample.jpg' )
		);
		plainImage = await requestUtils.uploadMedia(
			path.join( FIXTURES, 'no-exif.jpg' )
		);
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
		await requestUtils.deleteAllPosts();
	} );

	test( 'プラグインが有効化されている', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );
		const pluginRow = page.locator(
			'tr[data-slug="next-exif-display-block"]'
		);
		await expect( pluginRow ).toHaveClass( /active/ );
	} );

	test( '直前に画像がない場合はプレースホルダーを表示する', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( { name: BLOCK_NAME } );

		const block = editor.canvas.locator( `[data-type="${ BLOCK_NAME }"]` );
		await expect( block ).toContainText( '直前に画像ブロックがありません' );
	} );

	test( '直前の画像を自動で対象にし、Exif をプレビューする', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( {
			name: 'core/image',
			attributes: { id: exifImage.id, url: exifImage.source_url },
		} );
		await editor.insertBlock( { name: BLOCK_NAME } );

		const block = editor.canvas.locator( `[data-type="${ BLOCK_NAME }"]` );
		await expect( block ).toContainText( 'Canon EOS R5' );
		await expect( block ).toContainText( 'RF50mm F1.8 STM' );
		await expect( block ).toContainText( 'f/1.8' );
		await expect( block ).toContainText( '1/200s' );

		const [ , exifBlock ] = await editor.getBlocks();
		expect( exifBlock.attributes.attachmentId ).toBe( exifImage.id );
	} );

	test( '他のプラグインが JS 側だけで追加した属性があってもプレビューできる', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		// wp-ambientcss・wp-data-anim などと同じく、サーバーに登録しない属性を JS 側だけで追加する.
		await page.evaluate( ( name ) => {
			const { blocks } = window.wp;
			const { name: _name, ...settings } = blocks.getBlockType( name );
			blocks.unregisterBlockType( name );
			blocks.registerBlockType( name, {
				...settings,
				attributes: {
					...settings.attributes,
					thirdPartyOnly: { type: 'object' },
					// wp-data-anim のように既定値を持つ属性は、値を設定していなくても送られうる.
					thirdPartyWithDefault: {
						type: 'string',
						default: 'scroll',
					},
				},
			} );
		}, BLOCK_NAME );

		await editor.insertBlock( {
			name: 'core/image',
			attributes: { id: exifImage.id, url: exifImage.source_url },
		} );
		await editor.insertBlock( {
			name: BLOCK_NAME,
			attributes: { thirdPartyOnly: { enabled: true } },
		} );

		const block = editor.canvas.locator( `[data-type="${ BLOCK_NAME }"]` );
		await expect( block ).toContainText( 'Canon EOS R5' );
		await expect( block ).not.toContainText( 'Error' );
	} );

	test( 'ページ内の画像から手動で選択できる', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( {
			name: 'core/image',
			attributes: {
				id: exifImage.id,
				url: exifImage.source_url,
				alt: 'Exifあり',
			},
		} );
		await editor.insertBlock( {
			name: 'core/image',
			attributes: {
				id: plainImage.id,
				url: plainImage.source_url,
				alt: 'Exifなし',
			},
		} );
		await editor.insertBlock( { name: BLOCK_NAME } );

		const block = editor.canvas.locator( `[data-type="${ BLOCK_NAME }"]` );
		// 直前の画像には Exif がない.
		await expect( block ).toContainText( '表示できるExif情報がありません' );

		await editor.openDocumentSettingsSidebar();
		await page.getByLabel( 'ページ内の画像から選択' ).check();
		await page.getByRole( 'button', { name: 'Exifあり' } ).click();

		await expect( block ).toContainText( 'Canon EOS R5' );
		const blocks = await editor.getBlocks();
		expect( blocks[ 2 ].attributes ).toMatchObject( {
			source: 'manual',
			attachmentId: exifImage.id,
		} );
	} );

	test( 'レイアウトと表示項目を切り替えられる', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( {
			name: 'core/image',
			attributes: { id: exifImage.id, url: exifImage.source_url },
		} );
		await editor.insertBlock( { name: BLOCK_NAME } );

		const block = editor.canvas.locator( `[data-type="${ BLOCK_NAME }"]` );
		await expect( block.locator( 'dl' ) ).toBeVisible();

		await editor.clickBlockToolbarButton( 'レイアウト' );
		await page
			.getByRole( 'menuitem', { name: 'アイコン付きリスト' } )
			.click();
		await expect( block.locator( 'ul svg' ).first() ).toBeVisible();

		await editor.openDocumentSettingsSidebar();
		await page.getByLabel( '撮影モード' ).check();
		await expect( block ).toContainText( '絞り優先' );
		await page.getByLabel( 'カメラ', { exact: true } ).uncheck();
		await expect( block ).not.toContainText( 'Canon EOS R5' );
	} );
} );

test.describe( 'Frontend (anonymous)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'Exif を表示し、位置情報は出力しない／画像未選択では何も出力しない', async ( {
		page,
		requestUtils,
	} ) => {
		const media: Media = await requestUtils.uploadMedia(
			path.join( FIXTURES, 'exif-sample.jpg' )
		);
		const post = await requestUtils.createPost( {
			title: 'Exif E2E Test',
			status: 'publish',
			content: [
				`<!-- wp:${ BLOCK_NAME } {"attachmentId":${ media.id },"layout":"inline"} /-->`,
				`<!-- wp:${ BLOCK_NAME } {"attachmentId":0} /-->`,
			].join( '\n' ),
		} );

		await page.goto( `/?p=${ post.id }` );

		const blocks = page.locator( '.wp-block-next-exif-display-block' );
		await expect( blocks ).toHaveCount( 1 );
		await expect( blocks ).toContainText( 'Canon EOS R5' );
		await expect( blocks ).not.toContainText( '139' );

		await requestUtils.deleteAllMedia();
		await requestUtils.deleteAllPosts();
	} );
} );
