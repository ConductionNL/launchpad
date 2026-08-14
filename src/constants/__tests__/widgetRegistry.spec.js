/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit test for `widgetRegistry.js`. LaunchPad's registry no longer
 * hardcodes per-type entries — it CONSUMES nc-vue's communal
 * `dashboardWidgetRegistry` and applies a thin overlay (renderer overrides for
 * the host-wrapped types, form overrides for types the communal registry leaves
 * form-less, displayName localisation). These tests assert that consumption +
 * overlay contract. Per-type `defaultContent` values are nc-vue's
 * responsibility and are tested there, not re-frozen here.
 */

import { beforeEach, describe, expect, it } from 'vitest'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('widgetRegistry — consumes the communal catalog', () => {
	it('exposes the communal types with a renderer, form, and object defaultContent', async () => {
		const { widgetRegistry } = await import('../widgetRegistry.js')
		for (const type of [
			'label',
			'text',
			'image',
			'divider',
			'header',
			'links',
			'tile',
		]) {
			expect(widgetRegistry[type], `missing ${type}`).toBeDefined()
			expect(widgetRegistry[type].renderer, `${type} renderer`).toBeTruthy()
			expect(widgetRegistry[type].form, `${type} form`).toBeTruthy()
			expect(
				typeof widgetRegistry[type].defaultContent,
				`${type} defaultContent`,
			).toBe('object')
		}
	})

	it('listWidgetTypes() surfaces the addable communal types', async () => {
		const { listWidgetTypes } = await import('../widgetRegistry.js')
		const types = listWidgetTypes()
		for (const type of [
			'label',
			'text',
			'image',
			'link',
			'divider',
			'header',
			'links',
			'container',
			'tile',
		]) {
			expect(types, `picker should offer ${type}`).toContain(type)
		}
	})

	it('includes the analytics widgets from the shared catalog (OpenBuild parity)', async () => {
		const { listWidgetTypes } = await import('../widgetRegistry.js')
		const types = listWidgetTypes()
		for (const type of [
			'stat',
			'delta',
			'gauge',
			'object-list',
			'chart',
			'stats-block',
			'table',
		]) {
			expect(types, `picker should offer ${type}`).toContain(type)
		}
	})

	it('getWidgetTypeEntry returns null for unknown type', async () => {
		const { getWidgetTypeEntry } = await import('../widgetRegistry.js')
		expect(getWidgetTypeEntry('does-not-exist')).toBeNull()
	})

	it('getDefaultContent returns a fresh copy', async () => {
		const { getDefaultContent } = await import('../widgetRegistry.js')
		const a = getDefaultContent('label')
		const b = getDefaultContent('label')
		expect(a).toEqual(b)
		expect(a).not.toBe(b)
	})

	it('resolves a RENDERER for nc-widget so proxied placements are not blank', async () => {
		// Regression: the shared bundle exports CnNcWidgetWidget but (as of
		// beta.155) omits its self-registration in the published browser dist,
		// so `nc-widget` placements fell onto WidgetRenderer's broken legacy
		// path and rendered blank. widgetRegistry.js registers the renderer.
		const { getWidgetTypeEntry } = await import('../widgetRegistry.js')
		const entry = getWidgetTypeEntry('nc-widget')
		expect(entry, 'nc-widget must resolve').toBeTruthy()
		expect(entry.renderer, 'nc-widget needs a renderer').toBeTruthy()
	})
})

describe('widgetRegistry — LaunchPad overlay', () => {
	it('overrides the renderer for host-wrapped types (link/container/chart/stats-block)', async () => {
		const { getWidgetTypeEntry } = await import('../widgetRegistry.js')
		// The launchpad hosts render an identifiable wrapper, distinct from the
		// communal stub renderer named `<type>-renderer`.
		for (const type of ['link', 'container', 'chart', 'stats-block']) {
			const entry = getWidgetTypeEntry(type)
			expect(entry, `missing ${type}`).toBeTruthy()
			expect(entry.renderer, `${type} renderer`).toBeTruthy()
			expect(
				entry.renderer.name,
				`${type} should use a LaunchPad host renderer`,
			).not.toBe(`${type}-renderer`)
		}
	})

	it('supplies a form for types the communal registry leaves form-less', async () => {
		const { getWidgetTypeEntry, listWidgetTypes } =
			await import('../widgetRegistry.js')
		const types = listWidgetTypes()
		for (const type of ['calendar', 'people', 'spend-analytics', 'nc-widget']) {
			expect(types, `picker should offer ${type}`).toContain(type)
			expect(
				getWidgetTypeEntry(type).form,
				`${type} form override`,
			).toBeTruthy()
		}
	})

	it('localises the displayName through t(launchpad, …)', async () => {
		let seen = null
		globalThis.t = (app, key) => {
			if (app === 'launchpad') {
				seen = key
			}
			return key
		}
		const { getWidgetTypeEntry } = await import('../widgetRegistry.js')
		const entry = getWidgetTypeEntry('text')
		expect(typeof entry.displayName).toBe('string')
		expect(
			seen,
			'displayName should pass through t(launchpad, …)',
		).not.toBeNull()
	})
})
