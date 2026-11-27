<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-admin__section announcement-managers" data-testid="admin-announcement-managers">
		<h3 class="announcement-managers__label">
			{{ t('launchpad', 'Manage announcements') }}
		</h3>
		<p class="launchpad-admin__hint">
			{{ t('launchpad', 'Administrators and the groups below manage announcements. Who may is set in Nextcloud\'s Administration privileges.') }}
		</p>
		<p v-if="loading" class="launchpad-admin__hint">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<p v-else-if="!available" class="launchpad-admin__hint" data-testid="announcement-managers-unavailable">
			{{ t('launchpad', 'The groups cannot be read here; see Administration privileges.') }}
		</p>
		<ul v-else-if="groups.length > 0" class="announcement-managers__groups" data-testid="announcement-managers-groups">
			<li v-for="group in groups" :key="group.id" class="announcement-managers__group">
				{{ group.displayName }}
			</li>
		</ul>
		<p v-else class="launchpad-admin__hint" data-testid="announcement-managers-none">
			{{ t('launchpad', 'No group yet: only administrators manage announcements.') }}
		</p>
		<NcButton variant="secondary" :href="privilegesUrl" data-testid="announcement-managers-link">
			{{ t('launchpad', 'Open Administration privileges') }}
		</NcButton>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { NcButton } from '@nextcloud/vue'
import { announcementsApi } from '../../services/announcementsApi.js'
import { logger } from '../../utils/logger.js'

/**
 * AnnouncementManagersRow — the "Manage announcements" row of the Roles tab
 * (board LpBeheerRollen, announcements-managed-in-settings D1): read-only,
 * the groups that hold Launchpad's announcements setting through Nextcloud's
 * admin delegation, with a link to Administration privileges, where an
 * administrator changes them. Delegation is the one authority.
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
 */
export default {
	name: 'AnnouncementManagersRow',

	components: { NcButton },

	data() {
		return {
			loading: true,
			available: true,
			groups: [],
		}
	},

	computed: {
		/**
		 * @return {string} Nextcloud's Administration privileges page.
		 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
		 */
		privilegesUrl() {
			return generateUrl('/settings/admin/admindelegation')
		},
	},

	/** @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3 */
	created() {
		this.load()
	},

	methods: {
		/**
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
		 */
		async load() {
			try {
				const data = await announcementsApi.delegation()
				this.available = data?.available !== false
				this.groups = Array.isArray(data?.groups) ? data.groups : []
			} catch (error) {
				logger.error('Loading who manages announcements failed:', error)
				this.available = false
				this.groups = []
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.announcement-managers__label {
	margin: 0 0 4px;
	font-size: 16px;
}

.announcement-managers__groups {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	padding: 0;
	margin: 8px 0 12px;
	list-style: none;
}

.announcement-managers__group {
	padding: 2px 10px;
	border-radius: var(--border-radius-pill);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}
</style>
