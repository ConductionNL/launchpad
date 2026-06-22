/**
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-1
 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-2
 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-3
 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-4
 * @spec openspec/changes/nc-dashboard-widget-proxy/tasks.md#1
 * @spec openspec/changes/nc-dashboard-widget-proxy/specs/legacy-widget-bridge/spec.md#req-lwb-005
 * @spec openspec/changes/nc-dashboard-widget-proxy/specs/legacy-widget-bridge/spec.md#req-lwb-006
 */

/**
 * Bridge for legacy Nextcloud widgets that use the callback registration pattern
 * (window.OCA.Dashboard.register)
 */
class WidgetBridge {

	/** @spec openspec/specs/legacy-widget-bridge/spec.md */
	constructor() {
		this.widgetCallbacks = new Map()
		this.statusCallbacks = new Map()
		this.interceptRegistration()
	}

	/**
	 * Intercept the global OCA.Dashboard.register calls
	 * Legacy widgets call this to register their rendering callback
	 *
	 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-1
	 */
	interceptRegistration() {
		// Ensure OCA and OCA.Dashboard exist
		window.OCA = window.OCA || {}
		window.OCA.Dashboard = window.OCA.Dashboard || {}
		const dash = window.OCA.Dashboard

		// Expose the registries the shared CnNcWidgetWidget reads to mount a
		// native widget + resolve its header title: `callbacks[id]`,
		// `widgets[id]` and `getWidget(id)`. On the custom workspace page NC's
		// own dashboard registry isn't present, so without this every
		// callback-based widget falls back to its raw id + "No items available".
		// Only define getWidget when absent so the native /dashboard registry
		// is never clobbered.
		dash.callbacks = dash.callbacks || {}
		dash.widgets = dash.widgets || {}
		if (typeof dash.getWidget !== 'function') {
			dash.getWidget = (id) => dash.widgets[id] || null
		}

		// Store original methods if they exist
		const originalRegister = dash.register
		const originalRegisterStatus = dash.registerStatus

		// Override register method
		dash.register = (appId, callback) => {
			console.debug('LaunchPad: Widget registered via callback:', appId)
			this.widgetCallbacks.set(appId, callback)
			// Surface the callback + a metadata stub where CnNcWidgetWidget looks.
			dash.callbacks[appId] = callback
			dash.widgets[appId] = { ...(dash.widgets[appId] || {}), id: appId, callback }

			// Also call original if it exists (for compatibility)
			if (originalRegister) {
				originalRegister(appId, callback)
			}
		}

		// Override registerStatus method
		dash.registerStatus = (appId, callback) => {
			console.debug('LaunchPad: Status widget registered:', appId)
			this.statusCallbacks.set(appId, callback)

			// Also call original if it exists
			if (originalRegisterStatus) {
				originalRegisterStatus(appId, callback)
			}
		}
	}

	/**
	 * Merge widget display metadata (title + icon) into the OCA.Dashboard
	 * registry so CnNcWidgetWidget's header shows a human title rather than the
	 * raw widget id. `register()` only carries the render callback, so titles
	 * come from the available-widgets descriptor (the dashboard widgets API).
	 * Merges (never clobbers a previously-registered `callback`).
	 *
	 * @param {Array<{id: string, title?: string, iconUrl?: string, iconClass?: string}>} widgets available widgets.
	 * @return {void}
	 */
	setWidgetMetadata(widgets) {
		if (!Array.isArray(widgets)) {
			return
		}
		const dash = window.OCA && window.OCA.Dashboard
		if (!dash) {
			return
		}
		dash.widgets = dash.widgets || {}
		for (const w of widgets) {
			if (!w || !w.id) {
				continue
			}
			dash.widgets[w.id] = {
				...(dash.widgets[w.id] || {}),
				id: w.id,
				title: w.title,
				iconUrl: w.iconUrl,
				iconClass: w.iconClass,
			}
		}
	}

	/**
	 * Mount a legacy widget into a container element
	 *
	 * @param {string} widgetId - The widget ID (appId)
	 * @param {HTMLElement} container - The DOM element to mount into
	 * @param {object} widgetData - The widget metadata (optional)
	 *
	 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-2
	 */
	mountWidget(widgetId, container, widgetData = {}) {
		console.log('[WidgetBridge] mountWidget called for:', widgetId)
		console.log('[WidgetBridge] Available callbacks:', Array.from(this.widgetCallbacks.keys()))

		const callback = this.widgetCallbacks.get(widgetId)

		if (callback && typeof callback === 'function') {
			try {
				// Clear container first
				container.innerHTML = ''
				console.log('[WidgetBridge] Calling widget callback for:', widgetId)

				// Call the widget's callback with the container and widget data
				// Some widgets expect a second parameter with widget metadata
				callback(container, { widget: widgetData })
				console.log('[WidgetBridge] Mounted legacy widget:', widgetId)
				console.log('[WidgetBridge] Container after mount:', container.innerHTML.substring(0, 200))
			} catch (error) {
				console.error('[WidgetBridge] Error mounting legacy widget:', widgetId, error)
			}
		} else {
			console.warn('[WidgetBridge] No callback found for widget:', widgetId)
			console.log('[WidgetBridge] Callback type:', typeof callback)
		}
	}

	/**
	 * Mount a status widget into a container element
	 *
	 * @param {string} widgetId - The status widget ID
	 * @param {HTMLElement} container - The DOM element to mount into
	 *
	 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-3
	 */
	mountStatusWidget(widgetId, container) {
		const callback = this.statusCallbacks.get(widgetId)

		if (callback && typeof callback === 'function') {
			try {
				container.innerHTML = ''
				callback(container)
				console.debug('LaunchPad: Mounted status widget:', widgetId)
			} catch (error) {
				console.error('LaunchPad: Error mounting status widget:', widgetId, error)
			}
		}
	}

	/**
	 * Check if a widget has been registered via callback
	 *
	 * @param {string} widgetId - The widget ID
	 * @return {boolean}
	 *
	 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-4
	 */
	hasWidgetCallback(widgetId) {
		return this.widgetCallbacks.has(widgetId)
	}

	/**
	 * Get all registered widget IDs
	 *
	 * @return {string[]}
	 *
	 * @spec openspec/changes/archive/2026-04-24-retrofit-legacy-widget-bridge/tasks.md#task-4
	 */
	getRegisteredWidgetIds() {
		return Array.from(this.widgetCallbacks.keys())
	}

	/**
	 * Poll for late callback registration (REQ-LWB-005).
	 *
	 * Periodically checks `hasWidgetCallback(widgetId)` until either a
	 * callback is registered (resolves true), the maximum retry count is
	 * exhausted (resolves false), or the caller aborts via the optional
	 * AbortSignal (resolves false immediately).
	 *
	 * Defaults: 200 ms interval × 15 retries (~3 s total).
	 *
	 * The first check is synchronous — when the callback is already
	 * registered no `setInterval` is scheduled and the promise resolves on
	 * the next microtask. This keeps the polling helper consistent with
	 * `hasWidgetCallback` (REQ-LWB-006: single source of truth for "is
	 * registered").
	 *
	 * @param {string} widgetId The widget ID (appId) to poll for.
	 * @param {object} [options] Optional configuration.
	 * @param {number} [options.intervalMs] Poll interval in milliseconds (default 200).
	 * @param {number} [options.maxRetries] Maximum number of poll ticks (default 15).
	 * @param {AbortSignal} [options.signal] Optional abort signal.
	 * @return {Promise<boolean>} Resolves true when a callback is registered, false otherwise.
	 *
	 * @spec openspec/changes/nc-dashboard-widget-proxy/specs/legacy-widget-bridge/spec.md#req-lwb-005
	 * @spec openspec/changes/nc-dashboard-widget-proxy/specs/legacy-widget-bridge/spec.md#req-lwb-006
	 */
	pollForCallback(widgetId, options = {}) {
		const intervalMs = options.intervalMs ?? 200
		const maxRetries = options.maxRetries ?? 15
		const signal = options.signal

		return new Promise((resolve) => {
			// REQ-LWB-006: synchronous first check via hasWidgetCallback.
			if (this.hasWidgetCallback(widgetId)) {
				resolve(true)
				return
			}

			// Honour an already-aborted signal up-front.
			if (signal && signal.aborted) {
				resolve(false)
				return
			}

			let retries = 0
			let timer = null
			let abortListener = null

			const cleanup = () => {
				if (timer !== null) {
					clearInterval(timer)
					timer = null
				}
				if (signal && abortListener) {
					signal.removeEventListener('abort', abortListener)
					abortListener = null
				}
			}

			if (signal) {
				abortListener = () => {
					cleanup()
					resolve(false)
				}
				signal.addEventListener('abort', abortListener)
			}

			timer = setInterval(() => {
				retries += 1
				if (this.hasWidgetCallback(widgetId)) {
					cleanup()
					resolve(true)
					return
				}
				if (retries >= maxRetries) {
					cleanup()
					resolve(false)
				}
			}, intervalMs)
		})
	}

}

// Export singleton instance
export const widgetBridge = new WidgetBridge()
