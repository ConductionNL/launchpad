/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard configuration offers a parent and a web address name when
 * editing, sends them with the save, and shows why a save was refused
 * (dashboard-tree-navigation REQ-TREEUI-003).
 */

import { describe, expect, it } from 'vitest'
import DashboardConfigModal from '../DashboardConfigModal.vue'

describe('DashboardConfigModal tree fields', () => {
	it('offers No parent plus the other dashboards, never itself', () => {
		const host = {
			dashboard: { uuid: 'hr' },
			parentOptions: [
				{ uuid: 'hr', name: 'HR' },
				{ uuid: 'it', name: 'IT' },
			],
		}
		const choices = DashboardConfigModal.computed.parentChoices.call(host)
		expect(choices.map((c) => c.uuid)).toEqual(['', 'it'])
	})

	it('declares the props the page passes', () => {
		expect(DashboardConfigModal.props.parentOptions).toBeDefined()
		expect(DashboardConfigModal.props.saveError).toBeDefined()
	})
})
