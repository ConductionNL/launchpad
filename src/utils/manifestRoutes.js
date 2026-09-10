/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest's `pages[]` turned into a vue-router table, plus the guard that
 * enforces what those pages declare.
 *
 * Both halves live here rather than in `main.js` for one reason: `main.js`
 * calls `createApp`, installs Pinia, reads `window.location` and fires an HTTP
 * request on import, so nothing can unit-test a function defined inside it.
 * A permission check nothing can test is a permission check nobody has watched
 * fail — which is how the nav half of this same field stayed inert.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import { permits } from './permissions.js'

/**
 * Build the vue-router config from the manifest. Each declared page becomes one
 * route, named for its `id`, so a page cannot be declared without being served.
 *
 * The page's declared `permission` travels with the route as `meta.permission`,
 * which is what {@link permissionGuard} reads. It used to be dropped here,
 * while the v2 manifest schema calls that field "the permission identifier
 * required to access this page" (`app-manifest-v2.schema.json`).
 *
 * @param {object} manifest  The merged manifest.
 * @param {object} component The component every manifest route renders.
 *
 * @return {Array<object>} vue-router 4 routes.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */
export function routesFromManifest(manifest, component) {
	const routes = (manifest.pages ?? []).map((page) => ({
		name: page.id,
		path: page.route,
		component,
		props: page.route.includes(':'),
		meta: { permission: page.permission ?? '' },
	}))

	// ⚠️ vue-router 4 REMOVED the bare `path: '*'` wildcard, and does not warn:
	// the route simply never matches, so an unknown URL renders the shell with
	// an empty content area. The named-param form is the v4 spelling.
	routes.push({ path: '/:pathMatch(.*)*', redirect: '/' })
	return routes
}

/**
 * The route half of the manifest's `permission` field.
 *
 * `CnAppNav` filters the MENU entry. Nothing filtered the ROUTE — not this app
 * and not `@conduction/nextcloud-vue`, which never sees the router because the
 * app builds it. Fixing only the nav would reproduce exactly the defect Dossiq
 * shipped (ConductionNL/dossiq#2307): the working half is what hides the
 * broken one, because everybody checks "can a non-admin see this?" by opening
 * the menu, and the menu answers correctly.
 *
 * A redirect to the dashboard rather than an error page: after the nav fix the
 * only ways onto a gated route are a hand-typed URL and a link that outlived
 * somebody's group membership. Both want the dashboard, which is where the
 * catch-all already sends an unmatched path.
 *
 * READ IT FOR WHAT IT IS. This stops the page from rendering. It is not an
 * authorization boundary and nothing behind it depends on it: launchpad's
 * two gated pages redirect to a Nextcloud settings section that Nextcloud
 * gates server-side, and its data endpoints check the caller themselves.
 *
 * @param {object}        to          The route being entered.
 * @param {Array<string>} permissions The permissions the account holds.
 *
 * @return {boolean|object} True to allow, or the redirect target.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */
export function permissionGuard(to, permissions) {
	if (permits(to?.meta?.permission, permissions)) {
		return true
	}
	return { path: '/' }
}
