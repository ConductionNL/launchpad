/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The page registry: the dashboard view renders without waiting for a chunk,
 * and no admin page is an app page (the admin page is in Nextcloud's settings,
 * REQ-ADM-035).
 *
 * @spec openspec/specs/runtime-shell/spec.md
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-no-admin-page-in-the-apps-router-or-manifest-req-adm-035
 */

import { describe, expect, it, vi } from 'vitest'

const workspaceView = { name: 'WorkspaceApp', render: () => null }
vi.mock('../views/WorkspaceApp.vue', () => ({ default: workspaceView }))

const { default: registry } = await import('../registry.js')

describe('page registry', () => {
	it('carries the dashboard view itself, so the first render needs no chunk', () => {
		expect(registry.WorkspaceApp.kind).toBe('page')
		expect(registry.WorkspaceApp.component).toBe(workspaceView)
	})

	it('holds no admin page', () => {
		expect(Object.keys(registry).filter((name) => /admin/i.test(name))).toEqual([])
	})
})
