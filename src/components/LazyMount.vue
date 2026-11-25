<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	LazyMount — mounts its slot once the element comes within one viewport
	height of the visible area, once the page has settled (SETTLE_DELAY_MS,
	then the next idle moment), or when the page is about to print; whichever comes
	first (widgets REQ-WDG-044). Until then it holds an empty box that fills
	its parent, so the card around it keeps its place. Once mounted the slot
	stays mounted. `eager`, and a browser without IntersectionObserver, mount
	at once.
-->

<template>
	<div class="launchpad-lazy-mount" :data-mounted="shown ? 'true' : 'false'">
		<slot v-if="shown" />
	</div>
</template>

<script>
import { FLAG_PROP } from '../constants/propDefinitions.js'

/** How long a deferred slot leaves the widgets on screen to load first. */
export const SETTLE_DELAY_MS = 2500

/** After that delay, the longest it waits for the browser to be idle. */
export const IDLE_TIMEOUT_MS = 1000

/** How far ahead of the visible area a slot mounts: one viewport height. */
export const ROOT_MARGIN = '100% 0px'

/**
 * The browser features LazyMount uses, behind one seam so tests can swap them.
 *
 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
 */
export const LazyMountSupport = {
	/**
	 * The IntersectionObserver constructor, or null without one.
	 *
	 * @return {typeof IntersectionObserver|null} The constructor.
	 */
	observer() {
		return typeof window !== 'undefined' && typeof window.IntersectionObserver === 'function'
			? window.IntersectionObserver
			: null
	},

	/**
	 * Run `callback` `delay` ms from now, at the browser's next idle moment
	 * after that (at most `timeout` ms later).
	 *
	 * @param {() => void} callback What to run.
	 * @param {number} delay The wait before looking for an idle moment, in ms.
	 * @param {number} timeout The longest wait for that idle moment, in ms.
	 * @return {() => void} Cancels the wait.
	 */
	whenSettled(callback, delay, timeout) {
		let idleHandle = null
		const timer = window.setTimeout(() => {
			if (typeof window.requestIdleCallback === 'function') {
				idleHandle = window.requestIdleCallback(callback, { timeout })
			} else {
				callback()
			}
		}, delay)
		return () => {
			window.clearTimeout(timer)
			if (idleHandle !== null) {
				window.cancelIdleCallback?.(idleHandle)
			}
		}
	},
}

/**
 * Mounts its slot when it is about to be seen.
 *
 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
 */
export default {
	name: 'LazyMount',

	props: {
		eager: FLAG_PROP,
	},

	data() {
		return {
			shown: this.eager === true || !LazyMountSupport.observer(),
		}
	},

	watch: {
		/**
		 * Becoming eager (for example entering edit mode) mounts at once.
		 *
		 * @param {boolean} value The new eager flag.
		 * @return {void}
		 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
		 */
		eager(value) {
			if (value === true) {
				this.reveal()
			}
		},
	},

	/**
	 * Watch the element, the idle moment and the print, unless already shown.
	 *
	 * @return {void}
	 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
	 */
	mounted() {
		if (this.shown) {
			return
		}
		const Observer = LazyMountSupport.observer()
		this.observer = new Observer(this.onIntersect, { rootMargin: ROOT_MARGIN })
		this.observer.observe(this.$el)
		this.cancelIdle = LazyMountSupport.whenSettled(() => this.reveal(), SETTLE_DELAY_MS, IDLE_TIMEOUT_MS)
		this.onBeforePrint = () => this.reveal()
		window.addEventListener('beforeprint', this.onBeforePrint)
	},

	/**
	 * Stop watching.
	 *
	 * @return {void}
	 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
	 */
	beforeUnmount() {
		this.stopWatching()
	},

	methods: {
		/**
		 * The observer reports the element within reach.
		 *
		 * @param {IntersectionObserverEntry[]} entries The observed entries.
		 * @return {void}
		 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
		 */
		onIntersect(entries) {
			if (entries.some((entry) => entry.isIntersecting)) {
				this.reveal()
			}
		},

		/**
		 * Mount the slot for good.
		 *
		 * @return {void}
		 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
		 */
		reveal() {
			this.shown = true
			this.stopWatching()
		},

		/**
		 * Drop the observer, the idle callback and the print listener.
		 *
		 * @return {void}
		 * @spec openspec/changes/widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
		 */
		stopWatching() {
			this.observer?.disconnect()
			this.observer = null
			this.cancelIdle?.()
			this.cancelIdle = null
			if (this.onBeforePrint) {
				window.removeEventListener('beforeprint', this.onBeforePrint)
				this.onBeforePrint = null
			}
		},
	},
}

</script>

<style scoped>
.launchpad-lazy-mount {
	display: contents;
}

.launchpad-lazy-mount[data-mounted='false'] {
	display: block;
	width: 100%;
	height: 100%;
	min-height: 24px;
}
</style>
