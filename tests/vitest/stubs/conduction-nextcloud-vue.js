/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest stub for `@conduction/nextcloud-vue`.
 *
 * The published package ships a CJS bundle that does `require('foo.vue')`
 * which Vite cannot transform under the unit-test pipeline (vue-loader is
 * a webpack plugin; the @vitejs/plugin-vue2 transform is gated on the
 * Vite resolver, not Node's `require`). Tests that mount these components
 * do not exercise their rendered markup — they only need the symbol to be
 * a valid Vue component object — so we substitute lightweight stubs at
 * the alias layer.
 *
 * Real visual coverage of `@conduction/nextcloud-vue` lives in the
 * upstream package's own test suite.
 */

const stub = (name) => ({ name, render: (h) => h('div') })

export const NcModal = stub('NcModal')
export const NcButton = stub('NcButton')
export const NcTextField = stub('NcTextField')
export const NcSelect = stub('NcSelect')
export const NcEmptyContent = stub('NcEmptyContent')
export const NcAppNavigation = stub('NcAppNavigation')
export const NcAppContent = stub('NcAppContent')
export const NcContent = stub('NcContent')
export const NcDashboardWidget = stub('NcDashboardWidget')
export const NcCheckboxRadioSwitch = stub('NcCheckboxRadioSwitch')
export const NcLoadingIcon = stub('NcLoadingIcon')
export const CnWidgetWrapper = stub('CnWidgetWrapper')
export const CnWidgetEditCog = stub('CnWidgetEditCog')
export const CnDashboardGrid = stub('CnDashboardGrid')
export const getDashboardColumnOpts = () => ({ breakpoints: [], layout: 'moveScale', breakpointForWindow: true })
export const placeNewWidget = () => ({ x: 0, y: 0, w: 4, h: 4, pushed: [] })

// Dashboard widget library (v2) — renderers + config forms now sourced from
// @conduction/nextcloud-vue and aliased in widgetRegistry.js. Stubbed here so
// the registry's `renderer`/`form` entries are valid component objects.
// Renders an identifiable element so registry-dispatch tests (e.g.
// ContainerWidget recursive dispatch) can assert the child rendered.
export const CnLabelWidget = { name: 'CnLabelWidget', render: (h) => h('div', { class: 'cn-label-widget' }) }
export const CnTextWidget = stub('CnTextWidget')
export const CnImageWidget = stub('CnImageWidget')
export const CnLinkButtonWidget = stub('CnLinkButtonWidget')
export const CnFilesWidget = stub('CnFilesWidget')
export const CnNcWidgetWidget = stub('CnNcWidgetWidget')
export const CnHeaderWidget = stub('CnHeaderWidget')
export const CnDividerWidget = stub('CnDividerWidget')
export const CnVideoWidget = stub('CnVideoWidget')
export const CnQuicklinksWidget = stub('CnQuicklinksWidget')
export const CnLinksWidget = stub('CnLinksWidget')
export const CnMenuWidget = stub('CnMenuWidget')
export const CnPeopleWidget = stub('CnPeopleWidget')
export const CnNewsWidget = stub('CnNewsWidget')
export const CnSpendAnalyticsWidget = stub('CnSpendAnalyticsWidget')
export const CnCalendarWidget = stub('CnCalendarWidget')
// Functional contract stub (data + updateField + assembledContent + validate +
// emits update:content) so CnAddWidgetModal logic tests can drive a sub-form.
export const CnLabelWidgetForm = {
	name: 'CnLabelWidgetForm',
	props: {
		editingWidget: { type: Object, default: null },
		value: { type: Object, default: () => ({}) },
	},
	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			text: initial.text ?? '',
			fontSize: initial.fontSize ?? '16px',
			color: initial.color ?? '',
			backgroundColor: initial.backgroundColor ?? '',
			fontWeight: initial.fontWeight ?? 'bold',
			textAlign: initial.textAlign ?? 'center',
		}
	},
	computed: {
		assembledContent() {
			return {
				text: this.text,
				fontSize: this.fontSize,
				color: this.color,
				backgroundColor: this.backgroundColor,
				fontWeight: this.fontWeight,
				textAlign: this.textAlign,
			}
		},
	},
	methods: {
		updateField(field, val) {
			this[field] = val
			this.$emit('update:content', this.assembledContent)
		},
		validate() {
			return (typeof this.text === 'string' && this.text.trim() !== '')
				? []
				: ['Label text is required']
		},
	},
	render(h) {
		return h('div', { class: 'cn-label-widget-form' })
	},
}
export const CnTextWidgetForm = stub('CnTextWidgetForm')
export const CnImageWidgetForm = stub('CnImageWidgetForm')
export const CnLinkButtonWidgetForm = stub('CnLinkButtonWidgetForm')
export const CnNcDashboardWidgetForm = stub('CnNcDashboardWidgetForm')
export const CnHeaderWidgetForm = stub('CnHeaderWidgetForm')
export const CnDividerWidgetForm = stub('CnDividerWidgetForm')
export const CnFilesWidgetForm = stub('CnFilesWidgetForm')
export const CnPeopleWidgetForm = stub('CnPeopleWidgetForm')
export const CnQuicklinksWidgetForm = stub('CnQuicklinksWidgetForm')
export const CnNewsWidgetForm = stub('CnNewsWidgetForm')
export const CnVideoWidgetForm = stub('CnVideoWidgetForm')
export const CnCalendarWidgetForm = stub('CnCalendarWidgetForm')
export const CnLinksWidgetForm = stub('CnLinksWidgetForm')
export const CnMenuWidgetForm = stub('CnMenuWidgetForm')
export const CnContainerWidgetForm = stub('CnContainerWidgetForm')
export const CnDashTileWidget = stub('CnDashTileWidget')
export const CnDashTileWidgetForm = stub('CnDashTileWidgetForm')
export const CnSpendAnalyticsWidgetForm = stub('CnSpendAnalyticsWidgetForm')

export default {
	NcModal,
	NcButton,
	NcTextField,
	NcSelect,
	NcEmptyContent,
	NcAppNavigation,
	NcAppContent,
	NcContent,
	NcDashboardWidget,
	NcCheckboxRadioSwitch,
	NcLoadingIcon,
	CnWidgetWrapper,
	CnWidgetEditCog,
	CnDashboardGrid,
	CnLabelWidget,
	CnTextWidget,
	CnImageWidget,
	CnHeaderWidget,
	CnDividerWidget,
	CnVideoWidget,
	CnQuicklinksWidget,
	CnLinksWidget,
	CnMenuWidget,
	CnLinkButtonWidget,
	CnFilesWidget,
	CnNcWidgetWidget,
	CnPeopleWidget,
	CnNewsWidget,
	CnSpendAnalyticsWidget,
	CnCalendarWidget,
	CnLabelWidgetForm,
	CnTextWidgetForm,
	CnImageWidgetForm,
	CnLinkButtonWidgetForm,
	CnNcDashboardWidgetForm,
	CnHeaderWidgetForm,
	CnDividerWidgetForm,
	CnFilesWidgetForm,
	CnPeopleWidgetForm,
	CnQuicklinksWidgetForm,
	CnNewsWidgetForm,
	CnVideoWidgetForm,
	CnCalendarWidgetForm,
	CnLinksWidgetForm,
	CnMenuWidgetForm,
	CnContainerWidgetForm,
	CnDashTileWidget,
	CnDashTileWidgetForm,
	CnSpendAnalyticsWidgetForm,
}
