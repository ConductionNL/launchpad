<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - Root component — mounts the runtime-shell orchestrator (WorkspaceApp.vue)
  - which owns the four-region page chrome and delegates the grid surface to
  - the existing Views.vue.
  -
  - ADR-036 Decision 8: injects the reactive runtime-manifest ref provided
  - by main.js. The ref is updated once the async fetch in main.js resolves.
  - Provides the live manifest value down the tree via `provide` so future
  - manifest-aware descendants can inject it without prop-drilling.
-->

<template>
	<div class="launchpad-root">
		<!--
			Skip link (WCAG 2.2 AA SC 2.4.1 "Bypass Blocks").

			LaunchPad does not root on <NcContent>/<CnAppRoot>, so it does not
			inherit Nextcloud's own skip link — it writes its own shell
			(WorkspaceApp's .workspace-shell). That shell puts the org
			navigation rail, the sidebar and the toolbar ahead of the grid in
			DOM order, so a keyboard or screen-reader user reaches the grid
			only after tabbing through every navigation control on the page.
			This anchor is the bypass.

			The target `#launchpad-main-content` is the grid region in
			WorkspaceApp.vue. It already carried `tabindex="-1"` (for the
			quick-search Esc contract), which is exactly what a fragment
			target needs to accept programmatic focus in every browser —
			without it Safari and Firefox move the *scroll* position but leave
			focus on the anchor, so the next Tab returns to the navigation the
			user just asked to skip.
		-->
		<a
			class="launchpad-skip-link"
			href="#launchpad-main-content"
			@click="focusMainContent">
			{{ t('launchpad', 'Skip to main content') }}
		</a>
		<WorkspaceApp />
	</div>
</template>

<script>
import WorkspaceApp from './views/WorkspaceApp.vue'
import { ICON_CATALOGUE } from './services/iconCatalogue.js'

/**
 * Root component — mounts the runtime-shell orchestrator
 * (`WorkspaceApp.vue`) which owns the four-region page chrome and
 * delegates the grid surface to the existing `Views.vue`.
 *
 * Injects the runtime-manifest reactive ref from the root `provide` set up
 * in `main.js` (ADR-036 Decision 8). The manifest starts as the bundled
 * stub and is replaced with the per-user manifest once the async fetch
 * completes.
 */
export default {
	name: 'App',
	components: {
		WorkspaceApp,
	},

	inject: {
		/**
		 * Reactive Vue.observable wrapping the current manifest value.
		 * Initial value is the bundled stub; replaced by the runtime fetch.
		 *
		 * @type {{ value: object }}
		 */
		runtimeManifest: {
			from: 'runtimeManifest',
			default: null,
		},

		/**
		 * Reactive Vue.observable tracking whether the manifest fetch is
		 * in flight. False once the fetch resolves or fails.
		 *
		 * @type {{ value: boolean }}
		 */
		manifestLoading: {
			from: 'manifestLoading',
			default: () => ({ value: false }),
		},
	},

	/** @spec openspec/specs/runtime-shell/spec.md */
	provide() {
		// Re-provide the reactive manifest refs so deeply nested
		// descendants don't need to prop-drill through WorkspaceApp/Views.
		// `cnIconCatalogue` feeds CnIconBrowser instances inside widget config
		// forms (which can't easily receive an `icons` prop) the full MDI set.
		return {
			runtimeManifest: this.runtimeManifest,
			manifestLoading: this.manifestLoading,
			cnIconCatalogue: ICON_CATALOGUE,
		}
	},

	methods: {
		/**
		 * Move focus to the main content region when the skip link is used.
		 *
		 * The plain `href="#launchpad-main-content"` already moves the SCROLL
		 * position everywhere, but only Chrome reliably moves FOCUS with it.
		 * In Firefox and Safari focus stays on the anchor, so the very next
		 * Tab lands back on the navigation the user just asked to skip —
		 * the link appears to work and does nothing. Focusing the target
		 * explicitly makes the behaviour identical in every browser.
		 *
		 * The target carries `tabindex="-1"`, so it accepts programmatic
		 * focus without joining the tab order.
		 *
		 * `preventDefault` is deliberately NOT called: the default hash
		 * navigation is what puts the region in view, and it also leaves a
		 * history entry a user can go back from.
		 *
		 * @spec openspec/specs/runtime-shell/spec.md
		 * @return {void}
		 */
		focusMainContent() {
			const main = document.getElementById('launchpad-main-content')
			if (main !== null) {
				main.focus()
			}
		},
	},
}
</script>

<style>
/* All other styling lives inside WorkspaceApp/Views. The skip link is the
   one thing that belongs to the root, because it has to precede every
   region the shell renders. */

/* Off-screen until focused, then pinned to the top-left over the chrome.
   `clip`/`width:1px` rather than `display:none` or `visibility:hidden` —
   the latter two remove the element from the accessibility tree entirely,
   so it could never receive focus and the link would be dead for exactly
   the users it exists for (WCAG 2.2 AA SC 2.4.1). */
.launchpad-skip-link {
	position: absolute;
	left: -9999px;
	top: auto;
	width: 1px;
	height: 1px;
	overflow: hidden;
	z-index: 10000;
}

.launchpad-skip-link:focus,
.launchpad-skip-link:focus-visible {
	position: fixed;
	left: 8px;
	top: 8px;
	width: auto;
	height: auto;
	padding: 8px 16px;
	overflow: visible;
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #222);
	border: 2px solid var(--color-primary-element, #0082c9);
	border-radius: var(--border-radius, 3px);
	text-decoration: none;
}
</style>
