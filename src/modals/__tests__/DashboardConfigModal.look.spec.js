/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Look tab of the dashboard configuration (platform-dashboard-theme-and-css
 * REQ-THM-001/002): the owner gets the tab, the contrast check runs while
 * choosing, and only the look fields that changed are sent with the save.
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', async (importOriginal) => ({ ...(await importOriginal()), generateUrl: (path) => path }))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({ ...(await importOriginal()), t: (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? String(vars[k]) : m)) }))
vi.mock('../../services/resourceService.js', () => ({
	uploadDataUrl: vi.fn(),
	uploadFile: vi.fn(async () => ({ url: '/apps/launchpad/resource/resource_x.1.jpg', name: 'resource_x.1.jpg', size: 3 })),
}))

import DashboardConfigModal from '../DashboardConfigModal.vue'
import { uploadFile } from '../../services/resourceService.js'

const computed = DashboardConfigModal.computed

/**
 * A host with the look computeds bound, as the component would have them.
 *
 * @param {object} look The look being edited.
 * @param {object} initialLook The stored look.
 * @return {object}
 */
function host(look, initialLook = { accent: '', background: '', resource: '' }) {
	const self = { look, initialLook, form: { name: 'Team Groen' } }
	for (const name of ['lookAccentHex', 'lookBackgroundHex', 'lookFormatError', 'lookAccentCheck', 'lookValid', 'lookPayload', 'canSave']) {
		Object.defineProperty(self, name, { get: () => computed[name].call(self) })
	}
	return self
}

describe('DashboardConfigModal look', () => {
	it('gives the owner a Look tab between Languages and Sharing', () => {
		const tabs = computed.tabs.call({ canManageShares: true, dashboard: { uuid: 'u-1' }, localShares: [] })
		expect(tabs.map((tab) => tab.id)).toEqual(['general', 'languages', 'look', 'sharing'])
		expect(computed.tabs.call({ canManageShares: false, dashboard: { uuid: 'u-1' }, localShares: [] }).map((tab) => tab.id)).toEqual(['general'])
	})

	it('refuses a pale accent while choosing and blocks the save (REQ-THM-002)', () => {
		const pale = host({ accent: '#f5f5dc', background: '', resource: '' })
		expect(pale.lookAccentCheck).toEqual({ ratio: 1.1, readable: false })
		expect(pale.canSave).toBe(false)

		const green = host({ accent: '#1f5c3a', background: '', resource: '' })
		expect(green.lookValid).toBe(true)
		expect(green.canSave).toBe(true)
	})

	it('says when a field holds something other than a colour', () => {
		expect(host({ accent: 'green', background: '', resource: '' }).lookFormatError).toBe('Enter colors like #1f5c3a.')
		expect(host({ accent: 'green', background: '', resource: '' }).canSave).toBe(false)
		expect(host({ accent: '', background: '', resource: '' }).lookFormatError).toBe('')
	})

	it('sends only the look fields that changed, and nothing for a rename', () => {
		const stored = { accent: '#1f5c3a', background: '', resource: 'resource_a.png' }
		expect(host({ ...stored }, stored).lookPayload).toBeNull()
		expect(host({ ...stored, accent: '#1F5C3A' }, stored).lookPayload).toBeNull()
		expect(host({ ...stored, background: '#12301F', resource: '' }, stored).lookPayload).toEqual({
			themeBackgroundColor: '#12301f',
			themeBackgroundResource: '',
		})
	})

	it('uploads a background image and keeps its resource name', async () => {
		const self = { look: { accent: '', background: '', resource: '' }, lookUploading: false, lookUploadError: '' }
		const file = new Blob(['x'], { type: 'image/jpeg' })

		await DashboardConfigModal.methods.onLookImage.call(self, { target: { files: [file] } })

		expect(uploadFile).toHaveBeenCalledWith(file)
		expect(self.look.resource).toBe('resource_x.1.jpg')
		expect(self.lookUploading).toBe(false)
	})

	it('emits the changed look with the save', async () => {
		const emitted = []
		const self = host({ accent: '#1f5c3a', background: '', resource: '' })
		Object.assign(self, {
			isCreate: false,
			canManageShares: false,
			sharesDirty: false,
			dashboard: { id: 7, uuid: 'u-7' },
			form: { name: 'Team Groen', description: '', icon: 'x', parent: { uuid: '' }, slug: '', isDefault: false },
			_initialIsDefault: false,
			$emit: (...args) => emitted.push(args),
		})

		await DashboardConfigModal.methods.onSave.call(self)

		expect(emitted[0][0]).toBe('save')
		expect(emitted[0][1].look).toEqual({ themeAccent: '#1f5c3a' })
	})
})
