/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The bookmark import dialog: reading the file, ticking folders, refusing a
 * too large file before sending, the skipped list and the quota message
 * (launcher-bookmark-import REQ-BMI-001..004).
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */
import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import BookmarkImportModal from '../BookmarkImportModal.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k] ?? `{${k}}`)),
}))

const stubs = {
	NcModal: { name: 'NcModal', props: ['name'], emits: ['close'], template: '<div class="nc-modal-stub"><slot /></div>' },
	NcButton: { props: ['disabled'], emits: ['click'], template: '<button class="btn" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
}

const FILE = `<DL><p>
<DT><H3>Werk</H3><DL><p><DT><A HREF="https://wiki.example">Wiki</A><DT><A HREF="https://mail.example">Mail</A></DL><p>
<DT><H3>Privé</H3><DL><p><DT><A HREF="https://bank.example">Bank</A></DL><p>
<DT><A HREF="javascript:alert(1)">Run</A>
</DL>`

const fileOf = (text, size) => ({ size: size ?? text.length, text: async () => text })

function mountModal(importBookmarks = vi.fn()) {
	return mount(BookmarkImportModal, {
		props: { open: true, importBookmarks },
		global: { stubs, mocks: { t: (_a, k, vars = {}) => k.replace(/\{(\w+)\}/g, (_m, key) => String(vars[key] ?? `{${key}}`)) } },
	})
}

async function choose(wrapper, file) {
	const input = wrapper.find('[data-testid="bookmark-import-file"]')
	Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
	await input.trigger('change')
	await flushPromises()
}

const confirm = (wrapper) => wrapper.find('[data-testid="bookmark-import-confirm"]')

describe('BookmarkImportModal', () => {
	it('shows the folders and loose bookmarks of the file, all ticked, and labels every control', async () => {
		const wrapper = mountModal()
		expect(confirm(wrapper).attributes('disabled')).toBeDefined()
		await choose(wrapper, fileOf(FILE))

		const folders = wrapper.findAll('[data-testid="bookmark-import-folder"]')
		expect(folders).toHaveLength(2)
		expect(folders.every((box) => box.element.checked)).toBe(true)
		expect(wrapper.text()).toContain('Werk (2)')
		expect(wrapper.findAll('[data-testid="bookmark-import-bookmark"]')).toHaveLength(1)
		for (const box of wrapper.findAll('input[type="checkbox"]')) {
			expect(box.element.closest('label')).not.toBeNull()
		}
		expect(wrapper.find('[data-testid="bookmark-import-file"]').attributes('aria-label')).toBe('Import bookmarks')
		expect(confirm(wrapper).attributes('disabled')).toBeUndefined()
	})

	it('sends only the ticked folder: Werk, without Privé or the loose bookmark', async () => {
		const importBookmarks = vi.fn().mockResolvedValue({ placements: [{ id: 1 }], skipped: [] })
		const wrapper = mountModal(importBookmarks)
		await choose(wrapper, fileOf(FILE))
		const folders = wrapper.findAll('[data-testid="bookmark-import-folder"]')
		await folders[1].setValue(false)
		await wrapper.find('[data-testid="bookmark-import-bookmark"]').setValue(false)
		await confirm(wrapper).trigger('click')
		await flushPromises()

		expect(importBookmarks).toHaveBeenCalledWith({
			folders: [{ name: 'Werk', bookmarks: [{ title: 'Wiki', url: 'https://wiki.example' }, { title: 'Mail', url: 'https://mail.example' }] }],
			bookmarks: [],
		})
		expect(wrapper.emitted('imported')[0][0]).toEqual({ placements: [{ id: 1 }], skipped: [] })
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('says how many bookmarks were skipped and stays open', async () => {
		const importBookmarks = vi.fn().mockResolvedValue({ placements: [], skipped: [{ title: 'Run', url: 'javascript:alert(1)', reason: 'x' }] })
		const wrapper = mountModal(importBookmarks)
		await choose(wrapper, fileOf(FILE))
		await confirm(wrapper).trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="bookmark-import-skipped"]').text()).toBe('1 skipped: only web addresses can become tiles')
		expect(wrapper.emitted('close')).toBeUndefined()
	})

	it('says how much room is left when the import does not fit (409)', async () => {
		const importBookmarks = vi.fn().mockRejectedValue({ response: { status: 409, data: { quota: 'widgets', limit: 20, current: 18 } } })
		const wrapper = mountModal(importBookmarks)
		await choose(wrapper, fileOf(FILE))
		await confirm(wrapper).trigger('click')
		await flushPromises()

		const error = wrapper.find('[data-testid="bookmark-import-error"]')
		expect(error.attributes('role')).toBe('alert')
		expect(error.text()).toBe('This dashboard can take 2 more. Choose fewer folders or bookmarks.')
	})

	it('refuses a file with more than 2,000 bookmarks and sends nothing', async () => {
		const many = '<DL><p>' + Array.from({ length: 2001 }, (_v, i) => `<DT><A HREF="https://e.org/${i}">${i}</A>`).join('') + '</DL>'
		const importBookmarks = vi.fn()
		const wrapper = mountModal(importBookmarks)
		await choose(wrapper, fileOf(many))

		expect(wrapper.find('[data-testid="bookmark-import-error"]').text()).toBe('This file has more than 2,000 bookmarks. Export one folder at a time.')
		expect(confirm(wrapper).attributes('disabled')).toBeDefined()
		expect(importBookmarks).not.toHaveBeenCalled()
	})

	it('refuses a file over 5 MB without reading it', async () => {
		const wrapper = mountModal()
		await choose(wrapper, { size: 6 * 1024 * 1024, text: () => { throw new Error('read') } })
		expect(wrapper.find('[data-testid="bookmark-import-error"]').text()).toBe('This file is larger than 5 MB. Export one folder at a time.')
	})
})
