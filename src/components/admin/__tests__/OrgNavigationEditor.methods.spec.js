/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the OrgNavigationEditor working-copy methods the
 * main spec leaves out: nesting children, merging row edits, the move
 * guards at either end, language switching, the position guard, the
 * success toast timeout and the UUID fallback.
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import OrgNavigationEditor from '../OrgNavigationEditor.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('../../../services/api.js', () => ({
	api: {
		getOrgNavigation: vi.fn(),
		updateOrgNavigation: vi.fn(),
		getOrgNavigationPosition: vi.fn(),
		updateOrgNavigationPosition: vi.fn(),
	},
}))

const FIXED_UUID = '11111111-1111-4111-8111-111111111111'

/**
 * Let pending promises settle.
 */
async function flush() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

/**
 * Mount the editor on a fresh pinia and wait for bootstrap.
 *
 * @return {Promise<object>} The wrapper.
 */
async function mountEditor() {
	const pinia = createPinia()
	setActivePinia(pinia)
	const wrapper = mount(OrgNavigationEditor, { global: { plugins: [pinia] } })
	await flush()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getOrgNavigation.mockImplementation((lang) =>
		Promise.resolve({
			data: { tree: [{ id: `root-${lang}`, label: lang, children: [] }], language: lang },
		}),
	)
	api.updateOrgNavigation.mockImplementation((tree, lang) =>
		Promise.resolve({ data: { tree, language: lang } }),
	)
	api.getOrgNavigationPosition.mockResolvedValue({ data: { position: 'top' } })
	api.updateOrgNavigationPosition.mockResolvedValue({ data: { position: 'left' } })
	vi.spyOn(globalThis.crypto, 'randomUUID').mockReturnValue(FIXED_UUID)
})

afterEach(() => {
	vi.restoreAllMocks()
	vi.useRealTimers()
})

describe('OrgNavigationEditor methods', () => {
	it('bootstraps the position and the Dutch tree from the store', async () => {
		const wrapper = await mountEditor()

		expect(api.getOrgNavigation).toHaveBeenCalledWith('nl')
		expect(wrapper.vm.selectedPosition).toBe('top')
		expect(wrapper.vm.workingTree).toEqual([
			{ id: 'root-nl', label: 'nl', children: [] },
		])
	})

	it('addChild creates the children list when missing and appends a link', async () => {
		const wrapper = await mountEditor()
		const parent = { id: 'p' }

		wrapper.vm.addChild({ parent, kind: 'link' })
		wrapper.vm.addChild({ parent, kind: 'section' })

		expect(parent.children).toEqual([
			{
				id: FIXED_UUID,
				label: 'New link',
				icon: null,
				url: '/',
				openInNewTab: false,
				groupVisibility: null,
				children: [],
			},
			{
				id: FIXED_UUID,
				label: 'New section',
				icon: null,
				url: null,
				openInNewTab: false,
				groupVisibility: null,
				children: [],
			},
		])
	})

	it('onUpdate merges the patch into the node', async () => {
		const wrapper = await mountEditor()
		const node = { id: 'n', label: 'Old', url: '/a' }

		wrapper.vm.onUpdate({ node, patch: { label: 'New', openInNewTab: true } })

		expect(node).toEqual({ id: 'n', label: 'New', url: '/a', openInNewTab: true })
	})

	it('moveUp at the top and moveDown at the bottom leave the order alone', async () => {
		const wrapper = await mountEditor()
		const siblings = ['a', 'b', 'c']

		wrapper.vm.moveUp({ siblings, index: 0 })
		wrapper.vm.moveDown({ siblings, index: 2 })

		expect(siblings).toEqual(['a', 'b', 'c'])
	})

	it('moveDown swaps the node with the next sibling', async () => {
		const wrapper = await mountEditor()
		const siblings = ['a', 'b', 'c']

		wrapper.vm.moveDown({ siblings, index: 0 })

		expect(siblings).toEqual(['b', 'a', 'c'])
	})

	it('changing the language refetches and replaces the working tree with a copy', async () => {
		const wrapper = await mountEditor()

		await wrapper.find('select').setValue('en')
		await flush()

		expect(api.getOrgNavigation).toHaveBeenLastCalledWith('en')
		expect(wrapper.vm.workingTree).toEqual([
			{ id: 'root-en', label: 'en', children: [] },
		])
		expect(wrapper.vm.workingTree).not.toBe(wrapper.vm.store.visibleTree)
	})

	it('an unknown position is not sent to the store', async () => {
		const wrapper = await mountEditor()

		wrapper.vm.selectedPosition = 'bottom'
		await wrapper.vm.onPositionChange()

		expect(api.updateOrgNavigationPosition).not.toHaveBeenCalled()
	})

	it('picking a position from the select persists it', async () => {
		const wrapper = await mountEditor()

		await wrapper.findAll('select')[1].setValue('right')
		await flush()

		expect(api.updateOrgNavigationPosition).toHaveBeenCalledWith('right')
	})

	it('hides the success message three seconds after a save', async () => {
		const wrapper = await mountEditor()
		vi.useFakeTimers()

		await wrapper.vm.save()
		expect(wrapper.vm.successFlag).toBe(true)

		vi.advanceTimersByTime(2999)
		expect(wrapper.vm.successFlag).toBe(true)
		vi.advanceTimersByTime(1)
		expect(wrapper.vm.successFlag).toBe(false)
	})

	it('cloneTree returns an empty list for a non-array', async () => {
		const wrapper = await mountEditor()

		expect(wrapper.vm.cloneTree(null)).toEqual([])
		expect(wrapper.vm.cloneTree({ id: 'x' })).toEqual([])
	})

	it('generateUuid falls back to a v4 UUID without crypto.randomUUID', async () => {
		const wrapper = await mountEditor()
		const original = globalThis.crypto
		Object.defineProperty(globalThis, 'crypto', { value: {}, configurable: true })
		try {
			const id = wrapper.vm.generateUuid()
			expect(id).toMatch(
				/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
			)
		} finally {
			Object.defineProperty(globalThis, 'crypto', {
				value: original,
				configurable: true,
			})
		}
	})
})
