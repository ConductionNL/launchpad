/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the app-tile specs (icon-only tiles, the tile form, the Add
 * tiles dialog): place a one-cell tile at an exact spot, read a tile's
 * stored content back, and find a rendered tile by its placement.
 *
 * Nothing here asserts a scenario; it seeds and re-reads.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { readPlacements } from './ownDashboard.ts'
import { API } from './principals.ts'

/** What a seeded tile is made of. */
export interface AppTileSeed {
	title: string
	x: number
	y: number
	/** Hex background; the tile form's colour before REQ-ICT-004. */
	bgColor?: string
	icon?: string
	iconType?: string
	linkType?: string
	linkValue?: string
	content?: Record<string, unknown>
}

/**
 * Place a tile and make it one cell at an exact spot. The tile endpoint
 * stores 2 x 2, so the tile is created on a parking row far below and then
 * moved and shrunk with a placement update (as responsive-grid-breakpoints
 * does).
 *
 * @param api The owner's context.
 * @param dashboardId The dashboard.
 * @param seed The tile.
 * @return The placement id.
 */
export async function placeOneCellTile(
	api: APIRequestContext,
	dashboardId: number,
	seed: AppTileSeed,
): Promise<number> {
	const created = await api.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: {
			title: seed.title,
			icon: seed.icon ?? '📁',
			iconType: seed.iconType ?? 'emoji',
			bgColor: seed.bgColor ?? '#3b82f6',
			textColor: '#ffffff',
			linkType: seed.linkType ?? 'url',
			linkValue: seed.linkValue ?? 'https://example.org/',
			gridX: 0,
			gridY: 40 + 2 * seed.y + seed.x,
			...(seed.content ? { content: seed.content } : {}),
		},
	})
	expect(created.status(), await created.text()).toBe(201)
	const id = Number((await created.json()).id)
	const moved = await api.put(`${API}/widgets/${id}`, {
		data: { gridX: seed.x, gridY: seed.y, gridWidth: 1, gridHeight: 1 },
	})
	expect(moved.status(), await moved.text()).toBe(200)
	if (seed.content) {
		// The create path may drop content keys it does not know; the update
		// path stores the blob as given, so the GIVEN is certain either way.
		const stored = await api.put(`${API}/widgets/${id}`, {
			data: { content: seed.content },
		})
		expect(stored.status(), await stored.text()).toBe(200)
	}
	return id
}

/**
 * A placement's stored content, decoded.
 *
 * @param api A context that may read the dashboard.
 * @param dashboardId The dashboard.
 * @param placementId The placement.
 * @return The content blob.
 */
export async function storedContent(
	api: APIRequestContext,
	dashboardId: number,
	placementId: number,
): Promise<Record<string, any>> {
	const placement = (await readPlacements(api, dashboardId)).find(
		(p) => Number(p.id) === placementId,
	)
	expect(placement, `placement ${placementId} is not on the dashboard`).toBeTruthy()
	const content = placement!.content
	return typeof content === 'string' ? JSON.parse(content || '{}') : (content ?? {})
}

/**
 * The rendered tile of a placement.
 *
 * @param page The page.
 * @param placementId The placement.
 * @return The `.tile-widget` element inside its grid item.
 */
export function tileOf(page: Page, placementId: number): Locator {
	return page.locator(`.grid-stack-item[gs-id="${placementId}"] .tile-widget`)
}

/**
 * The computed value of a CSS custom property on the document root.
 *
 * @param page The page.
 * @param token The property, e.g. `--sc-primary`.
 * @return The value as the browser resolves it, trimmed.
 */
export async function rootToken(page: Page, token: string): Promise<string> {
	return page.evaluate(
		(name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim(),
		token,
	)
}

/**
 * Resolve any CSS colour to the `rgb(...)` form computed styles use.
 *
 * @param page The page.
 * @param colour A CSS colour.
 * @return The computed colour.
 */
export async function computedColour(page: Page, colour: string): Promise<string> {
	return page.evaluate((value) => {
		const probe = document.createElement('span')
		probe.style.color = value
		document.body.appendChild(probe)
		const resolved = getComputedStyle(probe).color
		probe.remove()
		return resolved
	}, colour)
}
