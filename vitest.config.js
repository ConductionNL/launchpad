/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest configuration for LaunchPad Vue 3 unit tests.
 *
 * Test files live next to the code they cover under
 * `src/<area>/__tests__/<Subject>.spec.js` and run in a jsdom environment
 * so DOM assertions (`wrapper.find`, `wrapper.text()`, inline-style
 * inspection) work without launching a browser.
 *
 * The Nextcloud `t()` translation helper is stubbed in each test (see
 * `beforeEach` in the spec files) — we deliberately do NOT install a
 * global setup file so the stub stays visible inside the test that uses it.
 */

const path = require('path')
const vue = require('@vitejs/plugin-vue')

/**
 * Side-effect imports of `*.css` from `@nextcloud/vue` (and friends) crash
 * Vite's transform pipeline because those CSS files don't exist on disk —
 * they are produced by a parallel vite build and referenced via tree-shaken
 * `import './foo.css'` lines that survive transpilation. A small plugin
 * intercepts `*.css` resolution and returns a virtual empty module so unit
 * tests can mount components without ever loading a stylesheet.
 */
const cssNoop = {
	name: 'launchpad-css-noop',
	enforce: 'pre',
	resolveId(id) {
		// Match any CSS-like resolution (relative, absolute, with or
		// without query). Some side-effect imports surface as fully
		// resolved absolute paths from the optimizer; handle both.
		if (typeof id === 'string' && /\.css(\?.*)?$/.test(id)) {
			return '\0virtual:css-noop'
		}
		return null
	},
	load(id) {
		if (id === '\0virtual:css-noop') {
			return 'export default {}'
		}
		return null
	},
}

module.exports = {
	plugins: [
		cssNoop,
		vue.default ? vue.default() : vue(),
	],
	test: {
		environment: 'jsdom',
		globals: false,
		// Some specs mount the full Views / CnWidgetWrapper widget tree, which
		// is heavy enough to occasionally exceed the 5s default under parallel
		// load. Raise the global ceiling so those don't flake.
		testTimeout: 30000,
		// Several admin specs resolve the component under test with a dynamic
		// `await import()` inside `beforeEach`. The first call pays Vite's
		// full transform cost for the SFC and its dependency tree, which
		// overruns the 10s default hook timeout on a loaded machine — the
		// symptom is "Hook timed out in 10000ms" with no assertion failure.
		hookTimeout: 30000,
		include: ['src/**/__tests__/**/*.spec.{js,ts}'],
		setupFiles: [path.resolve(__dirname, 'tests/vitest/setup.js')],
		server: {
			deps: {
				// Inline Vue 2 + Nextcloud + transitive packages so Vite
				// transforms their .css side-effect imports through the
				// `cssNoop` plugin above. Without this, Vitest hands the
				// raw .css path to Node's ESM loader which crashes with
				// `ERR_UNKNOWN_FILE_EXTENSION`.
				inline: [
					/@nextcloud\/vue/,
					/@nextcloud\/axios/,
					/@conduction\/nextcloud-vue/,
					/@nextcloud\/dialogs/,
					/vue-material-design-icons/,
					/vue-select/,
					/vue-multiselect/,
					/vue2-datepicker/,
					/floating-vue/,
				],
			},
		},
	},
	resolve: {
		alias: [
			{ find: '@', replacement: path.resolve(__dirname, 'src') },
			// VTU v2 silently ignores v1's top-level stubs/provide/mocks. This
			// adapter hoists them into `global` so the legacy specs keep the
			// isolation they were written with. See the file's docblock.
			{ find: /^@vue\/test-utils$/, replacement: path.resolve(__dirname, 'tests/vitest/vueTestUtilsCompat.js') },
			// `@conduction/nextcloud-vue` ships a CJS bundle that
			// `require()`s `.vue` files which Vite's transform pipeline
			// cannot consume. Tests that need the actual component
			// behaviour use `vi.mock(...)`; everyone else gets a tiny
			// stub so transitive imports don't crash.
			//
			// This redirect applies to EVERY spec, so no unit test in this
			// suite runs against the real library — library integration is
			// covered only by the Playwright e2e suite. See the stub's
			// docblock ("CONSEQUENCE") before trusting a green unit run.
			{ find: /^@conduction\/nextcloud-vue$/, replacement: path.resolve(__dirname, 'tests/vitest/stubs/conduction-nextcloud-vue.js') },
		],
	},
}
