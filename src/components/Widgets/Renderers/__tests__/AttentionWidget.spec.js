/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AttentionWidget: what the employee is told in each state
 * (openspec/specs/attention-feed REQ-ATT-004, REQ-ATT-005). The feed's rules
 * are attentionFeed.spec.js; here the feed is given and the screen is read.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const loadAttentionFeed = vi.fn()
vi.mock('../../../../services/attentionFeed.js', () => ({
	loadAttentionFeed: (...args) => loadAttentionFeed(...args),
}))

import AttentionWidget from '../AttentionWidget.vue'

const item = (appId, appName, severity, count, overrides = {}) => ({
	key: `${appId}/x`,
	id: 'x',
	appId,
	appName,
	title: `${appName} title`,
	severity,
	count,
	attention: true,
	failed: false,
	reason: `${count} things need you.`,
	actionLabel: `Open ${appName}`,
	href: `/index.php/apps/${appId}/list?assignee=@me`,
	...overrides,
})

const CLEAR = 'Nothing needs your attention right now.'

async function mountWith(feed, content = {}) {
	loadAttentionFeed.mockResolvedValue(feed)
	const wrapper = mount(AttentionWidget, { props: { content } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	loadAttentionFeed.mockReset()
})

describe('AttentionWidget', () => {
	it('shows one line per item that needs attention', async () => {
		const wrapper = await mountWith({
			items: [item('pipelinq', 'Pipelinq', 'error', 5), item('dossiq', 'Dossiq', 'error', 3), item('decidiq', 'Decidiq', 'warning', 2)],
			failedApps: [],
			sourceCount: 3,
		})

		const lines = wrapper.findAll('.attention-widget__item')
		expect(lines.map((line) => line.find('.attention-widget__app').text())).toEqual(['Pipelinq', 'Dossiq', 'Decidiq'])
		const dossiq = wrapper.find('[data-testid="attention-item-dossiq-x"]')
		expect(dossiq.text()).toContain('Dossiq title')
		expect(dossiq.text()).toContain('3 things need you.')
		expect(dossiq.find('a').text()).toBe('Open Dossiq')
		expect(dossiq.find('a').attributes('href')).toBe('/index.php/apps/dossiq/list?assignee=@me')
		expect(dossiq.classes()).toContain('attention-widget__item--error')
		expect(wrapper.find('[data-testid="attention-item-decidiq-x"]').classes()).toContain('attention-widget__item--warning')
		expect(wrapper.text()).not.toContain(CLEAR)
		expect(wrapper.find('[data-testid="attention-failed"]').exists()).toBe(false)
	})

	it('says the severity in words, not by colour alone', async () => {
		const wrapper = await mountWith({
			items: [item('a', 'A', 'error', 1), item('b', 'B', 'warning', 1), item('c', 'C', 'info', 1)],
			failedApps: [],
			sourceCount: 3,
		})

		expect(wrapper.findAll('.attention-widget__sr').map((el) => el.text()))
			.toEqual(['Urgent:', 'Soon:', 'For your information:'])
	})

	it('leaves out a source that does not need attention', async () => {
		// The feed only hands over what needs attention; the widget shows
		// exactly that and does not invent a line for the rest.
		const wrapper = await mountWith({ items: [item('dossiq', 'Dossiq', 'error', 3)], failedApps: [], sourceCount: 2 })

		expect(wrapper.findAll('.attention-widget__item')).toHaveLength(1)
		expect(wrapper.text()).not.toContain('Learniq')
	})

	it('says nothing needs attention only when every source was checked', async () => {
		const wrapper = await mountWith({ items: [], failedApps: [], sourceCount: 2 })

		expect(wrapper.find('[data-testid="attention-clear"]').text()).toBe(CLEAR)
		expect(wrapper.find('[data-testid="attention-none"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="attention-failed"]').exists()).toBe(false)
	})

	it('a failed source is named and never reads as nothing to do', async () => {
		const wrapper = await mountWith({ items: [], failedApps: ['Dossiq'], sourceCount: 2 })

		expect(wrapper.find('[data-testid="attention-failed"]').text()).toBe('Could not check: Dossiq')
		expect(wrapper.find('[data-testid="attention-clear"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="attention-none"]').exists()).toBe(false)
		expect(wrapper.text()).not.toContain(CLEAR)
	})

	it('a failed source does not hide the others', async () => {
		const wrapper = await mountWith({
			items: [item('pipelinq', 'Pipelinq', 'error', 5)],
			failedApps: ['Dossiq', 'Learniq'],
			sourceCount: 3,
		})

		expect(wrapper.find('[data-testid="attention-item-pipelinq-x"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="attention-failed"]').text()).toBe('Could not check: Dossiq, Learniq')
		expect(wrapper.text()).not.toContain(CLEAR)
	})

	it('says so when no app declares anything', async () => {
		const wrapper = await mountWith({ items: [], failedApps: [], sourceCount: 0 })

		expect(wrapper.find('[data-testid="attention-none"]').text())
			.toBe('None of your apps reports attention items yet.')
		expect(wrapper.find('[data-testid="attention-clear"]').exists()).toBe(false)
	})

	it('says the list could not be loaded when the sources endpoint fails', async () => {
		loadAttentionFeed.mockRejectedValue(new Error('500'))
		const wrapper = mount(AttentionWidget)
		await flushPromises()

		expect(wrapper.find('[data-testid="attention-error"]').text())
			.toBe('This list could not be loaded. Reload the page to try again.')
		expect(wrapper.text()).not.toContain(CLEAR)
		expect(wrapper.find('[data-testid="attention-none"]').exists()).toBe(false)
	})

	it('shows a loading line until the feed answers', async () => {
		let resolve
		loadAttentionFeed.mockReturnValue(new Promise((r) => { resolve = r }))
		const wrapper = mount(AttentionWidget)
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-testid="attention-loading"]').exists()).toBe(true)
		expect(wrapper.text()).not.toContain(CLEAR)

		resolve({ items: [], failedApps: [], sourceCount: 1 })
		await flushPromises()
		expect(wrapper.find('[data-testid="attention-loading"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="attention-clear"]').exists()).toBe(true)
	})

	it('shows at most the configured number of lines and counts the rest', async () => {
		const items = Array.from({ length: 7 }, (_v, i) => item(`app${i}`, `App ${i}`, 'info', 1))

		const five = await mountWith({ items, failedApps: [], sourceCount: 7 })
		expect(five.findAll('.attention-widget__item')).toHaveLength(5)
		expect(five.find('[data-testid="attention-more"]').text()).toBe('And 2 more.')

		const two = await mountWith({ items, failedApps: [], sourceCount: 7 }, { limit: 2 })
		expect(two.findAll('.attention-widget__item')).toHaveLength(2)
		expect(two.find('[data-testid="attention-more"]').text()).toBe('And 5 more.')

		const capped = await mountWith({ items, failedApps: [], sourceCount: 7 }, { limit: 500 })
		expect(capped.findAll('.attention-widget__item')).toHaveLength(7)
		expect(capped.find('[data-testid="attention-more"]').exists()).toBe(false)

		const nonsense = await mountWith({ items, failedApps: [], sourceCount: 7 }, { limit: 'many' })
		expect(nonsense.findAll('.attention-widget__item')).toHaveLength(5)
	})
})
