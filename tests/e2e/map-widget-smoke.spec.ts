/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright smoke test for the `map` widget (openspec change `map-support`).
 *
 * The data is real OpenRegister data: a throwaway register and schema with a
 * GeoJSON `location` property and three objects, created through
 * OpenRegister's API and removed in afterAll. A throwaway account places a
 * `map` widget on its fresh dashboard through LaunchPad's dashboard API,
 * pointed at that register and schema with `geoProperty` and `detailLink`.
 *
 * The test reads the dashboard as that account, waits for one Leaflet marker
 * per object, clicks one and checks the new tab opens the detail link of the
 * object whose name the marker carries.
 *
 * Map tiles come from external hosts, so every tile request is answered with
 * a local PNG: the test does not depend on the internet.
 *
 * Gate-19 @e2e traceability:
 *   @e2e map-widget::objects-with-a-location-appear-on-the-map
 *   @e2e map-widget::open-an-object-from-the-map
 *
 * @spec openspec/changes/map-support/specs/map-widget/spec.md
 */

import type { APIRequestContext, Route } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { solidPng } from './support/pngFixture.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const SLUG = `e2e-map-smoke-${STAMP}`
const OR_API = '/index.php/apps/openregister/api'
const DETAIL = '/apps/launchpad/#/map-smoke/{id}'

const PLACES: Array<{ title: string, lng: number, lat: number }> = [
	{ title: `Utrecht ${STAMP}`, lng: 5.1214, lat: 52.0907 },
	{ title: `Amersfoort ${STAMP}`, lng: 5.3878, lat: 52.1561 },
	{ title: `Zwolle ${STAMP}`, lng: 6.0830, lat: 52.5168 },
]

/** Tile hosts the map's base layers read from. */
const TILE_HOSTS = /tile\.openstreetmap\.org|service\.pdok\.nl|tile\.osm\.org/

const TILE = solidPng(256, 256, [220, 220, 220, 255])

let own: OwnDashboard | undefined
let admin: APIRequestContext
let schemaId: number | undefined
let registerId: number | undefined
const objects: Array<{ id: string, title: string }> = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()

	const schema = await admin.post(`${OR_API}/schemas`, {
		data: {
			title: `E2E map smoke ${STAMP}`,
			slug: SLUG,
			properties: {
				title: { type: 'string' },
				location: { type: 'object', format: 'geojson' },
			},
		},
	})
	expect(schema.status(), await schema.text()).toBeLessThan(300)
	schemaId = Number((await schema.json()).id)

	const register = await admin.post(`${OR_API}/registers`, {
		data: { title: `E2E map smoke ${STAMP}`, slug: SLUG, schemas: [schemaId] },
	})
	expect(register.status(), await register.text()).toBeLessThan(300)
	registerId = Number((await register.json()).id)

	for (const place of PLACES) {
		const res = await admin.post(`${OR_API}/objects/${SLUG}/${SLUG}`, {
			data: {
				title: place.title,
				location: { type: 'Point', coordinates: [place.lng, place.lat] },
			},
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		objects.push({ id: String((await res.json()).id), title: place.title })
	}

	own = await setUpOwnDashboard('e2e-map-smoke', `E2E map smoke ${STAMP}`)
	const placed = await own.owner.api.post(`${API}/dashboard/${own.id}/widgets`, {
		data: {
			widgetId: 'map',
			gridX: 0,
			gridY: 0,
			gridWidth: 8,
			gridHeight: 6,
			content: {
				autoFit: true,
				clustering: false,
				markers: {
					geoProperty: 'location',
					popupField: 'title',
					detailLink: DETAIL,
					dataSource: { register: SLUG, schema: SLUG },
				},
			},
		},
	})
	expect(placed.status(), await placed.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	if (admin === undefined) {
		return
	}
	for (const object of objects) {
		await admin.delete(`${OR_API}/objects/${SLUG}/${SLUG}/${object.id}`)
	}
	if (registerId !== undefined) {
		await admin.delete(`${OR_API}/registers/${registerId}`)
	}
	if (schemaId !== undefined) {
		await admin.delete(`${OR_API}/schemas/${schemaId}`)
	}
	await admin.dispose()
})

test('the map draws the register objects and a clicked one opens its detail link', async ({ browser }) => {
	if (own === undefined) {
		throw new Error('set-up did not create the dashboard')
	}
	const session = await loginAs(browser, own.owner.username, own.owner.password)
	try {
		let stubbedTiles = 0
		await session.context.route(TILE_HOSTS, (route: Route) => {
			stubbedTiles++
			return route.fulfill({ status: 200, contentType: 'image/png', body: TILE })
		})

		const { page } = session
		await page.setViewportSize({ width: 1600, height: 1000 })
		const read = page.waitForResponse((res) =>
			res.url().includes(`/apps/openregister/api/objects/${SLUG}/${SLUG}`)
			&& res.request().method() === 'GET', { timeout: 30_000 })
		await page.goto('/index.php/apps/launchpad/')
		expect((await read).status()).toBe(200)

		const map = page.locator('.cn-map-widget').first()
		await expect(map).toBeVisible({ timeout: 30_000 })
		const markers = map.locator('.leaflet-overlay-pane path.leaflet-interactive')
		await expect(markers).toHaveCount(PLACES.length, { timeout: 30_000 })
		await expect(map.getByTestId('cn-map-markers-failed')).toHaveCount(0)
		await expect(map.getByTestId('cn-map-markers-empty')).toHaveCount(0)
		expect(stubbedTiles, 'the base map asked for tiles').toBeGreaterThan(0)

		const marker = markers.first()
		await marker.hover()
		const tooltip = map.locator('.leaflet-tooltip').first()
		await expect(tooltip).toBeVisible()
		const label = (await tooltip.innerText()).trim()
		const clicked = objects.find((object) => object.title === label)
		expect(clicked, `the hovered marker is named "${label}"`).toBeDefined()

		const [detail] = await Promise.all([
			session.context.waitForEvent('page'),
			marker.click(),
		])
		await detail.waitForURL(/map-smoke/)
		const opened = new URL(detail.url())
		// The detail link keeps the page's own address, which is the dashboard's canonical slug path.
		expect(opened.pathname).toMatch(/\/apps\/launchpad(\/[a-z0-9-]+)?\/?$/)
		expect(opened.hash).toBe(`#/map-smoke/${encodeURIComponent(clicked!.id)}`)
		// The dashboard tab stays where it was.
		expect(new URL(page.url()).hash).not.toContain('map-smoke')
	} finally {
		await session.context.close()
	}
})
