/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest for engagement-announcements: the Announcements widget (list,
 * category filter, like and comment counts, comments, follow), the notice
 * banner and its region (dismiss, non-dismissible, end time), and the editor
 * modal (one dialog with a live preview, reach and "Preview as", one
 * primary action that schedules or publishes).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AnnouncementEditorModal, { confirmBeforeIso, confirmBeforeProblem, endTimeProblem, groupChoices, previewNoteText, toIso, visibleUntilText } from '../../../modals/AnnouncementEditorModal.vue'
import AnnouncementModal from '../../../modals/AnnouncementModal.vue'
import AnnouncementsWidget, { readConfirmationState } from '../../Widgets/Renderers/AnnouncementsWidget.vue'
import AnnouncementsWidgetForm from '../../Widgets/Renderers/AnnouncementsWidgetForm.vue'
import AnnouncementCard, { confirmBeforeLabel } from '../AnnouncementCard.vue'
import NoticeBanner from '../NoticeBanner.vue'
import NoticeBannerRegion, { TICK_MS } from '../NoticeBannerRegion.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import { logger } from '../../../utils/logger.js'

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
		groups: vi.fn(),
		confirmRead: vi.fn(),
		readState: vi.fn(),
		markSeen: vi.fn(),
		receipts: vi.fn(),
		dismissedNotices: vi.fn(),
		saveDismissedNotices: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

function field (name) {
  return {
	name,
	props: ['modelValue', 'label', 'inputLabel', 'options'],
	emits: ['update:modelValue'],
	template: `<input class="${name}" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)">`,
}
}
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
	AcknowledgementReportModal: { name: 'AcknowledgementReportModal', props: ['open', 'announcementUuid', 'title'], template: '<div class="report-stub" />' },
}
const mountWith = (component, props = {}) => mount(component, { props, global: { stubs } })

const NEWS = [
	{ uuid: 'a', kind: 'news', title: 'Nieuwe werkplekken op de 3e verdieping', category: 'Facilitair', allowComments: true, publishAt: '2026-11-14T08:00:00Z' },
	{ uuid: 'b', kind: 'news', title: 'Inloopspreekuur privacy', category: 'Privacy', allowComments: false, publishAt: '2026-11-13T08:00:00Z' },
	{ uuid: 'n', kind: 'notice', title: 'Onderhoud zaaksysteem', level: 'warning', dismissible: false, expiresAt: '2099-01-01T00:00:00Z' },
]

beforeEach(() => {
	vi.clearAllMocks()
	announcementsApi.readState.mockResolvedValue({ confirmed: [], receipts: [] })
	announcementsApi.markSeen.mockResolvedValue([])
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

	it('shows no like, comment or follow button on the items (REQ-ANN-010)', async () => {
		const wrapper = mountWith(AnnouncementsWidget, { content: { category: 'Privacy' } })
		await flushPromises()

		expect(wrapper.find('[data-test="announcement-like"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="announcement-comments"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="announcements-follow"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="announcement-confirm-read"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="announcement-close"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="announcement-open"]').exists()).toBe(true)
	})

	it('Open opens the view, where likes, comments and follow live (REQ-ANN-011)', async () => {
		announcementsApi.like.mockResolvedValue({ likes: 1, liked: true })
		announcementsApi.comments.mockResolvedValue([])
		announcementsApi.comment.mockResolvedValue({ id: '9', displayName: 'Pieter', message: 'Komen er ook sta-bureaus?' })
		announcementsApi.follow.mockResolvedValue(['Facilitair'])
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()

		await wrapper.find('[data-test="announcement-open"]').trigger('click')
		await flushPromises()
		const modal = () => wrapper.findComponent(AnnouncementModal)
		expect(modal().props('announcement').uuid).toBe('a')
		expect(announcementsApi.comments).toHaveBeenCalledWith('a')

		await modal().find('[data-test="announcement-like"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.like).toHaveBeenCalledWith('a')
		expect(wrapper.vm.reactionsOf('a').likes).toBe(1)

		modal().vm.draftComment = 'Komen er ook sta-bureaus?'
		await modal().vm.sendComment()
		await flushPromises()
		expect(announcementsApi.comment).toHaveBeenCalledWith('a', 'Komen er ook sta-bureaus?')
		expect(modal().find('[data-test="announcement-comment-list"]').text()).toContain('Komen er ook sta-bureaus?')
		expect(wrapper.vm.reactionsOf('a').comments).toBe(1)

		await modal().find('[data-test="announcements-follow"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.follow).toHaveBeenCalledWith('Facilitair')
		expect(modal().find('[data-test="announcements-follow"]').text()).toBe('Unfollow Facilitair')

		modal().vm.$emit('close')
		await flushPromises()
		expect(wrapper.vm.openItem).toBeNull()
	})

	it('records the items on screen as seen once, after render', async () => {
		announcementsApi.readState.mockResolvedValue({ seen: ['b'], confirmed: [], closed: [] })
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()

		expect(announcementsApi.markSeen).toHaveBeenCalledTimes(1)
		expect(announcementsApi.markSeen).toHaveBeenCalledWith(['a'])
		wrapper.vm.markSeen()
		expect(announcementsApi.markSeen).toHaveBeenCalledTimes(1)
	})

	it('shows the load error and offers managers Manage, no form (REQ-ANN-010)', async () => {
		announcementsApi.list.mockRejectedValueOnce(new Error('x'))
		const failed = mountWith(AnnouncementsWidget)
		await flushPromises()
		expect(failed.find('[role="alert"]').text()).toBe('Announcements could not be loaded')

		announcementsApi.list.mockResolvedValue({ announcements: [], canAuthor: true })
		const author = mountWith(AnnouncementsWidget)
		await flushPromises()
		expect(author.text()).toContain('No announcements')
		expect(author.find('[data-test="announcements-new"]').exists()).toBe(false)
		expect(author.find('[data-test="announcements-manage"]').exists()).toBe(true)
		expect(author.find('[data-test="announcement-editor"]').exists()).toBe(false)
		expect(author.vm.manageUrl).toMatch(/\/settings\/admin\/launchpad#announcements$/)
		expect(author.vm.manageUrlOf('abc')).toMatch(/#announcements\/abc$/)
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
	it('shows the level by icon and text, and the check and the x on every notice', () => {
		const warning = mountWith(NoticeBanner, { notice: { uuid: 'n', title: 'Onderhoud', level: 'warning', dismissible: false } })
		expect(warning.attributes('role')).toBe('alert')
		expect(warning.find('.notice-banner__level').text()).toBe('Warning')
		warning.find('[data-test="notice-confirm"]').trigger('click')
		warning.find('[data-test="notice-dismiss"]').trigger('click')
		expect(warning.emitted('confirm')).toEqual([['n', 'check'], ['n', 'x']])

		const info = mountWith(NoticeBanner, { notice: { uuid: 'i', title: 'Let op', level: 'info' }, read: true })
		expect(info.find('.notice-banner__level').text()).toBe('Information')
		expect(info.find('[data-test="notice-confirm"]').attributes('aria-label')).toBe('Read')

		const preview = mountWith(NoticeBanner, { notice: { uuid: 'i', title: 'Let op', dismissible: true }, preview: true })
		expect(preview.find('[data-test="notice-dismiss"]').exists()).toBe(false)
		expect(preview.find('[data-test="notice-confirm"]').exists()).toBe(false)
	})
})

describe('NoticeBannerRegion', () => {
	const notices = () => [
		{ uuid: 'w', kind: 'notice', title: 'Onderhoud zaaksysteem', level: 'warning', dismissible: false, expiresAt: '2026-11-14T12:00:00Z' },
		{ uuid: 'd', kind: 'notice', title: 'Nieuwe printer', level: 'info', dismissible: true, expiresAt: '2026-11-20T00:00:00Z' },
		{ uuid: 'x', kind: 'notice', title: 'Al weggeklikt', level: 'info', dismissible: true, expiresAt: '2026-11-20T00:00:00Z' },
		{ uuid: 'c', kind: 'notice', title: 'Eerder gesloten', level: 'info', dismissible: false, expiresAt: '2026-11-20T00:00:00Z' },
		NEWS[0],
	]

	it('hides closed notices, marks read ones, records the rest as seen, and drops one at its end time', async () => {
		vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'Date'] })
		vi.setSystemTime(new Date('2026-11-13T18:00:00Z'))
		announcementsApi.list.mockResolvedValue({ announcements: notices() })
		announcementsApi.dismissedNotices.mockResolvedValue(['x', 'gone'])
		announcementsApi.readState.mockResolvedValue({ seen: ['w'], confirmed: ['w', 'c'], closed: ['c'], receipts: [] })
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		expect(wrapper.findAll('[data-test="notice-banner"]').map((b) => b.find('.notice-banner__title').text()))
			.toEqual(['Onderhoud zaaksysteem', 'Nieuwe printer'])
		expect(wrapper.findAllComponents(NoticeBanner).map((b) => b.props('read'))).toEqual([true, false])
		expect(announcementsApi.markSeen).toHaveBeenCalledTimes(1)
		expect(announcementsApi.markSeen).toHaveBeenCalledWith(['w', 'd'])

		vi.setSystemTime(new Date('2026-11-14T12:01:00Z'))
		vi.advanceTimersByTime(TICK_MS)
		await flushPromises()
		expect(wrapper.findAll('[data-test="notice-banner"]')).toHaveLength(1)
		wrapper.unmount()
	})

	it('the check confirms and keeps the banner, the x confirms and hides it', async () => {
		announcementsApi.list.mockResolvedValue({ announcements: [{ uuid: 'd', kind: 'notice', title: 'Nieuwe printer', level: 'info' }, { uuid: 'e', kind: 'notice', title: 'Brandoefening', level: 'info' }] })
		announcementsApi.dismissedNotices.mockResolvedValue([])
		announcementsApi.confirmRead.mockResolvedValue({ confirmed: true })
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		await wrapper.find('[data-test="notice-confirm"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.confirmRead).toHaveBeenCalledWith('d', 'check')
		expect(wrapper.findAll('[data-test="notice-banner"]')).toHaveLength(2)
		expect(wrapper.findAllComponents(NoticeBanner)[0].props('read')).toBe(true)

		await wrapper.findAll('[data-test="notice-dismiss"]')[1].trigger('click')
		await flushPromises()
		expect(announcementsApi.confirmRead).toHaveBeenCalledWith('e', 'x')
		expect(wrapper.findAll('[data-test="notice-banner"]')).toHaveLength(1)
		expect(announcementsApi.saveDismissedNotices).not.toHaveBeenCalled()
		wrapper.unmount()
	})

	it('asks for the notices, the dismissed ones and the read state at the same time', async () => {
		let release
		announcementsApi.list.mockReturnValue(new Promise((resolve) => { release = resolve }))
		announcementsApi.dismissedNotices.mockResolvedValue([])
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		expect(announcementsApi.dismissedNotices).toHaveBeenCalled()
		expect(announcementsApi.readState).toHaveBeenCalled()
		release({ announcements: [] })
		await flushPromises()
		expect(announcementsApi.markSeen).not.toHaveBeenCalled()
		wrapper.unmount()
	})

	it('still shows the notices when the dismissed list or the read state cannot be read', async () => {
		announcementsApi.list.mockResolvedValue({
			announcements: [{ uuid: 'd', kind: 'notice', title: 'Nieuwe printer', level: 'info' }],
		})
		announcementsApi.dismissedNotices.mockRejectedValue(new Error('preferences down'))
		announcementsApi.readState.mockRejectedValue(new Error('state down'))
		announcementsApi.markSeen.mockRejectedValue(new Error('seen down'))
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		expect(wrapper.vm.dismissed).toEqual([])
		expect(wrapper.findAll('[data-test="notice-banner"]')).toHaveLength(1)
		expect(logger.error).toHaveBeenCalledWith('Failed to load dismissed notices:', expect.any(Error))
		expect(logger.error).toHaveBeenCalledWith('Failed to load the read state of the notices:', expect.any(Error))
		expect(logger.error).toHaveBeenCalledWith('Failed to record the notices as seen:', expect.any(Error))
		wrapper.unmount()
	})

	it('shows no region when the notices cannot be loaded', async () => {
		announcementsApi.list.mockRejectedValue(new Error('server down'))
		announcementsApi.dismissedNotices.mockResolvedValue(['d'])
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		expect(wrapper.find('[data-test="notice-region"]').exists()).toBe(false)
		expect(wrapper.vm.notices).toEqual([])
		expect(logger.error).toHaveBeenCalledWith('Failed to load notices:', expect.any(Error))
		wrapper.unmount()
	})

	it('hides a closed notice even when saving the confirmation fails', async () => {
		announcementsApi.list.mockResolvedValue({
			announcements: [{ uuid: 'd', kind: 'notice', title: 'Nieuwe printer', level: 'info' }],
		})
		announcementsApi.dismissedNotices.mockResolvedValue([])
		announcementsApi.confirmRead.mockRejectedValue(new Error('save failed'))
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		await wrapper.vm.confirm('d', 'x')
		await flushPromises()
		expect(wrapper.find('[data-test="notice-region"]').exists()).toBe(false)
		expect(logger.error).toHaveBeenCalledWith('Failed to confirm the notice:', expect.any(Error))
		wrapper.unmount()
	})

	it('ignores a confirmation for an unknown notice', async () => {
		announcementsApi.list.mockResolvedValue({ announcements: [] })
		announcementsApi.dismissedNotices.mockResolvedValue([])
		const wrapper = mountWith(NoticeBannerRegion)
		await flushPromises()

		await wrapper.vm.confirm('nope', 'x')
		expect(announcementsApi.confirmRead).not.toHaveBeenCalled()
		wrapper.unmount()
	})
})

describe('AnnouncementEditorModal', () => {
	beforeEach(() => {
		announcementsApi.groups.mockResolvedValue([{ id: 'burgerzaken', displayName: 'Burgerzaken' }])
		announcementsApi.reach.mockResolvedValue(4)
	})

	it('refuses a notice without an end time before any call', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, { kind: 'notice', title: 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00' })
		await wrapper.vm.submit()

		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('Choose until when the announcement shows')
		expect(announcementsApi.create).not.toHaveBeenCalled()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	it('requires "Show until" for news too, and explains it', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		const input = wrapper.find('[data-test="editor-expires-at"]')
		expect(input.attributes('required')).toBeDefined()
		expect(wrapper.text()).toContain('Show until')
		expect(wrapper.find('[data-test="editor-show-until-hint"]').text()).toBe('After this time the announcement disappears from every dashboard.')

		wrapper.vm.form.title = 'Inloopspreekuur privacy'
		await wrapper.vm.submit()
		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('Choose until when the announcement shows')
		expect(announcementsApi.create).not.toHaveBeenCalled()
	})

	it('refuses an end time before the publish time', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, { title: 'Inloopspreekuur privacy', publishAt: '2099-11-02T08:00', expiresAt: '2099-11-01T17:00' })
		await wrapper.vm.submit()
		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('The end time must be after the publish time')
		expect(announcementsApi.create).not.toHaveBeenCalled()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	it('shows the form and "This is how it looks" side by side, the kind as two choice cards', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		await flushPromises()

		const columns = wrapper.find('.announcement-editor__columns')
		expect(columns.find('[data-test="announcement-editor-form"]').exists()).toBe(true)
		expect(columns.find('[data-test="editor-preview-pane"]').text()).toContain('This is how it looks')
		expect(wrapper.findAll('[data-test="editor-kind"] input[type="radio"]').map((radio) => radio.element.value)).toEqual(['news', 'notice'])
		expect(wrapper.find('[data-test="editor-kind-notice"]').text()).toContain('Maintenance notice')
		expect(wrapper.find('[data-test="editor-kind-notice"]').text()).toContain('Shown at the top of every dashboard until the end time.')
		expect(wrapper.find('[data-test="editor-kind-news"]').classes()).toContain('announcement-editor__kind--chosen')

		await wrapper.find('[data-test="editor-kind-notice"] input').setValue(true)
		expect(wrapper.vm.form.kind).toBe('notice')
		expect(wrapper.find('[data-test="notice-banner"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="announcement-card"]').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('Allow comments and following')
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	it('Write a news item: the preview shows the title at once, without saving', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		await flushPromises()
		await wrapper.find('[data-test="editor-title"]').setValue('Nieuwe werkplekken op de 3e verdieping')
		await wrapper.find('[data-test="editor-body"]').setValue('Vanaf maandag')

		const card = wrapper.find('[data-test="editor-preview-pane"] [data-test="announcement-card"]')
		expect(card.find('h4').text()).toBe('Nieuwe werkplekken op de 3e verdieping')
		expect(card.text()).toContain('Vanaf maandag')
		expect(wrapper.text()).toContain('Allow comments and following')
		expect(announcementsApi.create).not.toHaveBeenCalled()
		expect(announcementsApi.update).not.toHaveBeenCalled()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	it('offers the groups and counts the reach again when the target group changes', async () => {
		announcementsApi.reach.mockResolvedValueOnce(120).mockResolvedValueOnce(42)
		const wrapper = mountWith(AnnouncementEditorModal)
		await flushPromises()
		expect(wrapper.vm.groupOptions).toEqual([{ id: 'burgerzaken', displayName: 'Burgerzaken' }])
		expect(wrapper.find('[data-test="editor-reach"]').text()).toBe('Reaches 120 people')

		wrapper.vm.form.targetGroups = ['burgerzaken']
		await flushPromises()
		expect(announcementsApi.reach).toHaveBeenLastCalledWith(['burgerzaken'])
		expect(wrapper.find('[data-test="editor-reach"]').text()).toBe('Reaches 42 people')
	})

	it('keeps the preview working when the groups or reach cannot be loaded', async () => {
		announcementsApi.groups.mockRejectedValueOnce(new Error('x'))
		announcementsApi.reach.mockRejectedValueOnce(new Error('y'))
		const wrapper = mountWith(AnnouncementEditorModal)
		await flushPromises()
		expect(wrapper.vm.groups).toEqual([])
		expect(wrapper.find('[data-test="editor-reach"]').text()).toBe('Reaches 0 people')
		expect(logger.error).toHaveBeenCalledTimes(2)
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	it('says only the author sees the preview until the publication time', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		expect(wrapper.find('[data-test="editor-preview-note"]').text()).toBe('Only you see this preview until you publish the announcement.')
		wrapper.vm.form.publishAt = '2099-11-02T08:00'
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-test="editor-preview-note"]').text()).toMatch(/^Only you see this preview until .*November/)
	})

	it('names the end time in the preview and reads Schedule for a future publish time', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, { title: 'Inloopspreekuur privacy', publishAt: '2099-11-02T08:00', expiresAt: '2099-11-06T17:00' })
		await wrapper.vm.$nextTick()
		const until = wrapper.find('[data-test="editor-visible-until"]').text()
		expect(until.startsWith('Visible until ')).toBe(true)
		expect(until).toMatch(/6/)
		expect(until).toMatch(/November/)
		expect(wrapper.find('[data-test="editor-publish"]').text()).toBe('Schedule')
		expect(wrapper.find('[data-test="editor-cancel"]').text()).toBe('Cancel')
	})

	it('reads Publish when the publish time is empty or past', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, { title: 'Inloopspreekuur privacy', expiresAt: '2099-11-06T17:00' })
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-test="editor-publish"]').text()).toBe('Publish')
		wrapper.vm.form.publishAt = '2020-01-01T08:00'
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-test="editor-publish"]').text()).toBe('Publish')
	})

	it('Cancel closes without saving', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		await wrapper.find('[data-test="editor-cancel"]').trigger('click')
		expect(wrapper.emitted('close')).toHaveLength(1)
		expect(announcementsApi.create).not.toHaveBeenCalled()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	it('saves and schedules a notice in one step, and checks "Preview as" before', async () => {
		announcementsApi.create.mockResolvedValue({ uuid: 'u1' })
		announcementsApi.reachFor.mockResolvedValue({ reach: 42, reachesUser: false })
		announcementsApi.publish.mockResolvedValue({ uuid: 'u1', status: 'published' })
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, {
			kind: 'notice',
			title: 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00',
			targetGroups: ['Burgerzaken'],
			level: 'warning',
			dismissible: false,
			publishAt: '2099-11-13T08:00',
			expiresAt: '2099-11-14T12:00',
		})
		await flushPromises()

		wrapper.vm.previewAs = 'sanne'
		await wrapper.vm.checkPreviewAs()
		await flushPromises()
		expect(announcementsApi.reachFor).toHaveBeenCalledWith(['Burgerzaken'], 'sanne')
		expect(wrapper.find('[data-test="editor-preview-as-answer"]').text()).toBe('sanne does not see this announcement')

		expect(wrapper.find('[data-test="editor-publish"]').text()).toBe('Schedule')
		await wrapper.find('[data-test="editor-publish"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.create).toHaveBeenCalledWith(expect.objectContaining({
			kind: 'notice', level: 'warning', dismissible: false, allowComments: false, targetGroups: ['Burgerzaken'], category: null,
			publishAt: toIso('2099-11-13T08:00'),
		}))
		expect(announcementsApi.publish).toHaveBeenCalledWith('u1')
		expect(wrapper.emitted('published')).toEqual([[{ uuid: 'u1', status: 'published' }]])
	})

	it('shows the server refusal and updates the same announcement on a second try', async () => {
		announcementsApi.create.mockResolvedValue({ uuid: 'u2' })
		announcementsApi.update.mockResolvedValue({ uuid: 'u2' })
		announcementsApi.publish.mockRejectedValueOnce({ response: { data: { message: 'The end time has already passed' } } })
		const wrapper = mountWith(AnnouncementEditorModal)
		wrapper.vm.form.title = 'Nieuwe werkplekken op de 3e verdieping'
		wrapper.vm.form.expiresAt = '2099-11-06T17:00'
		await wrapper.vm.submit()
		await flushPromises()
		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('The end time has already passed')
		expect(wrapper.emitted('published')).toBeUndefined()

		announcementsApi.publish.mockResolvedValueOnce({ uuid: 'u2', status: 'published' })
		await wrapper.vm.submit()
		await flushPromises()
		expect(announcementsApi.create).toHaveBeenCalledTimes(1)
		expect(announcementsApi.update).toHaveBeenCalledWith('u2', expect.objectContaining({ kind: 'news', allowComments: true, level: null }))
		expect(wrapper.emitted('published')).toHaveLength(1)
	})

	it('does nothing without a title', async () => {
		const wrapper = mountWith(AnnouncementEditorModal)
		wrapper.vm.form.expiresAt = '2099-11-06T17:00'
		await wrapper.vm.submit()
		expect(announcementsApi.create).not.toHaveBeenCalled()
		expect(wrapper.find('[data-test="editor-publish"]').attributes('disabled')).toBeDefined()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	it('keeps chosen groups the list does not hold, and names the preview audience', () => {
		expect(groupChoices([{ id: 'a', displayName: 'A' }], ['a', 'gone'])).toEqual([{ id: 'a', displayName: 'A' }, { id: 'gone', displayName: 'gone' }])
		expect(previewNoteText('', 'nl-NL')).toBe('Only you see this preview until you publish the announcement.')
		expect(previewNoteText('2026-10-30T09:00', 'nl-NL')).toBe('Only you see this preview until 30 oktober 09:00.')
	})

	it('checks the end time against the publish time or now', () => {
		const now = new Date('2026-10-29T12:00')
		expect(endTimeProblem('', '', now)).toBe('Choose until when the announcement shows')
		expect(endTimeProblem('', '2026-10-29T11:00', now)).toBe('The end time must be after the publish time')
		expect(endTimeProblem('', '2026-10-30T17:00', now)).toBe('')
		expect(endTimeProblem('2026-10-30T17:00', '2026-10-30T17:00', now)).toBe('The end time must be after the publish time')
		expect(visibleUntilText('', 'nl-NL')).toBe('')
		expect(visibleUntilText('2026-10-30T17:00', 'nl-NL')).toBe('30 oktober 17:00')
	})

	it('turns a datetime-local value into ISO, and empty into null', () => {
		expect(toIso('')).toBeNull()
		expect(toIso('nonsense')).toBeNull()
		expect(toIso('2026-11-14T12:00')).toBe(new Date('2026-11-14T12:00').toISOString())
	})
})

describe('Read confirmation (REQ-ANN-007)', () => {
	const ASKING = { uuid: 'r', kind: 'news', title: 'New procedure', requiresConfirmation: true, confirmBefore: '2026-10-30T22:59:00Z', publishAt: '2026-10-26T08:00:00Z' }

	// @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('the card shows the check, the x and the date to confirm before; Manage only for a manager', async () => {
		const wrapper = mountWith(AnnouncementCard, { announcement: ASKING, manageUrl: '/settings/admin/launchpad#announcements/r' })
		expect(wrapper.find('[data-test="announcement-confirm-before"]').text()).toMatch(/^Confirm before .*30/)
		await wrapper.find('[data-test="announcement-confirm-read"]').trigger('click')
		await wrapper.find('[data-test="announcement-close"]').trigger('click')
		await wrapper.find('[data-test="announcement-open"]').trigger('click')
		expect(wrapper.emitted('confirm')).toEqual([['r', 'check'], ['r', 'x']])
		expect(wrapper.emitted('open')).toEqual([['r']])
		expect(wrapper.find('[data-test="announcement-manage"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="announcement-confirm-read"]').attributes('aria-label')).toBe('Mark as read')

		await wrapper.setProps({ confirmed: true, manageUrl: '' })
		expect(wrapper.find('[data-test="announcement-confirm-read"]').attributes('aria-label')).toBe('Read')
		expect(wrapper.find('[data-test="announcement-manage"]').exists()).toBe(false)

		const plain = mountWith(AnnouncementCard, { announcement: NEWS[0] })
		expect(plain.find('[data-test="announcement-confirm-before"]').exists()).toBe(false)
		expect(plain.find('[data-test="announcement-confirm-read"]').exists()).toBe(true)
		expect(confirmBeforeLabel(null)).toBe('')
		expect(confirmBeforeLabel('not a date')).toBe('')
	})

	// @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('in the widget the check marks the item read and keeps it, the x confirms and hides it', async () => {
		announcementsApi.list.mockResolvedValue({ announcements: [ASKING, NEWS[0]], reactions: {}, followedCategories: [], canAuthor: true })
		announcementsApi.readState.mockResolvedValue({ seen: [], confirmed: [], closed: [] })
		announcementsApi.confirmRead.mockResolvedValue({ uuid: 'r', confirmed: true })
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()
		expect(wrapper.find('[data-test="announcement-manage"]').attributes('href')).toMatch(/#announcements\/r$/)

		await wrapper.find('[data-test="announcement-confirm-read"]').trigger('click')
		await flushPromises()
		expect(announcementsApi.confirmRead).toHaveBeenCalledWith('r', 'check')
		expect(wrapper.findAll('[data-test="announcement-card"]')).toHaveLength(2)
		expect(wrapper.findAllComponents(AnnouncementCard)[0].props('confirmed')).toBe(true)

		wrapper.vm.open('a')
		await wrapper.findAll('[data-test="announcement-close"]')[1].trigger('click')
		await flushPromises()
		expect(announcementsApi.confirmRead).toHaveBeenCalledWith('a', 'x')
		expect(wrapper.findAll('[data-test="announcement-card"]')).toHaveLength(1)
		expect(wrapper.vm.openItem).toBeNull()
	})

	// @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('a failed confirmation says so and takes the mark back', async () => {
		announcementsApi.list.mockResolvedValue({ announcements: [ASKING], reactions: {}, followedCategories: [], canAuthor: false })
		announcementsApi.readState.mockRejectedValue(new Error('offline'))
		announcementsApi.confirmRead.mockRejectedValue(new Error('offline'))
		const wrapper = mountWith(AnnouncementsWidget)
		await flushPromises()
		// Without the read state the list still shows, nothing confirmed.
		expect(wrapper.find('[data-test="announcement-confirm-read"]').exists()).toBe(true)
		expect(announcementsApi.list).toHaveBeenCalledTimes(1)

		await wrapper.vm.confirm('r', 'x')
		expect(wrapper.vm.error).toBe('Your read confirmation could not be saved')
		expect(wrapper.vm.readState.confirmed).toEqual([])
		expect(wrapper.vm.readState.closed).toEqual([])
		expect(readConfirmationState({ confirmed: ['a', 5], closed: 'x' })).toEqual({ seen: [], confirmed: ['a'], closed: [] })
	})

	// @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
	it('the view shows the full text and the read row, and the social part only when allowed', async () => {
		const modal = mountWith(AnnouncementModal, { announcement: { ...ASKING, body: 'Lange tekst', allowComments: false }, manageUrl: '/x' })
		await flushPromises()
		expect(modal.find('[data-test="announcement-modal-body"]').text()).toBe('Lange tekst')
		expect(modal.find('[data-test="announcement-modal-social"]').exists()).toBe(false)
		expect(modal.find('[data-test="announcement-modal-manage"]').exists()).toBe(true)
		expect(announcementsApi.comments).not.toHaveBeenCalled()
		await modal.find('[data-test="announcement-modal-check"]').trigger('click')
		await modal.find('[data-test="announcement-modal-x"]').trigger('click')
		expect(modal.emitted('confirm')).toEqual([['r', 'check'], ['r', 'x']])

		announcementsApi.comments.mockRejectedValue(new Error('down'))
		announcementsApi.unlike.mockRejectedValue(new Error('down'))
		const social = mountWith(AnnouncementModal, { announcement: { ...ASKING, allowComments: true, category: 'Privacy' }, followed: ['Privacy'], reactions: { likes: 2, comments: 0, liked: true } })
		await flushPromises()
		expect(social.find('[data-test="announcements-follow"]').text()).toBe('Unfollow Privacy')
		expect(logger.error).toHaveBeenCalledWith('Failed to load comments:', expect.any(Error))
		await social.vm.toggleLike()
		expect(logger.error).toHaveBeenCalledWith('Failed to change the like:', expect.any(Error))
		await social.vm.sendComment()
		expect(announcementsApi.comment).not.toHaveBeenCalled()
		expect(mountWith(AnnouncementModal, { announcement: null }).find('[data-test="announcement-modal"]').exists()).toBe(false)
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('the form asks for it with a date, and the preview shows the check and "Confirm before"', async () => {
		announcementsApi.groups.mockResolvedValue([])
		announcementsApi.reach.mockResolvedValue(4)
		const wrapper = mountWith(AnnouncementEditorModal)
		await flushPromises()
		expect(wrapper.text()).toContain('Ask for read confirmation')
		expect(wrapper.find('[data-test="editor-confirm-before"]').exists()).toBe(false)
		expect(wrapper.vm.payload.requiresConfirmation).toBe(false)
		expect(wrapper.vm.payload.confirmBefore).toBeNull()

		Object.assign(wrapper.vm.form, { title: 'New procedure', requiresConfirmation: true })
		await flushPromises()
		await wrapper.find('[data-test="editor-confirm-before"]').setValue('2099-10-30')

		const preview = wrapper.find('[data-test="editor-preview-pane"]')
		expect(preview.find('[data-test="announcement-confirm-read"]').attributes('aria-label')).toBe('Mark as read')
		expect(preview.find('[data-test="announcement-confirm-read"]').attributes('disabled')).toBeDefined()
		expect(preview.find('[data-test="announcement-confirm-before"]').text()).toMatch(/^Confirm before .*30/)
		expect(wrapper.vm.payload.requiresConfirmation).toBe(true)
		expect(wrapper.vm.payload.confirmBefore).toBe(toIso('2099-10-30T23:59'))

		wrapper.vm.form.kind = 'notice'
		expect(wrapper.vm.payload.requiresConfirmation).toBe(false)
		expect(wrapper.vm.payload.confirmBefore).toBeNull()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('refuses a confirm-before date that is not after the publish time, before any call', async () => {
		announcementsApi.groups.mockResolvedValue([])
		announcementsApi.reach.mockResolvedValue(4)
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, {
			title: 'New procedure',
			publishAt: '2099-11-02T08:00',
			expiresAt: '2099-11-30T17:00',
			requiresConfirmation: true,
			confirmBefore: '2099-11-01',
		})
		await wrapper.vm.submit()

		expect(wrapper.find('[data-test="editor-error"]').text()).toBe('The confirm-before date must be after the publish time')
		expect(announcementsApi.create).not.toHaveBeenCalled()

		const now = new Date('2099-11-01T12:00:00Z')
		expect(confirmBeforeProblem('', null, now)).toBe('')
		expect(confirmBeforeProblem('', '2099-11-02T00:00:00Z', now)).toBe('')
		expect(confirmBeforeProblem('', '2099-11-01T10:00:00Z', now)).toBe('The confirm-before date must be after the publish time')
		expect(confirmBeforeIso('')).toBeNull()
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('sends the question and its deadline with the announcement', async () => {
		announcementsApi.groups.mockResolvedValue([])
		announcementsApi.reach.mockResolvedValue(4)
		announcementsApi.create.mockResolvedValue({ uuid: 'new' })
		announcementsApi.publish.mockResolvedValue({ uuid: 'new', status: 'published' })
		const wrapper = mountWith(AnnouncementEditorModal)
		Object.assign(wrapper.vm.form, { title: 'New procedure', expiresAt: '2099-11-30T17:00', requiresConfirmation: true, confirmBefore: '2099-11-20' })
		await wrapper.vm.submit()

		expect(announcementsApi.create).toHaveBeenCalledWith(expect.objectContaining({ requiresConfirmation: true, confirmBefore: toIso('2099-11-20T23:59') }))
		expect(wrapper.emitted('published')).toHaveLength(1)
	})
})
