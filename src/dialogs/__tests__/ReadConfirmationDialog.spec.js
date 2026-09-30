/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ReadConfirmationDialog (REQ-ACK-007): ticking "Ask readers to confirm"
 * emits the payload PlacementUpdater::applyAcknowledgementUpdates takes
 * (see tests/Unit/Service/PlacementUpdaterReadConfirmationTest.php).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ReadConfirmationDialog from '../ReadConfirmationDialog.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['open'],
		template: '<div v-if="open"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template:
			'<input type="checkbox" class="ask" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)">',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input class="prompt" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
}))

describe('ReadConfirmationDialog', () => {
	it('REQ-ACK-007: asks for confirmation with a prompt and a deadline', async () => {
		const wrapper = mount(ReadConfirmationDialog, {
			props: { open: true, placement: { id: 5, requiresAcknowledgement: 0 } },
		})
		await wrapper.find('input.ask').setValue(true)
		await wrapper
			.find('input.prompt')
			.setValue('I have read the new expense rules')
		await wrapper.find('input[type="date"]').setValue('2026-10-15')
		await wrapper.find('[data-testid="read-confirmation-save"]').trigger('click')
		expect(wrapper.emitted('save')[0][0]).toEqual({
			requiresAcknowledgement: 1,
			acknowledgementPrompt: 'I have read the new expense rules',
			acknowledgementDeadline: '2026-10-15',
		})
	})

	it('REQ-ACK-007: unticking stops asking', async () => {
		const wrapper = mount(ReadConfirmationDialog, {
			props: {
				open: true,
				placement: {
					id: 5,
					requiresAcknowledgement: 1,
					acknowledgementPrompt: 'Read it',
					acknowledgementDeadline: null,
				},
			},
		})
		expect(wrapper.find('input.ask').element.checked).toBe(true)
		await wrapper.find('input.ask').setValue(false)
		await wrapper.find('[data-testid="read-confirmation-save"]').trigger('click')
		expect(wrapper.emitted('save')[0][0]).toEqual({
			requiresAcknowledgement: 0,
			acknowledgementPrompt: 'Read it',
			acknowledgementDeadline: null,
		})
	})
})
