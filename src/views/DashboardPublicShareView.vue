<!--
SPDX-FileCopyrightText: 2026 Conduction B.V.
SPDX-License-Identifier: EUPL-1.2

Anonymous read-only view of a shared dashboard. Delegates password-unlock
UI to PublicSharePasswordDialog. Requires no Nextcloud login.

@spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
-->
<template>
	<div class="public-share-view">
		<!-- Password unlock dialog (extracted per ADR-004) -->
		<PublicSharePasswordDialog v-if="showPasswordModal"
			:error="unlockError"
			:loading="unlocking"
			@unlock="submitPassword" />

		<!-- Loading state -->
		<div v-else-if="loading" class="public-share-view__loading">
			<NcLoadingIcon :size="48" />
		</div>

		<!-- Error state -->
		<div v-else-if="error" class="public-share-view__error">
			<NcEmptyContent :name="errorTitle" :description="errorDescription">
				<template #icon>
					<AlertCircleIcon />
				</template>
			</NcEmptyContent>
		</div>

		<!-- Dashboard render (read-only) -->
		<div v-else-if="dashboard" class="public-share-view__content">
			<header class="public-share-view__header">
				<h1>{{ dashboard.title }}</h1>
				<span class="public-share-view__badge">{{ t('mydash', 'Read-only view') }}</span>
			</header>
			<p v-if="dashboard.description" class="public-share-view__description">
				{{ dashboard.description }}
			</p>
			<div class="public-share-view__widgets">
				<p class="public-share-view__widgets-note">
					{{ t('mydash', 'Dashboard loaded successfully.') }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import { defineComponent, ref, onMounted } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import PublicSharePasswordDialog from '../dialogs/PublicSharePasswordDialog.vue'
import { usePublicShareStore } from '../stores/publicShares.js'

const baseUrl = generateUrl('/apps/mydash')

export default defineComponent({
	name: 'DashboardPublicShareView',

	components: {
		AlertCircleIcon,
		NcEmptyContent,
		NcLoadingIcon,
		PublicSharePasswordDialog,
	},

	props: {
		/** Share token from the URL path parameter. */
		token: {
			type: String,
			required: true,
		},
	},

	setup(props) {
		const shareStore = usePublicShareStore()

		const loading = ref(false)
		const dashboard = ref(null)
		const share = ref(null)
		const error = ref(null)
		const showPasswordModal = ref(false)
		const unlocking = ref(false)
		const unlockError = ref(null)

		const errorTitle = ref(t('mydash', 'Not available'))
		const errorDescription = ref(t('mydash', 'This shared dashboard is not available.'))

		const loadShare = async (password = null) => {
			loading.value = true
			error.value = null

			try {
				const headers = {}
				if (password) headers['X-Share-Password'] = password

				const { data } = await axios.get(
					`${baseUrl}/s/${encodeURIComponent(props.token)}`,
					{ headers }
				)
				dashboard.value = data.dashboard
				share.value = data.share
				showPasswordModal.value = false
			} catch (err) {
				if (err.response?.status === 401 && err.response?.data?.passwordRequired) {
					if (shareStore.isUnlocked(props.token)) {
						shareStore.clearUnlocked(props.token)
					}
					showPasswordModal.value = true
				} else if (err.response?.status === 404) {
					error.value = 'not_found'
					errorTitle.value = t('mydash', 'Not found')
					errorDescription.value = t('mydash', 'This shared dashboard does not exist or has expired.')
				} else {
					error.value = 'server_error'
					errorTitle.value = t('mydash', 'Error')
					errorDescription.value = t('mydash', 'An error occurred loading the shared dashboard.')
				}
			} finally {
				loading.value = false
			}
		}

		const submitPassword = async (password) => {
			if (unlocking.value === true) return
			unlockError.value = null
			unlocking.value = true

			try {
				const { data } = await axios.post(
					`${baseUrl}/s/${encodeURIComponent(props.token)}/unlock`,
					{ password }
				)

				if (data.access === true) {
					shareStore.markUnlocked(props.token)
					await loadShare(password)
				} else {
					unlockError.value = t('mydash', 'Incorrect password. Please try again.')
				}
			} catch (err) {
				if (err.response?.status === 429) {
					unlockError.value = t('mydash', 'unlock_throttled')
				} else {
					unlockError.value = t('mydash', 'Failed to verify password.')
				}
			} finally {
				unlocking.value = false
			}
		}

		onMounted(async () => {
			await loadShare()
		})

		return {
			loading,
			dashboard,
			share,
			error,
			errorTitle,
			errorDescription,
			showPasswordModal,
			unlocking,
			unlockError,
			submitPassword,
			t,
		}
	},
})
</script>

<style scoped>
.public-share-view {
	min-height: 100vh;
	padding: 1.5rem;
}

.public-share-view__loading {
	display: flex;
	justify-content: center;
	padding-top: 4rem;
}

.public-share-view__header {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	margin-bottom: 0.5rem;
}

.public-share-view__badge {
	background: var(--color-primary-light);
	color: var(--color-primary-text);
	border-radius: 1rem;
	padding: 0.2em 0.7em;
	font-size: 0.8em;
}

.public-share-view__description {
	color: var(--color-text-lighter);
	margin-bottom: 1.5rem;
}

.public-share-view__widgets-note {
	color: var(--color-text-lighter);
	font-style: italic;
}
</style>
