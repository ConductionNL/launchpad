/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `VideoHost.vue` (REQ-VID-006): a Nextcloud-file
 * video is resolved through the placement's file endpoint before
 * CnVideoWidget renders it; hosted players pass straight through.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import VideoHost from '../VideoHost.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnVideoWidget: { name: 'CnVideoWidget', props: ['content', 'canEdit'], emits: ['edit'], template: '<div class="cn-video" />' },
	NcLoadingIcon: { name: 'NcLoadingIcon', template: '<span class="loading" />' },
}))

const FILE = { sourceType: 'nc-file', fileId: 42, autoplay: false }

beforeEach(() => {
	globalThis.t = (_app, key) => key
	axios.get.mockReset()
})

/**
 * Mount the host and let the file request settle.
 *
 * @param {object} content The placement content.
 * @return {Promise<object>} the CnVideoWidget content prop.
 */
async function renderedContent(content) {
	const wrapper = mount(VideoHost, { props: { content, placement: { id: 7 } } })
	await flushPromises()
	return wrapper.findComponent({ name: 'CnVideoWidget' }).props('content')
}

/**
 * An axios rejection carrying the endpoint's error code.
 *
 * @param {number} status The HTTP status.
 * @param {string} error The error code.
 * @return {Error}
 */
function refusal(status, error) {
	return Object.assign(new Error('refused'), { response: { status, data: { error } } })
}

describe('VideoHost', () => {
	it('asks the placement\'s file endpoint and plays the streaming URL it answers', async () => {
		axios.get.mockResolvedValue({ data: { streamingUrl: '/remote.php/dav/files/bob/a.webm' } })

		const content = await renderedContent(FILE)

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/widgets/video/7/file')
		expect(content).toEqual({ ...FILE, fileStreamingUrl: '/remote.php/dav/files/bob/a.webm' })
	})

	it('shows "Video file not found" for a deleted file and "File is not a video" for a non-video', async () => {
		axios.get.mockRejectedValueOnce(refusal(404, 'not_found'))
		expect(await renderedContent(FILE)).toMatchObject({ error: 'Video file not found', fileStreamingUrl: '' })

		axios.get.mockRejectedValueOnce(refusal(422, 'not_video'))
		expect(await renderedContent(FILE)).toMatchObject({ error: 'File is not a video', fileStreamingUrl: '' })
	})

	it('leaves the URL empty for an unreadable file, so the widget says "Video not accessible"', async () => {
		axios.get.mockRejectedValue(refusal(403, 'not_accessible'))

		const content = await renderedContent(FILE)

		expect(content.fileStreamingUrl).toBe('')
		expect(content.error).toBeUndefined()
	})

	it('passes a hosted player through without asking the file endpoint', async () => {
		const youtube = { sourceType: 'youtube', videoUrl: 'https://www.youtube.com/embed/ABC' }

		expect(await renderedContent(youtube)).toEqual(youtube)
		expect(axios.get).not.toHaveBeenCalled()
	})

	it('passes edit mode to the widget and relays its "Configure now" as edit', async () => {
		const wrapper = mount(VideoHost, { props: { content: { sourceType: null }, placement: { id: 7 }, canEdit: true } })
		await flushPromises()
		const widget = wrapper.findComponent({ name: 'CnVideoWidget' })
		expect(widget.props('canEdit')).toBe(true)
		widget.vm.$emit('edit')
		expect(wrapper.emitted('edit')).toHaveLength(1)
	})
})
