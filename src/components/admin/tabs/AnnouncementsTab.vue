<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcements-tab" data-testid="announcements-tab">
		<h3>{{ t('launchpad', 'Announcement editors') }}</h3>
		<p class="announcements-tab__intro">
			{{
				t(
					'launchpad',
					'Members of these groups write announcements and notices. Administrators always can.',
				)
			}}
		</p>

		<p v-if="loading">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<form v-else class="announcements-tab__form" @submit.prevent="save">
			<NcSelect
				v-model="groups"
				multiple
				taggable
				:options="groups"
				:inputLabel="t('launchpad', 'Group IDs')" />
			<p v-if="error" class="announcements-tab__error" role="alert">
				{{ error }}
			</p>
			<p v-if="saved" role="status">
				{{ t('launchpad', 'Announcement editors saved.') }}
			</p>
			<NcButton type="submit" variant="primary" :disabled="saving">
				{{ t('launchpad', 'Save announcement editors') }}
			</NcButton>
		</form>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcSelect } from '@nextcloud/vue'
import {
	getAnnouncementEditorGroups,
	saveAnnouncementEditorGroups,
} from '../../../services/announcements.js'

/**
 * AnnouncementsTab: which groups may write announcements besides the
 * administrators (engagement-announcements D6).
 */
export default {
	name: 'AnnouncementsTab',

	components: { NcButton, NcSelect },

	data() {
		return {
			loading: true,
			saving: false,
			saved: false,
			error: '',
			groups: [],
		}
	},

	/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
	async mounted() {
		try {
			this.groups = await getAnnouncementEditorGroups()
		} catch {
			this.error = t(
				'launchpad',
				'The announcement editors could not be loaded.',
			)
		} finally {
			this.loading = false
		}
	},

	methods: {
		t,

		/**
		 * Store the editor groups; an unknown group is refused by the server.
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
		 */
		async save() {
			this.saving = true
			this.saved = false
			this.error = ''
			try {
				this.groups = await saveAnnouncementEditorGroups(this.groups)
				this.saved = true
			} catch (error) {
				this.error =
					error?.response?.data?.error
					|| t('launchpad', 'The announcement editors could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.announcements-tab__intro {
	color: var(--color-text-maxcontrast);
}

.announcements-tab__form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 480px;
}

.announcements-tab__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
