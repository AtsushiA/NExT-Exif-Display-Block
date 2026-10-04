import { rm } from 'fs/promises';
import { test as setup } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * ログイン状態の保存先。@wordpress/e2e-test-utils-playwright の requestUtils フィクスチャと
 * ブラウザ（playwright.config.ts の storageState）で同じファイルを共有する。
 */
const authFile = process.env.STORAGE_STATE_PATH as string;

/**
 * REST 経由でログインし、Cookie を storageState に保存する（Gutenberg の global-setup と同じ方法）。
 *
 * - wp-env を作り直すと保存済みの Cookie が無効になり、requestUtils が応答待ちのまま止まるため、
 *   毎回ファイルを削除してから新しくログインする。
 * - ログイン画面を操作する方法は、作り直した直後の環境で入力値が書き換えられて不安定なため使わない。
 */
setup( 'authenticate', async ( { baseURL } ) => {
	setup.setTimeout( 120_000 );
	await rm( authFile, { force: true } );
	const requestUtils = await RequestUtils.setup( {
		baseURL,
		storageStatePath: authFile,
	} );
	await requestUtils.setupRest();
	await requestUtils.request.dispose();
} );
