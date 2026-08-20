/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The app's single console boundary.
 *
 * WHY THIS EXISTS
 * ---------------
 * `src/` carried 127 direct `console.*` calls, every one of them suppressed in
 * `eslint-suppressions.json`. 37 of those were `console.log` / `console.debug`
 * tracing — `[WidgetRenderer] mounted hook called`, `[WidgetStore] API
 * response:` and similar — which ran on EVERY dashboard load in production and
 * wrote straight to the browser console of every user.
 *
 * Routing through this module turns that into one deliberate, documented place
 * where the app is allowed to touch the console, instead of 127 undocumented
 * ones, and gives a single chokepoint for anything later (level control, a
 * switch to `@nextcloud/logger`, error reporting).
 *
 * WHAT CHANGED IN BEHAVIOUR, AND WHAT DID NOT
 * -------------------------------------------
 * `warn` and `error` are PURE PASS-THROUGHS. They forward their arguments
 * verbatim and call `console.warn` / `console.error` at call time, so existing
 * `vi.spyOn(console, 'warn')` tests — several of which assert exact arguments
 * (`toHaveBeenCalledWith('Unknown internal action: …')`) — keep working. No
 * prefix is added, on purpose: adding one would have broken those assertions
 * and quietly changed every log line's shape.
 *
 * `debug` is the one real change: it is SILENT unless the developer opts in.
 * Diagnostic call sites are kept (they are genuinely useful when debugging the
 * widget-mount path) but they no longer ship noise to end users.
 *
 * Enable it from the browser console:
 *
 *     localStorage.setItem('launchpad:debug', '1')   // then reload
 *     localStorage.removeItem('launchpad:debug')     // back to quiet
 */

/* eslint-disable no-console -- This module IS the console boundary. Every
   other file in src/ goes through it, which is what makes the rule meaningful
   rather than suppressed 127 times over. */

/**
 * localStorage key that turns debug tracing on.
 *
 * @type {string}
 */
const DEBUG_KEY = 'launchpad:debug'

/**
 * Whether debug tracing is enabled for this browser.
 *
 * Read per call rather than cached at module load so toggling the key takes
 * effect on the next log line instead of requiring a reload of the bundle, and
 * wrapped because `localStorage` throws in private-mode/sandboxed contexts.
 *
 * @return {boolean} true when tracing is on.
 */
function debugEnabled() {
	try {
		return globalThis.localStorage?.getItem(DEBUG_KEY) === '1'
	} catch {
		return false
	}
}

export const logger = {
	/**
	 * Developer tracing. Silent unless `launchpad:debug` is set.
	 *
	 * @param {...unknown} args values to log.
	 * @return {void}
	 * @spec exclude console plumbing — this module carries no product
	 *   behaviour. It exists so `src/` has ONE place that may touch the
	 *   console; what gets logged, and when, is specified where the calling
	 *   code is specified.
	 */
	debug(...args) {
		if (debugEnabled()) {
			console.debug(...args)
		}
	},

	/**
	 * A recoverable problem the user may need to know about. Always shown.
	 *
	 * @param {...unknown} args values to log.
	 * @return {void}
	 * @spec exclude console plumbing — this module carries no product
	 *   behaviour. It exists so `src/` has ONE place that may touch the
	 *   console; what gets logged, and when, is specified where the calling
	 *   code is specified.
	 */
	warn(...args) {
		console.warn(...args)
	},

	/**
	 * A failure. Always shown.
	 *
	 * @param {...unknown} args values to log.
	 * @return {void}
	 * @spec exclude console plumbing — this module carries no product
	 *   behaviour. It exists so `src/` has ONE place that may touch the
	 *   console; what gets logged, and when, is specified where the calling
	 *   code is specified.
	 */
	error(...args) {
		console.error(...args)
	},
}
