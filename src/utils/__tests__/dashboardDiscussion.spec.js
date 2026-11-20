/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * When a dashboard shows its discussion (openregister-leaf-integrations
 * tasks 4 to 6).
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-5
 */

import { describe, expect, it } from 'vitest'
import { discussionAvailable, isCollaborative } from '../dashboardDiscussion.js'

describe('discussionAvailable', () => {
	it('needs both OpenRegister and Talk', () => {
		expect(discussionAvailable({ openregister: '/apps/openregister', spreed: '/apps/spreed' })).toBe(true)
		expect(discussionAvailable({ openregister: '/apps/openregister' })).toBe(false)
		expect(discussionAvailable({ spreed: '/apps/spreed' })).toBe(false)
		expect(discussionAvailable(null)).toBe(false)
	})
})

describe('isCollaborative', () => {
	const personal = { id: 7, uuid: 'u-7', type: 'user', userId: 'alice' }

	it('is true for a group dashboard', () => {
		expect(isCollaborative({ dashboard: { id: 1, uuid: 'g', type: 'group_shared' } })).toBe(true)
	})

	it('is true for a dashboard shared with me, matched by id or uuid', () => {
		expect(isCollaborative({ dashboard: personal, sharedWithMe: [{ id: 7 }] })).toBe(true)
		expect(isCollaborative({ dashboard: personal, sharedWithMe: [{ uuid: 'u-7' }] })).toBe(true)
	})

	it('is true for my dashboard with shares, and false for my unshared one', () => {
		expect(isCollaborative({ dashboard: personal, ownShares: [{ shareType: 'user', shareWith: 'bob' }] })).toBe(true)
		expect(isCollaborative({ dashboard: personal, ownShares: [] })).toBe(false)
	})

	it('is false for an admin template and for no dashboard', () => {
		expect(isCollaborative({ dashboard: { id: 2, uuid: 't', type: 'admin_template' }, ownShares: [{}] })).toBe(false)
		expect(isCollaborative({ dashboard: null })).toBe(false)
	})
})
