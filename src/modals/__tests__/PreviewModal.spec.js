/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PreviewModal (scheduled-exports task 16): POSTs /api/exports/{id}/preview
 * with the chosen format and recipient and asks for the file; a PDF is
 * embedded, a PNG shown as an image, a CSV or XLSX offered as a download,
 * and a refused render (422, JSON inside the Blob) shows the reason. Only
 * HTTP, the router, l10n and the object-URL API are mocked.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PreviewModal from '../PreviewModal.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
	generateRemoteUrl: (service) => `/remote.php/${service}`,
}))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock(
	'@conduction/nextcloud-vue',
	() => import('./helpers/scheduledExportStubs.js'),
)

const BASE = '/index.php/apps/launchpad/api/exports'

const exportData = {
	id: 'e1',
	name: 'CBS return',
	renderTargets: [{ format: 'xlsx' }, { format: 'pdf' }],
	recipients: [
		{
			id: 'r1',
			channel: 'email',
			channelConfig: { addresses: ['compliance@gemeente.nl'] },
		},
		{
			id: 'r2',
			channel: 'sftp',
			channelConfig: { host: 'sftp.cbs.nl' },
			filterContext: { gemeenteCode: 'GM0355' },
		},
	],
}

const originalCreate = URL.createObjectURL
const originalRevoke = URL.revokeObjectURL

/**
 * Mount the preview for the export.
 *
 * @return {object} The wrapper.
 */
function mountPreview() {
	return mount(PreviewModal, { props: { open: true, exportData } })
}

describe('PreviewModal', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
		URL.createObjectURL = vi.fn(() => 'blob:preview-1')
		URL.revokeObjectURL = vi.fn()
	})

	afterEach(() => {
		URL.createObjectURL = originalCreate
		URL.revokeObjectURL = originalRevoke
	})

	it("starts on the export's first format and offers each recipient", () => {
		const wrapper = mountPreview()
		const format = wrapper.find('[data-testid="preview-format"]')
		expect(format.element.value).toBe('Excel workbook (XLSX)')
		const recipients = wrapper
			.find('[data-testid="preview-recipient"]')
			.findAll('option')
			.map((o) => o.text())
		expect(recipients).toEqual([
			'No recipient (all data)',
			'Email: compliance@gemeente.nl',
			'SFTP: sftp.cbs.nl',
		])
	})

	it('embeds a PDF rendered without a recipient', async () => {
		axios.post.mockResolvedValue({
			data: new Blob(['%PDF'], { type: 'application/pdf' }),
		})
		const wrapper = mountPreview()

		await wrapper.find('[data-testid="preview-format"]').setValue('PDF document')
		await wrapper.find('[data-testid="preview-render"]').trigger('click')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/e1/preview`, null, {
			params: { recipientId: '', format: 'pdf' },
			responseType: 'blob',
		})
		const pdf = wrapper.find('[data-testid="preview-pdf"]')
		expect(pdf.attributes('data')).toBe('blob:preview-1')
		expect(pdf.attributes('type')).toBe('application/pdf')
	})

	it('shows a PNG as an image', async () => {
		axios.post.mockResolvedValue({
			data: new Blob(['png'], { type: 'image/png' }),
		})
		const wrapper = mountPreview()

		await wrapper.find('[data-testid="preview-format"]').setValue('PNG image')
		await wrapper.find('[data-testid="preview-render"]').trigger('click')
		await flushPromises()

		const img = wrapper.find('[data-testid="preview-png"]')
		expect(img.attributes('src')).toBe('blob:preview-1')
		expect(img.attributes('alt')).toBe('Preview of CBS return')
	})

	it('offers a CSV for the chosen recipient as a download', async () => {
		axios.post.mockResolvedValue({
			data: new Blob(['a;b'], { type: 'text/csv' }),
		})
		const wrapper = mountPreview()

		await wrapper.find('[data-testid="preview-format"]').setValue('CSV table')
		await wrapper
			.find('[data-testid="preview-recipient"]')
			.setValue('SFTP: sftp.cbs.nl')
		await wrapper.find('[data-testid="preview-render"]').trigger('click')
		await flushPromises()

		expect(axios.post.mock.calls[0][2].params).toEqual({
			recipientId: 'r2',
			format: 'csv',
		})
		const link = wrapper.find('[data-testid="preview-download"] a')
		expect(link.attributes('href')).toBe('blob:preview-1')
		expect(link.attributes('download')).toBe('CBS return.csv')
		expect(wrapper.find('[data-testid="preview-pdf"]').exists()).toBe(false)
	})

	it('shows the reason a render was refused', async () => {
		const body = new Blob(
			[
				JSON.stringify({
					error: 'render_failed',
					message: 'No headless browser is configured',
				}),
			],
			{ type: 'application/json' },
		)
		axios.post.mockRejectedValue({ response: { status: 422, data: body } })
		const wrapper = mountPreview()

		await wrapper.find('[data-testid="preview-render"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="preview-error"]').text()).toBe(
			'No headless browser is configured',
		)
		expect(URL.createObjectURL).not.toHaveBeenCalled()
	})

	it('frees the previous file when a new preview is made', async () => {
		axios.post.mockResolvedValue({ data: new Blob(['x']) })
		const wrapper = mountPreview()
		await wrapper.find('[data-testid="preview-render"]').trigger('click')
		await flushPromises()
		await wrapper.find('[data-testid="preview-render"]').trigger('click')
		await flushPromises()

		expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-1')
	})
})
