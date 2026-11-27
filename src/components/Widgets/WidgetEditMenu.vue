<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	WidgetEditMenu — the three-dot menu a widget shows in edit mode (board
	LpBewerken): Edit widget, "Who sees this widget?", Duplicate, Create embed
	token and Delete widget. Tiles use it too, with their own labels. Create embed token
	is an edit-mode action only (REQ-WDG-050, review 27 Nov item 21).
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
			{{ editLabel || t('launchpad', 'Edit widget') }}
		</NcActionButton>
		<NcActionButton
			v-if="showWhoSees"
			:closeAfterClick="true"
			data-testid="widget-who-sees"
			@click="$emit('whoSees')">
			<template #icon>
				<AccountEye :size="20" />
			</template>
			{{ t('launchpad', 'Who sees this widget?') }}
		</NcActionButton>
		<!-- REQ-WDG-050: Duplicate places a copy below the other widgets. -->
		<NcActionButton
			:closeAfterClick="true"
			data-testid="widget-duplicate"
			@click="$emit('duplicate')">
			<template #icon>
				<ContentCopy :size="20" />
			</template>
			{{ t('launchpad', 'Duplicate') }}
		</NcActionButton>
		<!-- REQ-WDG-050: only for people who may create embed tokens (the host
		     passes a link), disabled for a widget that is not saved yet. With
		     unsaved changes it is a button: the host asks to save first. -->
		<NcActionButton
			v-if="embedHref !== '' && !embedDisabled && embedAsksFirst"
			:closeAfterClick="true"
			data-testid="widget-create-embed-token"
			@click="$emit('embed', embedHref)">
			<template #icon>
				<CodeTags :size="20" />
			</template>
			{{ t('launchpad', 'Create embed token') }}
		</NcActionButton>
		<NcActionLink
			v-else-if="embedHref !== '' && !embedDisabled"
			:href="embedHref"
			data-testid="widget-create-embed-token">
			<template #icon>
				<CodeTags :size="20" />
			</template>
			{{ t('launchpad', 'Create embed token') }}
		</NcActionLink>
		<NcActionButton
			v-else-if="embedHref !== ''"
			:disabled="true"
			data-testid="widget-create-embed-token">
			<template #icon>
				<CodeTags :size="20" />
			</template>
			{{ t('launchpad', 'Create embed token') }}
		</NcActionButton>
		<NcActionButton
			v-if="!removeLocked"
			:closeAfterClick="true"
			data-testid="cn-widget-edit-cog-delete"
			@click="$emit('remove')">
			<template #icon>
				<Delete :size="20" />
			</template>
			{{ deleteLabel || t('launchpad', 'Delete widget') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { NcActionButton, NcActions } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { NcActionLink } from '@nextcloud/vue'
import AccountEye from 'vue-material-design-icons/AccountEye.vue'
import CodeTags from 'vue-material-design-icons/CodeTags.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import DotsVertical from 'vue-material-design-icons/DotsVertical.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'

export default {
	name: 'WidgetEditMenu',

	components: {
		AccountEye,
		CodeTags,
		ContentCopy,
		Delete,
		DotsVertical,
		NcActionButton,
		NcActionLink,
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

		/** Label of the edit item; '' keeps "Edit widget". */
		editLabel: {
			type: String,
			default: '',
		},

		/** Label of the delete item; '' keeps "Delete widget". */
		deleteLabel: {
			type: String,
			default: '',
		},

		/** Offers "Who sees this widget?" (REQ-CVUI-007). */
		showWhoSees: {
			type: Boolean,
			default: true,
		},

		/**
		 * The admin link that creates an embed token for this widget
		 * (REQ-WDG-050); '' when the person may not create one.
		 */
		embedHref: {
			type: String,
			default: '',
		},

		/** Shows Create embed token disabled, for a widget not saved yet. */
		embedDisabled: {
			type: Boolean,
			default: false,
		},

		/**
		 * The draft has unsaved changes: Create embed token emits `embed`
		 * with its link, so the host asks to save first (REQ-EDIT-003).
		 */
		embedAsksFirst: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['edit', 'whoSees', 'duplicate', 'embed', 'remove'],

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
