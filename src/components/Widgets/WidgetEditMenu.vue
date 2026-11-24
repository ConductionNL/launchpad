<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	WidgetEditMenu — the three-dot menu a widget shows in edit mode (board
	LpBewerken): Edit widget, "Who sees this widget?" and Delete widget.
	The same look and test ids as nc-vue's CnWidgetEditCog, which offers no
	room for a third item; "Who sees this widget?" is here so the rules
	dialog is reachable by keyboard and not only by right-click
	(conditional-visibility-editor REQ-CVUI-007).
-->

<template>
	<NcActions
		:aria-label="menuLabel"
		:forceMenu="true"
		placement="bottom-end"
		variant="tertiary"
		class="widget-edit-menu"
		data-testid="cn-widget-edit-cog"
		@click.stop>
		<template #icon>
			<DotsVertical :size="20" />
		</template>
		<NcActionButton
			:closeAfterClick="true"
			data-testid="cn-widget-edit-cog-edit"
			@click="$emit('edit')">
			<template #icon>
				<Pencil :size="20" />
			</template>
			{{ t('launchpad', 'Edit widget') }}
		</NcActionButton>
		<NcActionButton
			:closeAfterClick="true"
			data-testid="widget-who-sees"
			@click="$emit('whoSees')">
			<template #icon>
				<AccountEye :size="20" />
			</template>
			{{ t('launchpad', 'Who sees this widget?') }}
		</NcActionButton>
		<NcActionButton
			v-if="!removeLocked"
			:closeAfterClick="true"
			data-testid="cn-widget-edit-cog-delete"
			@click="$emit('remove')">
			<template #icon>
				<Delete :size="20" />
			</template>
			{{ t('launchpad', 'Delete widget') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { NcActionButton, NcActions } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import AccountEye from 'vue-material-design-icons/AccountEye.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import DotsVertical from 'vue-material-design-icons/DotsVertical.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'WidgetEditMenu',

	components: {
		AccountEye,
		Delete,
		DotsVertical,
		NcActionButton,
		NcActions,
		Pencil,
	},

	props: {
		/** Accessible name of the menu. */
		menuLabel: {
			type: String,
			default: 'Widget menu',
		},

		/** Leaves Delete out, for a widget the user may not remove. */
		removeLocked: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['edit', 'whoSees', 'remove'],

	methods: {
		t,
	},
}
</script>

<style scoped>
/* The white rounded button of the widget chrome, as CnWidgetEditCog draws it,
   so the menu reads on any widget background. */
.widget-edit-menu :deep(.action-item__menutoggle),
.widget-edit-menu :deep(.button-vue) {
	background-color: var(--color-main-background) !important;
	border: 1px solid var(--color-border) !important;
	border-radius: var(--border-radius, 8px) !important;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
	color: var(--color-main-text) !important;
}

.widget-edit-menu :deep(.action-item__menutoggle:hover),
.widget-edit-menu :deep(.button-vue:hover) {
	background-color: var(--color-background-hover) !important;
}
</style>
