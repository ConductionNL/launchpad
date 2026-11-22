/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Launchpad's page header holds only its own actions (design-system #1192,
 * DEMO-SEED "No buildiq control"): the manifest opts out of the Buildiq
 * in-app edit button that CnAppRoot adds where Buildiq is enabled.
 *
 * @spec openspec/changes/no-buildiq-edit-control/specs/runtime-shell/spec.md
 */

import { describe, expect, it } from 'vitest'
import manifest from '../manifest.json'

describe('the launchpad manifest', () => {
	it('opts out of the Buildiq edit button', () => {
		expect(manifest.openbuildEditable).toBe(false)
	})
})
