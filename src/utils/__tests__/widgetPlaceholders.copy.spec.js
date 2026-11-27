/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The committed copy of nextcloud-vue's widget-placeholders.json is the
 * installed package's file, and the fallback table names no type that copy
 * declares (widget-declared-placeholders D3, D4).
 *
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-2
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-4
 */

import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import kinds from '../../../lib/Service/first-paint-kinds.json'
import copy from '../../../lib/Service/widget-placeholders.json'

const require = createRequire(import.meta.url)

describe('lib/Service/widget-placeholders.json', () => {
	it('equals the installed package file (run `npm run prebuild` after a library upgrade)', () => {
		const installed = JSON.parse(readFileSync(require.resolve('@conduction/nextcloud-vue/widget-placeholders.json'), 'utf8'))
		expect(copy).toEqual(installed)
	})

	it('has the installed package\'s placeholder CSS next to it (inlined by the first-paint shell)', () => {
		const installed = readFileSync(require.resolve('@conduction/nextcloud-vue/css/widget-placeholder.css'), 'utf8')
		expect(readFileSync(join(__dirname, '../../../lib/Service/widget-placeholder.css'), 'utf8')).toBe(installed)
	})

	it('leaves no fallback entry for a declared type', () => {
		const shadowed = Object.keys(kinds.kinds).filter((type) => Object.hasOwn(copy, type))
		expect(shadowed).toEqual([])
		expect(kinds.default).toBe('rows')
	})
})
