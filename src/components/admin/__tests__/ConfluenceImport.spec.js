/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ConfluenceImport.vue`: picking the archive, the
 * dry-run preview and the import (with the optional parent), the busy
 * labels, the results and the server or fallback error message.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConfluenceImport from '../ConfluenceImport.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

vi.mock('../../../services/api.js', () => ({
	api: { confluenceImportDryRun: vi.fn(), confluenceImport: vi.fn() },
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const ButtonStub = {
	props: ['disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const ZIP = new File(['zip'], 'export.zip', { type: 'application/zip' })

function mountImport() {
	return mount(ConfluenceImport, {
		global: { stubs: { NcButton: ButtonStub, Eye: true, Upload: true } },
	})
}

const dryRunButton = (w) => w.find('[data-test="confluence-dry-run"]')
const importButton = (w) => w.find('[data-test="confluence-import-submit"]')

/**
 * Select a file on the archive input.
 *
 * @param {object} wrapper the mounted component.
 * @param {File[]} files the selected files.
 */
async function pick(wrapper, files) {
	const input = wrapper.find('[data-test="confluence-import-file"]')
	Object.defineProperty(input.element, 'files', { value: files, configurable: true })
	await input.trigger('change')
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('ConfluenceImport', () => {
	it('disables both actions until an archive is picked, and takes the picked file', async () => {
		const wrapper = mountImport()
		expect(dryRunButton(wrapper).attributes('disabled')).toBeDefined()
		expect(importButton(wrapper).attributes('disabled')).toBeDefined()

		await pick(wrapper, [ZIP])
		expect(wrapper.vm.selectedFile).toBe(ZIP)

		await pick(wrapper, [])
		expect(wrapper.vm.selectedFile).toBeNull()
	})

	it('does nothing without an archive', async () => {
		const wrapper = mountImport()
		await wrapper.vm.runDryRun()
		await wrapper.vm.runImport()
		expect(api.confluenceImportDryRun).not.toHaveBeenCalled()
		expect(api.confluenceImport).not.toHaveBeenCalled()
	})

	it('previews the archive and lists its warnings', async () => {
		let resolve
		api.confluenceImportDryRun.mockReturnValue(new Promise((r) => { resolve = r }))
		const wrapper = mountImport()
		await pick(wrapper, [ZIP])

		const pending = wrapper.vm.runDryRun()
		await wrapper.vm.$nextTick()
		expect(dryRunButton(wrapper).text()).toBe('Inspecting…')
		expect(importButton(wrapper).attributes('disabled')).toBeDefined()

		resolve({ data: { pageCount: 4, attachmentCount: 2, estimatedDashboards: 4, assetFolder: '/Confluence', warnings: ['Skipped macro'] } })
		await pending
		await flushPromises()

		expect(api.confluenceImportDryRun).toHaveBeenCalledWith(ZIP)
		expect(wrapper.vm.dryRunResult.estimatedDashboards).toBe(4)
		expect(wrapper.find('.launchpad-confluence-import__sub').exists()).toBe(true)
		expect(wrapper.findAll('.launchpad-confluence-import__result li').map((li) => li.text())).toEqual(['Skipped macro'])
		expect(dryRunButton(wrapper).text()).toBe('Dry-run preview')
	})

	it('imports under the given parent, replacing the preview with the result', async () => {
		api.confluenceImportDryRun.mockResolvedValue({ data: { pageCount: 1 } })
		api.confluenceImport.mockResolvedValue({
			data: { createdDashboardCount: 3, skippedPageCount: 1, errors: [{ pageId: 'p9', reason: 'empty' }], warnings: ['w1'] },
		})
		const wrapper = mountImport()
		await pick(wrapper, [ZIP])
		await wrapper.vm.runDryRun()
		await flushPromises()
		await wrapper.find('#launchpad-cfli-parent').setValue('parent-uuid')

		await wrapper.vm.runImport()
		await flushPromises()

		expect(api.confluenceImport).toHaveBeenCalledWith(ZIP, { parentUuid: 'parent-uuid' })
		expect(wrapper.vm.dryRunResult).toBeNull()
		expect(wrapper.findAll('.launchpad-confluence-import__result li').map((li) => li.text())).toEqual(['p9: empty', 'w1'])
		expect(wrapper.find('.launchpad-confluence-import__sub').exists()).toBe(false)
	})

	it('imports as root dashboards without a parent', async () => {
		api.confluenceImport.mockResolvedValue({ data: {} })
		const wrapper = mountImport()
		await pick(wrapper, [ZIP])
		await wrapper.vm.runImport()
		await flushPromises()
		expect(api.confluenceImport).toHaveBeenCalledWith(ZIP, { parentUuid: null })
	})

	it('shows the server error message, else a fallback, and logs', async () => {
		api.confluenceImportDryRun.mockRejectedValue({ response: { data: { error: 'Not a Confluence export' } } })
		api.confluenceImport.mockRejectedValue(new Error('network'))
		const wrapper = mountImport()
		await pick(wrapper, [ZIP])

		await wrapper.vm.runDryRun()
		await flushPromises()
		expect(wrapper.find('.launchpad-confluence-import__error').text()).toBe('Not a Confluence export')

		await wrapper.vm.runImport()
		await flushPromises()
		expect(wrapper.find('.launchpad-confluence-import__error').text()).toBe('Confluence import failed. Please try again.')
		expect(wrapper.vm.running).toBe('')
		expect(logger.error.mock.calls.map((c) => c[0])).toEqual([
			'launchpad confluence dry-run failed',
			'launchpad confluence import failed',
		])
	})

	it('clears results and errors when another archive is picked', async () => {
		api.confluenceImportDryRun.mockRejectedValue(new Error('x'))
		const wrapper = mountImport()
		await pick(wrapper, [ZIP])
		await wrapper.vm.runDryRun()
		await flushPromises()
		expect(wrapper.vm.errorMessage).toBe('Confluence dry-run failed. Please try again.')

		await pick(wrapper, [ZIP])
		expect(wrapper.vm.errorMessage).toBe('')
		expect(wrapper.find('.launchpad-confluence-import__error').exists()).toBe(false)
	})
})
