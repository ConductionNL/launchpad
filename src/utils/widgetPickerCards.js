/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * The widget picker's cards, kinds and sections as board LpWidgetKiezen
 * draws them (widget-picker-board-sync, REQ-WDG-040..043).
 *
 * Every entry the picker offers becomes one card: `{key, kind, section,
 * title, line, icon, disabled, legacy, action}`. `action` is the event the
 * picker emits when the card is placed with Add widget, and its argument.
 */

/** The kinds in the column, in board order; `all` holds every card. */
export const KINDS = Object.freeze(['all', 'content', 'tiles', 'people', 'data', 'apps', 'office'])

/** The card sections, in board order. */
export const SECTIONS = Object.freeze(['launchpad', 'tiles', 'apps'])

/**
 * The kind of each Launchpad widget type. A type not named here is content.
 *
 * @type {Record<string, string>}
 */
const TYPE_KINDS = Object.freeze({
	link: 'tiles',
	quicklinks: 'tiles',
	tile: 'tiles',
	links: 'tiles',
	menu: 'tiles',
	container: 'tiles',
	'app-tiles': 'tiles',
	'app-tile': 'tiles',
	livetile: 'tiles',
	people: 'people',
	'colleague-activity': 'people',
	communities: 'people',
	stat: 'data',
	delta: 'data',
	gauge: 'data',
	'object-list': 'data',
	chart: 'data',
	'stats-block': 'data',
	table: 'data',
	'spend-analytics': 'data',
	'pie-chart': 'data',
	graph: 'data',
	'activity-report': 'data',
	'status-report': 'data',
	'nc-widget': 'apps',
	'work-packages': 'apps',
	'assigned-cases': 'apps',
})

/**
 * The kind a Launchpad widget type belongs to.
 *
 * @param {string} type The registry key.
 * @return {string} One of {@link KINDS} other than `all`.
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
 */
export function kindOfType(type) {
	return TYPE_KINDS[type] ?? 'content'
}

/**
 * The column's label of a kind.
 *
 * @param {string} kind The kind.
 * @param {function(string, string): string} t The translate function.
 * @return {string}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
 */
export function kindLabel(kind, t) {
	return {
		all: t('launchpad', 'All'),
		content: t('launchpad', 'Content'),
		tiles: t('launchpad', 'Tiles and links'),
		people: t('launchpad', 'People'),
		data: t('launchpad', 'Data and charts'),
		apps: t('launchpad', 'From other apps'),
		office: t('launchpad', 'Office'),
	}[kind] ?? kind
}

/**
 * The heading of a card section.
 *
 * @param {string} section The section.
 * @param {function(string, string): string} t The translate function.
 * @return {string}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
 */
export function sectionLabel(section, t) {
	return {
		launchpad: t('launchpad', 'Widgets from Launchpad'),
		tiles: t('launchpad', 'App tiles'),
		apps: t('launchpad', 'From other Nextcloud apps'),
	}[section] ?? section
}

/**
 * The one line that says what a Launchpad widget type does.
 *
 * @param {string} type The registry key.
 * @param {function(string, string): string} t The translate function.
 * @return {string}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
 */
export function typeLine(type, t) {
	const lines = {
		label: () => t('launchpad', 'A short line of text.'),
		text: () => t('launchpad', 'Formatted text you write yourself.'),
		image: () => t('launchpad', 'A picture with alternative text.'),
		link: () => t('launchpad', 'One button to a page.'),
		divider: () => t('launchpad', 'A line between widgets.'),
		header: () => t('launchpad', 'A heading with a picture behind it.'),
		quicklinks: () => t('launchpad', 'A row of links to pages.'),
		video: () => t('launchpad', 'A video from a file or a web address.'),
		news: () => t('launchpad', 'Items from a news feed.'),
		tile: () => t('launchpad', 'One app or page as a tile.'),
		links: () => t('launchpad', 'A list of links.'),
		menu: () => t('launchpad', 'A menu of pages.'),
		container: () => t('launchpad', 'A group of tiles that share an order.'),
		files: () => t('launchpad', 'Files from a folder.'),
		stat: () => t('launchpad', 'One number from your data.'),
		'app-tiles': () => t('launchpad', 'Tiles for several apps at once.'),
		'app-tile': () => t('launchpad', 'One app as a tile.'),
		delta: () => t('launchpad', 'A number and how it changed.'),
		gauge: () => t('launchpad', 'A number against its target.'),
		'object-list': () => t('launchpad', 'A list of items from a register.'),
		chart: () => t('launchpad', 'A chart of your data.'),
		'stats-block': () => t('launchpad', 'Several numbers side by side.'),
		table: () => t('launchpad', 'Rows from a register as a table.'),
		calendar: () => t('launchpad', 'Upcoming events from your calendars.'),
		people: () => t('launchpad', 'Colleagues with their details.'),
		'spend-analytics': () => t('launchpad', 'Spending over time.'),
		'nc-widget': () => t('launchpad', 'A widget from another Nextcloud app.'),
		map: () => t('launchpad', 'Places on a map.'),
		'ai-assistant': () => t('launchpad', 'Ask the assistant a question.'),
		attention: () => t('launchpad', 'What needs you first, across apps.'),
		'colleague-activity': () => t('launchpad', 'What colleagues worked on.'),
		'recent-work': () => t('launchpad', 'Files and items you opened lately.'),
		'activity-report': () => t('launchpad', 'Activity per month.'),
		'status-report': () => t('launchpad', 'The status of a set of items.'),
		clock: () => t('launchpad', 'The time, here or elsewhere.'),
		communities: () => t('launchpad', 'The communities you are in.'),
		weather: () => t('launchpad', 'The weather where you are.'),
		livetile: () => t('launchpad', 'A live number from a connection.'),
		iframe: () => t('launchpad', 'A page from another site.'),
		search: () => t('launchpad', 'Search from the dashboard.'),
		'section-heading': () => t('launchpad', 'A heading above a row of widgets.'),
		'wiki-pages': () => t('launchpad', 'Pages from the wiki.'),
		'work-packages': () => t('launchpad', 'Your open work packages.'),
		'assigned-cases': () => t('launchpad', 'Your open cases.'),
		'pie-chart': () => t('launchpad', 'Shares of a whole as a pie.'),
		graph: () => t('launchpad', 'Counts over time as bars or lines.'),
		announcements: () => t('launchpad', 'News and notices for your group.'),
	}
	return (lines[type] ?? (() => t('launchpad', 'A Launchpad widget.')))()
}

/**
 * Whether the viewer's role may add a widget. The allow-list is the one the
 * server answers with (`allowedWidgets` in the initial state, REQ-RFP-010):
 * `null` means no restriction; otherwise only the ids it names, as
 * `RoleFeaturePermissionService::isWidgetAllowed` decides on save.
 *
 * @param {string} id The widget id or type key.
 * @param {Array<string>|null|undefined} allowedWidgets The allow-list.
 * @return {boolean}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-widgets-the-role-may-not-add-req-wdg-043
 */
export function roleAllows(id, allowedWidgets) {
	return !Array.isArray(allowedWidgets) || allowedWidgets.includes(id)
}

/**
 * Whether a Nextcloud widget is of the older kind: it registers a callback
 * and offers no item API (the legacy widget bridge renders it).
 *
 * @param {object} widget The Nextcloud widget.
 * @return {boolean}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-widgets-the-role-may-not-add-req-wdg-043
 */
export function isLegacyWidget(widget) {
	return Array.isArray(widget?.itemApiVersions) && widget.itemApiVersions.length === 0
}

/**
 * Every card the picker offers, in section order.
 *
 * @param {object} input What the picker was given.
 * @param {Array<object>} input.customTypes `{type, title}` per Launchpad type.
 * @param {boolean} input.officePack Whether the office pack is offered.
 * @param {Array<object>} input.catalogueApps The reader's tile catalogue.
 * @param {Array<object>} input.discoveredApps The apps this server runs for the reader.
 * @param {Array<object>} input.widgets The Nextcloud widgets.
 * @param {Array<string>|null} input.allowedWidgets The role's allow-list.
 * @param {Array<string>} input.placedWidgetIds The widgets on the dashboard.
 * @param {Array<string>} input.placedAppIds The discovered apps on the dashboard.
 * @param {function(string, string, object=): string} t The translate function.
 * @return {Array<object>} The cards.
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md
 */
export function pickerCards(input, t) {
	const { customTypes = [], officePack = false, catalogueApps = [], discoveredApps = [], widgets = [], allowedWidgets = null, placedWidgetIds = [], placedAppIds = [] } = input
	const cards = []

	for (const entry of customTypes) {
		cards.push({
			key: `type:${entry.type}`,
			kind: kindOfType(entry.type),
			section: 'launchpad',
			title: entry.title,
			line: typeLine(entry.type, t),
			icon: { kind: 'type', name: entry.icon || '' },
			disabled: !roleAllows(entry.type, allowedWidgets),
			legacy: false,
			badge: '',
			action: { event: 'addCustom', arg: entry.type },
			testid: `widget-picker-type-${entry.type}`,
		})
	}
	if (officePack) {
		cards.push({
			key: 'office-pack',
			kind: 'office',
			section: 'launchpad',
			title: t('launchpad', 'Office tiles'),
			line: t('launchpad', 'A tile for each new document and office app.'),
			icon: { kind: 'type', name: 'office' },
			disabled: !roleAllows('container', allowedWidgets),
			legacy: false,
			badge: '',
			action: { event: 'addOfficePack', arg: undefined },
			testid: 'widget-picker-office-pack',
		})
	}

	const tilesAllowed = roleAllows('tile', allowedWidgets)
	for (const app of catalogueApps) {
		cards.push({
			key: `catalogue:${app.uuid}`,
			kind: 'tiles',
			section: 'tiles',
			title: app.name,
			line: app.description || t('launchpad', 'An app your organisation approved.'),
			icon: { kind: 'tile', appId: app.uuid, url: app.icon && app.iconType === 'url' ? app.icon : '', className: app.icon && app.iconType !== 'url' ? app.icon : '' },
			disabled: !tilesAllowed,
			legacy: false,
			badge: '',
			action: { event: 'addCatalogueApp', arg: app },
			testid: `widget-picker-catalogue-${app.uuid}`,
		})
	}
	for (const app of discoveredApps) {
		cards.push({
			key: `app:${app.appId}`,
			kind: 'tiles',
			section: 'tiles',
			title: app.name,
			line: t('launchpad', 'An app on this server.'),
			icon: { kind: 'tile', appId: app.appId, mask: app.icon || '' },
			disabled: !tilesAllowed,
			legacy: false,
			badge: placedAppIds.includes(app.appId) ? t('launchpad', 'On this dashboard') : '',
			action: { event: 'addApp', arg: app },
			testid: `widget-picker-app-tile-${app.appId}`,
		})
	}
	cards.push({
		key: 'own-tile',
		kind: 'tiles',
		section: 'tiles',
		title: t('launchpad', 'Own tile'),
		line: t('launchpad', 'A tile for any address.'),
		icon: { kind: 'own' },
		disabled: !tilesAllowed,
		legacy: false,
		badge: '',
		action: { event: 'addCustom', arg: 'tile' },
		testid: 'widget-picker-custom-tile',
	})

	const sorted = [...widgets].sort((a, b) => {
		const aPlaced = placedWidgetIds.includes(a.id)
		const bPlaced = placedWidgetIds.includes(b.id)
		if (aPlaced !== bPlaced) {
			return aPlaced ? 1 : -1
		}
		return (a.order || 0) - (b.order || 0)
	})
	for (const widget of sorted) {
		cards.push({
			key: `widget:${widget.id}`,
			kind: 'apps',
			section: 'apps',
			title: widget.title,
			line: t('launchpad', 'A widget from another Nextcloud app.'),
			icon: { kind: 'widget', url: widget.iconUrl || '', className: widget.iconClass || '' },
			disabled: !roleAllows(widget.id, allowedWidgets),
			legacy: isLegacyWidget(widget),
			badge: placedWidgetIds.includes(widget.id) ? t('launchpad', 'Already added') : '',
			action: { event: 'add', arg: widget.id },
			testid: `widget-picker-widget-${widget.id}`,
		})
	}
	return cards
}

/**
 * The cards of one kind that match the search, by title or line.
 *
 * @param {Array<object>} cards The cards.
 * @param {string} kind The chosen kind.
 * @param {string} query The search.
 * @return {Array<object>}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
 */
export function filterCards(cards, kind, query) {
	const needle = String(query || '').trim().toLowerCase()
	return cards.filter((card) => (kind === 'all' || card.kind === kind)
		&& (needle === '' || String(card.title).toLowerCase().includes(needle) || String(card.line).toLowerCase().includes(needle)))
}

/**
 * The number of cards per kind, for the column.
 *
 * @param {Array<object>} cards The cards.
 * @return {Record<string, number>}
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
 */
export function countByKind(cards) {
	const counts = Object.fromEntries(KINDS.map((kind) => [kind, 0]))
	for (const card of cards) {
		counts.all += 1
		counts[card.kind] = (counts[card.kind] ?? 0) + 1
	}
	return counts
}
