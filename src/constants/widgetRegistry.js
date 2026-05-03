/**
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Widget registry — single source of truth for "what custom widget types
 * exist" on top of the Nextcloud-discovered widget set.
 *
 * Each entry maps a `type` string (the value persisted in
 * `oc_mydash_widget_placements`) to a `{renderer, form, defaultContent,
 * displayName, icon}` descriptor. The Add Widget modal consults this registry
 * to render the type picker and the per-type sub-form, and the dashboard
 * grid uses it to pick the right renderer for a placement.
 *
 * Adding a new widget type means adding an entry here plus the matching
 * Renderer + Form Vue components — no other wiring is required. The registry
 * tolerates entries with `form: null` (renderer only) — `listWidgetTypes()`
 * filters those out so the AddWidgetModal type picker only shows types the
 * user can actually configure. Per-widget proposals that haven't yet shipped
 * their sub-form should not appear in the picker.
 *
 * REQ-LBL-007: The widget type `label` MUST be registered with a renderer
 * reference to `LabelWidget.vue`, a form reference to `LabelForm.vue`, and a
 * `defaultContent` of `{text:'', fontSize:'16px', color:'',
 * backgroundColor:'', fontWeight:'bold', textAlign:'center'}`.
 *
 * REQ-TXT-005 / REQ-TXT-001..004: The widget type `text` MUST be registered
 * with a renderer reference to `TextDisplayWidget.vue`, a form reference to
 * `TextDisplayForm.vue`, and a `defaultContent` of `{text:'',
 * fontSize:'14px', color:'', backgroundColor:'', textAlign:'left'}`.
 *
 * REQ-LBN-001..007: The widget type `link` MUST be registered with a
 * renderer reference to `LinkButtonWidget.vue`, a form reference to
 * `LinkButtonForm.vue`, and a `defaultContent` of `{label:'', url:'',
 * icon:'', actionType:'external', backgroundColor:'', textColor:''}`.
 *
 * REQ-DIV-002: The widget type `divider` MUST be registered with a renderer
 * reference to `DividerWidget.vue`, a form reference to `DividerForm.vue`,
 * and a `defaultContent` of `{style:'line', lineColor:'',
 * lineThickness:1, lineStyle:'solid', whitespaceSize:'medium',
 * headingText:''}`. The divider is fully client-side; no API endpoints
 * are required.
 *
 * REQ-FLS-001..011: The widget type `files` MUST be registered with a
 * renderer reference to `FilesWidget.vue`, a form reference to
 * `FilesForm.vue`, and a `defaultContent` of `{folderPath:'',
 * fileId:null, viewMode:'list', showThumbnails:true, mimeTypeFilter:[],
 * allowUpload:false, allowDelete:false, sortBy:'name',
 * sortDescending:false}`.
 *
 * REQ-PPL-001 / REQ-PPL-002: The widget type `people` MUST be registered
 * with a renderer reference to `PeopleWidget.vue`, a form reference to
 * `PeopleForm.vue`, and a `defaultContent` matching the spec's per-placement
 * config shape (layout, selectionMode, filters, excludeDisabled,
 * showBirthdays, birthdayWindowDays, sortBy, columns, showFields).
 *
 * REQ-NEWS-001..011: The widget type `news` MUST be registered with a
 * renderer reference to `NewsWidget.vue`, a form reference to
 * `NewsForm.vue`, and a `defaultContent` of `{feedUrls:[],
 * layout:'list', itemLimit:10, showThumbnails:true, showSummary:true,
 * summaryMaxChars:200, dateFormat:'relative', metadataFilter:null}`.
 *
 * REQ-WDG-014: The set of supported widget types MUST come from this single
 * registry. Toolbar dropdown, modal type selector, and grid renderer all
 * consult `listWidgetTypes()` / `getWidgetTypeEntry()`.
 */

import LabelWidget from '../components/Widgets/Renderers/LabelWidget.vue'
import LabelForm from '../components/Widgets/Forms/LabelForm.vue'
import TextDisplayWidget from '../components/Widgets/Renderers/TextDisplayWidget.vue'
import TextDisplayForm from '../components/Widgets/Forms/TextDisplayForm.vue'
import ImageWidget from '../components/Widgets/Renderers/ImageWidget.vue'
import ImageForm from '../components/Widgets/Forms/ImageForm.vue'
import LinkButtonWidget from '../components/Widgets/Renderers/LinkButtonWidget.vue'
import LinkButtonForm from '../components/Widgets/Forms/LinkButtonForm.vue'
import NcDashboardWidget from '../components/Widgets/Renderers/NcDashboardWidget.vue'
import NcDashboardForm from '../components/Widgets/Forms/NcDashboardForm.vue'
import HeaderWidget from '../components/Widgets/Renderers/HeaderWidget.vue'
import HeaderForm from '../components/Widgets/Forms/HeaderForm.vue'
import DividerWidget from '../components/Widgets/Renderers/DividerWidget.vue'
import DividerForm from '../components/Widgets/Forms/DividerForm.vue'
import FilesWidget from '../components/Widgets/Renderers/FilesWidget.vue'
import FilesForm from '../components/Widgets/Forms/FilesForm.vue'
import PeopleWidget from '../components/Widgets/Renderers/PeopleWidget.vue'
import PeopleForm from '../components/Widgets/Forms/PeopleForm.vue'
import QuicklinksWidget from '../components/Widgets/Renderers/QuicklinksWidget.vue'
import QuicklinksForm from '../components/Widgets/Forms/QuicklinksForm.vue'
import NewsWidget from '../components/Widgets/Renderers/NewsWidget.vue'
import NewsForm from '../components/Widgets/Forms/NewsForm.vue'

/**
 * @typedef {object} WidgetRegistryEntry
 * @property {object} renderer Vue component reference for the dashboard grid
 * @property {object|null} form Vue component reference for the AddWidgetModal sub-form, or null if no form is registered yet
 * @property {object} defaultContent Initial `content` payload for new placements
 * @property {string} displayName Human-readable type name for the type picker
 * @property {string} icon Material Design icon name used in the type picker
 */

/** @type {Record<string, WidgetRegistryEntry>} */
export const widgetRegistry = {
	label: {
		renderer: LabelWidget,
		form: LabelForm,
		defaultContent: {
			text: '',
			fontSize: '16px',
			color: '',
			backgroundColor: '',
			fontWeight: 'bold',
			textAlign: 'center',
		},
		displayName: t('mydash', 'Label'),
		icon: 'FormatTitle',
	},
	text: {
		renderer: TextDisplayWidget,
		form: TextDisplayForm,
		defaultContent: {
			text: '',
			fontSize: '14px',
			color: '',
			backgroundColor: '',
			textAlign: 'left',
		},
		displayName: t('mydash', 'Text'),
		icon: 'FormatText',
	},
	image: {
		renderer: ImageWidget,
		form: ImageForm,
		defaultContent: {
			url: '',
			alt: '',
			link: '',
			fit: 'cover',
		},
		displayName: t('mydash', 'Image'),
		icon: 'Camera',
	},
	link: {
		renderer: LinkButtonWidget,
		form: LinkButtonForm,
		defaultContent: {
			label: '',
			url: '',
			icon: '',
			actionType: 'external',
			backgroundColor: '',
			textColor: '',
		},
		displayName: t('mydash', 'Link Button'),
		icon: 'LinkVariant',
	},
	'nc-widget': {
		renderer: NcDashboardWidget,
		form: NcDashboardForm,
		defaultContent: {
			widgetId: '',
			displayMode: 'vertical',
		},
		displayName: t('mydash', 'Nextcloud Widget'),
		icon: 'ViewDashboard',
	},
	header: {
		renderer: HeaderWidget,
		form: HeaderForm,
		defaultContent: {
			title: '',
			subtitle: '',
			backgroundImageUrl: '',
			backgroundImageFileId: null,
			backgroundColor: '',
			overlayMode: 'none',
			overlayColor: '',
			overlayOpacity: 0.4,
			textColor: '',
			textAlign: 'center',
			verticalAlign: 'middle',
			height: 'medium',
			cta: null,
		},
		displayName: t('mydash', 'Header Banner'),
		icon: 'ViewHeadline',
	},
	divider: {
		renderer: DividerWidget,
		form: DividerForm,
		defaultContent: {
			style: 'line',
			lineColor: '',
			lineThickness: 1,
			lineStyle: 'solid',
			whitespaceSize: 'medium',
			headingText: '',
		},
		displayName: t('mydash', 'Divider'),
		icon: 'Minus',
	},
	files: {
		renderer: FilesWidget,
		form: FilesForm,
		defaultContent: {
			folderPath: '',
			fileId: null,
			viewMode: 'list',
			showThumbnails: true,
			mimeTypeFilter: [],
			allowUpload: false,
			allowDelete: false,
			sortBy: 'name',
			sortDescending: false,
		},
		displayName: t('mydash', 'Files'),
		icon: 'Folder',
	},
	people: {
		renderer: PeopleWidget,
		form: PeopleForm,
		defaultContent: {
			layout: 'grid',
			selectionMode: 'filter',
			selectedUsers: [],
			filters: [],
			filterOperator: 'AND',
			excludeDisabled: true,
			showBirthdays: true,
			birthdayWindowDays: 7,
			sortBy: 'displayName',
			columns: 3,
			showFields: {
				displayName: true,
				role: true,
				organisation: true,
				email: true,
				phone: true,
				avatar: true,
				birthdate: true,
			},
		},
		displayName: t('mydash', 'People'),
		icon: 'AccountGroup',
	},
	quicklinks: {
		renderer: QuicklinksWidget,
		form: QuicklinksForm,
		defaultContent: {
			links: [],
			iconSize: 'medium',
			iconShape: 'rounded',
			showLabels: true,
			labelPosition: 'below',
			columns: 'auto',
			tileBackgroundStyle: 'transparent',
			hoverEffect: 'lift',
		},
		displayName: t('mydash', 'Quicklinks'),
		icon: 'Star',
	},
	news: {
		renderer: NewsWidget,
		form: NewsForm,
		defaultContent: {
			feedUrls: [],
			layout: 'list',
			itemLimit: 10,
			showThumbnails: true,
			showSummary: true,
			summaryMaxChars: 200,
			dateFormat: 'relative',
			metadataFilter: null,
		},
		displayName: t('mydash', 'News'),
		icon: 'RssBox',
	},
}

/**
 * List every registered widget type that has a usable form component. The
 * AddWidgetModal type picker calls this; types without a `form` entry MUST
 * be excluded so the user is never offered a type they cannot configure.
 *
 * Per-widget proposals (text-display-widget, link-button-widget,
 * nc-dashboard-widget-proxy) each register their own form when they land —
 * until then those types are renderer-only and stay out of the picker.
 *
 * @return {string[]} list of registered type keys with a non-null form
 */
export function listWidgetTypes() {
	return Object.keys(widgetRegistry).filter(
		(type) => widgetRegistry[type] && widgetRegistry[type].form !== null && widgetRegistry[type].form !== undefined,
	)
}

/**
 * Look up a widget type entry; returns null when the type is unknown so the
 * caller can fall back gracefully.
 *
 * @param {string} type the widget type key
 * @return {WidgetRegistryEntry|null} the registry entry or null
 */
export function getWidgetTypeEntry(type) {
	return widgetRegistry[type] || null
}

/**
 * Return the `defaultContent` blob for a registered type, or `{}` for unknown
 * types so the caller never has to null-check.
 *
 * @param {string} type the widget type key
 * @return {object} a fresh copy of the type's defaultContent
 */
export function getDefaultContent(type) {
	const entry = widgetRegistry[type]
	if (!entry) {
		return {}
	}
	// Return a shallow copy so callers can mutate freely without polluting
	// the registry's frozen-by-convention defaults.
	return { ...entry.defaultContent }
}
