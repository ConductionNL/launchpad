/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The shared placeholder fixture through nextcloud-vue's resolver, with the
 * same registry copy and launchpad's own types' shapes the server's twin
 * (FirstPaintShell::kindOf, tests/Unit/Service/FirstPaintShellPlaceholderTest.php)
 * uses, so the shell and the mounted page draw the same shape for every
 * placement (widget-declared-placeholders D1).
 *
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-3
 */

// The library barrel is stubbed in Vitest (vitest.config.js); the resolver is a plain module.
import { resolveWidgetPlaceholder } from '@conduction/nextcloud-vue/src/utils/resolveWidgetPlaceholder.js'
import { describe, expect, it } from 'vitest'
import kinds from '../../../lib/Service/first-paint-kinds.json'
import registry from '../../../lib/Service/widget-placeholders.json'
import fixture from '../../../tests/fixtures/widget-placeholders.json'

/**
 * The registry entry the mounted page has for a type: the library's
 * declaration, or the shape of a type launchpad registers itself.
 *
 * @param {string} type The widget type.
 * @return {object|undefined} An entry with `placeholder`, or undefined.
 */
function registryEntry(type) {
	const declared = registry[type] ?? kinds.kinds[type]
	return declared === undefined ? undefined : { placeholder: declared }
}

describe('the shared placeholder fixture', () => {
	it.each(fixture.cases.map((c) => [c.name, c]))('%s', (_name, c) => {
		const resolved = resolveWidgetPlaceholder(c.placement ?? {}, registryEntry(c.widgetId), c.catalogue)
		expect(resolved.shape).toBe(c.expected)
	})
})
