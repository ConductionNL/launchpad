/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * WikiPagesWidget: favourites, recent pages and search over the wiki, and
 * what it says when the wiki is not connected (wiki-widget REQ-WIKI-001..003).
 * What OpenRegister answers is wikiPages.spec.js; here the service is given.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => {
	const actual = await importOriginal()
	const fill = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
	return { ...actual, translate: (_app, text, vars) => fill(text, vars) }
})
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const searchWikiPages = vi.fn()
const readWikiFavourites = vi.fn()
const saveWikiFavourites = vi.fn()
vi.mock('../../../../services/wikiPages.js', () => ({
	searchWikiPages: (...args) => searchWikiPages(...args),
	readWikiFavourites: (...args) => readWikiFavourites(...args),
	saveWikiFavourites: (...args) => saveWikiFavourites(...args),
}))

import WikiPagesWidget from '../WikiPagesWidget.vue'

const CnStatusList = {
	name: 'CnStatusList',
	props: ['items', 'loading', 'emptyLabel'],
	template: `<ul class="list"><li v-if="items.length === 0" class="empty">{{ emptyLabel }}</li>
		<li v-for="item in items" :key="item.id" class="row" :data-href="item.href" :data-meta="item.meta" :data-external="String(item.external)">
			<span class="title">{{ item.title }}</span><slot name="actions" :item="item" /></li></ul>`,
}
const NcButton = {
	name: 'NcButton',
	props: ['ariaLabel', 'pressed', 'variant'],
	emits: ['click'],
	template: '<button class="star" :aria-label="ariaLabel" :aria-pressed="String(pressed)" @click="$emit(\'click\')"><slot name="icon" /></button>',
}
const NcTextField = {
	name: 'NcTextField',
	props: ['modelValue', 'label'],
	emits: ['update:modelValue', 'trailingButtonClick'],
	template: '<input class="search" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const TERMIJNEN = { reference: 'Kennisbank.Termijnen', title: 'Termijnen Omgevingswet', space: 'Kennisbank Zuiddrecht', url: 'https://wiki.example/termijnen' }
const KAP = { reference: 'Vergunningen.Kap', title: 'Werkinstructie kapvergunningen', space: 'Vergunningen', url: 'https://wiki.example/kap' }
const LEGES = { reference: 'Vergunningen.Leges', title: 'Legesverordening 2026', space: 'Vergunningen', url: 'https://wiki.example/leges' }

/**
 * Mount the widget.
 *
 * @param {object} content The widget content.
 * @return {Promise<object>} The wrapper.
 */
async function mountWidget(content = {}) {
	const wrapper = mount(WikiPagesWidget, {
		props: { content, placement: {} },
		global: { stubs: { CnStatusList, NcButton, NcTextField, Star: true, StarOutline: true } },
	})
	await flushPromises()
	return wrapper
}

const titles = (section) => section.findAll('.row .title').map((node) => node.text())

beforeEach(() => {
	vi.useFakeTimers()
	searchWikiPages.mockReset().mockResolvedValue({ state: 'ok', pages: [TERMIJNEN, KAP, LEGES] })
	readWikiFavourites.mockReset().mockResolvedValue([KAP])
	saveWikiFavourites.mockReset().mockImplementation(async (pages) => pages)
})

afterEach(() => {
	vi.useRealTimers()
})

describe('WikiPagesWidget', () => {
	it('shows the favourites and the recent pages as links into the wiki, opening in a new tab', async () => {
		const wrapper = await mountWidget()
		expect(searchWikiPages).toHaveBeenCalledWith('', 3)
		expect(titles(wrapper.get('[data-testid="wiki-pages-favourites"]'))).toEqual(['Werkinstructie kapvergunningen'])
		const recent = wrapper.get('[data-testid="wiki-pages-recent"]')
		expect(titles(recent)).toEqual(['Termijnen Omgevingswet', 'Werkinstructie kapvergunningen', 'Legesverordening 2026'])
		const row = recent.find('.row')
		expect(row.attributes('data-href')).toBe('https://wiki.example/termijnen')
		expect(row.attributes('data-external')).toBe('true')
		expect(row.attributes('data-meta')).toBe('Kennisbank Zuiddrecht')
	})

	it('keeps to one space and to the list length', async () => {
		const wrapper = await mountWidget({ space: 'Vergunningen', limit: 1 })
		expect(searchWikiPages).toHaveBeenCalledWith('', 50)
		expect(titles(wrapper.get('[data-testid="wiki-pages-recent"]'))).toEqual(['Werkinstructie kapvergunningen'])
	})

	it('searches once typing pauses, and a cleared search shows the lists again', async () => {
		const wrapper = await mountWidget()
		searchWikiPages.mockResolvedValueOnce({ state: 'ok', pages: [LEGES] })
		await wrapper.get('input.search').setValue('leges')
		expect(searchWikiPages).toHaveBeenCalledTimes(1)
		await vi.advanceTimersByTimeAsync(300)
		await flushPromises()
		expect(searchWikiPages).toHaveBeenLastCalledWith('leges', 10)
		expect(titles(wrapper.get('[data-testid="wiki-pages-results"]'))).toEqual(['Legesverordening 2026'])
		await wrapper.get('input.search').setValue('')
		expect(wrapper.find('[data-testid="wiki-pages-results"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="wiki-pages-recent"]').exists()).toBe(true)
	})

	it('stars a recent page as the newest favourite and unstars a favourite', async () => {
		const wrapper = await mountWidget()
		const recentStars = wrapper.get('[data-testid="wiki-pages-recent"]').findAll('button.star')
		expect(recentStars[0].attributes('aria-label')).toBe('Add Termijnen Omgevingswet to favourites')
		expect(recentStars[1].attributes('aria-pressed')).toBe('true')
		await recentStars[0].trigger('click')
		await flushPromises()
		expect(saveWikiFavourites).toHaveBeenLastCalledWith([TERMIJNEN, KAP])
		expect(titles(wrapper.get('[data-testid="wiki-pages-favourites"]'))).toEqual(['Termijnen Omgevingswet', 'Werkinstructie kapvergunningen'])

		await wrapper.get('[data-testid="wiki-pages-favourites"]').findAll('button.star')[1].trigger('click')
		await flushPromises()
		expect(saveWikiFavourites).toHaveBeenLastCalledWith([TERMIJNEN])
	})

	it('puts the favourites back when saving fails', async () => {
		saveWikiFavourites.mockRejectedValueOnce(new Error('500'))
		const wrapper = await mountWidget()
		await wrapper.get('[data-testid="wiki-pages-favourites"]').find('button.star').trigger('click')
		await flushPromises()
		expect(titles(wrapper.get('[data-testid="wiki-pages-favourites"]'))).toEqual(['Werkinstructie kapvergunningen'])
	})

	it('says the wiki is not connected when OpenRegister has no XWiki source', async () => {
		searchWikiPages.mockResolvedValue({ state: 'not-connected', pages: [] })
		const wrapper = await mountWidget()
		expect(wrapper.get('[data-testid="wiki-pages-not-connected"]').text()).toBe('The wiki is not connected yet. Ask your administrator to connect XWiki.')
		expect(wrapper.find('input.search').exists()).toBe(false)
	})

	it('says OpenRegister is needed when it is not there', async () => {
		searchWikiPages.mockResolvedValue({ state: 'unavailable', pages: [] })
		const wrapper = await mountWidget()
		expect(wrapper.get('[data-testid="wiki-pages-not-connected"]').text()).toContain('needs OpenRegister')
	})
})
