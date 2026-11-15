/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `OrgNavigationEditorRow.vue`: the patches each field
 * emits, the move / add-child / delete buttons and when they are disabled,
 * the group visibility controls, and nested rows re-emitting their events.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import OrgNavigationEditorRow from '../OrgNavigationEditorRow.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key, translate: (_app, key) => key }))

const DraggableStub = {
	props: ['list'],
	template: '<ul class="draggable-stub"><template v-for="(element, index) in list" :key="element.id"><slot name="item" :element="element" :index="index" /></template></ul>',
}
const IconBrowserStub = { name: 'CnIconBrowser', emits: ['input'], template: '<div class="icon-stub" />' }

const NODE = { id: 'n1', label: 'Home', url: '/home', icon: 'Home', openInNewTab: false, groupVisibility: null }

function mountRow(props = {}) {
	return mount(OrgNavigationEditorRow, {
		props: { node: NODE, index: 0, siblings: [NODE, { id: 'n2' }], ...props },
		global: { stubs: { Draggable: DraggableStub, CnIconBrowser: IconBrowserStub } },
	})
}

const buttons = (wrapper) => wrapper.find('.org-nav-row__row').findAll('button.org-nav-row__btn')
const updates = (wrapper) => wrapper.emitted('update').map(([payload]) => payload.patch)

describe('OrgNavigationEditorRow fields', () => {
	it('emits label, url, icon and new-tab patches for the node', async () => {
		const wrapper = mountRow()

		await wrapper.find('.org-nav-row__label').setValue('Start')
		await wrapper.find('.org-nav-row__url').setValue('')
		wrapper.findComponent(IconBrowserStub).vm.$emit('input', '')
		wrapper.findComponent(IconBrowserStub).vm.$emit('input', 'Star')
		await wrapper.find('.org-nav-row__row .org-nav-row__checkbox input').setValue(true)

		expect(wrapper.emitted('update')[0][0].node).toEqual(NODE)
		expect(updates(wrapper)).toEqual([
			{ label: 'Start' },
			{ url: null },
			{ icon: null },
			{ icon: 'Star' },
			{ openInNewTab: true },
		])
		expect(wrapper.vm.localIcon).toBe('Star')
	})

	it('indents by level', () => {
		expect(mountRow({ level: 3 }).find('.org-nav-row__row').attributes('style')).toContain('padding-left: 32px')
	})
})

describe('OrgNavigationEditorRow buttons', () => {
	it('disables move up on the first row and move down on the last', () => {
		const first = buttons(mountRow({ index: 0 }))
		expect(first[0].attributes('disabled')).toBeDefined()
		expect(first[1].attributes('disabled')).toBeUndefined()

		const last = buttons(mountRow({ index: 1 }))
		expect(last[0].attributes('disabled')).toBeUndefined()
		expect(last[1].attributes('disabled')).toBeDefined()
	})

	it('allows children only above the maximum depth', () => {
		expect(buttons(mountRow({ level: 2, maxDepth: 3 }))[2].attributes('disabled')).toBeUndefined()
		expect(buttons(mountRow({ level: 3, maxDepth: 3 }))[2].attributes('disabled')).toBeDefined()
	})

	it('emits move, add-child and delete with the row position', async () => {
		const siblings = [{ id: 'a' }, NODE, { id: 'c' }]
		const wrapper = mountRow({ index: 1, siblings })
		const [up, down, add, del] = buttons(wrapper)

		await up.trigger('click')
		await down.trigger('click')
		await add.trigger('click')
		await del.trigger('click')

		expect(wrapper.emitted('moveUp')).toEqual([[{ siblings, index: 1 }]])
		expect(wrapper.emitted('moveDown')).toEqual([[{ siblings, index: 1 }]])
		expect(wrapper.emitted('addChild')).toEqual([[{ parent: NODE, kind: 'link' }]])
		expect(wrapper.emitted('delete')).toEqual([[{ siblings, index: 1 }]])
	})
})

describe('OrgNavigationEditorRow visibility', () => {
	const allToggle = (wrapper) => wrapper.find('.org-nav-row__visibility input[type="checkbox"]')

	it('restricts to no groups, then back to everyone', async () => {
		const wrapper = mountRow()
		expect(wrapper.find('.org-nav-row__groups-text').exists()).toBe(false)

		await allToggle(wrapper).setValue(false)
		expect(wrapper.vm.localVisibility).toEqual([])
		expect(wrapper.find('.org-nav-row__groups-text').exists()).toBe(true)

		await allToggle(wrapper).setValue(true)
		expect(wrapper.vm.localVisibility).toBeNull()
		expect(updates(wrapper)).toEqual([{ groupVisibility: [] }, { groupVisibility: null }])
	})

	it('parses free-text group ids, an empty list meaning everyone', async () => {
		const wrapper = mountRow({ node: { ...NODE, groupVisibility: ['a'] } })
		const input = wrapper.find('.org-nav-row__groups-text')
		expect(input.element.value).toBe('a')

		await input.setValue(' staff , ,ops ')
		await input.setValue(' , ')

		expect(updates(wrapper)).toEqual([{ groupVisibility: ['staff', 'ops'] }, { groupVisibility: null }])
		expect(wrapper.vm.localVisibility).toEqual([])
	})

	it('offers a multi-select when groups are known', async () => {
		const wrapper = mountRow({
			node: { ...NODE, groupVisibility: ['g1'] },
			groups: [{ id: 'g1', displayName: 'One' }, { id: 'g2', displayName: 'Two' }],
		})
		const select = wrapper.find('select.org-nav-row__groups')
		expect(select.findAll('option').map((o) => o.element.value)).toEqual(['g1', 'g2'])

		select.findAll('option')[1].element.selected = true
		await select.trigger('change')

		expect(updates(wrapper)).toEqual([{ groupVisibility: ['g1', 'g2'] }])
	})
})

describe('OrgNavigationEditorRow children', () => {
	it('renders children one level deeper and re-emits their events', async () => {
		const first = { id: 'c1', label: 'First', url: '/c1' }
		const second = { id: 'c2', label: 'Second', url: '/c2' }
		const wrapper = mountRow({ node: { ...NODE, children: [first, second] } })
		const rowOf = (id) => wrapper.find(`[data-test-id="${id}"]`)
		const childButtons = (id) => rowOf(id).find('.org-nav-row__row').findAll('button.org-nav-row__btn')

		expect(rowOf('c1').find('.org-nav-row__row').attributes('style')).toContain('padding-left: 16px')

		await rowOf('c1').find('.org-nav-row__label').setValue('Kid')
		await childButtons('c2')[0].trigger('click')
		await childButtons('c1')[1].trigger('click')
		await childButtons('c1')[2].trigger('click')
		await childButtons('c2')[3].trigger('click')

		expect(wrapper.emitted('update')).toEqual([[{ node: first, patch: { label: 'Kid' } }]])
		expect(wrapper.emitted('moveUp')).toEqual([[{ siblings: [first, second], index: 1 }]])
		expect(wrapper.emitted('moveDown')).toEqual([[{ siblings: [first, second], index: 0 }]])
		expect(wrapper.emitted('addChild')).toEqual([[{ parent: first, kind: 'link' }]])
		expect(wrapper.emitted('delete')).toEqual([[{ siblings: [first, second], index: 1 }]])
	})

	it('renders no child list for a leaf', () => {
		expect(mountRow().find('.draggable-stub').exists()).toBe(false)
	})
})
