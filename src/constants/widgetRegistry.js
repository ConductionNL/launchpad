/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Widget registry — single source of truth for "what custom widget types
 * exist" on top of the Nextcloud-discovered widget set.
 *
 * Each entry maps a `type` string (the value persisted in
 * `oc_launchpad_widget_placements`) to a `{renderer, form, defaultContent,
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
 * REQ-TXT-005 / REQ-TXT-001..004 / REQ-TXMD-001..007: The widget type `text`
 * MUST be registered with a renderer reference to `TextDisplayWidget.vue`, a
 * form reference to `TextDisplayForm.vue`, and a `defaultContent` of
 * `{text:'', fontSize:'14px', color:'', backgroundColor:'', textAlign:'left',
 * contentMode:'markdown'}`. New widgets default to markdown mode; existing
 * placements without `contentMode` render through the legacy HTML branch.
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
 * REQ-LNKS-001..010: The widget type `links` MUST be registered with a
 * renderer reference to `LinksWidget.vue`, a form reference to
 * `LinksForm.vue`, and a `defaultContent` of `{sections:[], columns:3,
 * linkLayout:'card', iconSize:'medium', openInNewTab:true,
 * showSectionTitles:true, showLinkDescriptions:true}`.
 *
 * REQ-WDG-014: The set of supported widget types MUST come from this single
 * registry. Toolbar dropdown, modal type selector, and grid renderer all
 * consult `listWidgetTypes()` / `getWidgetTypeEntry()`.
 *
 * REQ-SAW-001..003: The widget type `spend-analytics` MUST be registered with
 * a renderer reference to `SpendAnalyticsWidget.vue`, a form reference to
 * `SpendAnalyticsForm.vue`, a `defaultContent` of `{viewMode:'summary',
 * period:'quarter', filters:{categoryIds:[], departmentIds:[], vendorIds:[]},
 * drillThroughTarget:'detail-page', attachEvidence:true,
 * aiInsights:{enabled:false}}`, and a soft `requires.graphql` of at minimum
 * `['financeq.transactions', 'procest.cases']`. The `requires` clause is a
 * runtime-source hint only — it MUST NOT appear in `manifest.dependencies`.
 */

// Widget renderers + config forms are sourced from the shared
// @conduction/nextcloud-vue library wherever the component is a drop-in
// (aliased to the historical local names so the registry map below is
// unchanged). Presentational renderers (label/text/image/header/divider/
// video/quicklinks/links/menu) and ALL config forms move to the library;
// data-driven renderers that call launchpad's own endpoints (link-button,
// nc-widget, files, people, news, calendar, spend-analytics, container, tile)
// stay local for now (they need a data-source adapter / carry app-specific
// behaviour) — see docs/migration/widget-library-to-ncvue.md.
import {
	CnLabelWidget as LabelWidget,
	CnTextWidget as TextDisplayWidget,
	CnImageWidget as ImageWidget,
	CnHeaderWidget as HeaderWidget,
	CnDividerWidget as DividerWidget,
	CnVideoWidget as VideoWidget,
	CnQuicklinksWidget as QuicklinksWidget,
	CnLinksWidget as LinksWidget,
	CnMenuWidget as MenuWidget,
	CnLabelWidgetForm as LabelForm,
	CnTextWidgetForm as TextDisplayForm,
	CnImageWidgetForm as ImageForm,
	CnLinkButtonWidgetForm as LinkButtonForm,
	CnNcDashboardWidgetForm as NcDashboardForm,
	CnHeaderWidgetForm as HeaderForm,
	CnDividerWidgetForm as DividerForm,
	CnFilesWidgetForm as FilesForm,
	CnPeopleWidgetForm as PeopleForm,
	CnQuicklinksWidgetForm as QuicklinksForm,
	CnNewsWidgetForm as NewsForm,
	CnVideoWidgetForm as VideoForm,
	CnCalendarWidgetForm as CalendarForm,
	CnLinksWidgetForm as LinksForm,
	CnMenuWidgetForm as MenuForm,
	CnContainerWidgetForm as ContainerForm,
	CnDashTileWidgetForm as TileForm,
	CnSpendAnalyticsWidgetForm as SpendAnalyticsForm,
} from '@conduction/nextcloud-vue'
import LinkButtonWidget from '../components/Widgets/Renderers/LinkButtonWidget.vue'
import NcDashboardWidget from '../components/Widgets/Renderers/NcDashboardWidget.vue'
import FilesWidget from '../components/Widgets/Renderers/FilesWidget.vue'
import PeopleWidget from '../components/Widgets/Renderers/PeopleWidget.vue'
import NewsWidget from '../components/Widgets/Renderers/NewsWidget.vue'
import CalendarWidget from '../components/Widgets/Renderers/CalendarWidget.vue'
import ContainerWidget from '../components/Widgets/Renderers/ContainerWidget.vue'
import TileWidget from '../components/Widgets/Renderers/TileWidget.vue'
import SpendAnalyticsWidget from '../components/Widgets/Renderers/SpendAnalyticsWidget.vue'

/**
 * @typedef {object} WidgetRegistryEntry
 * @property {object} renderer Vue component reference for the dashboard grid
 * @property {object|null} form Vue component reference for the AddWidgetModal sub-form, or null if no form is registered yet
 * @property {object} defaultContent Initial `content` payload for new placements
 * @property {string} displayName Human-readable type name for the type picker
 * @property {string} icon Material Design icon name used in the type picker
 * @property {{graphql?: string[]}} [requires] Soft runtime-source declaration for cross-app widgets — names the sibling-app GraphQL schemas the widget reads (REQ-SAW-001). NEVER a `manifest.dependencies` entry.
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
		displayName: t('launchpad', 'Label'),
		icon: 'FormatTitle',
	},
	text: {
		renderer: TextDisplayWidget,
		form: TextDisplayForm,
		// REQ-TXMD-001 / REQ-TXMD-005: new text widgets default to
		// markdown mode. Existing placements without a `contentMode` key
		// fall through to the renderer's legacy 'html' branch — the
		// registry default only seeds new placements via AddWidgetModal.
		defaultContent: {
			text: '',
			fontSize: '14px',
			color: '',
			backgroundColor: '',
			textAlign: 'left',
			contentMode: 'markdown',
			tableMode: false,
			tableData: null,
		},
		displayName: t('launchpad', 'Text'),
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
		displayName: t('launchpad', 'Image'),
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
			// REQ-LBLM-001/005/009: list-mode defaults. `displayMode`
			// defaults to `'button'` so newly-created placements behave
			// exactly like the legacy single-link widget; the `links`,
			// `listOrientation`, `listItemGap` keys ride along so the
			// edit form has a stable starting shape.
			displayMode: 'button',
			listOrientation: 'vertical',
			listItemGap: 'normal',
			links: [],
		},
		displayName: t('launchpad', 'Link Button'),
		icon: 'LinkVariant',
	},
	'nc-widget': {
		renderer: NcDashboardWidget,
		form: NcDashboardForm,
		defaultContent: {
			widgetId: '',
			displayMode: 'vertical',
		},
		displayName: t('launchpad', 'Nextcloud Widget'),
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
		displayName: t('launchpad', 'Header Banner'),
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
		displayName: t('launchpad', 'Divider'),
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
		displayName: t('launchpad', 'Files'),
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
		displayName: t('launchpad', 'People'),
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
		displayName: t('launchpad', 'Quicklinks'),
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
		displayName: t('launchpad', 'News'),
		icon: 'RssBox',
	},
	video: {
		renderer: VideoWidget,
		form: VideoForm,
		defaultContent: {
			sourceType: null,
			videoUrl: '',
			fileId: null,
			autoplay: false,
			muted: true,
			loop: false,
			controls: true,
			aspectRatio: '16:9',
			posterUrl: '',
		},
		displayName: t('launchpad', 'Video'),
		icon: 'Video',
	},
	calendar: {
		renderer: CalendarWidget,
		form: CalendarForm,
		defaultContent: {
			internalCalendars: [],
			externalIcsUrls: [],
			viewMode: 'agenda',
			daysAhead: 14,
			colorByCalendar: true,
		},
		displayName: t('launchpad', 'Calendar'),
		icon: 'Calendar',
	},
	links: {
		renderer: LinksWidget,
		form: LinksForm,
		defaultContent: {
			sections: [],
			columns: 3,
			linkLayout: 'card',
			iconSize: 'medium',
			openInNewTab: true,
			showSectionTitles: true,
			showLinkDescriptions: true,
		},
		displayName: t('launchpad', 'Links'),
		icon: 'LinkBoxVariant',
	},
	menu: {
		renderer: MenuWidget,
		form: MenuForm,
		defaultContent: {
			items: [],
			style: 'dropdown',
			orientation: 'horizontal',
			showIcons: true,
			expandedByDefault: false,
			activeItemHighlight: 'underline',
		},
		displayName: t('launchpad', 'Menu'),
		icon: 'ViewDashboard',
	},
	// REQ-CONT-001: container widget — recursive sub-grid host. Children
	// live in `content.placements[]` and are rendered through the inner
	// GridStack instance bounded by the container's outer cell. Server-
	// side REQ-CONT-006 caps recursion at 3 levels deep.
	container: {
		renderer: ContainerWidget,
		form: ContainerForm,
		defaultContent: {
			placements: [],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: '',
		},
		displayName: t('launchpad', 'Container'),
		icon: 'ViewDashboard',
	},
	// REQ-WDG-022 / REQ-TILE-PLACEMENT: tile widget — registry-driven
	// replacement for the deprecated standalone tile-creation flow. The
	// renderer reads from BOTH the new inline `content.{...}` shape AND
	// the legacy flat `placement.tile*` columns so dashboards holding
	// tile placements created via the deprecated `oc_launchpad_tiles` flow
	// keep rendering without a migration step.
	tile: {
		renderer: TileWidget,
		form: TileForm,
		defaultContent: {
			title: '',
			icon: '',
			iconType: 'class',
			backgroundColor: '#3b82f6',
			textColor: '#ffffff',
			linkType: 'app',
			linkValue: '',
		},
		displayName: t('launchpad', 'Tile'),
		icon: 'ViewGrid',
	},
	// REQ-SAW-001/-002/-003: spend-analytics widget — consumes runtime
	// GraphQL from financeq + procest. The soft `requires.graphql`
	// declaration names the sibling-app schemas it reads; it MUST NEVER
	// be promoted to a top-level `manifest.dependencies` entry
	// (feedback_launchpad-no-or-dependency.md) — the renderer registers
	// regardless and falls back to an empty-state when a source is
	// absent at runtime.
	'spend-analytics': {
		renderer: SpendAnalyticsWidget,
		form: SpendAnalyticsForm,
		defaultContent: {
			viewMode: 'summary',
			period: 'quarter',
			filters: { categoryIds: [], departmentIds: [], vendorIds: [] },
			drillThroughTarget: 'detail-page',
			attachEvidence: true,
			aiInsights: { enabled: false },
		},
		requires: { graphql: ['financeq.transactions', 'procest.cases'] },
		displayName: t('launchpad', 'Spend analytics'),
		icon: 'ChartLine',
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
