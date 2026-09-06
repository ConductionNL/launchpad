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
	<CnAppRoot
		:manifest="liveManifest"
		:registry="registry"
		:pageTypes="pageTypes"
		appId="launchpad" />
</template>

<script>
import { CnAppRoot } from '@conduction/nextcloud-vue'
import { ICON_CATALOGUE } from './services/iconCatalogue.js'

/**
 * Root component — mounts `CnAppRoot`, which renders the shared chrome
 * (`CnAppNav` from `manifest.menu`, including ADR-114's four footer
 * destinations) around a `router-view`.
 *
 * 🔴 THIS APP USED TO ROOT ON `WorkspaceApp` DIRECTLY, with no router. It
 * declared nine pages in its manifest and served one: `/store`, `/reports` and
 * `/flows` all redirected to the dashboard. `WorkspaceApp` is the `/` and
 * `/dashboards/:id` route's view now (`launchpad-manifest-tier-3`).
 *
 * The bespoke skip link is gone with it. It existed BECAUSE this app did not
 * root on `NcContent` and so did not inherit Nextcloud's; `CnAppRoot` renders
 * `NcContent`, so the platform one is there and two bypass links would be
 * worse than one. `#launchpad-main-content` keeps `tabindex="-1"`, which the
 * quick-search Esc contract needs independently.
 *
 * Injects the runtime-manifest reactive ref from the root `provide` set up
 * in `main.js` (ADR-036 Decision 8). The manifest starts as the bundled
 * stub and is replaced with the per-user manifest once the async fetch
 * completes.
 */
export default {
	name: 'App',
	components: {
		CnAppRoot,
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

	props: {
		/**
		 * Bundled (stub) manifest from the bootstrap. `liveManifest` prefers
		 * the runtime one once it arrives.
		 */
		manifest: {
			type: Object,
			required: true,
		},

		/**
		 * V2 kind-tagged registry (ADR-036). `CnPageRenderer` resolves every
		 * `type: "custom"` page's `component` string against it.
		 */
		registry: {
			type: Object,
			required: true,
		},

		/**
		 * Declarative page-type components, from the library's defaults.
		 */
		pageTypes: {
			type: Object,
			required: true,
		},
	},

	computed: {
		/**
		 * The manifest CnAppRoot should render.
		 *
		 * Prefers the runtime one once `main.js`'s fetch resolves, and falls
		 * back to the bundled stub — which is what the menu and the route
		 * table were built from at boot.
		 *
		 * @return {object} The manifest.
		 *
		 * @spec openspec/changes/launchpad-manifest-tier-3/specs/manifest-routing/spec.md#requirement-req-route-004-the-shared-chrome-renders-with-the-workspace-inside-it
		 */
		liveManifest() {
			const runtime = this.runtimeManifest?.value
			if (!runtime) {
				return this.manifest
			}

			/*
			 * MERGED, NOT REPLACED, and the difference is the whole app.
			 *
			 * `/api/manifest` is NOT this app's manifest. ManifestController
			 * says so in its own docblock: it assembles a document from the
			 * user's OpenRegister dashboard objects, one `type: "dashboard"`
			 * page and one menu entry per dashboard, and "when the user has no
			 * dashboards the manifest returns empty pages/menu".
			 *
			 * This used to be `runtime ?? this.manifest`. An empty object is
			 * truthy, so a user with no dashboards got a manifest with NO pages
			 * and NO menu — and since `launchpad-manifest-tier-3` builds the
			 * router and the nav FROM the manifest, the app rendered nothing at
			 * all. Measured on a clean install: `#workspace-vue` held 7 bytes,
			 * an empty comment, against 43,564 for the same instance before the
			 * tier-3 adoption.
			 *
			 * A user WITH dashboards was no better off, only less obviously:
			 * the runtime document still replaced the nine declared pages, the
			 * ADR-114 footer and the walkthrough, which is what the chrome and
			 * routing specs were failing on.
			 *
			 * So the two are composed, which is what each is for. The bundled
			 * manifest owns the app's declared surfaces; the runtime one
			 * contributes the dashboards that only the server can know about,
			 * and the live `runtime` block.
			 */
			const byId = (entries) => {
				const seen = new Map()
				for (const entry of entries) {
					if (entry && entry.id !== undefined) {
						seen.set(entry.id, entry)
					}
				}
				return [...seen.values()]
			}

			return {
				...this.manifest,
				...(runtime.runtime ? { runtime: runtime.runtime } : {}),
				pages: byId([
					...(this.manifest.pages ?? []),
					...(runtime.pages ?? []),
				]),

				menu: byId([...(this.manifest.menu ?? []), ...(runtime.menu ?? [])]),
			}
		},
	},
}
</script>
