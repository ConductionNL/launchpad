/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The shared placeholder fixture through the resolver the card uses
 * (src/utils/widgetPlaceholder.js: nextcloud-vue's resolver with the same registry copy and launchpad's own types' shapes the server's twin
 * (FirstPaintShell::kindOf, tests/Unit/Service/FirstPaintShellPlaceholderTest.php)
 * uses), so the shell and the mounted page draw the same shape for every
 * placement (widget-declared-placeholders D1).
 *
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-3
 */

import { describe, expect, it } from 'vitest'
import fixture from '../../../tests/fixtures/widget-placeholders.json'
import { placeholderRegistryEntry, placementPlaceholder } from '../widgetPlaceholder.js'

describe('the shared placeholder fixture', () => {
	it.each(fixture.cases.map((c) => [c.name, c]))('%s', (_name, c) => {
		const placement = { ...(c.placement ?? {}), widgetId: c.widgetId }
		expect(placementPlaceholder(placement, c.catalogue).shape).toBe(c.expected)
	})
})

describe('placeholderRegistryEntry', () => {
	it('has no entry without a type', () => {
		expect(placeholderRegistryEntry(undefined)).toBeUndefined()
		expect(placeholderRegistryEntry('')).toBeUndefined()
	})

	it('has no entry for a type nobody declares', () => {
		expect(placeholderRegistryEntry('no-such-widget-type')).toBeUndefined()
	})

	it("takes the library's declaration first, then launchpad's own type", () => {
		expect(placeholderRegistryEntry('chart')).toEqual({ placeholder: { shape: 'block' } })
		expect(placeholderRegistryEntry('section-heading')).toEqual({ placeholder: 'heading' })
	})

	it('does not read inherited object keys as types', () => {
		expect(placeholderRegistryEntry('toString')).toBeUndefined()
	})
})

describe('placementPlaceholder', () => {
	it('draws rows for a placement nobody declares', () => {
		expect(placementPlaceholder({ widgetId: 'no-such-widget-type' }, null)).toEqual({ shape: 'rows' })
		expect(placementPlaceholder(null, undefined)).toEqual({ shape: 'rows' })
	})

	it("takes the placement's own declaration over the registry", () => {
		expect(placementPlaceholder({ widgetId: 'chart', placeholder: 'lines' }, null).shape).toBe('lines')
	})

	it("takes the catalogue's declaration for an undeclared Nextcloud widget", () => {
		expect(placementPlaceholder({ widgetId: 'nc-some-widget' }, { placeholder: 'block' }).shape).toBe('block')
	})
})
