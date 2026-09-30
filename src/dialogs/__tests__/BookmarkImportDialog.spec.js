/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `BookmarkImportDialog.vue` and the "Import
 * bookmarks…" menu entry (launcher-bookmark-import, REQ-BMI-001..004): the
 * file is read in the browser, only the chosen items are sent, skipped
 * bookmarks and a full dashboard are explained, an oversized file never
 * reaches the server, and a view-only dashboard offers no import.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardRowActions from '../../components/Workspace/DashboardRowActions.vue'
import BookmarkImportDialog from '../BookmarkImportDialog.vue'

vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path, params = {}) => path.replace('{id}', params.id),
}))

const FILE = `<!DOCTYPE NETSCAPE-Bookmark-file-1><DL><p>
<DT><A HREF="https://los.nl/">Los</A>
<DT><H3>Werk</H3><DL><p><DT><A HREF="https://zaken.nl/">Zaken</A><DT><A HREF="javascript:alert(1)">Script</A></DL><p>
<DT><H3>Privé</H3><DL><p><DT><A HREF="https://nos.nl/">NOS</A></DL><p>
</DL><p>`

const stubs = {
	NcDialog: {
		props: ['open', 'name'],
		template:
			'<div v-if="open" class="dialog"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}

function settle() {
	return new Promise((resolve) => setTimeout(resolve, 0))
}

async function chooseFile(wrapper, text, size = text.length) {
	const input = wrapper.find('input[type="file"]')
	Object.defineProperty(input.element, 'files', {
		value: [{ size, text: () => Promise.resolve(text) }],
		configurable: true,
	})
	await input.trigger('change')
	await settle()
}

describe('BookmarkImportDialog', () => {
	beforeEach(() => {
		axios.post.mockReset()
	})

	function mountDialog() {
		return mount(BookmarkImportDialog, {
			props: { open: true, dashboardId: 7 },
			global: { stubs },
		})
	}

	it('REQ-BMI-001: sends only the chosen folder and reports the skipped bookmark', async () => {
		axios.post.mockResolvedValue({
			data: {
				placements: [{ id: 101, widgetId: 'container' }],
				containers: 1,
				tiles: 1,
				skipped: [
					{
						title: 'Script',
						url: 'javascript:alert(1)',
						reason: 'not-a-web-address',
					},
				],
			},
		})
		const wrapper = mountDialog()
		await chooseFile(wrapper, FILE)

		const options = wrapper.findAll('.bookmark-import__option')
		expect(options.map((option) => option.text())).toEqual([
			'Werk (2)',
			'Privé (1)',
			'Los',
		])
		const confirm = wrapper.find('[data-testid="bookmark-import-confirm"]')
		expect(confirm.attributes('disabled')).toBeDefined()

		await options[0].find('input').setValue(true)
		await wrapper
			.find('[data-testid="bookmark-import-confirm"]')
			.trigger('click')
		await settle()

		expect(axios.post).toHaveBeenCalledWith(
			'/apps/launchpad/api/dashboard/7/tiles/import',
			{
				folders: [
					{
						name: 'Werk',
						bookmarks: [
							{ title: 'Zaken', url: 'https://zaken.nl/' },
							{ title: 'Script', url: 'javascript:alert(1)' },
						],
					},
				],
				bookmarks: [],
			},
		)
		expect(wrapper.text()).toContain('Tiles added: 1')
		expect(wrapper.text()).toContain(
			'1 bookmark skipped: only web addresses can become tiles',
		)
		expect(wrapper.emitted('imported')[0][0]).toEqual([
			{ id: 101, widgetId: 'container' },
		])
	})

	it('REQ-BMI-003: a full dashboard says how much room is left', async () => {
		axios.post.mockRejectedValue({
			response: {
				status: 409,
				data: {
					error: 'quota_exceeded',
					quota: 'widgets',
					limit: 20,
					current: 18,
				},
			},
		})
		const wrapper = mountDialog()
		await chooseFile(wrapper, FILE)
		await wrapper.findAll('.bookmark-import__option input')[0].setValue(true)
		await wrapper
			.find('[data-testid="bookmark-import-confirm"]')
			.trigger('click')
		await settle()

		expect(wrapper.find('[role="alert"]').text()).toBe(
			'Room left on this dashboard: 2. Pick fewer folders.',
		)
		expect(wrapper.emitted('imported')).toBeUndefined()
	})

	it('REQ-BMI-004: more than 2,000 bookmarks is refused in the browser', async () => {
		const many =
			'<DL><p>'
			+ Array.from(
				{ length: 2001 },
				(_, i) => `<DT><A HREF="https://x.nl/${i}">${i}</A>`,
			).join('')
			+ '</DL>'
		const wrapper = mountDialog()
		await chooseFile(wrapper, many)

		expect(wrapper.find('[role="alert"]').text()).toBe(
			'This file has more than 2,000 bookmarks. Export one folder at a time.',
		)
		expect(axios.post).not.toHaveBeenCalled()
	})

	it('REQ-BMI-004: a file over 5 MB is refused in the browser', async () => {
		const wrapper = mountDialog()
		await chooseFile(wrapper, FILE, 6 * 1024 * 1024)

		expect(wrapper.find('[role="alert"]').text()).toBe(
			'This file is larger than 5 MB. Export one folder at a time.',
		)
	})
})

describe('Import bookmarks menu entry', () => {
	function mountActions(props) {
		return mount(DashboardRowActions, {
			props: {
				dashboard: { id: 7, uuid: 'u', name: 'Mijn werkplek' },
				source: 'user',
				...props,
			},
			global: {
				stubs: {
					NcActions: { template: '<div><slot /></div>' },
					NcActionButton: {
						emits: ['click'],
						template:
							'<button @click="$emit(\'click\')"><slot /></button>',
					},
				},
			},
		})
	}

	it('is offered on the active dashboard when the person may add widgets', async () => {
		const wrapper = mountActions({ canEdit: true, canImportBookmarks: true })
		const entry = wrapper.find('[data-testid="cog-import-bookmarks"]')
		expect(entry.exists()).toBe(true)
		await entry.trigger('click')
		expect(wrapper.emitted('importBookmarks')).toHaveLength(1)
	})

	it('REQ-BMI-001: a view-only dashboard offers no import', () => {
		expect(
			mountActions({ canEdit: false, canImportBookmarks: true })
				.find('[data-testid="cog-import-bookmarks"]')
				.exists(),
		).toBe(false)
	})
})
