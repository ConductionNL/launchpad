/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AiAssistantWidgetForm.vue`: the optional Hermiq
 * agent field seeds from the widget being edited (falling back to the
 * `value` prop), emits a trimmed `{agentUuid}` on input, and validate()
 * never reports errors because nothing is required.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AiAssistantWidgetForm from '../AiAssistantWidgetForm.vue'

describe('AiAssistantWidgetForm — initial state', () => {
	it('prefers the edited widget content over the value prop', () => {
		const wrapper = mount(AiAssistantWidgetForm, {
			props: {
				editingWidget: { content: { agentUuid: 'agent-from-widget' } },
				value: { agentUuid: 'agent-from-value' },
			},
		})
		expect(wrapper.vm.agentUuid).toBe('agent-from-widget')
		expect(wrapper.find('[data-testid="ai-assistant-agent"]').element.value).toBe('agent-from-widget')
	})

	it('falls back to the value prop when no widget is being edited', () => {
		const wrapper = mount(AiAssistantWidgetForm, {
			props: { value: { agentUuid: 'agent-from-value' } },
		})
		expect(wrapper.vm.agentUuid).toBe('agent-from-value')
	})

	it('falls back to the value prop when the edited widget has no content', () => {
		const wrapper = mount(AiAssistantWidgetForm, {
			props: { editingWidget: { id: 4 }, value: { agentUuid: 'v' } },
		})
		expect(wrapper.vm.agentUuid).toBe('v')
	})

	it('starts empty when the content carries no agentUuid', () => {
		const wrapper = mount(AiAssistantWidgetForm, {
			props: { editingWidget: { content: { other: 1 } } },
		})
		expect(wrapper.vm.agentUuid).toBe('')
	})

	it('starts empty with the default value prop', () => {
		const wrapper = mount(AiAssistantWidgetForm)
		expect(wrapper.vm.agentUuid).toBe('')
	})
})

describe('AiAssistantWidgetForm — emitted content', () => {
	it('emits the trimmed agent uuid when the field is typed in', async () => {
		const wrapper = mount(AiAssistantWidgetForm)
		await wrapper.find('[data-testid="ai-assistant-agent"]').setValue('  abc-123  ')
		const emitted = wrapper.emitted('update:content')
		expect(emitted).toBeTruthy()
		expect(emitted[emitted.length - 1][0]).toEqual({ agentUuid: 'abc-123' })
	})

	it('emits an empty uuid when the field is cleared to whitespace', async () => {
		const wrapper = mount(AiAssistantWidgetForm, {
			props: { value: { agentUuid: 'abc' } },
		})
		await wrapper.find('[data-testid="ai-assistant-agent"]').setValue('   ')
		const emitted = wrapper.emitted('update:content')
		expect(emitted[emitted.length - 1][0]).toEqual({ agentUuid: '' })
	})

	it('does not emit before any input', () => {
		const wrapper = mount(AiAssistantWidgetForm)
		expect(wrapper.emitted('update:content')).toBeUndefined()
	})
})

describe('AiAssistantWidgetForm — validate()', () => {
	it('reports no errors, whether or not an agent is set', () => {
		expect(mount(AiAssistantWidgetForm).vm.validate()).toEqual([])
		expect(mount(AiAssistantWidgetForm, {
			props: { value: { agentUuid: 'x' } },
		}).vm.validate()).toEqual([])
	})
})
