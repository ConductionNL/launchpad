/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Guards for the manifest `permission` field: the nav list, the route table
 * that carries it, and the guard that denies on it.
 *
 * WHAT THESE TESTS ARE FOR, and what they cannot do.
 * -------------------------------------------------
 * Every assertion below is on the DENIED case, because a permission test that
 * only asserts the permitted case passes against a broken gate. And each
 * denial is asserted in two parts wherever emptiness could substitute for a
 * decision: a list that omits `admin` is only a gate if it is ALSO non-empty,
 * since `CnAppNav.passesPermission` renders every entry when handed `[]`.
 *
 * `vitest.config.js` redirects `@conduction/nextcloud-vue` to a stub for EVERY
 * spec in this suite, so no unit test here can mount the real `CnAppNav` and
 * none of these prove the library filters anything. They prove the list this
 * app hands it cannot trip the library's empty-list escape, and they prove the
 * route guard denies. The library integration is proved by
 * `tests/e2e/admin-menu-permissions.spec.ts`, against a real non-admin
 * session and the real bundle.
 */

import { describe, expect, it } from 'vitest'
import manifest from '../../manifest.json'
import { currentPermissions, permits } from '../permissions.js'
import { permissionGuard, routesFromManifest } from '../manifestRoutes.js'

/**
 * `CnAppNav.passesPermission`, copied verbatim from
 * `@conduction/nextcloud-vue` (`src/components/CnAppNav/CnAppNav.vue:1061`).
 *
 * Copied rather than imported because the library is stubbed in this suite.
 * It is here to pin ONE property: that the list this app supplies never hits
 * the middle line, which is the line that made the declaration inert.
 *
 * @param {object}        item        A manifest menu entry.
 * @param {Array<string>} permissions The permissions held.
 *
 * @return {boolean} Whether the library would render the entry.
 */
function libraryPassesPermission(item, permissions) {
	if (!item.permission) return true
	if (!permissions || permissions.length === 0) return true
	return permissions.includes(item.permission)
}

const COMPONENT = { name: 'StubPage' }

describe('currentPermissions', () => {
	it('DENIES admin to a non-admin, and answers a NON-EMPTY list while doing it', () => {
		const held = currentPermissions(false)

		expect(held).not.toContain('admin')
		// The second half is the whole test. `[]` also fails `toContain`, and
		// `[]` is exactly the value that made CnAppNav render everything.
		expect(held.length, 'an empty list fails OPEN in CnAppNav').toBeGreaterThan(0)
	})

	it('grants admin to an admin', () => {
		expect(currentPermissions(true)).toContain('admin')
	})

	it.each([
		['undefined', undefined],
		['null', null],
		['the string "true"', 'true'],
		['1', 1],
		['an empty object', {}],
	])('fails CLOSED on %s rather than coercing it to admin', (_label, value) => {
		const held = currentPermissions(value)

		expect(held).not.toContain('admin')
		expect(held.length).toBeGreaterThan(0)
	})
})

describe('permits', () => {
	it('DENIES a required permission the account does not hold', () => {
		expect(permits('admin', ['user'])).toBe(false)
	})

	it('DENIES on an empty list rather than failing open like the nav rule does', () => {
		// This is the one line where this function deliberately disagrees with
		// CnAppNav. On a nav entry an empty list is a visibility bug; on a
		// route it would be the entire gate.
		expect(permits('admin', [])).toBe(false)
		expect(libraryPassesPermission({ permission: 'admin' }, [])).toBe(true)
	})

	it.each([
		['undefined', undefined],
		['null', null],
		['a string', 'admin'],
		['an object', { admin: true }],
	])('DENIES when the permission list is %s', (_label, value) => {
		expect(permits('admin', value)).toBe(false)
	})

	it('allows anything that declares no permission', () => {
		expect(permits('', ['user'])).toBe(true)
		expect(permits(undefined, ['user'])).toBe(true)
	})

	it('allows a permission the account holds', () => {
		expect(permits('admin', ['user', 'admin'])).toBe(true)
	})
})

describe('routesFromManifest', () => {
	it('carries a declared page permission onto the route as meta.permission', () => {
		const routes = routesFromManifest(
			{ pages: [{ id: 'p', route: '/admin/x', permission: 'admin' }] },
			COMPONENT,
		)

		expect(routes[0].meta.permission).toBe('admin')
	})

	it('leaves an undeclared page open rather than defaulting it to undefined', () => {
		const routes = routesFromManifest(
			{ pages: [{ id: 'p', route: '/x' }] },
			COMPONENT,
		)

		expect(routes[0].meta.permission).toBe('')
	})

	it('still appends the vue-router 4 catch-all', () => {
		const routes = routesFromManifest({ pages: [] }, COMPONENT)

		expect(routes.at(-1)).toEqual({
			path: '/:pathMatch(.*)*',
			redirect: '/',
		})
	})
})

describe('permissionGuard', () => {
	const nonAdmin = currentPermissions(false)
	const admin = currentPermissions(true)

	it('REDIRECTS a non-admin away from an admin route', () => {
		const verdict = permissionGuard(
			{ path: '/admin/settings', meta: { permission: 'admin' } },
			nonAdmin,
		)

		expect(verdict).toEqual({ path: '/' })
	})

	it('lets an admin through the same route', () => {
		expect(
			permissionGuard(
				{ path: '/admin/settings', meta: { permission: 'admin' } },
				admin,
			),
		).toBe(true)
	})

	it('lets a non-admin through a route that declares nothing', () => {
		expect(permissionGuard({ path: '/', meta: { permission: '' } }, nonAdmin)).toBe(
			true,
		)
	})

	it('DENIES a gated route when the guard is handed no list at all', () => {
		expect(
			permissionGuard({ path: '/admin/settings', meta: { permission: 'admin' } }),
		).toEqual({ path: '/' })
	})
})

describe('the shipped manifest, gated as a non-admin', () => {
	const nonAdmin = currentPermissions(false)
	const pageById = new Map((manifest.pages ?? []).map((page) => [page.id, page]))

	it('hides every gated MENU entry from a non-admin', () => {
		const gated = (manifest.menu ?? []).filter((item) => item.permission)

		// Guard the guard: if nothing declares a permission any more, this
		// whole describe block is vacuous and must say so out loud.
		expect(gated.length, 'no menu entry declares a permission').toBeGreaterThan(0)

		for (const item of gated) {
			expect(
				libraryPassesPermission(item, nonAdmin),
				`menu entry "${item.id}" renders for a non-admin`,
			).toBe(false)
		}
	})

	it('redirects a non-admin off every gated ROUTE', () => {
		const routes = routesFromManifest(manifest, COMPONENT)
		const gated = routes.filter((r) => r.meta?.permission)

		expect(gated.length, 'no page declares a permission').toBeGreaterThan(0)

		for (const route of gated) {
			expect(
				permissionGuard(route, nonAdmin),
				`route "${route.path}" is reachable by a non-admin`,
			).toEqual({ path: '/' })
		}
	})

	it('gates the PAGE wherever it gates the MENU ENTRY', () => {
		// The invariant that catches the second door. Dossiq shipped four admin
		// surfaces reachable by URL because three of them had a gated menu
		// entry and a page that declared nothing — and the working nav half is
		// what hid it, since everyone checks visibility through the menu.
		for (const item of manifest.menu ?? []) {
			if (!item.permission) {
				continue
			}
			const page = pageById.get(item.route)
			expect(page, `menu entry "${item.id}" routes nowhere`).toBeDefined()
			expect(
				page.permission,
				`menu entry "${item.id}" is gated on "${item.permission}" but its page is not`,
			).toBe(item.permission)
		}
	})

	it('names a page that EXISTS, on every menu entry that routes at all', () => {
		// 🔴 A SECOND DEFECT, found while proving the first. `CnAppNav.itemTo`
		// builds `{ name: item.route }` — `route` is a route NAME, and the
		// route table names each route for its page `id`. Both admin entries
		// declared a PATH (`/admin/settings`), which names no route, so
		// vue-router's `resolve` threw and the entry rendered as a nav item
		// with no working link. Measured in the browser: four
		// `Object.resolve` errors on the console and no anchor for either
		// entry, for an ADMIN.
		//
		// That is why the fail-open filter went unnoticed. The visible symptom
		// was "a menu entry that does nothing", which reads as a dead link
		// rather than as an ungated one.
		for (const item of manifest.menu ?? []) {
			if (item.href || !item.route) {
				continue
			}
			expect(
				pageById.get(item.route),
				`menu entry "${item.id}" routes to "${item.route}", which is not a page id — CnAppNav builds { name: route } and vue-router cannot resolve it`,
			).toBeDefined()
		}
	})
})
