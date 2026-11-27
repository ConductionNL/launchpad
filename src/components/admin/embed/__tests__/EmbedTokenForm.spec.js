/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed token form: subject picking (dashboard, or a widget of a
 * dashboard), host origins checked as the server checks them, the CSP
 * preview, required fields, and the edit form offering only what may change
 * (embedded-analytics task 18).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import EmbedTokenForm from '../EmbedTokenForm.vue'

const dashboardsApi = vi.hoisted(() => ({ getDashboardById: vi.fn() }))
vi.mock('../../../../services/api.js', () => ({ api: dashboardsApi }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`) }))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

function Field (name) {
  return {
	name,
	props: ['modelValue', 'label', 'error', 'helperText'],
	emits: ['update:modelValue', 'keydown'],
	template: `<label class="${name}" :data-label="label">{{ helperText }}<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"></label>`,
}
}
const Choice = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'value', 'type'],
	emits: ['update:modelValue'],
	template: '<label class="choice"><input :type="type" @change="toggle"><slot /></label>',
	methods: {
		toggle() {
			if (this.type === 'radio') {
				this.$emit('update:modelValue', this.value)
			} else {
				const list = [...this.modelValue]
				this.$emit('update:modelValue', list.includes(this.value) ? list.filter((item) => item !== this.value) : [...list, this.value])
			}
		},
	},
}
const Select = { name: 'NcSelect', props: ['modelValue', 'options', 'inputLabel', 'disabled', 'loading'], emits: ['update:modelValue'], template: '<div class="select" />' }
const Button = { name: 'NcButton', props: ['type'], emits: ['click'], template: '<button :type="type || \'button\'" @click="$emit(\'click\')"><slot /></button>' }

const DASHBOARDS = [{ id: 7, uuid: 'dash-uuid', name: 'Mijn week' }, { id: 8, uuid: 'other', name: 'Team' }]

function mountForm (props = {}) {
  return mount(EmbedTokenForm, {
	props: { dashboards: DASHBOARDS, themes: [{ id: 'th-1', name: 'Zeist' }], ...props },
	global: { stubs: { NcTextField: Field('text-field'), NcTextArea: Field('text-area'), NcCheckboxRadioSwitch: Choice, NcSelect: Select, NcButton: Button } },
})
}

const select = (wrapper, test) => wrapper.findAllComponents(Select).find((item) => item.attributes('data-test') === test)

/**
 * Type an origin and press Add.
 *
 * @param {object} wrapper The mounted form.
 * @param {string} origin The origin.
 */
async function addOrigin(wrapper, origin) {
	await wrapper.find('[data-test="embed-token-new-origin"] input').setValue(origin)
	await wrapper.find('[data-test="embed-token-add-origin"]').trigger('click')
}

describe('EmbedTokenForm', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('refuses wildcard and malformed origins and adds a valid one normalised, once', async () => {
		const wrapper = mountForm()

		await addOrigin(wrapper, 'https://*.zeist.nl')
		expect(wrapper.find('[data-test="embed-token-new-origin"]').text()).toBe('Wildcards are not allowed; list each site.')
		await addOrigin(wrapper, 'https://www.zeist.nl/woo')
		expect(wrapper.find('[data-test="embed-token-new-origin"]').text()).toContain('without a path')
		await addOrigin(wrapper, 'https://WWW.Zeist.nl/')
		await addOrigin(wrapper, 'https://www.zeist.nl')

		expect(wrapper.findAll('[data-test="embed-token-origins"] code').map((node) => node.text())).toEqual(['https://www.zeist.nl'])
		expect(wrapper.find('[data-test="embed-token-new-origin"]').text()).toBe('')

		await wrapper.find('[data-test="embed-token-csp-toggle"]').trigger('click')
		expect(wrapper.find('[data-test="embed-token-csp"]').text()).toBe('Content-Security-Policy: frame-ancestors https://www.zeist.nl')

		await wrapper.find('[data-test="embed-token-origins"] button').trigger('click')
		expect(wrapper.findAll('[data-test="embed-token-origins"] code')).toHaveLength(0)
	})

	it('names every missing required field and emits nothing', async () => {
		const wrapper = mountForm()
		await wrapper.find('form').trigger('submit')

		expect(wrapper.emitted('submit')).toBeUndefined()
		expect(wrapper.find('[data-test="embed-token-name"]').text()).toBe('Give the token a name.')
		expect(wrapper.find('[role="alert"]').text()).toBe('Choose what to embed.')
		expect(wrapper.find('[data-test="embed-token-new-origin"]').text()).toBe('Add at least one site that may embed it.')
	})

	it('emits the create payload of a dashboard token', async () => {
		const wrapper = mountForm()
		await wrapper.find('[data-test="embed-token-name"] input').setValue('Zeist WOO')
		select(wrapper, 'embed-subject-dashboard').vm.$emit('update:modelValue', DASHBOARDS[0])
		await wrapper.find('[data-test="embed-scope-mode-read-with-interactions"] input').trigger('change')
		await wrapper.find('[data-test="embed-scope-action-export"] input').trigger('change')
		await addOrigin(wrapper, 'https://a.nl')
		select(wrapper, 'embed-token-theme').vm.$emit('update:modelValue', { id: 'th-1', name: 'Zeist' })
		await wrapper.find('form').trigger('submit')

		expect(wrapper.emitted('submit')).toEqual([[{
			name: 'Zeist WOO',
			description: '',
			subject: { type: 'dashboard', id: 'dash-uuid' },
			scope: { mode: 'read-with-interactions', allowedFilters: [], allowedActions: ['export'] },
			hostOrigins: ['https://a.nl'],
			tenantThemeId: 'th-1',
			tokenExpiresAt: null,
			rateLimitPolicy: null,
		}]])
		expect(dashboardsApi.getDashboardById).not.toHaveBeenCalled()
	})

	it('offers the widgets of the chosen dashboard for a widget token', async () => {
		dashboardsApi.getDashboardById.mockResolvedValue({ data: { placements: [{ id: 41, widgetId: 'chart', customTitle: 'Aanvragen' }, { id: 42, widgetId: 'text' }] } })
		const wrapper = mountForm()
		await wrapper.find('[data-test="embed-subject-type-widget"] input').trigger('change')
		select(wrapper, 'embed-subject-dashboard').vm.$emit('update:modelValue', DASHBOARDS[1])
		await flushPromises()

		expect(dashboardsApi.getDashboardById).toHaveBeenCalledWith(8)
		const widgets = select(wrapper, 'embed-subject-widget')
		expect(widgets.props('options')).toEqual([{ id: 41, label: 'Aanvragen' }, { id: 42, label: 'text #42' }])
		widgets.vm.$emit('update:modelValue', { id: 42, label: 'text #42' })
		await wrapper.find('[data-test="embed-token-name"] input').setValue('Tekst')
		await addOrigin(wrapper, 'https://a.nl')
		await wrapper.find('form').trigger('submit')

		expect(wrapper.emitted('submit')[0][0].subject).toEqual({ type: 'widget', id: '42' })
		expect(wrapper.find('[data-test="embed-token-filter-context"]').exists()).toBe(false)
	})

	// @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	it('offers the widgets inside a well or tabs widget as <placement>.<key>', async () => {
		dashboardsApi.getDashboardById.mockResolvedValue({ data: { placements: [{
			id: 43,
			widgetId: 'container',
			customTitle: 'Werk',
			content: {
				placements: [{ type: 'tile', uuid: '43-0', tileTitle: 'Mail' }, { type: 'label' }],
				tabs: [{ title: 'Team', placements: [{ type: 'clock', uuid: 'c1' }] }],
			},
		}] } })
		const wrapper = mountForm()
		await wrapper.find('[data-test="embed-subject-type-widget"] input').trigger('change')
		select(wrapper, 'embed-subject-dashboard').vm.$emit('update:modelValue', DASHBOARDS[1])
		await flushPromises()

		expect(select(wrapper, 'embed-subject-widget').props('options')).toEqual([
			{ id: 43, label: 'Werk' },
			{ id: '43.43-0', label: 'Werk › Mail' },
			{ id: '43.c1', label: 'Werk › Team › clock' },
		])
	})

	it('edits only name, description and rate limit of a stored token', async () => {
		const token = { id: 't-1', name: 'Zeist', description: '', subject: { type: 'dashboard', id: 'dash-uuid' }, scope: { mode: 'read' }, hostOrigins: ['https://a.nl'], rateLimitPolicy: null }
		const wrapper = mountForm({ token })

		expect(wrapper.find('[data-test="embed-subject-dashboard"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="embed-token-origins"]').exists()).toBe(false)
		await wrapper.find('[data-test="embed-token-rpm"] input').setValue('120')
		await wrapper.find('form').trigger('submit')

		expect(wrapper.emitted('submit')).toEqual([[{ name: 'Zeist', description: '', rateLimitPolicy: { requestsPerMinute: 120, burstSize: 120 } }]])
		expect(wrapper.find('[data-test="embed-token-submit"]').text()).toBe('Save')
	})
})
