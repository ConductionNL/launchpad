/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `SetupWizardModal.vue`. Covers REQ-WIZ-002
 * (six-step shell; the storage step was retired by decision 131),
 * REQ-WIZ-008 (state load on mount), and REQ-WIZ-009 (Finish triggers
 * `completeSetupWizard`).
 *
 * The `api` module is mocked at the import boundary so no HTTP traffic
 * is generated. The embedded `GroupPriorityOrder` is stubbed to avoid
 * pulling its mount-time API call into the wizard test scope.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SetupWizardModal from '../SetupWizardModal.vue'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getSetupWizardState: vi.fn(),
		completeSetupWizard: vi.fn(),
	},
}))

// `emits: ['click']` is required under Vue 3: listeners arrive in `$attrs`
// as `onClick` and are auto-applied to the root `<button>`, so without the
// declaration the parent's `@click` handler runs twice per click — once
// natively and once via `$emit`. Declaring the event removes `onClick`
// from `$attrs`, leaving `$emit` as the only path.
const ncButtonStub = {
	name: 'NcButton',
	props: ['type', 'disabled'],
	emits: ['click'],
	template:
		'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}
const ncModalStub = {
	name: 'NcModal',
	template: '<div class="nc-modal-stub"><slot /></div>',
}
const groupPriorityStub = {
	name: 'GroupPriorityOrder',
	template: '<div class="group-priority-stub" />',
}

beforeEach(() => {
	api.getSetupWizardState.mockReset().mockResolvedValue({
		data: {
			complete: false,
			currentRecommendedStep: 1,
			stepStatuses: { 1: 'done', 2: 'pending' },
		},
	})
	api.completeSetupWizard
		.mockReset()
		.mockResolvedValue({ data: { complete: true } })
})

function mountWizard() {
	return mount(SetupWizardModal, {
		stubs: {
			NcButton: ncButtonStub,
			NcModal: ncModalStub,
			GroupPriorityOrder: groupPriorityStub,
		},
	})
}

async function flush() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('SetupWizardModal', () => {
	it('REQ-WIZ-008: loads wizard state on mount', async () => {
		mountWizard()
		await flush()

		expect(api.getSetupWizardState).toHaveBeenCalledOnce()
	})

	it('REQ-WIZ-002: starts at step 1 with the counter rendered', async () => {
		const wrapper = mountWizard()
		await flush()

		expect(wrapper.vm.currentStep).toBe(1)
		// The Vue mixin's t() stub returns the bare key; we just assert the
		// counter element is present and tied to the wizard's step state.
		expect(wrapper.find('[data-test="setup-wizard-counter"]').exists()).toBe(
			true,
		)
		expect(wrapper.vm.totalSteps).toBe(6)
	})

	it('REQ-WIZ-002: Next advances; Back returns; counter updates', async () => {
		const wrapper = mountWizard()
		await flush()

		await wrapper.find('[data-test="setup-wizard-next"]').trigger('click')
		await flush()
		expect(wrapper.vm.currentStep).toBe(2)

		await wrapper.find('[data-test="setup-wizard-back"]').trigger('click')
		expect(wrapper.vm.currentStep).toBe(1)
	})

	it('decision 131: Step 2 is the group order and no storage step renders', async () => {
		const wrapper = mountWizard()
		await flush()

		await wrapper.find('[data-test="setup-wizard-next"]').trigger('click')
		await flush()
		expect(wrapper.vm.currentStep).toBe(2)
		expect(wrapper.find('.group-priority-stub').exists()).toBe(true)
		expect(wrapper.find('[data-test="storage-database"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="storage-groupfolder"]').exists()).toBe(
			false,
		)
	})

	it('REQ-WIZ-002: Step 6 Next is labelled Finish and calls completeSetupWizard', async () => {
		const wrapper = mountWizard()
		await flush()

		// Jump straight to step 6 to keep the test focused.
		wrapper.vm.currentStep = 6
		await flush()

		// The Vue mixin's t() stub returns the bare key; the component
		// branches on `isFinalStep` to swap "Next" → "Finish".
		expect(wrapper.vm.isFinalStep).toBe(true)
		expect(wrapper.find('[data-test="setup-wizard-skip"]').exists()).toBe(false)

		await wrapper.find('[data-test="setup-wizard-next"]').trigger('click')
		await flush()
		expect(api.completeSetupWizard).toHaveBeenCalledOnce()
		expect(wrapper.emitted('completed')).toBeTruthy()
		expect(wrapper.emitted('close')).toBeTruthy()
	})

	it('REQ-WIZ-002: Skip advances without committing on optional steps', async () => {
		const wrapper = mountWizard()
		await flush()
		wrapper.vm.currentStep = 4
		await flush()

		await wrapper.find('[data-test="setup-wizard-skip"]').trigger('click')
		expect(wrapper.vm.currentStep).toBe(5)
		expect(api.completeSetupWizard).not.toHaveBeenCalled()
	})
})
