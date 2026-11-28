/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed part of the Sharing tab (REQ-EMB-011): the dashboard's tokens
 * only, a new token shown once with the code for the website, and Withdraw
 * through the revocation (REQ-EMB-009).
 *
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SharingEmbedTokens from '../SharingEmbedTokens.vue'

const embed = vi.hoisted(() => ({ listTokens: vi.fn(), createToken: vi.fn(), revokeToken: vi.fn() }))
const api = vi.hoisted(() => ({ getDashboardById: vi.fn() }))
vi.mock('../../../../services/embedApi.js', async (importOriginal) => ({ ...(await importOriginal()), embedApi: embed }))
vi.mock('../../../../services/api.js', () => ({ api }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const Button = { name: 'NcButton', emits: ['click'], template: '<button @click="$emit(\'click\', $event)"><slot /></button>' }
const FormStub = { name: 'EmbedTokenForm', props: ['dashboards', 'themes', 'defaults', 'saving'], emits: ['submit', 'cancel'], template: '<div class="form-stub" />' }
const DialogStub = { name: 'RevokeEmbedTokenDialog', props: ['open', 'name'], emits: ['update:open', 'confirm'], template: '<div class="revoke-stub" />' }

const DASHBOARD = { id: 3, uuid: 'dash-3', name: 'Cases' }
const WHOLE = { id: 't-1', name: 'Intranet', subject: { type: 'dashboard', id: 'dash-3' }, hostOrigins: ['https://intra.nl'] }
const WIDGET = { id: 't-2', name: 'Website Zuiddrecht', subject: { type: 'widget', id: '11' }, hostOrigins: ['https://www.zuiddrecht.nl'] }
const OTHER = { id: 't-3', name: 'Elsewhere', subject: { type: 'dashboard', id: 'dash-9' }, hostOrigins: [] }

function mountIt() {
	return mount(SharingEmbedTokens, {
		props: { dashboard: DASHBOARD },
		global: {
			stubs: {
				NcButton: Button,
				NcNoteCard: { props: ['type'], template: '<div :class="\'note-\' + type"><slot /></div>' },
				NcLoadingIcon: true,
				EmbedTokenForm: FormStub,
				RevokeEmbedTokenDialog: DialogStub,
			},
		},
	})
}

describe('SharingEmbedTokens', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		embed.listTokens.mockResolvedValue([WHOLE, WIDGET, OTHER])
		api.getDashboardById.mockResolvedValue({ data: { placements: [{ id: 11, customTitle: 'Open cases per case type' }] } })
	})

	it('lists only the tokens of this dashboard and its widgets, with what they show', async () => {
		const wrapper = mountIt()
		await flushPromises()
		expect(api.getDashboardById).toHaveBeenCalledWith(3)
		expect(wrapper.find('[data-test="sharing-embed-row-t-1"]').text()).toContain('The whole dashboard')
		expect(wrapper.find('[data-test="sharing-embed-row-t-2"]').text()).toContain('Widget Open cases per case type')
		expect(wrapper.find('[data-test="sharing-embed-row-t-2"]').text()).toContain('https://www.zuiddrecht.nl')
		expect(wrapper.find('[data-test="sharing-embed-row-t-3"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="sharing-embed-state-t-1"]').text()).toBe('Active')
	})

	it('starts a new token on this dashboard', async () => {
		const wrapper = mountIt()
		await flushPromises()
		await wrapper.find('[data-test="sharing-embed-new"]').trigger('click')
		const form = wrapper.findComponent(FormStub)
		expect(form.props('dashboards')).toEqual([{ id: 3, uuid: 'dash-3', name: 'Cases' }])
		expect(form.props('defaults')).toEqual({ subjectType: 'dashboard', subjectId: 'dash-3', dashboardId: 3 })
	})

	it('shows a new token once, lists it as Active and gives the iframe code with it', async () => {
		const created = { ...WIDGET, id: 't-4' }
		embed.createToken.mockResolvedValue({ id: 't-4', name: 'Website Zuiddrecht', jwt: 'header.body.sig' })
		const wrapper = mountIt()
		await flushPromises()
		embed.listTokens.mockResolvedValue([WHOLE, WIDGET, created])
		await wrapper.find('[data-test="sharing-embed-new"]').trigger('click')
		wrapper.findComponent(FormStub).vm.$emit('submit', { name: 'Website Zuiddrecht' })
		await flushPromises()

		expect(wrapper.find('[data-test="sharing-embed-issued"]').text()).toContain('Token Website Zuiddrecht has been created. Copy it now: it is not shown again.')
		expect(wrapper.find('[data-test="sharing-embed-jwt"]').text()).toBe('header.body.sig')
		expect(wrapper.find('[data-test="sharing-embed-state-t-4"]').text()).toBe('Active')
		const code = wrapper.find('[data-test="sharing-embed-code"] textarea').element.value
		expect(code).toContain('<iframe src="')
		expect(code).toContain('/index.php/apps/launchpad/embed/widget/11?token=header.body.sig')

		await wrapper.find('[data-test="sharing-embed-issued-done"]').trigger('click')
		expect(wrapper.find('[data-test="sharing-embed-jwt"]').exists()).toBe(false)
		expect(wrapper.find('[data-test="sharing-embed-code"] textarea').element.value).toContain('token=YOUR_TOKEN')
	})

	it('withdraws a token through the revocation and shows it as revoked', async () => {
		embed.revokeToken.mockResolvedValue({ ...WHOLE, revokedAt: '2026-11-24T10:00:00Z', revocationReason: 'no_longer_needed' })
		const wrapper = mountIt()
		await flushPromises()
		await wrapper.find('[data-test="sharing-embed-withdraw-t-1"]').trigger('click')
		wrapper.findComponent(DialogStub).vm.$emit('confirm', { reason: 'no_longer_needed', notes: '' })
		await flushPromises()

		expect(embed.revokeToken).toHaveBeenCalledWith('t-1', 'no_longer_needed', '')
		expect(wrapper.find('[data-test="sharing-embed-state-t-1"]').text()).toBe('Revoked on 2026-11-24 (no_longer_needed)')
		expect(wrapper.find('[data-test="sharing-embed-withdraw-t-1"]').exists()).toBe(false)
	})

	it('says when the tokens cannot be loaded', async () => {
		embed.listTokens.mockRejectedValue(new Error('403'))
		const wrapper = mountIt()
		await flushPromises()
		expect(wrapper.find('[data-test="sharing-embed-error"]').text()).toBe('Embed tokens could not be loaded.')
	})
})
