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
export const CnWidgetStyleEditorModal = stub('CnWidgetStyleEditorModal')
export const CnDashboardGrid = stub('CnDashboardGrid')
export const CnIconPicker = stub('CnIconPicker')
export const CnDashboardIcon = stub('CnDashboardIcon')
export const getDashboardColumnOpts = () => ({ breakpoints: [], layout: 'moveScale', breakpointForWindow: true })
export const placeNewWidget = () => ({ x: 0, y: 0, w: 4, h: 4, pushed: [] })
export const DASHBOARD_ICONS = { ViewDashboard: {}, Star: {} }
export const DEFAULT_ICON = 'ViewDashboard'
export const getIconComponent = () => ({})
export const isCustomIconUrl = (n) => typeof n === 'string' && (n.startsWith('/') || n.startsWith('http'))

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
// Analytics widgets (OpenBuild parity) — renderers + config forms.
export const CnStatWidget = stub('CnStatWidget')
export const CnStatWidgetForm = stub('CnStatWidgetForm')
export const CnDeltaWidget = stub('CnDeltaWidget')
export const CnDeltaWidgetForm = stub('CnDeltaWidgetForm')
export const CnGaugeWidget = stub('CnGaugeWidget')
export const CnGaugeWidgetForm = stub('CnGaugeWidgetForm')
export const CnObjectListWidget = stub('CnObjectListWidget')
export const CnObjectListWidgetForm = stub('CnObjectListWidgetForm')
export const CnChartWidget = stub('CnChartWidget')
export const CnChartWidgetForm = stub('CnChartWidgetForm')
export const CnStatsBlockWidget = stub('CnStatsBlockWidget')
export const CnStatsBlockWidgetForm = stub('CnStatsBlockWidgetForm')

// Communal dashboard widget catalog stub — mirrors nc-vue's
// `dashboardWidgetRegistry` so launchpad's widgetRegistry (which now CONSUMES
// it) resolves types. The four `form: null` types (calendar/people/
// spend-analytics/nc-widget) match the real catalog; launchpad re-adds their
// forms via its FORM_OVERRIDES overlay.
// Each entry uses the REAL component stub as renderer/form so registry-dispatch
// tests (e.g. ContainerWidget rendering `.cn-label-widget`) and form-driven
// tests work. `form: null` for the four communal form-less types (launchpad
// re-adds those forms via FORM_OVERRIDES). defaultContent mirrors the real
// registrations for the types unit tests assert (label fully; others minimal).
const _DASH = {
	label: { renderer: CnLabelWidget, form: CnLabelWidgetForm, defaultContent: { text: '', fontSize: '16px', color: '', backgroundColor: '', fontWeight: 'bold', textAlign: 'center' } },
	text: { renderer: CnTextWidget, form: CnTextWidgetForm, defaultContent: { text: '', fontSize: '14px', color: '', backgroundColor: '', textAlign: 'left', contentMode: 'markdown', tableMode: false, tableData: null } },
	image: { renderer: CnImageWidget, form: CnImageWidgetForm, defaultContent: { url: '', alt: '', link: '', fit: 'cover' } },
	link: { renderer: CnLinkButtonWidget, form: CnLinkButtonWidgetForm, defaultContent: { label: '', url: '', icon: '', actionType: 'external', backgroundColor: '', textColor: '', displayMode: 'button', listOrientation: 'vertical', listItemGap: 'normal', links: [] } },
	divider: { renderer: CnDividerWidget, form: CnDividerWidgetForm, defaultContent: { style: 'line', lineColor: '', lineThickness: 1, lineStyle: 'solid', whitespaceSize: 'medium', headingText: '' } },
	header: { renderer: CnHeaderWidget, form: CnHeaderWidgetForm, defaultContent: {} },
	quicklinks: { renderer: CnQuicklinksWidget, form: CnQuicklinksWidgetForm, defaultContent: {} },
	video: { renderer: CnVideoWidget, form: CnVideoWidgetForm, defaultContent: {} },
	news: { renderer: CnNewsWidget, form: CnNewsWidgetForm, defaultContent: {} },
	tile: { renderer: CnDashTileWidget, form: CnDashTileWidgetForm, defaultContent: {} },
	links: { renderer: CnLinksWidget, form: CnLinksWidgetForm, defaultContent: {} },
	menu: { renderer: CnMenuWidget, form: CnMenuWidgetForm, defaultContent: {} },
	container: { renderer: stub('container-renderer'), form: CnContainerWidgetForm, defaultContent: {} },
	files: { renderer: CnFilesWidget, form: CnFilesWidgetForm, defaultContent: { folderPath: '/' } },
	stat: { renderer: CnStatWidget, form: CnStatWidgetForm, defaultContent: {} },
	delta: { renderer: CnDeltaWidget, form: CnDeltaWidgetForm, defaultContent: {} },
	gauge: { renderer: CnGaugeWidget, form: CnGaugeWidgetForm, defaultContent: {} },
	'object-list': { renderer: CnObjectListWidget, form: CnObjectListWidgetForm, defaultContent: {} },
	chart: { renderer: CnChartWidget, form: CnChartWidgetForm, defaultContent: {} },
	'stats-block': { renderer: CnStatsBlockWidget, form: CnStatsBlockWidgetForm, defaultContent: {} },
	table: { renderer: CnObjectListWidget, form: CnObjectListWidgetForm, defaultContent: {} },
	calendar: { renderer: CnCalendarWidget, form: null, defaultContent: {} },
	people: { renderer: CnPeopleWidget, form: null, defaultContent: {} },
	'spend-analytics': { renderer: CnSpendAnalyticsWidget, form: null, defaultContent: {} },
	'nc-widget': { renderer: CnNcWidgetWidget, form: null, defaultContent: {} },
}
export const dashboardWidgetRegistry = Object.fromEntries(Object.entries(_DASH).map(([type, e]) => [type, {
	renderer: e.renderer,
	form: e.form,
	defaultContent: e.defaultContent,
	displayName: type,
	icon: 'ViewDashboard',
}]))
export const registerDashboardWidget = (type, entry) => { dashboardWidgetRegistry[type] = entry }
export const listWidgetTypes = () => Object.keys(dashboardWidgetRegistry).filter((t) => dashboardWidgetRegistry[t].form)
export const getWidgetTypeEntry = (type) => dashboardWidgetRegistry[type] || null
export const getDefaultContent = (type) => ({ ...((dashboardWidgetRegistry[type] && dashboardWidgetRegistry[type].defaultContent) || {}) })

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
	CnWidgetStyleEditorModal,
	CnDashboardGrid,
	CnIconPicker,
	CnDashboardIcon,
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
	CnStatWidget,
	CnStatWidgetForm,
	CnDeltaWidget,
	CnDeltaWidgetForm,
	CnGaugeWidget,
	CnGaugeWidgetForm,
	CnObjectListWidget,
	CnObjectListWidgetForm,
	CnChartWidget,
	CnChartWidgetForm,
	CnStatsBlockWidget,
	CnStatsBlockWidgetForm,
	dashboardWidgetRegistry,
	registerDashboardWidget,
	listWidgetTypes,
	getWidgetTypeEntry,
	getDefaultContent,
}
