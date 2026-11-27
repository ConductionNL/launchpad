/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ContainerWidget: a well leaves out tiles the viewer may not open
 * (REQ-CONT-008) and can fill itself with the viewer's apps, with a limit and
 * a filter (REQ-CONT-009).
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const state = vi.hoisted(() => ({ apps: null }))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: (app, key, fallback) => (app === 'core' && key === 'apps' && state.apps !== null ? state.apps : fallback),
}))
vi.mock('gridstack', () => ({ GridStack: { init: vi.fn(() => ({ on: vi.fn(), destroy: vi.fn() })) } }))

const { default: ContainerWidget } = await import('../ContainerWidget.vue')

const ChildStub = {
	name: 'ContainerChild',
	props: ['placement', 'editMode', 'canEditTile'],
	template: '<span class="child">{{ placement.content.appId || placement.content.title || placement.type }}</span>',
}

function mountWell(content, props = {}) {
	return mount(ContainerWidget, {
		props: { content, placement: { id: 7 }, ...props },
		global: { stubs: { ContainerChild: ChildStub, NcButton: true } },
	})
}

function shown(wrapper) {
	return wrapper.findAll('.child').map((child) => child.text())
}

beforeEach(() => {
	globalThis.localStorage?.clear?.()
	state.apps = [
		{ id: 'launchpad', name: 'Launchpad', href: '/l', order: 0 },
		{ id: 'files', name: 'Files', href: '/f', order: 1 },
		{ id: 'zaaksysteem', name: 'Zaaksysteem', href: '/z', order: 2 },
		{ id: 'photos', name: 'Photos', href: '/p', order: 3 },
		{ id: 'spreed', name: 'Talk', href: '/t', order: 4 },
	]
})

describe('a well leaves out tiles the viewer may not open (REQ-CONT-008)', () => {
	const content = {
		placements: [
			{ id: 1, type: 'tile', gridX: 0, gridY: 0, gridWidth: 1, gridHeight: 2, content: { mode: 'app', appId: 'files' } },
			{ id: 2, type: 'tile', gridX: 1, gridY: 0, gridWidth: 1, gridHeight: 2, content: { mode: 'app', appId: 'deck' } },
			{ id: 3, type: 'tile', gridX: 2, gridY: 0, gridWidth: 1, gridHeight: 2, content: { mode: 'app', appId: 'spreed' } },
		],
	}

	it('shows Files and Talk side by side and nothing for Deck', async () => {
		const wrapper = mountWell(content)
		await flushPromises()

		expect(shown(wrapper)).toEqual(['files', 'spreed'])
		const cells = wrapper.findAll('.container-widget__child')
		expect([cells[0].attributes('gs-x'), cells[1].attributes('gs-x')]).toEqual(['0', '1'])
	})

	it('keeps every tile in edit mode, where the well is arranged', async () => {
		const wrapper = mountWell(content, { editMode: true })
		await flushPromises()

		expect(shown(wrapper)).toEqual(['files', 'deck', 'spreed'])
	})
})

describe('a well can fill itself with the viewer\'s apps (REQ-CONT-009)', () => {
	it('draws the pinned app first, without the excluded ones and Launchpad, then the placed tiles', async () => {
		const wrapper = mountWell({
			appList: { pinned: ['zaaksysteem'], exclude: ['photos'] },
			placements: [{ id: 5, type: 'text', content: { title: 'Note' } }],
		})
		await flushPromises()

		expect(wrapper.find('[data-testid="container-flow"]').exists()).toBe(true)
		expect(shown(wrapper)).toEqual(['zaaksysteem', 'files', 'spreed', 'Note'])
	})

	it('shows the limit, and the filter narrows by name across every app', async () => {
		const wrapper = mountWell({ appList: { mostUsedLimit: 1, showFilter: true } })
		await flushPromises()
		expect(shown(wrapper)).toEqual(['files'])

		await wrapper.find('[data-testid="container-app-filter"]').setValue('o')
		expect(shown(wrapper)).toEqual(['photos'])
		await wrapper.find('[data-testid="container-app-filter"]').setValue('')
		expect(shown(wrapper)).toEqual(['files'])
	})

	it('in edit mode shows the placed tiles on the grid with a note', async () => {
		const wrapper = mountWell({ appList: { pinned: [] }, placements: [{ id: 5, type: 'text', content: { title: 'Note' } }] }, { editMode: true })
		await flushPromises()

		expect(wrapper.find('[data-testid="container-flow"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="container-app-list-note"]').exists()).toBe(true)
		expect(shown(wrapper)).toEqual(['Note'])
	})
})
