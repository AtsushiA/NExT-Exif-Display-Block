import path from 'path';
import { defineConfig, devices } from '@playwright/test';

// requestUtils フィクスチャとブラウザで同じログイン状態を使う（tests/e2e/auth.setup.ts 参照）。
process.env.STORAGE_STATE_PATH ??= path.join(
	__dirname,
	'artifacts/storage-states/admin.json'
);

const config = defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: process.env.CI ? 'github' : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'setup',
			testMatch: /auth\.setup\.ts/,
		},
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: [ 'setup' ],
		},
	],
} );

export default config;
