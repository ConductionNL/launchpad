/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `resolveWidgetTitle` — the single rule shared by the
 * grid header (`WidgetWrapper.vue`) and quick search
 * (`useTileSearchHost.js`).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	dashboardWidgetRegistry: {
		search: { displayName: 'Search' },
		chart: { displayName: 'Chart' },
	},
}))

const { resolveWidgetTitle } = await import('../widgetTitle.js')

const CATALOG = [
	{ id: 'activity', title: 'Recent activity' },
	{ id: 'pipelinq_deals_overview_widget', title: 'Deals overview' },
	{ id: 'legacy-direct', title: 'Legacy direct widget' },
]

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('resolveWidgetTitle', () => {
	it('uses a launcher tile’s own title', () => {
		expect(
			resolveWidgetTitle(
				{ tileType: 'custom', tileTitle: 'Zaaksysteem' },
				CATALOG,
			),
		).toBe('Zaaksysteem')
	})

	it('falls back to "Tile" for a titleless tile', () => {
		expect(resolveWidgetTitle({ tileType: 'custom' }, CATALOG)).toBe('Tile')
	})

	it('prefers the author’s explicit override over everything else', () => {
		expect(
			resolveWidgetTitle(
				{ widgetId: 'nc-widget', customTitle: 'Our pipeline', content: { widgetId: 'activity' } },
				CATALOG,
			),
		).toBe('Our pipeline')
	})

	it('treats an empty customTitle as "not set" rather than blanking the header', () => {
		expect(
			resolveWidgetTitle(
				{ widgetId: 'legacy-direct', customTitle: '' },
				CATALOG,
			),
		).toBe('Legacy direct widget')
	})

	it('resolves a placement that IS a Nextcloud Dashboard widget', () => {
		expect(resolveWidgetTitle({ widgetId: 'legacy-direct' }, CATALOG)).toBe(
			'Legacy direct widget',
		)
	})

	/*
	 * The defect this rule exists for. An `nc-widget` placement's own
	 * `widgetId` is the literal string `nc-widget`, which is never in the
	 * catalog; the proxied widget's id lives in `content.widgetId`. Measured on
	 * a real dashboard 2026-08-19: six such placements all resolved to
	 * "Widget", rendering their real titles on screen while being unfindable
	 * by quick search.
	 */
	it('resolves the widget an nc-widget placement PROXIES (the unfindable-tile bug)', () => {
		expect(
			resolveWidgetTitle(
				{ widgetId: 'nc-widget', content: { widgetId: 'pipelinq_deals_overview_widget' } },
				CATALOG,
			),
		).toBe('Deals overview')

		expect(
			resolveWidgetTitle(
				{ widgetId: 'nc-widget', content: { widgetId: 'activity' } },
				CATALOG,
			),
		).toBe('Recent activity')
	})

	it('falls back to the widget type’s display name', () => {
		// The `search` widget itself shipped a header reading "Widget" before
		// this step existed.
		expect(resolveWidgetTitle({ widgetId: 'search' }, CATALOG)).toBe('Search')
		expect(resolveWidgetTitle({ widgetId: 'chart' }, CATALOG)).toBe('Chart')
	})

	it('falls back to the generic word only when nothing else resolves', () => {
		expect(resolveWidgetTitle({ widgetId: 'totally-unknown' }, CATALOG)).toBe(
			'Widget',
		)
	})

	it('tolerates a missing placement or catalog', () => {
		expect(resolveWidgetTitle(null, CATALOG)).toBe('Widget')
		expect(resolveWidgetTitle({ widgetId: 'search' }, null)).toBe('Search')
		expect(resolveWidgetTitle({ widgetId: 'unknown' }, undefined)).toBe(
			'Widget',
		)
	})

	it('does not mistake a proxied id for a direct one when both could match', () => {
		// A placement whose OWN id resolves must win over its content id, so a
		// legacy direct placement never picks up the wrong name.
		expect(
			resolveWidgetTitle(
				{ widgetId: 'legacy-direct', content: { widgetId: 'activity' } },
				CATALOG,
			),
		).toBe('Legacy direct widget')
	})
})
