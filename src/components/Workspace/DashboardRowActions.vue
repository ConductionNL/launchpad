<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	DashboardRowActions — wave3.6 per-row cog menu

	Lives at the right edge of every dashboard `<li>` in the sidebar.
	The 4 actions (Edit / Configure / Add widget / Delete) act on
	the row's own dashboard, not on whichever dashboard happens to be
	"active" — so the user can edit, configure, or delete any dashboard
	they have access to without having to switch first.

	Action gating:
	  - Edit dashboard         → only when `canEdit` is true (parent
	    flag derived from the active dashboard's permission level; for
	    other rows it remains advisory — clicking still emits the
	    event so the host can decide whether to switch + enter edit
	    mode or no-op).
	  - Dashboard configuration → only when the row's `dashboard.isOwner !== false`.
	  - Add widget              → only when `canEdit` is true.
	  - Delete dashboard        → only when the row's `dashboard.isOwner !== false`.

	Emits raw events; the host (`DashboardSwitcherSidebar.vue`) maps
	them to its `toggle-edit` / `open-config` / `add-widget` /
	`delete-dashboard` outputs with the per-row dashboard payload.

	`@click.stop` is set on the cog trigger so opening the menu never
	fires the row's `@click` switch handler.
-->

<template>
	<NcActions
		:aria-label="t('launchpad', 'Dashboard menu')"
		:forceMenu="true"
		placement="bottom-end"
		:type="buttonType"
		class="dashboard-row-actions"
		@click.stop
		@open="$emit('menuOpen')">
		<template #icon>
			<Cog :size="iconSize" />
		</template>
		<NcActionButton
			v-if="canEdit"
			:closeAfterClick="true"
			data-testid="cog-edit-dashboard"
			@click="$emit('toggleEdit')">
			<template #icon>
				<ContentSave v-if="showSave" :size="20" />
				<Pencil v-else :size="20" />
			</template>
			{{
				showSave
					? t('launchpad', 'Save dashboard')
					: t('launchpad', 'Edit dashboard')
			}}
		</NcActionButton>
		<NcActionButton
			v-if="isOwner"
			:closeAfterClick="true"
			data-testid="cog-dashboard-config"
			@click="$emit('openConfig')">
			<template #icon>
				<Tune :size="20" />
			</template>
			{{ t('launchpad', 'Dashboard configuration…') }}
		</NcActionButton>
		<NcActionButton
			v-if="canEdit"
			:closeAfterClick="true"
			data-testid="cog-add-widget"
			@click="$emit('addWidget')">
			<template #icon>
				<ShapePolygonPlus :size="20" />
			</template>
			{{ t('launchpad', 'Add widget') }}
		</NcActionButton>
		<NcActionButton
			v-if="canEdit"
			:closeAfterClick="true"
			data-testid="cog-import-bookmarks"
			@click="$emit('importBookmarks')">
			<template #icon>
				<BookmarkPlusOutline :size="20" />
			</template>
			{{ t('launchpad', 'Import bookmarks') }}
		</NcActionButton>
		<NcActionButton
			:closeAfterClick="true"
			data-testid="cog-set-default"
			@click="$emit('setDefault')">
			<template #icon>
				<StarCheck v-if="isDefault" :size="20" />
				<Star v-else :size="20" />
			</template>
			{{
				isDefault
					? t('launchpad', 'Default dashboard')
					: t('launchpad', 'Set as default')
			}}
		</NcActionButton>
		<NcActionButton
			v-if="canShare"
			:closeAfterClick="true"
			data-testid="cog-share"
			@click="$emit('share')">
			<template #icon>
				<ShareVariant :size="20" />
			</template>
			{{ t('launchpad', 'Share') }}
		</NcActionButton>
		<NcActionButton
			v-if="canEmbed && dashboard.uuid"
			:closeAfterClick="true"
			data-testid="cog-embed"
			@click="openEmbed">
			<template #icon>
				<CodeTags :size="20" />
			</template>
			{{ t('launchpad', 'Create embed token') }}
		</NcActionButton>
		<NcActionButton
			v-if="isOwner"
			:closeAfterClick="true"
			data-testid="cog-delete"
			@click="$emit('delete')">
			<template #icon>
				<TrashCanOutline :size="20" />
			</template>
			{{ t('launchpad', 'Delete dashboard') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcActionButton, NcActions } from '@nextcloud/vue'
import BookmarkPlusOutline from 'vue-material-design-icons/BookmarkPlusOutline.vue'
import CodeTags from 'vue-material-design-icons/CodeTags.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import ShapePolygonPlus from 'vue-material-design-icons/ShapePolygonPlus.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarCheck from 'vue-material-design-icons/StarCheck.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import {
	FLAG_PROP,
	REQUIRED_OBJECT_PROP,
	TEXT_PROP,
} from '../../constants/propDefinitions.js'
import { adminPageUrl } from '../../utils/adminPage.js'
import { embedAdminSearch } from '../../utils/embedToken.js'

/**
 * The address of Beheer ▸ Embeds with a dashboard pre-selected, or one
 * widget placement of it when `placement` is given.
 *
 * @param {object|null} dashboard The dashboard.
 * @param {object|null} placement A widget placement on the dashboard.
 * @return {string}
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
export function embedAdminHref(dashboard, placement = null) {
	const search = placement
		? embedAdminSearch('widget', placement.id ?? '', dashboard?.id ?? null)
		: embedAdminSearch('dashboard', dashboard?.uuid ?? '', dashboard?.id ?? null)
	return `${adminPageUrl('embeds')}?${search}`
}

/**
 * Whether the caller owns a dashboard: a row under "My dashboards" (the
 * `user` source) always, any other row only when the dashboard says
 * `isOwner: true`. Owner-gated actions (configure, delete) use it.
 *
 * @param {object|null} dashboard The dashboard.
 * @param {string} source The dashboard's section.
 * @return {boolean}
 * @spec openspec/specs/dashboard-switcher/spec.md
 */
export function isOwnedDashboard(dashboard, source) {
	if (source === 'user') {
		return true
	}
	return dashboard?.isOwner === true
}

export default {
	name: 'DashboardRowActions',

	components: {
		NcActions,
		NcActionButton,
		CodeTags,
		Cog,
		Pencil,
		ContentSave,
		Tune,
		ShapePolygonPlus,
		BookmarkPlusOutline,
		TrashCanOutline,
		Star,
		StarCheck,
		ShareVariant,
	},

	props: {
		dashboard: REQUIRED_OBJECT_PROP,

		source: {
			type: String,
			required: true,
			validator: (v) => ['group', 'default', 'user', 'shared'].includes(v),
		},

		canEdit: FLAG_PROP,

		/*
		 * Wave3.7 — UUID of the user's pinned default dashboard, or
		 * empty/null when no pin is set. The host fetches it once on
		 * mount via `GET /api/dashboards/default` and forwards it here
		 * so the cog menu can render the "Set as default" entry as
		 * either a star (this row is NOT default) or a filled star
		 * (this row IS the default).
		 */
		defaultUuid: TEXT_PROP,

		/*
		 * Wave3.9 — when this row matches the currently active
		 * dashboard AND the host is in edit mode, the cog's
		 * Edit / Save toggle button flips to "Save dashboard" with
		 * the ContentSave icon. The host owns the edit-mode boolean
		 * (lives on Views.vue's `isEditMode` data field) and the
		 * active id (`activeDashboard.id`) and forwards both down.
		 */
		isEditMode: FLAG_PROP,

		activeDashboardId: {
			type: [String, Number],
			default: null,
		},

		/*
		 * When true the menu renders a "Share" entry that emits `share`
		 * so the host can open the sharing drawer. Off by default — the
		 * sidebar rows don't expose sharing; the top-right active-dashboard
		 * cog passes the host's `canShareActiveDashboard` gate.
		 */
		canShare: FLAG_PROP,

		/*
		 * embedded-analytics task 40: the viewer is an administrator, so the
		 * menu offers "Create embed token", a link to Beheer ▸ Embeds with
		 * this dashboard pre-selected. Off by default.
		 */
		canEmbed: FLAG_PROP,

		/*
		 * NcActions toggle button style. Defaults to the subtle
		 * `tertiary-no-background` used at the edge of each sidebar row;
		 * the top-right active cog passes `secondary` so it matches the
		 * adjacent dashboards (hamburger) button.
		 */
		buttonType: {
			type: String,
			default: 'tertiary-no-background',
		},

		/*
		 * Cog icon size. 18 fits the sidebar rows; the top-right cluster
		 * passes 20 to line up with the 20px hamburger icon.
		 */
		iconSize: {
			type: Number,
			default: 18,
		},
	},

	emits: [
		'toggleEdit',
		'openConfig',
		'addWidget',
		// launcher-bookmark-import REQ-BMI-001: only where the user may add.
		'importBookmarks',
		'delete',
		'setDefault',
		'share',
		'menuOpen',
	],

	computed: {
		/**
		 * Beheer ▸ Embeds with this dashboard pre-selected for a new token.
		 *
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
		 */
		embedHref() {
			return embedAdminHref(this.dashboard)
		},

		/*
		 * Owner-gated actions (Configure, Delete) require the user to
		 * own this dashboard. The visibility API doesn't always set
		 * `isOwner` on every row, so we infer ownership from the
		 * `source` discriminator: rows under "MY DASHBOARDS" (user
		 * source) are inherently owned by the caller; group / default
		 * rows are owned by whoever created them, so we honour the
		 * explicit `dashboard.isOwner` flag when present and otherwise
		 * conservatively hide the destructive actions.
		 * @spec openspec/specs/dashboard-switcher/spec.md
		 */
		isOwner() {
			return isOwnedDashboard(this.dashboard, this.source)
		},

		/*
		 * Wave3.7 — true when THIS row's dashboard is the user's
		 * pinned default. Drives the cog entry's icon (star vs
		 * StarCheck) and label ("Set as default" vs "Default
		 * dashboard").
		 * @spec openspec/specs/dashboard-switcher/spec.md
		 */
		isDefault() {
			const uuid = this.dashboard?.uuid
			return !!uuid && uuid === this.defaultUuid
		},

		/*
		 * Wave3.9 — true when the cog button should show the Save
		 * variant (icon + label). Only true on the row whose
		 * dashboard is currently active AND the host is in edit
		 * mode. Other rows keep showing "Edit dashboard" so the
		 * user can still enter edit mode on them by switching first
		 * (the host's `maybeSwitchTo` helper handles that).
		 * @spec openspec/specs/dashboard-switcher/spec.md
		 */
		showSave() {
			return (
				this.isEditMode
				&& this.activeDashboardId !== null
				&& this.activeDashboardId !== undefined
				&& this.dashboard?.id === this.activeDashboardId
			)
		},
	},

	methods: {
		t,

		/**
		 * Open Beheer ▸ Embeds with this dashboard pre-selected.
		 *
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
		 */
		openEmbed() {
			window.location.assign(this.embedHref)
		},
	},
}
</script>

<style scoped>
.dashboard-row-actions {
	margin-inline-start: auto;
	flex: 0 0 auto;
}
</style>
