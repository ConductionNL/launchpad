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
