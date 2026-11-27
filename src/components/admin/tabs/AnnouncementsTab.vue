<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcements-tab" data-testid="announcements-tab">
		<CnSettingsSection
			:name="t('launchpad', 'Announcements')"
			:description="t('launchpad', 'News and maintenance notices for the people in your organisation.')"
			data-testid="admin-announcements">
			<slot>
				<p class="announcements-tab__intro">
					{{ t('launchpad', 'Announcements are managed on this tab. People who manage announcements without being an administrator get this tab through Nextcloud\'s administration privileges.') }}
				</p>
			</slot>
		</CnSettingsSection>
	</div>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'

/**
 * The Announcements tab of the admin settings page (board LpAankondiging):
 * its place in the page and the tab a delegated announcements manager holds
 * (admin-settings REQ-ADM-034). What it manages is change
 * `announcements-managed-in-settings`.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
 */
export default {
	name: 'AnnouncementsTab',

	components: {
		CnSettingsSection,
	},
}
</script>

<style scoped>
.announcements-tab__intro {
	color: var(--color-text-maxcontrast);
}
</style>
