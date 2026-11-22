/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Global setup for Vitest unit tests. Stubs the Nextcloud `t()` and `n()`
 * translation helpers so component renders that call them resolve to the
 * bare key string. Loaded automatically via `test.setupFiles` in
 * `vitest.config.js`.
 *
 * The helpers are exposed two ways because Vue 2's template compiler emits
 * `_vm.t(...)` (looking on the component instance), while plain script
 * code calls `t(...)` (looking on the global). We register them as a
 * global mixin so every mounted component has them on its `this`, AND on
 * `globalThis` so direct script-level calls resolve.
 */

// Vue 3 has no global `Vue` constructor, so the previous `Vue.mixin({...})`
// is gone — `config.global.mocks` is the per-mount equivalent and applies to
// every component mounted in the suite.
import { config } from '@vue/test-utils'

const tStub = (_app, key, _vars) => key
const nStub = (_app, singular, plural, count) => (count === 1 ? singular : plural)

globalThis.t = tStub
globalThis.n = nStub

config.global.mocks = {
	...config.global.mocks,
	t: tStub,
	n: nStub,
}

// p-queue 10 (nested under @nextcloud/vue) calls
// `process.getBuiltinModule('node:events').setMaxListeners(Infinity, this)`
// from its constructor. Under jsdom the queue extends jsdom's EventTarget,
// which Node's `setMaxListeners` rejects, so importing any @nextcloud/vue
// component that creates a queue at module load throws. Hand p-queue a
// `node:events` whose `setMaxListeners` ignores non-Node targets.
const getBuiltinModule = process.getBuiltinModule?.bind(process)
if (getBuiltinModule) {
	const events = getBuiltinModule('node:events')
	const tolerantEvents = {
		...events,
		setMaxListeners(n, ...targets) {
			try {
				events.setMaxListeners(n, ...targets)
			} catch (error) {
				if (error?.code !== 'ERR_INVALID_ARG_TYPE') {
					throw error
				}
			}
		},
	}
	process.getBuiltinModule = (id) => (
		id === 'node:events' || id === 'events' ? tolerantEvents : getBuiltinModule(id)
	)
}

// jsdom has no layout and no ResizeObserver; floating-vue (NcPopover,
// NcActions) constructs one on mount. Without layout nothing ever resizes,
// so an observer that never fires is what a browser would report here.
if (typeof globalThis.ResizeObserver === 'undefined') {
	globalThis.ResizeObserver = class ResizeObserver {
		observe() {}
		unobserve() {}
		disconnect() {}
	}
}
