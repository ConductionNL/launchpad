/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the footers of ScheduleDashboardDialog and
 * ReadConfirmationDialog: per filled-in state, the footer buttons (label,
 * disabled) and the events that Cancel and the primary button emit, compared
 * with fixtures/dialogActions.golden.json case by case. The CnDialogActions
 * double follows nc-vue's component: a cancel button and a primary button
 * disabled while saving or when canSubmit is false. Set RECORD_GOLDEN=1 to
 * rewrite it.
 */

import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import ReadConfirmationDialog from '../ReadConfirmationDialog.vue'
import ScheduleDashboardDialog from '../ScheduleDashboardDialog.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['open', 'name'],
		emits: ['update:open'],
		template: '<div v-if="open"><slot /><footer><slot name="actions" /></footer></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['disabled', 'variant'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	CnDialogActions: {
		name: 'CnDialogActions',
		props: {
			saving: { type: Boolean, default: false },
			canSubmit: { type: Boolean, default: true },
			confirmLabel: { type: String, default: 'Save' },
			cancelLabel: { type: String, default: 'Cancel' },
			variant: { type: String, default: 'primary' },
			confirmIcon: { type: String, default: '' },
		},
		emits: ['cancel', 'submit'],
		template:
			'<button :disabled="saving" @click="$emit(\'cancel\')">{{ cancelLabel }}</button><button :disabled="saving || !canSubmit" @click="$emit(\'submit\')">{{ confirmLabel }}</button>',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<label><slot /></label>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<input />',
	},
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/dialogActions.golden.json')

const SCHEDULES = {
	empty: {},
	'go live only': { goLive: '2026-10-01T09:00' },
	'take down only': { takeDown: '2026-11-01T18:00' },
	both: { goLive: '2026-10-01T09:00', takeDown: '2026-11-01T18:00' },
	'with error': { goLive: '2026-10-01T09:00', error: 'Refused' },
}

const CONFIRMATIONS = {
	'no placement': { placement: null },
	'asks with prompt and deadline': {
		placement: {
			requiresAcknowledgement: '1',
			acknowledgementPrompt: 'Read it',
			acknowledgementDeadline: '2026-12-01',
		},
	},
	'asks, blank prompt': {
		placement: { requiresAcknowledgement: 1, acknowledgementPrompt: '   ' },
	},
	'switched on in the dialog': { placement: { requiresAcknowledgement: 0 }, ask: true, prompt: ' Got it ' },
}

/**
 * An emitted value with every ISO timestamp written back as the viewer's
 * local `YYYY-MM-DDTHH:mm`, so the recording does not depend on the time zone.
 *
 * @param {unknown} value The emitted events.
 * @return {unknown} The same events, time zone free.
 */
function local(value) {
	return JSON.parse(
		JSON.stringify(value, (_key, entry) => {
			if (typeof entry !== 'string' || !/^\d{4}-\d\d-\d\dT.*Z$/.test(entry)) {
				return entry
			}
			const date = new Date(entry)
			const pad = (n) => String(n).padStart(2, '0')
			return `local ${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
		}),
	)
}

/**
 * The footer buttons and what clicking each emits, each on a fresh mount.
 *
 * @param {Function} make Mounts the dialog in its state.
 * @return {Promise<object>} The outcome.
 */
async function footer(make) {
	const first = await make()
	const buttons = first.findAll('footer button').map((b) => ({
		text: b.text(),
		disabled: b.attributes('disabled') !== undefined,
	}))
	const clicks = {}
	for (let index = 0; index < buttons.length; index++) {
		const wrapper = await make()
		await wrapper.findAll('footer button').at(index).trigger('click')
		clicks[buttons[index].text] = local(wrapper.emitted())
		delete clicks[buttons[index].text].click
	}
	return { buttons, clicks }
}

describe('dialog footers (golden)', () => {
	it('matches the recorded outcomes case by case', async () => {
		const actual = {}
		for (const [name, state] of Object.entries(SCHEDULES)) {
			actual[`schedule | ${name}`] = await footer(async () => {
				const wrapper = mount(ScheduleDashboardDialog, {
					props: { open: true, error: state.error ?? '' },
				})
				await wrapper.setData({
					goLive: state.goLive ?? '',
					takeDown: state.takeDown ?? '',
				})
				return wrapper
			})
		}
		for (const [name, state] of Object.entries(CONFIRMATIONS)) {
			actual[`read confirmation | ${name}`] = await footer(async () => {
				const wrapper = mount(ReadConfirmationDialog, {
					props: { open: true, placement: state.placement },
				})
				if ('ask' in state) {
					await wrapper.setData({ ask: state.ask, prompt: state.prompt })
				}
				return wrapper
			})
		}
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
		for (const [name, value] of Object.entries(expected)) {
			expect(actual[name], name).toEqual(value)
		}
	})
})
