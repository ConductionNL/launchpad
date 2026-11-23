/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The video form asks the parse endpoint about each URL and shows its error
 * under the field, which blocks saving (REQ-VID-005 "Invalid URL error").
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import VideoWidgetForm from '../VideoWidgetForm.vue'

vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnVideoWidgetForm: {
		name: 'CnVideoWidgetForm',
		props: ['editingWidget', 'value', 'urlError'],
		emits: ['update:content'],
		template: '<div class="cn-video-form" />',
		computed: { assembledContent: () => ({ videoUrl: 'x' }) },
		methods: {
			validate() {
				return this.urlError ? [this.urlError] : []
			},
		},
	},
}))

/**
 * Mount the form and type a URL into the shared form.
 *
 * @param {object} content The content the shared form emits.
 * @return {Promise<object>} The wrapper.
 */
async function typed(content) {
	const wrapper = mount(VideoWidgetForm)
	wrapper.findComponent({ name: 'CnVideoWidgetForm' }).vm.$emit('update:content', content)
	vi.advanceTimersByTime(300)
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.useFakeTimers()
	axios.post.mockReset()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('VideoWidgetForm', () => {
	it('shows the endpoint\'s error for an invalid URL, and validate returns it', async () => {
		axios.post.mockRejectedValue({ response: { status: 400, data: { isValid: false, error: 'Invalid URL format' } } })
		const wrapper = await typed({ sourceType: null, videoUrl: 'not a valid URL' })

		expect(axios.post).toHaveBeenCalledWith('/index.php/apps/launchpad/api/widgets/video/parse', { videoUrl: 'not a valid URL' })
		expect(wrapper.findComponent({ name: 'CnVideoWidgetForm' }).props('urlError')).toBe('Invalid URL format')
		expect(wrapper.vm.validate()).toEqual(['Invalid URL format'])
		expect(wrapper.emitted('update:content')).toHaveLength(2)
	})

	it('clears the error for a URL the endpoint accepts', async () => {
		axios.post.mockResolvedValue({ data: { isValid: true, canonicalUrl: 'https://www.youtube.com/embed/ABC123' } })
		const wrapper = await typed({ sourceType: 'youtube', videoUrl: 'https://www.youtube.com/embed/ABC123' })
		expect(wrapper.findComponent({ name: 'CnVideoWidgetForm' }).props('urlError')).toBe('')
	})

	it('does not parse a Nextcloud file source or an empty URL', async () => {
		await typed({ sourceType: 'nc-file', videoUrl: '42', fileId: 42 })
		await typed({ sourceType: null, videoUrl: '' })
		expect(axios.post).not.toHaveBeenCalled()
	})

	it('ignores the answer for a URL that is no longer in the field', async () => {
		let answer
		axios.post.mockImplementation(() => new Promise((resolve, reject) => {
			answer = reject
		}))
		const wrapper = await typed({ sourceType: null, videoUrl: 'old' })
		wrapper.findComponent({ name: 'CnVideoWidgetForm' }).vm.$emit('update:content', { sourceType: 'youtube', videoUrl: 'https://youtu.be/ABC123' })
		answer({ response: { data: { isValid: false, error: 'Invalid URL format' } } })
		await flushPromises()
		expect(wrapper.findComponent({ name: 'CnVideoWidgetForm' }).props('urlError')).toBe('')
	})

	it('hands the shared form\'s content to the dialog', () => {
		const wrapper = mount(VideoWidgetForm)
		expect(wrapper.vm.assembledContent).toEqual({ videoUrl: 'x' })
	})
})
