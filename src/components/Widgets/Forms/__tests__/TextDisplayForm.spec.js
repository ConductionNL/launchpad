/**
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `TextDisplayForm.vue` covering REQ-TXT-004 and
 * REQ-TXMD-001/004/005: validation returns `[t('Text is required')]` on
 * empty/whitespace text and an empty array otherwise; the form pre-fills
 * every one of its controls from `editingWidget.content`; the new Mode
 * toggle defaults to 'html' for existing widgets (backward compat) and
 * 'markdown' for newly-created widgets; switching modes preserves text;
 * `update:content` is emitted reactively whenever a field changes.
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TextDisplayForm from '../TextDisplayForm.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('TextDisplayForm', () => {
	it('REQ-TXT-004: validate() returns one error when text is empty', () => {
		const wrapper = mount(TextDisplayForm, {
			propsData: { value: { text: '' } },
			stubs: { NcTextField: true, NcSelect: true },
		})
		expect(wrapper.vm.validate()).toEqual(['Text is required'])
	})

	it('REQ-TXT-004: validate() returns one error when text is whitespace-only', () => {
		const wrapper = mount(TextDisplayForm, {
			propsData: { value: { text: '   \n ' } },
			stubs: { NcTextField: true, NcSelect: true },
		})
		expect(wrapper.vm.validate()).toEqual(['Text is required'])
	})

	it('REQ-TXT-004: validate() returns empty array when text is non-empty', () => {
		const wrapper = mount(TextDisplayForm, {
			propsData: { value: { text: 'hello' } },
			stubs: { NcTextField: true, NcSelect: true },
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('REQ-TXT-004: pre-fills all controls from editingWidget.content', () => {
		const editingWidget = {
			content: {
				text: 'Hi',
				fontSize: '20px',
				color: '#00ff00',
				backgroundColor: '#000000',
				textAlign: 'right',
				contentMode: 'markdown',
			},
		}
		const wrapper = mount(TextDisplayForm, {
			propsData: { editingWidget },
			stubs: { NcTextField: true, NcSelect: true },
		})
		expect(wrapper.vm.text).toBe('Hi')
		expect(wrapper.vm.fontSize).toBe('20px')
		expect(wrapper.vm.color).toBe('#00ff00')
		expect(wrapper.vm.backgroundColor).toBe('#000000')
		expect(wrapper.vm.textAlign).toBe('right')
		expect(wrapper.vm.contentMode).toBe('markdown')
	})

	it('REQ-TXT-004: emits update:content reactively when a field changes', () => {
		const wrapper = mount(TextDisplayForm, {
			propsData: { value: { text: '' } },
			stubs: { NcTextField: true, NcSelect: true },
		})
		wrapper.vm.updateField('text', 'Hello')
		const emitted = wrapper.emitted('update:content')
		expect(emitted).toBeTruthy()
		expect(emitted[emitted.length - 1][0]).toMatchObject({ text: 'Hello' })
		// Also confirm validate flips to valid
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('REQ-TXT-004: textarea reflects bound value and emits on input', async () => {
		const wrapper = mount(TextDisplayForm, {
			propsData: { value: { text: 'X' } },
			stubs: { NcTextField: true, NcSelect: true },
		})
		const textarea = wrapper.find('textarea')
		expect(textarea.exists()).toBe(true)
		expect(textarea.element.value).toBe('X')
		await textarea.setValue('Y')
		const emitted = wrapper.emitted('update:content')
		expect(emitted[emitted.length - 1][0]).toMatchObject({ text: 'Y' })
	})

	describe('REQ-TXMD-001/004/005: contentMode toggle', () => {
		it('defaults to "markdown" in create mode (no editingWidget)', () => {
			const wrapper = mount(TextDisplayForm, {
				propsData: { value: { text: 'X' } },
				stubs: { NcTextField: true, NcSelect: true },
			})
			expect(wrapper.vm.contentMode).toBe('markdown')
		})

		it('defaults to "html" when editing an existing widget without contentMode', () => {
			// Backward compatibility: pre-existing placements that have no
			// contentMode key MUST render as HTML — REQ-TXMD-006.
			const wrapper = mount(TextDisplayForm, {
				propsData: {
					editingWidget: { content: { text: 'legacy' } },
				},
				stubs: { NcTextField: true, NcSelect: true },
			})
			expect(wrapper.vm.contentMode).toBe('html')
		})

		it('honours an explicit contentMode on the editing widget', () => {
			const wrapper = mount(TextDisplayForm, {
				propsData: {
					editingWidget: {
						content: { text: 'X', contentMode: 'markdown' },
					},
				},
				stubs: { NcTextField: true, NcSelect: true },
			})
			expect(wrapper.vm.contentMode).toBe('markdown')
		})

		it('coerces an unknown contentMode value to the safe default', () => {
			const wrapper = mount(TextDisplayForm, {
				propsData: {
					editingWidget: {
						content: { text: 'X', contentMode: 'rst' },
					},
				},
				stubs: { NcTextField: true, NcSelect: true },
			})
			// Editing an existing widget with an invalid mode falls back
			// to the legacy 'html' behaviour rather than silently switching
			// to markdown — preserves user intent under uncertainty.
			expect(wrapper.vm.contentMode).toBe('html')
		})

		it('switching mode preserves text content', () => {
			const wrapper = mount(TextDisplayForm, {
				propsData: {
					editingWidget: {
						content: { text: '# Heading', contentMode: 'markdown' },
					},
				},
				stubs: { NcTextField: true, NcSelect: true },
			})
			expect(wrapper.vm.text).toBe('# Heading')
			wrapper.vm.updateField('contentMode', 'html')
			expect(wrapper.vm.text).toBe('# Heading')
			expect(wrapper.vm.contentMode).toBe('html')
			const emitted = wrapper.emitted('update:content')
			expect(emitted[emitted.length - 1][0]).toMatchObject({
				text: '# Heading',
				contentMode: 'html',
			})
		})

		it('updateField rejects invalid contentMode writes', () => {
			const wrapper = mount(TextDisplayForm, {
				propsData: { value: { text: 'X' } },
				stubs: { NcTextField: true, NcSelect: true },
			})
			expect(wrapper.vm.contentMode).toBe('markdown')
			wrapper.vm.updateField('contentMode', 'rst')
			expect(wrapper.vm.contentMode).toBe('markdown')
		})

		it('assembled content always includes contentMode in payload', () => {
			const wrapper = mount(TextDisplayForm, {
				propsData: { value: { text: 'X' } },
				stubs: { NcTextField: true, NcSelect: true },
			})
			wrapper.vm.updateField('text', 'Updated')
			const emitted = wrapper.emitted('update:content')
			const last = emitted[emitted.length - 1][0]
			expect(last).toHaveProperty('contentMode')
			expect(['html', 'markdown']).toContain(last.contentMode)
		})
	})
})
