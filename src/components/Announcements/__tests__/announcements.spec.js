/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest for engagement-announcements: the Announcements widget (list,
 * category filter, like and comment counts, comments, follow), the notice
 * banner and its region (dismiss, non-dismissible, end time), and the editor
 * modal (preview step with reach and "Preview as", publish as its own step).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AnnouncementEditorModal, { toIso } from '../../../modals/AnnouncementEditorModal.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import AnnouncementsWidget from '../../Widgets/Renderers/AnnouncementsWidget.vue'
import AnnouncementsWidgetForm from '../../Widgets/Renderers/AnnouncementsWidgetForm.vue'
import NoticeBanner from '../NoticeBanner.vue'
import NoticeBannerRegion, { TICK_MS } from '../NoticeBannerRegion.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
	translate: (_app, text) => text,
	n: (_app, one, many, count, vars = {}) => (count === 1 ? one : many).replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
}))
vi.mock('../../../services/announcementsApi.js', () => ({
	announcementsApi: {
		list: vi.fn(),
		like: vi.fn(),
		unlike: vi.fn(),
		comments: vi.fn(),
		comment: vi.fn(),
		follow: vi.fn(),
		unfollow: vi.fn(),
		create: vi.fn(),
		update: vi.fn(),
		publish: vi.fn(),
		reach: vi.fn(),
		reachFor: vi.fn(),
		dismissedNotices: vi.fn(),
		saveDismissedNotices: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const field = (name) => ({
	name,
	props: ['modelValue', 'label', 'inputLabel', 'options'],
	emits: ['update:modelValue'],
	template: `<input class="${name}" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)">`,
})
const stubs = {
	NcButton: {
		props: ['disabled', 'type'],
		emits: ['click'],
		template: '<button :type="type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
	},
	NcSelect: field('nc-select'),
	NcTextField: field('nc-text-field'),
	NcTextArea: field('nc-text-area'),
	NcCheckboxRadioSwitch: { props: ['modelValue'], emits: ['update:modelValue'], template: '<label><slot /></label>' },
	NcModal: { emits: ['close'], template: '<div class="nc-modal-stub"><slot /></div>' },
}
const mountWith = (component, props = {}) => mount(component, { props, global: { stubs } })

const NEWS = [
	{ uuid: 'a', kind: 'news', title: 'Nieuwe werkplekken op de 3e verdieping', category: 'Facilitair', allowComments: true, publishAt: '2026-11-14T08:00:00Z' },
	{ uuid: 'b', kind: 'news', title: 'Inloopspreekuur privacy', category: 'Privacy', allowComments: false, publishAt: '2026-11-13T08:00:00Z' },
	{ uuid: 'n', kind: 'notice', title: 'Onderhoud zaaksysteem', level: 'warning', dismissible: false, expiresAt: '2099-01-01T00:00:00Z' },
]

beforeEach(() => {
	vi.clearAllMocks()
	announcementsApi.list.mockResolvedValue({
		announcements: NEWS,
		reactions: { a: { likes: 0, comments: 0, liked: false } },
		followedCategories: [],
		canAuthor: false,
	})
})

afterEach(() => {
	vi.useRealTimers()
})

describe('AnnouncementsWidget', () => {
	it('lists the news, not the notices, and filters by category', async () => {
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()

		expect(wrapper.findAll('[data-test="announcement-card"]').map((card) => card.find('h4').text()))
			.toEqual(['Nieuwe werkplekken op de 3e verdieping', 'Inloopspreekuur privacy'])
		wrapper.vm.category = 'Privacy'
		await flushPromises()
		expect(wrapper.findAll('[data-test="announcement-card"]')).toHaveLength(1)
		expect(wrapper.find('[data-test="announcements-new"]').exists()).toBe(false)
	})

	it('likes, then comments, and the counts follow', async () => {
		announcementsApi.like.mockResolvedValue({ likes: 1, liked: true })
		announcementsApi.comments.mockResolvedValue([])
		announcementsApi.comment.mockResolvedValue({ id: '9', displayName: 'Pieter', message: 'Komen er ook sta-bureaus?' })
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()

		await wrapper.find('[data-test="announcement-like"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.like).toHaveBeenCalledWith('a')
		expect(wrapper.find('[data-test="announcement-like"]').text()).toBe('1')

		await wrapper.find('[data-test="announcement-comments"]').trigger('click')
		await flushPromises()
		wrapper.vm.draftComment = 'Komen er ook sta-bureaus?'
		await wrapper.vm.sendComment('a')
		await flushPromises()
		expect(announcementsApi.comment).toHaveBeenCalledWith('a', 'Komen er ook sta-bureaus?')
		expect(wrapper.find('[data-test="announcement-comment-list"]').text()).toContain('Komen er ook sta-bureaus?')
		expect(wrapper.find('[data-test="announcement-comments"]').text()).toBe('1')
	})

	it('a second click on like takes it back', async () => {
		announcementsApi.list.mockResolvedValue({ announcements: [NEWS[0]], reactions: { a: { likes: 3, comments: 0, liked: true } } })
		announcementsApi.unlike.mockResolvedValue({ likes: 2, liked: false })
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()

		await wrapper.vm.toggleLike('a')
		expect(announcementsApi.unlike).toHaveBeenCalledWith('a')
		expect(wrapper.vm.reactionsOf('a')).toEqual({ likes: 2, comments: 0, liked: false })
	})

	it('follows and unfollows the chosen category', async () => {
		announcementsApi.follow.mockResolvedValue(['Privacy'])
		announcementsApi.unfollow.mockResolvedValue([])
		const wrapper = mountWith(AnnouncementsWidget, { content: { category: 'Privacy' } })
		await flushPromises()

		const button = () => wrapper.find('[data-test="announcements-follow"]')
		expect(button().text()).toBe('Follow Privacy')
		await button().trigger('click')
		await flushPromises()
		expect(announcementsApi.follow).toHaveBeenCalledWith('Privacy')
		expect(button().text()).toBe('Unfollow Privacy')
		await button().trigger('click')
		await flushPromises()
		expect(announcementsApi.unfollow).toHaveBeenCalledWith('Privacy')
	})

	it('shows the load error and offers authors a new announcement', async () => {
		announcementsApi.list.mockRejectedValueOnce(new Error('x'))
		const failed = mountWith(AnnouncementsWidget)
		await flushPromises()
		expect(failed.find('[role="alert"]').text()).toBe('Announcements could not be loaded')

		announcementsApi.list.mockResolvedValue({ announcements: [], canAuthor: true })
		const author = mountWith(AnnouncementsWidget)
		await flushPromises()
		expect(author.text()).toContain('No announcements')
		await author.find('[data-test="announcements-new"]').trigger('click')
		expect(author.find('[data-test="announcement-editor"]').exists()).toBe(true)
	})
})

describe('AnnouncementsWidgetForm', () => {
	it('emits the category and a clamped limit', async () => {
		const wrapper = mountWith(AnnouncementsWidgetForm, { editingWidget: { content: { category: 'Privacy', limit: 99 } } })
		expect(wrapper.vm.limit).toBe(50)
		wrapper.vm.setLimit('0')
		wrapper.vm.set('category', ' Facilitair ')
		expect(wrapper.emitted('update:content')).toEqual([[{ category: 'Privacy', limit: 1 }], [{ category: 'Facilitair', limit: 1 }]])
	})
})

describe('NoticeBanner', () => {
	it('shows the level by icon and text, and a close button only when dismissible', () => {
		const warning = mountWith(NoticeBanner, { notice: { uuid: 'n', title: 'Onderhoud', level: 'warning', dismissible: false } })
		expect(warning.attributes('role')).toBe('alert')
		expect(warning.find('.notice-banner__level').text()).toBe('Warning')
		expect(warning.find('[data-test="notice-dismiss"]').exists()).toBe(false)

		const info = mountWith(NoticeBanner, { notice: { uuid: 'i', title: 'Let op', level: 'info', dismissible: true } })
		expect(info.find('.notice-banner__level').text()).toBe('Information')
		info.find('[data-test="notice-dismiss"]').trigger('click')
		expect(info.emitted('dismiss')).toEqual([['i']])

		const preview = mountWith(NoticeBanner, { notice: { uuid: 'i', title: 'Let op', dismissible: true }, preview: true })
		expect(preview.find('[data-test="notice-dismiss"]').exists()).toBe(false)
	})
})

describe('NoticeBannerRegion', () => {
	it('shows the notices, hides dismissed dismissible ones, and drops one at its end time', async () => {
		vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'Date'] })
		vi.setSystemTime(new Date('2026-11-13T18:00:00Z'))
		announcementsApi.list.mockResolvedValue({
			announcements: [
				{ uuid: 'w', kind: 'notice', title: 'Onderhoud zaaksysteem', level: 'warning', dismissible: false, expiresAt: '2026-11-14T12:00:00Z' },
				{ uuid: 'd', kind: 'notice', title: 'Nieuwe printer', level: 'info', dismissible: true, expiresAt: '2026-11-20T00:00:00Z' },
				{ uuid: 'x', kind: 'notice', title: 'Al weggeklikt', level: 'info', dismissible: true, expiresAt: '2026-11-20T00:00:00Z' },
				NEWS[0],
			],
		})
		announcementsApi.dismissedNotices.mockResolvedValue(['x', 'w', 'gone'])
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		expect(wrapper.findAll('[data-test="notice-banner"]').map((b) => b.find('.notice-banner__title').text()))
			.toEqual(['Onderhoud zaaksysteem', 'Nieuwe printer'])

		await wrapper.vm.dismiss('d')
		await wrapper.vm.dismiss('w')
		expect(announcementsApi.saveDismissedNotices).toHaveBeenCalledTimes(1)
		expect(announcementsApi.saveDismissedNotices).toHaveBeenCalledWith(['x', 'w', 'd'])
		expect(wrapper.findAll('[data-test="notice-banner"]')).toHaveLength(1)

		vi.setSystemTime(new Date('2026-11-14T12:01:00Z'))
		vi.advanceTimersByTime(TICK_MS)
		await flushPromises()
		expect(wrapper.find('[data-test="notice-region"]').exists()).toBe(false)
		wrapper.unmount()
	})
})

describe('AnnouncementEditorModal', () => {
	it('refuses a notice without an end time before any call', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, { kind: 'notice', title: 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00' })
		await wrapper.vm.toPreview()

		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('A notice needs an end time')
		expect(announcementsApi.create).not.toHaveBeenCalled()
	})

	it('saves a draft, previews the banner with its reach, checks "Preview as", then publishes', async () => {
		announcementsApi.create.mockResolvedValue({ uuid: 'u1' })
		announcementsApi.reach.mockResolvedValue(42)
		announcementsApi.reachFor.mockResolvedValue({ reach: 42, reachesUser: false })
		announcementsApi.publish.mockResolvedValue({ uuid: 'u1', status: 'published' })
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, {
			kind: 'notice',
			title: 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00',
			targetGroups: ['Burgerzaken'],
			level: 'warning',
			dismissible: false,
			expiresAt: '2026-11-14T12:00',
		})
		await wrapper.vm.toPreview()
		await flushPromises()

		expect(announcementsApi.create).toHaveBeenCalledWith(expect.objectContaining({
			kind: 'notice', level: 'warning', dismissible: false, allowComments: false, targetGroups: ['Burgerzaken'], category: null, publishAt: null,
		}))
		expect(announcementsApi.publish).not.toHaveBeenCalled()
		expect(wrapper.find('[data-test="notice-banner"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="editor-reach"]').text()).toBe('Reaches 42 people')

		wrapper.vm.previewAs = 'sanne'
		await wrapper.vm.checkPreviewAs()
		await flushPromises()
		expect(announcementsApi.reachFor).toHaveBeenCalledWith(['Burgerzaken'], 'sanne')
		expect(wrapper.find('[data-test="editor-preview-as-answer"]').text()).toBe('sanne does not see this announcement')

		await wrapper.find('[data-test="editor-publish"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.publish).toHaveBeenCalledWith('u1')
		expect(wrapper.emitted('published')).toEqual([[{ uuid: 'u1', status: 'published' }]])
	})

	it('shows the server refusal and updates the same draft on a second preview', async () => {
		announcementsApi.create.mockResolvedValue({ uuid: 'u2' })
		announcementsApi.update.mockResolvedValue({ uuid: 'u2' })
		announcementsApi.reach.mockResolvedValue(1)
		announcementsApi.publish.mockRejectedValue({ response: { data: { message: 'The end time has already passed' } } })
		const wrapper = mountWith(AnnouncementEditorModal)
		wrapper.vm.form.title = 'Nieuwe werkplekken op de 3e verdieping'
		await wrapper.vm.toPreview()
		expect(wrapper.find('[data-test="announcement-card"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="editor-reach"]').text()).toBe('Reaches 1 person')
		await wrapper.vm.publish()
		await flushPromises()
		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('The end time has already passed')

		wrapper.vm.step = 'write'
		await wrapper.vm.toPreview()
		expect(announcementsApi.update).toHaveBeenCalledWith('u2', expect.objectContaining({ kind: 'news', allowComments: true, level: null }))
	})

	it('turns a datetime-local value into ISO, and empty into null', () => {
		expect(toIso('')).toBeNull()
		expect(toIso('nonsense')).toBeNull()
		expect(toIso('2026-11-14T12:00')).toBe(new Date('2026-11-14T12:00').toISOString())
	})
})
