/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AnnouncementEditorModal (openspec/specs/announcements REQ-ANN-004): the
 * preview step renders the reader's component with the reach, "Preview as"
 * answers per person, and publishing is a separate action after the preview.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

import axios from '@nextcloud/axios'
import AnnouncementEditorModal from '../AnnouncementEditorModal.vue'

/**
 * A button in the modal by its text (NcModal renders into document.body).
 *
 * @param {object} _wrapper Unused; kept for readability at the call sites.
 * @param {string} text The button text.
 * @return {HTMLButtonElement|undefined} The button.
 */
function buttonByText(_wrapper, text) {
	return [...document.body.querySelectorAll('button')].find((node) => node.textContent.trim() === text)
}

async function writeNotice() {
	const wrapper = mount(AnnouncementEditorModal, { attachTo: document.body })
	wrapper.vm.form.kind = 'notice'
	wrapper.vm.form.level = 'warning'
	wrapper.vm.form.title = 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00'
	wrapper.vm.form.targetGroups = ['Burgerzaken']
	await flushPromises()
	return wrapper
}

/**
 * Answer the editor's POSTs the way the controller does.
 *
 * @param {{reaches: boolean|null, publishError?: string}} options Answers.
 */
function routePosts({ reaches, publishError = '' }) {
	axios.post.mockImplementation((url, body) => {
		if (url === '/apps/launchpad/api/announcements') {
			return Promise.resolve({ data: { uuid: 'u1', ...body } })
		}
		if (url === '/apps/launchpad/api/announcement-reach') {
			return Promise.resolve({ data: { reach: 42, reachesPreviewUser: body.previewUserId ? reaches : null } })
		}
		if (publishError) {
			return Promise.reject({ response: { status: 400, data: { error: publishError } } })
		}
		return Promise.resolve({ data: { uuid: 'u1', status: 'published' } })
	})
}

beforeEach(() => {
	axios.get.mockReset()
	axios.put.mockReset()
	axios.post.mockReset()
	document.body.innerHTML = ''
})

describe('AnnouncementEditorModal', () => {
	it('saves a draft on Preview and shows the banner readers will see with the reach', async () => {
		routePosts({ reaches: null })
		const wrapper = await writeNotice()

		await buttonByText(wrapper, 'Preview').click()
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith('/apps/launchpad/api/announcements', expect.objectContaining({ kind: 'notice', targetGroups: ['Burgerzaken'] }))
		expect(axios.post.mock.calls.map((call) => call[0])).not.toContain('/apps/launchpad/api/announcements/u1/publish')
		expect(document.body.querySelector('.notice-banner').textContent).toContain('Onderhoud zaaksysteem zaterdag 08:00 tot 12:00')
		expect(document.body.textContent).toContain('Reaches about 42 people')
		wrapper.unmount()
	})

	it('answers "Preview as" for one person', async () => {
		routePosts({ reaches: false })
		const wrapper = await writeNotice()
		await buttonByText(wrapper, 'Preview').click()
		await flushPromises()

		wrapper.vm.previewUserId = 'sanne'
		await flushPromises()
		await buttonByText(wrapper, 'Check').click()
		await flushPromises()

		expect(axios.post).toHaveBeenLastCalledWith('/apps/launchpad/api/announcement-reach', { targetGroups: ['Burgerzaken'], previewUserId: 'sanne' })
		expect(document.body.textContent).toContain('sanne does not see this announcement')
		wrapper.unmount()
	})

	it('publishes only from the preview, and shows the server refusal for a notice without an end time', async () => {
		routePosts({ reaches: null, publishError: 'A notice needs an end time' })
		const wrapper = await writeNotice()

		expect(buttonByText(wrapper, 'Publish')).toBeUndefined()
		await buttonByText(wrapper, 'Preview').click()
		await flushPromises()
		await buttonByText(wrapper, 'Publish').click()
		await flushPromises()

		expect(axios.post).toHaveBeenLastCalledWith('/apps/launchpad/api/announcements/u1/publish')
		expect(document.body.textContent).toContain('A notice needs an end time')
		expect(wrapper.emitted('published')).toBeUndefined()
		wrapper.unmount()
	})
})
