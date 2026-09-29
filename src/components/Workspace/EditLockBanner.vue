<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="message"
		class="launchpad-edit-lock-banner"
		role="status"
		data-testid="edit-lock-banner">
		<NcNoteCard :type="status === 'blocked' ? 'info' : 'warning'">
			<p>{{ message }}</p>
			<p v-if="freesUp">
				{{ freesUp }}
			</p>
			<NcButton
				v-if="isAdmin && status === 'blocked'"
				variant="secondary"
				data-testid="edit-lock-take-over"
				@click="$emit('takeOver')">
				{{ t('launchpad', 'Take over') }}
			</NcButton>
		</NcNoteCard>
	</div>
</template>

<script>
import { NcButton, NcNoteCard } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * EditLockBanner: what the workspace page says when it cannot let the
 * person edit, because a colleague holds the lock, the lock was lost, or
 * the server refused. Administrators get "Take over" on a colleague's lock.
 *
 * @spec openspec/specs/dashboard-locking/spec.md
 */
export default {
	name: 'EditLockBanner',

	components: {
		NcButton,
		NcNoteCard,
	},

	props: {
		/** Lock status from `useDashboardLock`. */
		status: {
			type: String,
			required: true,
		},

		holderName: {
			type: String,
			default: '',
		},

		/** Seconds until the colleague's lock ends if they stop refreshing it. */
		expiresIn: {
			type: Number,
			default: null,
		},

		isAdmin: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['takeOver'],

	computed: {
		/**
		 * @return {string} The banner text, empty when nothing is to be said.
		 * @spec openspec/specs/dashboard-locking/spec.md
		 */
		message() {
			switch (this.status) {
				case 'blocked':
					return t(
						'launchpad',
						'{name} is editing this dashboard. You can edit it once they are done.',
						{ name: this.holderName || t('launchpad', 'A colleague') },
					)
				case 'lost':
					return t(
						'launchpad',
						'Someone else is editing this dashboard now, so you are back in view mode.',
					)
				case 'forbidden':
					return t('launchpad', 'You cannot edit this dashboard.')
				case 'unavailable':
					return t(
						'launchpad',
						'The dashboard could not be opened for editing. Try again in a moment.',
					)
				default:
					return ''
			}
		},

		/**
		 * @return {string} When the colleague's lock ends, if known.
		 * @spec openspec/specs/dashboard-locking/spec.md
		 */
		freesUp() {
			if (
				this.status !== 'blocked'
				|| typeof this.expiresIn !== 'number'
				|| this.expiresIn <= 0
			) {
				return ''
			}
			const minutes = Math.ceil(this.expiresIn / 60)
			return t(
				'launchpad',
				'If they stop, the dashboard frees up within {minutes} minutes.',
				{ minutes },
			)
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.launchpad-edit-lock-banner {
	margin: 0 8px 8px;
}
</style>
