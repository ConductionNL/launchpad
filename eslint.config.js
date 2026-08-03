const {
	defineConfig,
} = require('@eslint/config-helpers')

const js = require('@eslint/js')

const {
	FlatCompat,
} = require('@eslint/eslintrc')

const compat = new FlatCompat({
	baseDirectory: __dirname,
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all,
})

module.exports = defineConfig([{
	extends: compat.extends('@nextcloud'),

	settings: {
		'import/resolver': {
			alias: {
				map: [['@', './src']],
				extensions: ['.js', '.ts', '.vue', '.json'],
			},
		},
	},

	rules: {
		'jsdoc/require-jsdoc': 'off',
		// `@spec openspec/...` is this repo's ADR-003 / ADR-020 traceability
		// tag linking a method to the capability Requirement it implements.
		// It is a real, enforced convention (`composer lint:spec-annotations`
		// checks it against tools/spec-annotations-allowlist.txt), so the
		// jsdoc plugin needs to be told the tag exists rather than the
		// convention being bent to satisfy the linter.
		'jsdoc/check-tag-names': ['warn', { definedTags: ['spec'] }],
		'vue/first-attribute-linebreak': 'off',
		'vue/no-unused-components': 'warn',
		'@typescript-eslint/no-explicit-any': 'off',
		'n/no-missing-import': 'off',
		'import/namespace': 'off',
		'import/default': 'off',
		'import/no-named-as-default': 'off',
		'import/no-named-as-default-member': 'off',
		'import/no-unresolved': ['error', { ignore: ['^@conduction/nextcloud-vue'] }],
		'no-console': 'off',
		'no-debugger': 'off',

		// ---------------------------------------------------------------
		// Vue 3 rule corrections.
		//
		// `@nextcloud/eslint-config@8` still resolves eslint-plugin-vue's
		// **Vue 2** preset (observable via `eslint --print-config`:
		// `vue/no-reserved-props` arrives as `{ vueVersion: 2 }`). Several
		// of those rules are not merely irrelevant under Vue 3 — they are
		// INVERTED, and forbid the exact syntax Vue 3 requires. Switching
		// the whole preset to `@nextcloud/eslint-config/vue3` crashes here
		// because that config references `@typescript-eslint/*` rules whose
		// plugin this project does not register, so the affected rules are
		// corrected individually instead.
		// ---------------------------------------------------------------

		// Vue 2 forbade a key on `<template v-for>`; Vue 3 REQUIRES it there
		// (the fragment is the keyed unit). `no-v-for-template-key-on-child`
		// is the Vue-3 counterpart — key on the child instead of the
		// template is the error now.
		'vue/no-v-for-template-key': 'off',
		'vue/no-v-for-template-key-on-child': 'error',

		// `v-model:arg` is the Vue 3 replacement for Vue 2's `.sync`.
		'vue/no-v-model-argument': 'off',

		// Vue 3 templates may have multiple roots (fragments).
		//
		// NOTE: `@conduction/nextcloud-vue@2.1.0-vue3.16` switched this rule
		// off in its own shared preset, so apps that extend
		// `@conduction/nextcloud-vue/eslint` no longer need a local disable.
		// This app does NOT extend that preset — it corrects the Vue-2
		// rules individually on top of `@nextcloud` (see the block comment
		// above). Verified with `eslint --print-config`: removing this line
		// takes the rule from `[0]` to `[2]`. It stays until launchpad
		// adopts the shared preset.
		'vue/no-multiple-template-root': 'off',

		// `.sync` was removed in Vue 3 — `valid-v-bind-sync` validates a
		// modifier that no longer exists, while `no-deprecated-v-bind-sync`
		// is what actually flags leftovers.
		'vue/valid-v-bind-sync': 'off',

		'vue/no-reserved-props': ['error', { vueVersion: 3 }],

		// Vue-2 idioms the Vue 3 compiler silently ignores rather than
		// erroring on — the failure mode is a dead listener or an unrendered
		// slot at runtime, so these are promoted to errors.
		'vue/no-deprecated-v-bind-sync': 'error',
		'vue/no-deprecated-dollar-listeners-api': 'error',
		'vue/no-deprecated-dollar-scopedslots-api': 'error',
		'vue/no-deprecated-destroyed-lifecycle': 'error',
		'vue/no-deprecated-events-api': 'error',
		'vue/no-deprecated-filter': 'error',
		'vue/no-deprecated-functional-template': 'error',
		'vue/no-deprecated-html-element-is': 'error',
		'vue/no-deprecated-inline-template': 'error',
		'vue/no-deprecated-props-default-this': 'error',
		'vue/no-deprecated-router-link-tag-prop': 'error',
		'vue/no-deprecated-scope-attribute': 'error',
		'vue/no-deprecated-slot-attribute': 'error',
		'vue/no-deprecated-slot-scope-attribute': 'error',
		'vue/no-deprecated-v-is': 'error',
		'vue/no-deprecated-v-on-native-modifier': 'error',
		'vue/no-deprecated-v-on-number-modifiers': 'error',
		'vue/no-deprecated-data-object-declaration': 'error',
		'vue/require-slots-as-functions': 'error',
	},
}, {
	// Test files may import devDependencies (vitest, @vue/test-utils, etc.)
	// without violating `n/no-unpublished-import`.
	files: [
		'src/**/__tests__/**/*.{js,ts}',
		'src/**/*.test.js',
		'src/**/*.spec.js',
		'src/__tests__/**/*.js',
		'tests/**/*.{js,ts}',
	],
	rules: {
		'n/no-unpublished-import': 'off',
	},
}])
