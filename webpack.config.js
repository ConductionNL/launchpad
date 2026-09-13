/**
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

const path = require('path')
const fs = require('fs')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
	admin: path.join(__dirname, 'src', 'admin.js'),
	// Anonymous read-only public-share page (/apps/launchpad/s/{token}).
	public: path.join(__dirname, 'src', 'public.js'),
}

webpackConfig.output = {
	...webpackConfig.output,
	filename: 'launchpad-[name].js',
	chunkFilename: 'launchpad-[name].js?v=[contenthash]',
}

// Use local source when available (monorepo dev), otherwise fall back to npm package
const localLib = path.resolve(__dirname, '../nextcloud-vue/src')
const useLocalLib = fs.existsSync(localLib)

webpackConfig.resolve = {
	...(webpackConfig.resolve || {}),
	alias: {
		...(webpackConfig.resolve?.alias || {}),
		...(useLocalLib ? { '@conduction/nextcloud-vue': localLib } : {}),
		// Deduplicate shared packages so the aliased library source uses
		// the same instances as the app (prevents dual-Pinia / dual-Vue bugs).
		vue$: path.resolve(__dirname, 'node_modules/vue'),
		// pinia 4 JOINED THE ESM-ONLY CATEGORY DESCRIBED JUST BELOW. 2.1.7
		// declared `main: index.js` and `module: dist/pinia.mjs`, so a
		// directory alias resolved; 4.0.3 declares NEITHER, only an `exports`
		// map of `{".": "./dist/pinia.js"}`. The directory alias then bypasses
		// `exports`, finds no main/index, and resolves to nothing — and because
		// webpack names the ISSUER, the error reads
		//
		//     Can't resolve 'pinia' in
		//       node_modules/@conduction/nextcloud-vue/dist/esm/composables
		//
		// which looks like a broken shared library rather than a stale line
		// here. Same shape as the `@nextcloud/axios` alias that broke on 2.6.0.
		pinia$: path.resolve(__dirname, 'node_modules/pinia/dist/pinia.js'),
		// @nextcloud/vue@9 and @nextcloud/dialogs@7 (the Vue-3 lines) are
		// ESM-only: no `main`/`module`, just an `exports` map with a single
		// "import" condition. Aliasing to the package DIRECTORY (as before)
		// bypasses `exports` and looks for a main/index that does not exist,
		// so every import fails to resolve. Point at the concrete ESM entry.
		// The `$` keeps deep imports (`@nextcloud/vue/components/NcButton`)
		// going through the exports map.
		'@nextcloud/vue$': path.resolve(
			__dirname,
			'node_modules/@nextcloud/vue/dist/index.mjs',
		),
		'@nextcloud/dialogs$': path.resolve(
			__dirname,
			'node_modules/@nextcloud/dialogs/dist/index.mjs',
		),
		// `@nextcloud/axios@2.6+` is ESM-only and its `exports` map has no
		// `require` condition, so the CJS bundle of `@nextcloud/vue@8.x`
		// (which does `require('@nextcloud/axios')`) fails to resolve.
		// Alias directly to the ESM entry so webpack bypasses the exports
		// map and transforms/interops the ESM module itself.
		'@nextcloud/axios$': path.resolve(
			__dirname,
			'node_modules/@nextcloud/axios/dist/index.js',
		),
	},
	// Ensure webpack resolves dependencies from the app's node_modules first,
	// preventing Vue 3 packages from nextcloud-vue/node_modules leaking in.
	modules: [path.resolve(__dirname, 'node_modules'), 'node_modules'],
}

module.exports = webpackConfig
