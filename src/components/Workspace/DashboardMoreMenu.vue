<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - DashboardMoreMenu: the start page's one "Meer" menu (LpVandaag). It holds
  - what used to be four controls beside "Startpagina aanpassen": the
  - dashboard switcher, the active dashboard's cog menu, the start page menu
  - and the Share button. Left: Dashboards and Instellingen. Right: Dit
  - dashboard and Launchpad. The settings and the app's own destinations
  - are only here while the navigation panel is left out, so nothing is
  - offered twice.
-->
<template>
	<CnGroupedMenu
		:columns="columns"
		:label="t('launchpad', 'More')"
		class="launchpad-more-menu"
		data-testid="launchpad-more-menu"
		@open="$emit('menuOpen')" />
</template>

<script>
import { CnGroupedMenu } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { markRaw } from 'vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import ChartBoxOutline from 'vue-material-design-icons/ChartBoxOutline.vue'
import CheckDecagramOutline from 'vue-material-design-icons/CheckDecagramOutline.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline.vue'
import FileReplaceOutline from 'vue-material-design-icons/FileReplaceOutline.vue'
import History from 'vue-material-design-icons/History.vue'
import Home from 'vue-material-design-icons/Home.vue'
import MapMarkerPath from 'vue-material-design-icons/MapMarkerPath.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import PowerPlugOutline from 'vue-material-design-icons/PowerPlugOutline.vue'
import Publish from 'vue-material-design-icons/Publish.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import ShieldAccountOutline from 'vue-material-design-icons/ShieldAccountOutline.vue'
import Sitemap from 'vue-material-design-icons/Sitemap.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarCheck from 'vue-material-design-icons/StarCheck.vue'
import StoreOutline from 'vue-material-design-icons/StoreOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import { isOwnedDashboard } from './DashboardRowActions.vue'
import { readChrome, startPageSections } from './StartPageMenu.vue'

/** How many dashboards the menu lists before "All dashboards". */
const DASHBOARD_LIMIT = 8

/**
 * The order of the app's own destinations, as the board lists them. An
 * entry not named here follows, in the panel's order.
 */
const LAUNCHPAD_ORDER = [
	'StoreMenu',
	'admin-templates',
	'ReportsMenu',
	'FlowsMenu',
	'IntegrationsMenu',
	'Documentation',
	'FeaturesRoadmapMenu',
]

/**
 * Panel entries the menu leaves out because another item already offers
 * them: the dashboards (the Dashboards group) and the in-app admin settings
 * route (the Settings group links to the admin settings).
 */
const OFFERED_ELSEWHERE = ['dashboards', 'admin-settings']

/** Icons of the app's own destinations, by menu entry id. */
const DESTINATION_ICONS = {
	StoreMenu: StoreOutline,
	'admin-templates': FileReplaceOutline,
	ReportsMenu: ChartBoxOutline,
	FlowsMenu: Sitemap,
	IntegrationsMenu: PowerPlugOutline,
	Documentation: BookOpenVariantOutline,
	FeaturesRoadmapMenu: MapMarkerPath,
	'personal-settings': AccountOutline,
	'admin-settings-link': ShieldAccountOutline,
}

/**
 * The app's own destinations in the board's order.
 *
 * @param {object[]} items The resolved panel items.
 * @return {object[]} The items, ordered.
 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
 */
export function orderDestinations(items) {
	const rank = (item) => {
		const at = LAUNCHPAD_ORDER.indexOf(item.id)
		return at === -1 ? LAUNCHPAD_ORDER.length : at
	}
	return items
		.map((item, index) => ({ item, index }))
		.sort((a, b) => rank(a.item) - rank(b.item) || a.index - b.index)
		.map(({ item }) => item)
}

/**
 * A panel item as a menu item: its link or router target, its action, its
 * icon.
 *
 * @param {object} item The resolved panel item.
 * @return {object} The menu item.
 */
function destinationItem(item) {
	return {
		id: item.id,
		label: item.label,
		icon: DESTINATION_ICONS[item.id]
			? markRaw(DESTINATION_ICONS[item.id])
			: undefined,
		href: item.href,
		target: item.href ? (item.external ? '_blank' : undefined) : undefined,
		to: item.to,
		onClick: item.onClick,
	}
}

export default {
	name: 'DashboardMoreMenu',

	components: {
		CnGroupedMenu,
	},

	inject: {
		/** What `App.vue` decided about the navigation panel (see StartPageMenu). */
		launchpadChrome: {
			from: 'launchpadChrome',
			default: null,
		},

		/** The shared library's opener for the personal settings dialog. */
		cnOpenUserSettings: {
			from: 'cnOpenUserSettings',
			default: null,
		},
	},

	props: {
		/** The active dashboard, or null. */
		dashboard: {
			type: Object,
			default: null,
		},

		/** The active dashboard's section: 'user', 'group', 'default' or 'shared'. */
		source: {
			type: String,
			default: 'user',
		},

		/** The person's own dashboards. */
		userDashboards: {
			type: Array,
			default: () => [],
		},

		/** The group, default and shared dashboards. */
		groupDashboards: {
			type: Array,
			default: () => [],
		},

		/** Which dashboards came through a share, to mark them. */
		sharedDashboards: {
			type: Array,
			default: () => [],
		},

		/** The uuid of the person's default dashboard. */
		defaultUuid: {
			type: String,
			default: '',
		},

		/** Whether the person may edit the active dashboard. */
		canEdit: {
			type: Boolean,
			default: false,
		},

		/** Whether the person may share the active dashboard. */
		canShare: {
			type: Boolean,
			default: false,
		},

		/** Whether the person may publish, unpublish and schedule it. */
		canManagePublication: {
			type: Boolean,
			default: false,
		},

		/** Whether the active dashboard's version history is offered. */
		canViewHistory: {
			type: Boolean,
			default: false,
		},

		/** Whether the read receipts of the active dashboard are offered. */
		canViewReadReceipts: {
			type: Boolean,
			default: false,
		},

		/** Whether the dashboard is being edited. */
		isEditMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'switch',
		'openAll',
		'toggleEdit',
		'openConfig',
		'addCustomWidget',
		'setDefault',
		'share',
		'publish',
		'unpublish',
		'schedule',
		'versionHistory',
		'readReceipts',
		'delete',
		'menuOpen',
	],

	computed: {
		/**
		 * The chrome decision, unwrapped.
		 *
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 * @return {{ railSuppressed: boolean, entries: object[], isAdmin: boolean, includePersonalSettings: boolean }}
		 */
		chrome() {
			return readChrome(this.launchpadChrome)
		},

		/**
		 * Whether the active dashboard is the person's own (Settings and
		 * Delete are for the owner).
		 *
		 * @return {boolean}
		 */
		isOwner() {
			return isOwnedDashboard(this.dashboard, this.source)
		},

		/**
		 * The Dashboards group: the person's dashboards, then the shared and
		 * group ones, the open one checked, and "All dashboards" for the
		 * full switcher.
		 *
		 * @return {object}
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 */
		dashboardsGroup() {
			const shared = new Set(this.sharedDashboards.map((d) => d?.id))
			const rows = [
				...this.userDashboards.map((d) => ({ d, source: 'user' })),
				...this.groupDashboards.map((d) => ({
					d,
					source: shared.has(d?.id) ? 'shared' : 'group',
				})),
			].filter(({ d }) => d && d.id !== undefined && d.id !== null)
			const seen = new Set()
			const items = []
			for (const { d, source } of rows) {
				if (seen.has(d.id) || items.length >= DASHBOARD_LIMIT) {
					continue
				}
				seen.add(d.id)
				const isDefault = Boolean(d.uuid) && d.uuid === this.defaultUuid
				items.push({
					id: `dashboard-${d.id}`,
					label: d.name || t('launchpad', 'Dashboard'),
					icon: markRaw(isDefault ? Home : ViewDashboardOutline),
					meta: isDefault
						? t('launchpad', 'default')
						: this.sourceNote(source),
					checked: d.id === this.dashboard?.id,
					onClick: () => this.$emit('switch', d.id, source),
				})
			}
			items.push({
				id: 'all-dashboards',
				label: t('launchpad', 'All dashboards'),
				icon: markRaw(ViewGridOutline),
				onClick: () => this.$emit('openAll'),
			})
			return { id: 'dashboards', title: t('launchpad', 'Dashboards'), items }
		},

		/**
		 * The panel's destinations while the panel is left out; none while
		 * the panel shows them.
		 *
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 * @return {Record<string, object[]>}
		 */
		sections() {
			if (!this.chrome.railSuppressed) {
				return { main: [], footer: [], settings: [] }
			}
			return startPageSections(this.chrome, this.cnOpenUserSettings)
		},

		/**
		 * The Settings group: the personal settings and, for an
		 * administrator, the admin settings.
		 *
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 * @return {object}
		 */
		settingsGroup() {
			const items = this.sections.settings
				.filter((item) =>
					['personal-settings', 'admin-settings-link'].includes(item.id),
				)
				.map(destinationItem)
			return { id: 'settings', title: t('launchpad', 'Settings'), items }
		},

		/**
		 * The Launchpad group: the app's own destinations, without the ones
		 * another item already offers.
		 *
		 * @return {object}
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 */
		launchpadGroup() {
			const items = [
				...this.sections.main,
				...this.sections.footer,
				...this.sections.settings,
			]
				.filter((item) => !OFFERED_ELSEWHERE.includes(item.id))
				.filter(
					(item) =>
						!['personal-settings', 'admin-settings-link'].includes(
							item.id,
						),
				)
			return {
				id: 'launchpad',
				title: t('launchpad', 'Launchpad'),
				items: orderDestinations(items).map(destinationItem),
			}
		},

		/**
		 * The This dashboard group, with what the person may do with the
		 * open dashboard, the same gates as the dashboard cog menu.
		 *
		 * @return {object[]} The group and, apart, Delete.
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 */
		dashboardGroups() {
			if (!this.dashboard) {
				return []
			}
			const isDefault =
				Boolean(this.dashboard.uuid)
				&& this.dashboard.uuid === this.defaultUuid
			const published = this.dashboard.publicationStatus === 'published'
			const items = [
				this.canEdit && {
					id: 'edit',
					label: this.isEditMode
						? t('launchpad', 'Save dashboard')
						: t('launchpad', 'Edit'),
					icon: markRaw(this.isEditMode ? ContentSave : Pencil),
					event: 'toggleEdit',
				},
				this.isOwner && {
					id: 'config',
					label: t('launchpad', 'Settings'),
					icon: markRaw(Cog),
					event: 'openConfig',
				},
				this.canEdit && {
					id: 'add-widget',
					label: t('launchpad', 'Add widget'),
					icon: markRaw(Plus),
					event: 'addCustomWidget',
				},
				this.canShare && {
					id: 'share',
					label: t('launchpad', 'Share'),
					icon: markRaw(ShareVariant),
					event: 'share',
				},
				{
					id: 'set-default',
					label: isDefault
						? t('launchpad', 'Default dashboard')
						: t('launchpad', 'Set as default'),
					icon: markRaw(isDefault ? StarCheck : Star),
					event: 'setDefault',
				},
				this.canViewHistory && {
					id: 'versions',
					label: t('launchpad', 'Versions'),
					icon: markRaw(History),
					event: 'versionHistory',
				},
				this.canManagePublication && {
					id: 'schedule',
					label: t('launchpad', 'Schedule'),
					icon: markRaw(CalendarClock),
					event: 'schedule',
				},
				this.canManagePublication
					&& (published
						? {
								id: 'unpublish',
								label: t('launchpad', 'Unpublish'),
								icon: markRaw(EyeOffOutline),
								event: 'unpublish',
							}
						: {
								id: 'publish',
								label: t('launchpad', 'Publish'),
								icon: markRaw(Publish),
								event: 'publish',
							}),
				this.canViewReadReceipts && {
					id: 'read-receipts',
					label: t('launchpad', 'Read receipts'),
					icon: markRaw(CheckDecagramOutline),
					event: 'readReceipts',
				},
			]
				.filter(Boolean)
				.map((item) => this.eventItem(item))
			const groups = [
				{
					id: 'this-dashboard',
					title: t('launchpad', 'This dashboard'),
					items,
				},
			]
			if (this.isOwner) {
				groups.push({
					id: 'delete',
					divided: true,
					items: [
						this.eventItem({
							id: 'delete',
							label: t('launchpad', 'Delete'),
							icon: markRaw(TrashCanOutline),
							event: 'delete',
							destructive: true,
						}),
					],
				})
			}
			return groups
		},

		/**
		 * The menu's two columns, as the board draws them.
		 *
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 * @return {Array<Array<object>>}
		 */
		columns() {
			return [
				[this.dashboardsGroup, this.settingsGroup],
				[...this.dashboardGroups, this.launchpadGroup],
			]
		},
	},

	methods: {
		t,

		/**
		 * The note beside a dashboard that is not the person's own.
		 *
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 * @param {string} source The dashboard's section.
		 * @return {string|undefined}
		 */
		sourceNote(source) {
			if (source === 'shared') {
				return t('launchpad', 'shared')
			}
			if (source === 'group') {
				return t('launchpad', 'group')
			}
			return undefined
		},

		/**
		 * A dashboard action as a menu item that emits its event.
		 *
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-one-meer-menu-holds-the-start-pages-actions
		 * @param {object} item The action, with the event it emits.
		 * @return {object} The menu item.
		 */
		eventItem(item) {
			const { event, ...rest } = item
			return { ...rest, onClick: () => this.$emit(event) }
		},
	},
}
</script>
