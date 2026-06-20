<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	WidgetEditCog — the single per-widget edit affordance shown in edit mode.

	A white, rounded cog button (matching the OpenBuild / nc-vue widget chrome)
	that opens a small action menu with Edit + Delete. Reused by every dashboard
	surface — data widgets, NC dashboard widgets, chrome-less label/divider/header
	widgets, and quick-access tiles — so they all share one consistent control
	instead of each rendering its own bespoke cog.

	It is positioned by its host (absolute overlay top-right) and emits raw
	`edit` / `remove` events; the host decides what they act on.
-->

<template>
	<NcActions
		:aria-label="menuLabel"
		:force-menu="true"
		placement="bottom-end"
		type="tertiary"
		class="widget-edit-cog"
		data-testid="widget-edit-cog"
		@click.native.stop>
		<template #icon>
			<Cog :size="20" />
		</template>
		<NcActionButton
			:close-after-click="true"
			data-testid="widget-edit-cog-edit"
			@click="$emit('edit')">
			<template #icon><Pencil :size="20" /></template>
			{{ editLabel }}
		</NcActionButton>
		<NcActionButton
			:close-after-click="true"
			data-testid="widget-edit-cog-delete"
			@click="$emit('remove')">
			<template #icon><Delete :size="20" /></template>
			{{ deleteLabel }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcActions, NcActionButton } from '@nextcloud/vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'WidgetEditCog',

	components: {
		NcActions,
		NcActionButton,
		Cog,
		Pencil,
		Delete,
	},

	props: {
		/** Accessible name for the cog trigger / menu. */
		menuLabel: {
			type: String,
			default: () => t('launchpad', 'Widget menu'),
		},
		/** Label for the Edit action. */
		editLabel: {
			type: String,
			default: () => t('launchpad', 'Edit widget'),
		},
		/** Label for the Delete action. */
		deleteLabel: {
			type: String,
			default: () => t('launchpad', 'Delete widget'),
		},
	},

	emits: ['edit', 'remove'],
}
</script>

<style scoped>
/* White rounded button matching the OpenBuild / nc-vue widget chrome — a
   persistent surface (not the default transparent tertiary) so the cog reads
   on any widget background. The trigger element is NcActions' menu toggle. */
.widget-edit-cog :deep(.action-item__menutoggle),
.widget-edit-cog :deep(.button-vue) {
	background-color: var(--color-main-background) !important;
	border: 1px solid var(--color-border) !important;
	border-radius: var(--border-radius, 8px) !important;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
	color: var(--color-main-text) !important;
}

.widget-edit-cog :deep(.action-item__menutoggle:hover),
.widget-edit-cog :deep(.button-vue:hover) {
	background-color: var(--color-background-hover) !important;
}
</style>
