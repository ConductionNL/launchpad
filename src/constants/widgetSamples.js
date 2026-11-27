// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The sample content of every widget type, for its miniature in Add widget
 * (REQ-WDG-048, board LpWidgetKiezen): the content the type's own renderer
 * needs to draw a believable widget without any request. A type that
 * nextcloud-vue registers may carry its own `sampleContent`, which wins.
 *
 * Every type of `lib/widget-types.json` MUST have an entry: the miniature
 * registry test fails on a type without one.
 *
 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
 */

const DAY = 24 * 60 * 60 * 1000

/**
 * A date a number of days from a fixed sample day, as an ISO string, so the
 * samples never depend on the clock.
 *
 * @param {number} days Days after 2 December 2026, 09:00 UTC.
 * @return {string}
 */
function sampleDate(days) {
	return new Date(Date.UTC(2026, 11, 2, 9) + days * DAY).toISOString()
}

const NEWS_ITEMS = [
	{ title: 'New opening hours of the town hall', summary: 'From January the desks open at eight.', date: sampleDate(-1) },
	{ title: 'Road works on the Dorpsstraat', summary: 'Two weeks of detours around the market.', date: sampleDate(-2) },
	{ title: 'Council meeting moved', summary: 'The meeting is now on Thursday.', date: sampleDate(-3) },
]

const PEOPLE = [
	{ uid: 'sample-anna', displayName: 'Anna de Vries', title: 'Policy officer' },
	{ uid: 'sample-pieter', displayName: 'Pieter Jansen', title: 'Case handler' },
	{ uid: 'sample-fatima', displayName: 'Fatima El Amrani', title: 'Team lead' },
]

const ROWS = [
	{ id: 's1', title: 'Permit Dorpsstraat 12', status: 'Open', updated: sampleDate(-1) },
	{ id: 's2', title: 'Objection parking fine', status: 'In progress', updated: sampleDate(-2) },
	{ id: 's3', title: 'Subsidy sports club', status: 'Open', updated: sampleDate(-4) },
]

const BARS = [
	{ label: 'Sep', value: 12 },
	{ label: 'Oct', value: 19 },
	{ label: 'Nov', value: 15 },
	{ label: 'Dec', value: 22 },
]

/** @type {Record<string, object>} */
export const SAMPLE_CONTENT = Object.freeze({
	label: { text: 'Welcome', fontSize: '16px', fontWeight: 'bold', textAlign: 'center' },
	text: { text: '**Today** the team meets at ten.\n\nBring the planning.', contentMode: 'markdown', textAlign: 'left' },
	image: { url: '', alt: 'A picture', fit: 'cover' },
	link: { label: 'Create document', url: '#', actionType: 'external', displayMode: 'button' },
	divider: { style: 'line', lineThickness: 1, lineStyle: 'solid', whitespaceSize: 'medium', headingText: '' },
	header: { title: 'Good morning', subtitle: 'Wednesday 2 December', greeting: false },
	quicklinks: {
		links: [
			{ label: 'Intranet', url: '#', icon: 'Web' },
			{ label: 'Wiki', url: '#', icon: 'BookOpenVariant' },
			{ label: 'Rooms', url: '#', icon: 'CalendarBlank' },
		],
		showLabels: true,
	},
	video: { title: 'Welcome video', url: '', provider: 'file' },
	news: { feedUrls: [], layout: 'list', itemLimit: 3, showThumbnails: false, showSummary: true, sampleItems: NEWS_ITEMS },
	tile: { mode: 'link', title: 'Intranet', linkType: 'url', linkValue: '#', icon: 'Web', iconType: 'mdi', colourToken: '--sc-primary', textColour: 'white' },
	links: {
		sections: [{ title: 'Work', links: [{ label: 'Intranet', url: '#' }, { label: 'Wiki', url: '#' }, { label: 'Rooms', url: '#' }] }],
		showSectionTitles: true,
	},
	menu: { items: [{ label: 'Home', url: '#' }, { label: 'News', url: '#' }, { label: 'Contact', url: '#' }] },
	container: { title: 'My apps', placements: [] },
	files: { folderPath: '/', sampleFiles: [{ name: 'Planning.xlsx' }, { name: 'Minutes.docx' }, { name: 'Logo.png' }] },
	stat: { label: 'Open cases', value: 42, sampleValue: 42, format: 'number' },
	'app-tiles': { pinned: [], exclude: [], showFilter: false, showMostUsed: false, sampleApps: ['files', 'mail', 'calendar', 'deck'] },
	// Drawn as a link-mode tile: the app-mode renderer reads the viewer's app menu.
	'app-tile': { mode: 'link', title: 'Files', linkType: 'url', linkValue: '#', icon: 'Folder', iconType: 'mdi', colourToken: '--sc-primary', textColour: 'white' },
	delta: { label: 'Requests this week', value: 18, previous: 14, sampleValue: 18, samplePrevious: 14 },
	gauge: { label: 'Handled on time', value: 82, target: 90, sampleValue: 82 },
	'object-list': { title: 'Open cases', sampleRows: ROWS },
	chart: { chartType: 'bar', title: 'Requests per month', series: [{ name: 'Requests', data: BARS.map((bar) => bar.value) }], labels: BARS.map((bar) => bar.label) },
	'stats-block': { items: [{ label: 'Open', value: 12 }, { label: 'Late', value: 3 }, { label: 'Done', value: 48 }] },
	table: { title: 'Cases', sampleRows: ROWS },
	calendar: { sampleEvents: [{ title: 'Team meeting', start: sampleDate(0) }, { title: 'Office hours', start: sampleDate(1) }] },
	people: { layout: 'list', samplePeople: PEOPLE },
	'spend-analytics': { sampleSeries: BARS },
	'nc-widget': { widgetId: '', displayMode: 'vertical' },
	map: { center: [52.09, 5.12], zoom: 11, markers: [] },
	'ai-assistant': { agentUuid: '' },
	attention: {
		limit: 3,
		sampleData: {
			sourceCount: 3,
			items: [
				{ key: 'a1', id: 'a1', appId: 'dossiq', appName: 'Cases', title: 'Permit Dorpsstraat 12', reason: 'Deadline today', severity: 'high', href: '#', actionLabel: 'Open' },
				{ key: 'a2', id: 'a2', appId: 'mail', appName: 'Mail', title: 'Question about the subsidy', reason: 'Unanswered for 3 days', severity: 'medium', href: '#', actionLabel: 'Reply' },
				{ key: 'a3', id: 'a3', appId: 'deck', appName: 'Deck', title: 'Plan the council meeting', reason: 'Assigned to you', severity: 'low', href: '#', actionLabel: 'Open' },
			],
		},
	},
	'colleague-activity': {
		limit: 3,
		days: 7,
		sampleData: {
			total: 3,
			actors: 3,
			entries: [
				{ id: 1, type: 'file_changed', actorDisplayName: 'Anna de Vries', title: 'changed Planning.xlsx', link: '#', timestamp: Date.UTC(2026, 11, 2, 8) / 1000 },
				{ id: 2, type: 'file_created', actorDisplayName: 'Pieter Jansen', title: 'created Minutes.docx', link: '#', timestamp: Date.UTC(2026, 11, 2, 7) / 1000 },
				{ id: 3, type: 'comments', actorDisplayName: 'Fatima El Amrani', title: 'commented on Budget 2027', link: '#', timestamp: Date.UTC(2026, 11, 1, 15) / 1000 },
			],
		},
	},
	'recent-work': {
		limit: 3,
		sampleData: {
			activities: [
				{ app: 'files', type: 'file_changed', subject: 'You changed Planning.xlsx', object_name: 'Planning.xlsx', link: '#', datetime: sampleDate(-0.1) },
				{ app: 'files', type: 'file_created', subject: 'You created Minutes.docx', object_name: 'Minutes.docx', link: '#', datetime: sampleDate(-0.5) },
				{ app: 'deck', type: 'deck', subject: 'You moved Plan the council meeting', object_name: 'Plan the council meeting', link: '#', datetime: sampleDate(-1) },
			],
		},
	},
	'activity-report': { days: 30 },
	'status-report': {},
	clock: { style: 'digital', hourFormat: '24', timezone: 'Europe/Amsterdam', showDate: true, sampleTime: sampleDate(0) },
	communities: { title: '', onlyPinned: false },
	weather: {
		location: 'Utrecht',
		unitsOverride: 'metric',
		sampleReading: { location: 'Utrecht', tempValue: 7, units: 'metric', condition: 'rain', conditionCode: 'rain', conditionText: 'Light rain', stale: false },
	},
	livetile: { label: 'Visitors today', sourceMode: 'url', url: '', sampleData: { reading: { value: 128, stale: false } } },
	iframe: { url: '', title: 'A page from another site', height: 400 },
	search: { placeholder: 'Search apps, files and people' },
	'section-heading': { title: 'My apps', description: 'One tile per app' },
	'wiki-pages': { space: '', limit: 3 },
	'work-packages': { scope: 'mine', project: '', limit: 3 },
	'assigned-cases': { register: 'dossiq', schema: 'case', limit: 3 },
	connections: {},
	'pie-chart': { chartKind: 'donut', source: 'values', values: [{ label: 'Open', value: 12 }, { label: 'In progress', value: 7 }, { label: 'Done', value: 21 }] },
	graph: { chartKind: 'bar', source: 'values', values: BARS },
	// nextcloud-vue display types the picker offers; their renderers draw
	// content only, so the sample needs no preview support there. Types that
	// read a register or poll (tasks, stacked bar, week strip, notepad, ...)
	// keep the icon until nextcloud-vue honours `preview`.
	banner: { text: 'The town hall is closed on 24 December.', variant: 'info' },
	markdown: { markdown: '### This week\n\n- Team meeting on Tuesday\n- Office hours on Thursday' },
	announcements: {
		category: '',
		limit: 3,
		sampleData: {
			announcements: [
				{ uuid: 's-1', kind: 'news', category: '', title: 'New opening hours of the town hall', body: 'From January the desks open at eight.', publishAt: sampleDate(-1) },
				{ uuid: 's-2', kind: 'news', category: '', title: 'Team outing on 12 December', body: 'Sign up with Fatima before Friday.', publishAt: sampleDate(-2) },
			],
		},
	},
})

/**
 * Types whose nextcloud-vue renderer still reads the server in the browser
 * with `preview` set (checked live on a sandbox, 27 Nov 2026: the people list
 * and its avatars, the app menu): their miniature shows the type's icon until
 * nextcloud-vue honours `preview` for them (REQ-WDG-048: no request).
 */
export const NOT_PREVIEW_SAFE = Object.freeze(['people', 'app-tiles'])

/**
 * The sample content of a widget type: the registry entry's own
 * `sampleContent` when it has one, else Launchpad's, else null.
 *
 * @param {string} type The widget type.
 * @param {{sampleContent?: object}|null} [entry] The type's registry entry.
 * @return {object|null} A fresh copy, or null for a type without one.
 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
 */
export function sampleContentOf(type, entry = null) {
	if (NOT_PREVIEW_SAFE.includes(type) && !entry?.sampleContent) {
		return null
	}
	const sample = entry?.sampleContent ?? SAMPLE_CONTENT[type] ?? null
	return sample ? JSON.parse(JSON.stringify(sample)) : null
}
