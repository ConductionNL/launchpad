/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The row tracks of a dashboard that fills the height, from nextcloud-vue's
 * own CnDashboardGrid `fillRowTracks`, against the fixture the server's twin
 * (FirstPaintShell::fillRowTracks) is tested on too, so the first-paint shell
 * shares the height the way the filled grid does after the hand-over
 * (first-paint-skeleton D3). The function is not in the package's exports,
 * so it is read from the component's source.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { describe, expect, it } from 'vitest'
import fixture from '../../../tests/fixtures/first-paint-fill-tracks.json'

const require = createRequire(import.meta.url)
const packageRoot = dirname(require.resolve('@conduction/nextcloud-vue/package.json'))
const source = readFileSync(join(packageRoot, 'src/components/CnDashboardGrid/CnDashboardGrid.vue'), 'utf8')
const body = source.match(/export function fillRowTracks[\s\S]*?\n}\n/)?.[0]?.replace('export ', '')
// eslint-disable-next-line no-new-func -- the library's own function, read from its source
const fillRowTracks = body ? new Function(`${body}; return fillRowTracks`)() : null

describe('fill row tracks fixture (first-paint-skeleton D3)', () => {
	it('finds the library function', () => {
		expect(typeof fillRowTracks).toBe('function')
	})

	for (const testCase of fixture.cases) {
		it(testCase.name, () => {
			const layout = testCase.cells.map(([x, y, w, h, fitRows]) => ({ gridX: x, gridY: y, gridWidth: w, gridHeight: h, fitRows }))
			expect(fillRowTracks(layout).map((track) => track.replace(/\s+/g, ''))).toEqual(testCase.tracks)
		})
	}
})
