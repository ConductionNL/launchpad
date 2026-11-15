<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcement-editors" data-test="announcement-editors-policy">
		<h3>{{ t('launchpad', 'Announcement editors') }}</h3>
		<p class="announcement-editors__hint">
			{{ t('launchpad', 'Members of these groups may write and publish announcements. Administrators always may.') }}
		</p>
		<NcSelectTags
			v-model="editorGroups"
			:options="groups"
			:multiple="true"
			:aria-label-combobox="t('launchpad', 'Announcement editor groups')"
			:placeholder="t('launchpad', 'Select groups (leave empty for administrators only)')"
			data-test="announcement-editor-groups"
			@update:modelValue="save" />
	</div>
</template>

<script>
import { NcSelectTags } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * AnnouncementEditorsPolicy — Beheer ▸ Sharing: the groups whose members
 * may write announcements besides administrators (engagement-announcements
 * D6). Reads and writes `announcementEditorGroups` through the admin
 * settings API.
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-2
 */
export default {
	name: 'AnnouncementEditorsPolicy',

	components: {
		NcSelectTags,
	},

	props: {
		/** Group ids offered in the picker. */
		groups: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			editorGroups: [],
		}
	},

	/** @spec openspec/changes/engagement-announcements/tasks.md#task-2 */
	created() {
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/changes/engagement-announcements/tasks.md#task-2 */
		async load() {
			try {
				const { data } = await api.getAdminSettings()
				const settings = data?.data ?? data ?? {}
				this.editorGroups = Array.isArray(settings.announcementEditorGroups)
					? settings.announcementEditorGroups
					: []
			} catch (error) {
				logger.error('Failed to load announcement editors:', error)
			}
		},

		/** @spec openspec/changes/engagement-announcements/tasks.md#task-2 */
		async save() {
			try {
				await api.updateAdminSettings({ announcementEditorGroups: this.editorGroups })
			} catch (error) {
				logger.error('Failed to save announcement editors:', error)
			}
		},
	},
}
</script>

<style scoped>
.announcement-editors__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
</style>
