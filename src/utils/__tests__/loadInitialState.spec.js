/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `loadInitialState`. Covers REQ-INIT-002, REQ-INIT-003,
 * REQ-INIT-004, REQ-INIT-005:
 *  - reader fills defaults for keys the server omitted (no `undefined`)
 *  - reader logs a console warning on schema-version mismatch
 *  - provide/inject pipe-through works for every workspace key
 *  - cloning an injected value into a local ref does not leak to siblings
 *
 * `@nextcloud/initial-state` is mocked at module-resolution time so each
 * test controls exactly which keys "the server pushed".
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

let pushedState = {}

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (_app, key, fallback) => {
		if (Object.hasOwn(pushedState, key)) {
			return pushedState[key]
		}
		return fallback
	},
}))

beforeEach(() => {
	pushedState = {}
	vi.resetModules()
})

afterEach(() => {
	vi.restoreAllMocks()
})

describe('loadInitialState', () => {
	it('fills defaults for every workspace key the server omitted', async () => {
		// Server pushes only the schema version — every other key is missing.
		pushedState = { _schemaVersion: 1 }

		const { loadInitialState } = await import('../loadInitialState.js')
		const state = loadInitialState('workspace')

		expect(state.widgets).toEqual([])
		expect(state.layout).toEqual([])
		expect(state.primaryGroup).toBe('default')
		expect(state.primaryGroupName).toBe('')
		expect(state.isAdmin).toBe(false)
		expect(state.activeDashboardId).toBe('')
		expect(state.dashboardSource).toBe('group')
		expect(state.groupDashboards).toEqual([])
		expect(state.userDashboards).toEqual([])
		expect(state.allowUserDashboards).toBe(false)

		for (const value of Object.values(state)) {
			expect(value).not.toBeUndefined()
		}
	})

	it('returns the server-pushed values when present', async () => {
		pushedState = {
			_schemaVersion: 1,
			widgets: [{ id: 'w1', title: 'Calendar' }],
			layout: [{ widgetId: 'w1', x: 0, y: 0 }],
			primaryGroup: 'engineering',
			primaryGroupName: 'Engineering',
			isAdmin: true,
			activeDashboardId: 'dash-uuid-1',
			dashboardSource: 'user',
			groupDashboards: [{ id: 'gd-1', name: 'Team', icon: '' }],
			userDashboards: [{ id: 'ud-1', name: 'Mine', icon: '' }],
			allowUserDashboards: true,
		}

		const { loadInitialState } = await import('../loadInitialState.js')
		const state = loadInitialState('workspace')

		expect(state.primaryGroup).toBe('engineering')
		expect(state.isAdmin).toBe(true)
		expect(state.activeDashboardId).toBe('dash-uuid-1')
		expect(state.widgets).toHaveLength(1)
		expect(state.userDashboards[0].id).toBe('ud-1')
	})

	it('logs a console warning on schema-version mismatch', async () => {
		pushedState = { _schemaVersion: 99 }
		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

		const { loadInitialState } = await import('../loadInitialState.js')
		loadInitialState('workspace')

		expect(warn).toHaveBeenCalled()
		const message = warn.mock.calls[0][0]
		expect(message).toMatch(/LaunchPad initial-state schema mismatch/)
		expect(message).toMatch(/server v99/)
		expect(message).toMatch(/client v3/)
	})

	it('does not warn when the schema versions match', async () => {
		pushedState = { _schemaVersion: 3 }
		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

		const { loadInitialState } = await import('../loadInitialState.js')
		loadInitialState('workspace')

		expect(warn).not.toHaveBeenCalled()
	})

	it('reads the public start page token, empty when the server pushed none', async () => {
		pushedState = { publicStartToken: 'Bib1Noord', _schemaVersion: 3 }
		const { loadInitialState } = await import('../loadInitialState.js')
		expect(loadInitialState('public-start')).toEqual({ publicStartToken: 'Bib1Noord' })

		pushedState = {}
		expect(loadInitialState('public-start')).toEqual({ publicStartToken: '' })
	})

	it('throws on an unknown page identifier', async () => {
		const { loadInitialState } = await import('../loadInitialState.js')
		expect(() => loadInitialState('not-a-page')).toThrow(/unknown page/)
	})

	it('exposes every key via provide/inject for descendant components (REQ-INIT-004)', async () => {
		pushedState = {
			_schemaVersion: 1,
			widgets: [{ id: 'w1' }],
			layout: [{ widgetId: 'w1' }],
			primaryGroup: 'g',
			primaryGroupName: 'G',
			isAdmin: true,
			activeDashboardId: 'a-1',
			dashboardSource: 'group',
			groupDashboards: [],
			userDashboards: [],
			allowUserDashboards: true,
		}

		const { loadInitialState } = await import('../loadInitialState.js')
		const state = loadInitialState('workspace')

		const Child = {
			name: 'Child',
			inject: [
				'widgets',
				'layout',
				'primaryGroup',
				'primaryGroupName',
				'isAdmin',
				'activeDashboardId',
				'dashboardSource',
				'groupDashboards',
				'userDashboards',
				'allowUserDashboards',
			],
			template: '<div />',
		}

		const Parent = {
			name: 'Parent',
			provide() {
				return { ...state }
			},
			components: { Child },
			template: '<Child />',
		}

		const wrapper = mount(Parent)
		const child = wrapper.findComponent(Child).vm

		expect(child.widgets).toEqual([{ id: 'w1' }])
		expect(child.isAdmin).toBe(true)
		expect(child.primaryGroup).toBe('g')
		expect(child.activeDashboardId).toBe('a-1')
		expect(child.dashboardSource).toBe('group')
		expect(child.allowUserDashboards).toBe(true)
	})

	it('mutating a cloned ref does not leak to a sibling (REQ-INIT-005)', async () => {
		pushedState = {
			_schemaVersion: 1,
			widgets: [],
			layout: [{ widgetId: 'w1', x: 0 }],
			primaryGroup: 'g',
			primaryGroupName: 'G',
			isAdmin: false,
			activeDashboardId: '',
			dashboardSource: 'group',
			groupDashboards: [],
			userDashboards: [],
			allowUserDashboards: false,
		}

		const { loadInitialState } = await import('../loadInitialState.js')
		const state = loadInitialState('workspace')

		const Mutator = {
			name: 'Mutator',
			inject: ['layout'],
			data() {
				return { localLayout: [...this.layout] }
			},
			methods: {
				bump() {
					this.localLayout.push({ widgetId: 'w2', x: 99 })
				},
			},
			template: '<div />',
		}

		const Sibling = {
			name: 'Sibling',
			inject: ['layout'],
			template: '<div />',
		}

		const Parent = {
			name: 'Parent',
			provide() {
				return { ...state }
			},
			components: { Mutator, Sibling },
			template: '<div><Mutator /><Sibling /></div>',
		}

		const wrapper = mount(Parent)
		const mutator = wrapper.findComponent(Mutator).vm
		const sibling = wrapper.findComponent(Sibling).vm

		mutator.bump()
		await nextTick()

		expect(mutator.localLayout).toHaveLength(2)
		expect(sibling.layout).toHaveLength(1)
		expect(sibling.layout[0].widgetId).toBe('w1')
	})

	it('fills defaults for every admin key the server omitted', async () => {
		pushedState = { _schemaVersion: 3 }

		const { loadInitialState } = await import('../loadInitialState.js')
		const state = loadInitialState('admin')

		expect(state.allGroups).toEqual([])
		expect(state.configuredGroups).toEqual([])
		expect(state.widgets).toEqual([])
		expect(state.allowUserDashboards).toBe(false)
		// REQ-LBN-004: createFile extension allow-list defaults to the
		// curated list when the admin has not customised it.
		expect(state.linkCreateFileExtensions).toEqual([
			'txt',
			'md',
			'docx',
			'xlsx',
			'csv',
			'odt',
		])
		for (const value of Object.values(state)) {
			expect(value).not.toBeUndefined()
		}
	})

	it('exports INITIAL_STATE_SCHEMA_VERSION matching the PHP constant (3)', async () => {
		const mod = await import('../loadInitialState.js')
		expect(mod.INITIAL_STATE_SCHEMA_VERSION).toBe(3)
	})

	describe('quicksearchFallbackTarget (tile-quick-search REQ-QSEARCH-004)', () => {
		it('defaults to "none" when the server omits the key (older deploy)', async () => {
			pushedState = { _schemaVersion: 3 }
			const { loadInitialState } = await import('../loadInitialState.js')
			const state = loadInitialState('workspace')
			expect(state.quicksearchFallbackTarget).toBe('none')
		})

		it('returns the server-pushed value when present', async () => {
			pushedState = {
				_schemaVersion: 3,
				quicksearchFallbackTarget: 'unified-search',
			}
			const { loadInitialState } = await import('../loadInitialState.js')
			const state = loadInitialState('workspace')
			expect(state.quicksearchFallbackTarget).toBe('unified-search')
		})
	})
})

/**
 * @spec openspec/changes/dashboard-first-load-from-initial-state/tasks.md#task-4
 */
describe('takeDashboardFirstLoad', () => {
	it('hands out the first load once, then null', async () => {
		const firstLoad = { visible: { items: [], quota: null }, active: { dashboard: { uuid: 'a' } } }
		pushedState = { dashboardFirstLoad: firstLoad }

		const { takeDashboardFirstLoad } = await import('../loadInitialState.js')

		expect(takeDashboardFirstLoad()).toEqual(firstLoad)
		expect(takeDashboardFirstLoad()).toBeNull()
	})

	it('is null when the page did not carry it', async () => {
		const { takeDashboardFirstLoad, loadInitialState } = await import('../loadInitialState.js')

		expect(loadInitialState('workspace').dashboardFirstLoad).toBeNull()
		expect(takeDashboardFirstLoad()).toBeNull()
	})

	it('is null for a value without the visible list', async () => {
		pushedState = { dashboardFirstLoad: { active: null } }

		const { takeDashboardFirstLoad } = await import('../loadInitialState.js')

		expect(takeDashboardFirstLoad()).toBeNull()
	})

	it('gives a missing active dashboard as null', async () => {
		pushedState = { dashboardFirstLoad: { visible: [] } }

		const { takeDashboardFirstLoad } = await import('../loadInitialState.js')

		expect(takeDashboardFirstLoad()).toEqual({ visible: [], active: null })
	})
})

/**
 * @spec openspec/changes/org-navigation-from-initial-state/tasks.md#task-4
 */
describe('takeOrgNavigation', () => {
	it('hands out the org navigation once, then null', async () => {
		pushedState = { orgNavigation: { position: 'left', tree: [{ id: 'n1' }], language: 'nl' } }

		const { takeOrgNavigation } = await import('../loadInitialState.js')

		expect(takeOrgNavigation()).toEqual({ position: 'left', tree: [{ id: 'n1' }], language: 'nl' })
		expect(takeOrgNavigation()).toBeNull()
	})

	it('is null when the page did not carry it', async () => {
		const { takeOrgNavigation, loadInitialState } = await import('../loadInitialState.js')

		expect(loadInitialState('workspace').orgNavigation).toBeNull()
		expect(takeOrgNavigation()).toBeNull()
	})

	it('is null for a value without a position or a tree', async () => {
		pushedState = { orgNavigation: { position: 'left', tree: 'not a list' } }

		const { takeOrgNavigation } = await import('../loadInitialState.js')

		expect(takeOrgNavigation()).toBeNull()
	})
})

/**
 * @spec openspec/changes/workspace-boot-reads-from-initial-state/tasks.md#task-4
 */
describe('takeBootRead', () => {
	it('hands out each part once, then undefined', async () => {
		pushedState = { workspaceBootReads: { tiles: [{ id: 1 }], defaultDashboard: { uuid: 'u' } } }

		const { takeBootRead } = await import('../loadInitialState.js')

		expect(takeBootRead('tiles')).toEqual([{ id: 1 }])
		expect(takeBootRead('tiles')).toBeUndefined()
		expect(takeBootRead('defaultDashboard')).toEqual({ uuid: 'u' })
	})

	it('is undefined for a part the page did not carry, and without the key', async () => {
		pushedState = { workspaceBootReads: { tiles: [] } }
		const { takeBootRead, loadInitialState } = await import('../loadInitialState.js')
		expect(takeBootRead('pendingAcknowledgements')).toBeUndefined()

		vi.resetModules()
		pushedState = {}
		const fresh = await import('../loadInitialState.js')
		expect(fresh.loadInitialState('workspace').workspaceBootReads).toBeNull()
		expect(fresh.takeBootRead('tiles')).toBeUndefined()
		expect(loadInitialState).toBeTypeOf('function')
	})
})

/**
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-4
 */
describe('takeRuntimeManifest', () => {
	it('hands out the kept manifest once, then null', async () => {
		pushedState = { runtimeManifest: { $schema: 'x', pages: [] } }

		const { takeRuntimeManifest } = await import('../loadInitialState.js')

		expect(takeRuntimeManifest()).toEqual({ $schema: 'x', pages: [] })
		expect(takeRuntimeManifest()).toBeNull()
	})

	it('is null without the key, and for a value that is not a manifest', async () => {
		pushedState = { runtimeManifest: { pages: [] } }
		const { takeRuntimeManifest, loadInitialState } = await import('../loadInitialState.js')
		expect(takeRuntimeManifest()).toBeNull()
		expect(loadInitialState('workspace').runtimeManifest).toEqual({ pages: [] })
	})
})
