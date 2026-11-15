/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the interactive parts of `OrgNavigationItem.vue`:
 * the disclosure toggle and its label, the section-header toggle, the
 * navigate event (also bubbled from a child), indentation, deep
 * auto-expansion and the URL boundary rules (REQ-ONAV-005, REQ-ONAV-009).
 * Rendering of links, icons and the basic active match is pinned in
 * OrgNavigationPanel.spec.js.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import OrgNavigationItem from '../OrgNavigationItem.vue'

/**
 * A translation stub that fills in the placeholders, so labels are asserted as rendered.
 *
 * @param {string} _app The app id.
 * @param {string} text The source text.
 * @param {object} [vars] The placeholder values.
 * @return {string} the text with its placeholders filled in.
 */
function translate(_app, text, vars = {}) {
	return text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? '')
}

/**
 * Mount one item.
 *
 * @param {object} node The tree node.
 * @param {object} [props] Further props.
 * @return {object} the wrapper.
 */
function mountItem(node, props = {}) {
	return mount(OrgNavigationItem, {
		props: { node, level: 1, currentUrl: '', ...props },
		global: { mocks: { t: translate } },
	})
}

const section = {
	id: 's',
	label: 'Section',
	url: null,
	icon: 'star',
	children: [
		{ id: 'c1', label: 'Child one', url: '/one', children: [] },
		{ id: 'c2', label: 'Child two', url: '/two', children: [] },
	],
}

describe('OrgNavigationItem disclosure', () => {
	it('expands and collapses its children from the toggle, with a matching label', async () => {
		const wrapper = mountItem(section)
		const toggle = wrapper.find('.org-nav-item__toggle')

		expect(toggle.element.tagName).toBe('BUTTON')
		expect(toggle.attributes('aria-label')).toBe('Expand Section')
		expect(toggle.text()).toBe('▸')
		expect(wrapper.attributes('aria-expanded')).toBe('false')
		expect(wrapper.find('.org-nav-item__children').exists()).toBe(false)

		await toggle.trigger('click')
		expect(toggle.attributes('aria-label')).toBe('Collapse Section')
		expect(toggle.text()).toBe('▾')
		expect(wrapper.attributes('aria-expanded')).toBe('true')
		expect(wrapper.findAll('.org-nav-item__children > li').map((li) => li.text())).toEqual(['Child one', 'Child two'])

		await toggle.trigger('click')
		expect(wrapper.find('.org-nav-item__children').exists()).toBe(false)
	})

	it('toggles from the section header button too, which carries the icon', async () => {
		const wrapper = mountItem(section)
		const header = wrapper.find('.org-nav-item__row--section')

		expect(header.find('.org-nav-item__icon').exists()).toBe(true)
		await header.trigger('click')
		expect(wrapper.vm.expanded).toBe(true)
	})

	it('has a placeholder instead of a toggle, and no aria-expanded, without children', async () => {
		const wrapper = mountItem({ id: 'e', label: 'Empty', url: null, children: [] })

		expect(wrapper.find('button.org-nav-item__toggle').exists()).toBe(false)
		expect(wrapper.find('.org-nav-item__toggle--placeholder').exists()).toBe(true)
		expect(wrapper.attributes('aria-expanded')).toBeUndefined()
		expect(wrapper.classes()).toContain('org-nav-item--section')

		await wrapper.find('.org-nav-item__row--section').trigger('click')
		expect(wrapper.vm.expanded).toBe(false)
	})
})

describe('OrgNavigationItem navigation', () => {
	it('emits navigate when its link is activated, and bubbles a child\'s', async () => {
		const link = mountItem({ id: 'l', label: 'Link', url: '/link', children: [] })
		await link.find('a').trigger('click')
		expect(link.emitted('navigate')).toHaveLength(1)
		expect(link.find('a').attributes('target')).toBeUndefined()
		expect(link.find('a').attributes('rel')).toBeUndefined()

		const parent = mountItem(section)
		await parent.find('.org-nav-item__toggle').trigger('click')
		await parent.findAll('.org-nav-item__children a')[1].trigger('click')
		expect(parent.emitted('navigate')).toHaveLength(1)
	})

	it('indents 12px per level beyond the root, and passes the next level to children', async () => {
		expect(mountItem(section).find('.org-nav-item__indent').attributes('style')).toContain('padding-left: 0px')

		const nested = mountItem(section, { level: 3 })
		expect(nested.find('.org-nav-item__indent').attributes('style')).toContain('padding-left: 24px')
		await nested.find('.org-nav-item__toggle').trigger('click')
		expect(nested.findAllComponents(OrgNavigationItem)[1].props('level')).toBe(4)
	})
})

describe('OrgNavigationItem active detection', () => {
	it('auto-expands for an active grandchild and marks the active descendant', () => {
		const tree = {
			id: 'root',
			label: 'Root',
			url: null,
			children: [
				{ id: 'mid', label: 'Mid', url: null, children: [{ id: 'leaf', label: 'Leaf', url: '/deep', children: [] }] },
			],
		}
		const wrapper = mountItem(tree, { currentUrl: '/deep' })

		expect(wrapper.vm.expanded).toBe(true)
		expect(wrapper.classes()).toContain('org-nav-item--has-active-child')
		expect(wrapper.classes()).not.toContain('org-nav-item--active')
	})

	it('stays collapsed when no descendant is active', () => {
		const wrapper = mountItem(section, { currentUrl: '/elsewhere' })

		expect(wrapper.vm.expanded).toBe(false)
		expect(wrapper.classes()).not.toContain('org-nav-item--has-active-child')
	})

	it('matches a prefix only up to a path, query or fragment boundary', () => {
		const active = (currentUrl) => mountItem({ id: 'h', label: 'Hub', url: '/hub', children: [] }, { currentUrl }).classes()
			.includes('org-nav-item--active')

		expect(active('/hub?tab=1')).toBe(true)
		expect(active('/hub#top')).toBe(true)
		expect(active('/hub/x')).toBe(true)
		expect(active('/hubs')).toBe(false)
		expect(active('/other')).toBe(false)
		expect(active('')).toBe(false)
	})
})
