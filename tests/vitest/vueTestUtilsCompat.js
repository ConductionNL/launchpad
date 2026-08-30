/**
 * SPDX-FileCopyrightText: 2026 ConductionNL / Launchpad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vue Test Utils v1 -> v2 mount-options adapter.
 *
 * VTU v2 moved the per-mount environment options under a `global` key:
 *
 *   v1: mount(C, { stubs, provide, mocks, directives, plugins, components })
 *   v2: mount(C, { global: { stubs, provide, mocks, directives, plugins, components } })
 *
 * v2 does not warn or throw on the v1 shape — it SILENTLY IGNORES those keys.
 * A spec that stubs a heavy child still mounts the real child, a spec that
 * provides an injection key mounts without it, and the failure surfaces far
 * away as a confusing assertion or a null-deref inside an unrelated component.
 * Several spec files here use the v1 shape.
 *
 * Rewriting them by hand is a large structural edit with a real chance of
 * silently changing what a spec asserts. This adapter hoists the v1 keys into
 * `global` at call time, preserving each spec's intent exactly, and is
 * trivially reversible: `vitest.config.js` aliases `@vue/test-utils` here, so
 * deleting the alias restores stock behaviour.
 *
 * It is a migration aid, not a permanent fixture — specs should move to the
 * native `global: { … }` shape over time, and this file deleted once
 * `grep -rE '^\s+(stubs|provide|mocks): ' tests/ src/` comes back empty.
 *
 * `propsData` is deliberately NOT touched: v2 still accepts it as an alias
 * for `props`.
 *
 * `pinia` gets the same treatment for the same reason. Under Vue 2 the store
 * reached the component through `Vue.use(PiniaVuePlugin)` plus a top-level
 * `pinia` mount option. Vue 3 has no global `Vue` to install a plugin on —
 * a Pinia instance IS an app plugin — so the option becomes
 * `global.plugins: [pinia]`. Like the keys above, v2 silently ignores the
 * top-level form, and the symptom is a component mounting against whatever
 * `setActivePinia()` last set rather than the instance the spec passed.
 */

// Import the real module by absolute path — importing '@vue/test-utils' here
// would hit the vitest alias that points at this file and recurse forever.
import * as vtu from '../../node_modules/@vue/test-utils/dist/vue-test-utils.esm-bundler.mjs'

/** Mount options v1 accepted at the top level and v2 expects under `global`. */
const HOISTED_KEYS = [
	'stubs',
	'provide',
	'mocks',
	'directives',
	'plugins',
	'components',
	'config',
	'renderStubDefaultSlot',
]

/**
 * Move any v1-style top-level environment options into `global`, leaving an
 * explicit `global` block the caller already wrote as the winner on conflict.
 *
 * @param {object} [options] Mount options as the spec wrote them.
 * @return {object} Options in the shape VTU v2 expects.
 */
function hoistGlobalOptions(options) {
	if (!options || typeof options !== 'object') {
		return options
	}
	const hoisted = {}
	let found = false
	for (const key of HOISTED_KEYS) {
		if (Object.prototype.hasOwnProperty.call(options, key)) {
			hoisted[key] = options[key]
			found = true
		}
	}
	if (!found) {
		return options
	}
	const next = { ...options }
	for (const key of HOISTED_KEYS) {
		delete next[key]
	}
	// An explicitly-written `global` block wins — it is already v2 syntax and
	// therefore states the author's current intent.
	next.global = { ...hoisted, ...(options.global || {}) }
	return next
}

/**
 * Turn a v1 top-level `pinia` mount option into a v2 `global.plugins` entry.
 *
 * @param {object} [options] Mount options, already run through hoistGlobalOptions.
 * @return {object} Options with `pinia` installed as an app plugin.
 */
function installPinia(options) {
	if (!options || typeof options !== 'object' || !options.pinia) {
		return options
	}
	const next = { ...options }
	const pinia = next.pinia
	delete next.pinia
	const global = { ...(next.global || {}) }
	global.plugins = [...(global.plugins || []), pinia]
	next.global = global
	return next
}

/**
 * Apply every v1 -> v2 mount-option adaptation in order.
 *
 * @param {object} [options] Mount options as the spec wrote them.
 * @return {object} Options in the shape VTU v2 expects.
 */
function adaptOptions(options) {
	return installPinia(hoistGlobalOptions(options))
}

export * from '../../node_modules/@vue/test-utils/dist/vue-test-utils.esm-bundler.mjs'

export const mount = (component, options) =>
	vtu.mount(component, adaptOptions(options))
export const shallowMount = (component, options) =>
	vtu.shallowMount(component, adaptOptions(options))
