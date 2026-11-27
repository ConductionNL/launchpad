/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The page registry: the dashboard view renders without waiting for a chunk,
 * the administration still loads on demand.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import { describe, expect, it, vi } from 'vitest'

const workspaceView = { name: 'WorkspaceApp', render: () => null }
vi.mock('../views/WorkspaceApp.vue', () => ({ default: workspaceView }))
vi.mock('../views/AdminPage.vue', () => ({ default: { name: 'AdminPage', render: () => null } }))

const { default: registry } = await import('../registry.js')

describe('page registry', () => {
	it('carries the dashboard view itself, so the first render needs no chunk', () => {
		expect(registry.WorkspaceApp.kind).toBe('page')
		expect(registry.WorkspaceApp.component).toBe(workspaceView)
	})

	it('keeps the administration page an on-demand chunk', () => {
		expect(registry.AdminPage.kind).toBe('page')
		expect(typeof registry.AdminPage.component.__asyncLoader).toBe('function')
	})
})
