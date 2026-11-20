/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CommunitiesWidget: what the viewer sees and does in each state
 * (openspec/changes/engagement-communities REQ-COMM-001..004). What Talk
 * answers is talkCommunities.spec.js; here the list is given.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => {
	const actual = await importOriginal()
	const fill = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
	return {
		...actual,
		translate: (_app, text, vars) => fill(text, vars),
		translatePlural: (_app, one, many, count, vars) => fill(count === 1 ? one : many, vars).replace('%n', String(count)),
	}
})
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const loadCommunities = vi.fn()
const joinCommunity = vi.fn()
vi.mock('../../../../services/talkCommunities.js', () => ({
	loadCommunities: (...args) => loadCommunities(...args),
	joinCommunity: (...args) => joinCommunity(...args),
	communityUrl: (token) => `/index.php/call/${token}`,
}))

import CommunitiesWidget from '../CommunitiesWidget.vue'

const NcButton = {
	name: 'NcButton',
	props: ['disabled', 'variant', 'ariaLabel'],
	emits: ['click'],
	template: '<button :disabled="disabled" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot /></button>',
}

const DUURZAAMHEID = {
	token: 'duurzaam42',
	name: 'Duurzaamheid',
	description: 'Ideeën voor een groenere organisatie',
	members: 14,
	joined: false,
	pinned: false,
}
const VRAAG_ICT = {
	token: 'vraagict',
	name: 'Vraag het de ICT',
	description: '',
	members: 1,
	joined: true,
	pinned: true,
}

/**
 * Mount the widget with the given Talk state.
 *
 * @param {object} opts Options.
 * @param {boolean} [opts.talkEnabled] Whether Talk is enabled.
 * @param {Array<string>} [opts.pinned] Pinned tokens.
 * @param {object} [opts.content] Widget content.
 * @return {Promise<object>} The wrapper.
 */
async function mountWidget({ talkEnabled = true, pinned = ['vraagict'], content = {} } = {}) {
	const wrapper = mount(CommunitiesWidget, {
		props: { content },
		global: {
			provide: { talkEnabled, pinnedCommunities: pinned },
			stubs: { NcButton },
		},
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	loadCommunities.mockReset()
	joinCommunity.mockReset()
	loadCommunities.mockResolvedValue([VRAAG_ICT, DUURZAAMHEID])
})

describe('CommunitiesWidget', () => {
	it('lists each community with its description and member count, and a Join button', async () => {
		const wrapper = await mountWidget()

		expect(loadCommunities).toHaveBeenCalledWith(['vraagict'])
		const row = wrapper.find('[data-testid="community-duurzaam42"]')
		expect(row.text()).toContain('Duurzaamheid')
		expect(row.text()).toContain('Ideeën voor een groenere organisatie')
		expect(row.text()).toContain('14 members')
		expect(row.find('[data-testid="community-join"]').text()).toBe('Join')
	})

	it('shows the pinned community first with its mark, and Open for one already joined', async () => {
		const wrapper = await mountWidget()

		const rows = wrapper.findAll('li')
		expect(rows[0].attributes('data-testid')).toBe('community-vraagict')
		expect(rows[0].find('[data-testid="community-pinned"]').text()).toBe('Pinned by your organisation')
		expect(rows[0].text()).toContain('1 member')
		expect(rows[0].find('[data-testid="community-open"]').attributes('href')).toBe('/index.php/call/vraagict')
		expect(rows[1].find('[data-testid="community-pinned"]').exists()).toBe(false)
	})

	it('joins in one click, and the button becomes Open', async () => {
		joinCommunity.mockResolvedValue(undefined)
		const wrapper = await mountWidget()

		await wrapper.find('[data-testid="community-duurzaam42"] [data-testid="community-join"]').trigger('click')
		await flushPromises()

		expect(joinCommunity).toHaveBeenCalledWith('duurzaam42')
		const row = wrapper.find('[data-testid="community-duurzaam42"]')
		expect(row.find('[data-testid="community-join"]').exists()).toBe(false)
		expect(row.find('[data-testid="community-open"]').attributes('href')).toBe('/index.php/call/duurzaam42')
	})

	it('says so when a join fails, and keeps the Join button', async () => {
		joinCommunity.mockRejectedValue(new Error('403'))
		const wrapper = await mountWidget()

		await wrapper.find('[data-testid="community-duurzaam42"] [data-testid="community-join"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="communities-join-error"]').text()).toBe(
			'You could not join Duurzaamheid. Try again later.',
		)
		expect(wrapper.find('[data-testid="community-duurzaam42"] [data-testid="community-join"]').exists()).toBe(true)
	})

	it('shows only the pinned ones when the form asks, and a title', async () => {
		const wrapper = await mountWidget({ content: { onlyPinned: true, title: 'Communities' } })

		expect(wrapper.findAll('li').map((li) => li.attributes('data-testid'))).toEqual(['community-vraagict'])
		expect(wrapper.find('h3').text()).toBe('Communities')
	})

	it('without Talk explains itself and calls nothing', async () => {
		const wrapper = await mountWidget({ talkEnabled: false })

		expect(loadCommunities).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="communities-no-talk"]').text()).toBe(
			'Communities need Nextcloud Talk. Ask your administrator to enable it.',
		)
	})

	it('tells a failure apart from an empty list', async () => {
		loadCommunities.mockRejectedValueOnce(new Error('500'))
		const failed = await mountWidget()
		expect(failed.find('[data-testid="communities-error"]').exists()).toBe(true)

		loadCommunities.mockResolvedValueOnce([])
		const empty = await mountWidget()
		expect(empty.find('[data-testid="communities-empty"]').text()).toBe('There are no open communities to join yet.')
	})
})
