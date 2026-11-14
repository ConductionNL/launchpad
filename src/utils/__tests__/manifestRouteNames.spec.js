/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every named route the manifest links to must be a route the router has.
 * `routesFromManifest` names one route per page id and nothing else, so an
 * unknown name only shows up at runtime as a vue-router MATCHER_NOT_FOUND
 * error in the browser console.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import { describe, expect, it } from 'vitest'
import manifest from '../../manifest.json'
import { routesFromManifest } from '../manifestRoutes.js'

/**
 * Collect every `{ name }` object held under a key ending in "route"
 * (route, viewAllRoute, ...), with the JSON path it was found at.
 *
 * @param {*}      node The manifest node to walk.
 * @param {string} path The JSON path of `node`.
 * @param {Array<object>} found Accumulator.
 *
 * @return {Array<{path: string, name: string}>} The named route references.
 */
function namedRouteRefs(node, path = '', found = []) {
	if (Array.isArray(node)) {
		node.forEach((child, i) => namedRouteRefs(child, `${path}[${i}]`, found))
	} else if (node && typeof node === 'object') {
		for (const [key, value] of Object.entries(node)) {
			if (/route$/i.test(key) && value && typeof value === 'object' && typeof value.name === 'string') {
				found.push({ path: `${path}.${key}`, name: value.name })
			}
			namedRouteRefs(value, `${path}.${key}`, found)
		}
	}
	return found
}

describe('manifest route names', () => {
	const routeNames = new Set(routesFromManifest(manifest, {}).map((r) => r.name).filter(Boolean))

	it('finds the named route references it checks', () => {
		expect(namedRouteRefs(manifest).length).toBeGreaterThan(0)
	})

	it('links widgets only to routes the router registers', () => {
		const unknown = namedRouteRefs(manifest).filter((ref) => !routeNames.has(ref.name))
		expect(unknown).toEqual([])
	})

	it('points every menu route at a registered route', () => {
		const unknown = (manifest.menu ?? [])
			.filter((item) => typeof item.route === 'string' && !routeNames.has(item.route))
			.map((item) => ({ id: item.id, route: item.route }))
		expect(unknown).toEqual([])
	})

	it('flags a name the router does not have', () => {
		const refs = namedRouteRefs({ widgets: [{ content: { route: { name: 'dashboards' } } }] })
		expect(refs.filter((ref) => !routeNames.has(ref.name))).toEqual([
			{ path: '.widgets[0].content.route', name: 'dashboards' },
		])
	})
})
