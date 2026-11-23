<!--
SPDX-FileCopyrightText: 2026 Conduction B.V.
SPDX-License-Identifier: EUPL-1.2

Anonymous read-only view of a shared dashboard. Delegates password-unlock
UI to PublicSharePasswordDialog. Requires no Nextcloud login. As the public
start page (`startPage`) it carries a bar with the organisation's name and a
"Sign in" button to the login form with `direct=1`
(sharing-public-start-page REQ-PSP-003).

@spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
-->
<template>
	<div class="public-share-view" :class="{ 'public-share-view--kiosk': kiosk }">
		<!-- The way in for staff on the public start page (REQ-PSP-003) -->
		<nav
			v-if="startPage"
			class="public-share-view__start-bar"
			:aria-label="t('launchpad', 'Sign in')">
			<img
				v-if="emblem"
				class="public-share-view__emblem"
				:src="emblem"
				:alt="organisationName" />
			<span class="public-share-view__brand">
				<span class="public-share-view__organisation">{{ organisationName }}</span>
				<strong v-if="dashboard" class="public-share-view__brand-name">
					{{ dashboard.name || dashboard.title }}
				</strong>
			</span>
			<a class="button primary public-share-view__sign-in" :href="signInHref">
				<LoginIcon :size="18" />
				{{ t('launchpad', 'Sign in for employees') }}
			</a>
		</nav>

		<!-- Password unlock dialog (extracted per ADR-004) -->
		<PublicSharePasswordDialog
			v-if="showPasswordModal"
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
				<h1>{{ dashboard.name || dashboard.title }}</h1>
				<p class="public-share-view__meta">
					<span class="public-share-view__badge">
						<EyeOutlineIcon :size="16" />
						{{ t('launchpad', 'Read-only view') }}
					</span>
					<span v-if="dashboard.description" class="public-share-view__description">
						{{ dashboard.description }}
					</span>
				</p>
			</header>

			<PublicDashboardGrid :placements="placements" />
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineComponent, onMounted, ref } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import EyeOutlineIcon from 'vue-material-design-icons/EyeOutline.vue'
import LoginIcon from 'vue-material-design-icons/Login.vue'
import PublicDashboardGrid from '../components/PublicDashboardGrid.vue'
import PublicSharePasswordDialog from '../dialogs/PublicSharePasswordDialog.vue'
import { usePublicShareStore } from '../stores/publicShares.js'
import { isKioskMode } from '../utils/kioskMode.js'
import { signInUrl } from '../utils/publicStart.js'

const baseUrl = generateUrl('/apps/launchpad')

export default defineComponent({
	name: 'DashboardPublicShareView',

	components: {
		AlertCircleIcon,
		EyeOutlineIcon,
		LoginIcon,
		NcEmptyContent,
		NcLoadingIcon,
		PublicDashboardGrid,
		PublicSharePasswordDialog,
	},

	props: {
		/** Share token from the URL path parameter. */
		token: {
			type: String,
			required: true,
		},

		/** Rendered as the public start page: shows the sign-in bar (REQ-PSP-003). */
		startPage: {
			type: Boolean,
			default: false,
		},
	},

	/**
	 * The anonymous render path for a share token: fetch the dashboard by
	 * token, surface the 404 shape for an invalid/revoked/expired token, and
	 * raise the password gate when the share is protected. No Nextcloud
	 * session is required or used.
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-004
	 * @param {object} props the component props (`token`).
	 * @return {object} the template bindings.
	 */
	setup(props) {
		const shareStore = usePublicShareStore()

		const loading = ref(false)
		const dashboard = ref(null)
		const share = ref(null)
		const placements = ref([])
		const error = ref(null)
		const showPasswordModal = ref(false)
		const unlocking = ref(false)
		const unlockError = ref(null)

		const errorTitle = ref(t('launchpad', 'Not available'))
		const errorDescription = ref(
			t('launchpad', 'This shared dashboard is not available.'),
		)

		const loadShare = async (password = null) => {
			loading.value = true
			error.value = null

			try {
				const headers = {}
				if (password) headers['X-Share-Password'] = password

				const { data } = await axios.get(
					`${baseUrl}/s/${encodeURIComponent(props.token)}/data`,
					{ headers },
				)
				dashboard.value = data.dashboard
				share.value = data.share
				placements.value = Array.isArray(data.placements)
					? data.placements
					: []
				showPasswordModal.value = false
			} catch (err) {
				if (
					err.response?.status === 401
					&& err.response?.data?.passwordRequired
				) {
					if (shareStore.isUnlocked(props.token)) {
						shareStore.clearUnlocked(props.token)
					}
					showPasswordModal.value = true
				} else if (err.response?.status === 404) {
					error.value = 'not_found'
					errorTitle.value = t('launchpad', 'Not found')
					errorDescription.value = t(
						'launchpad',
						'This shared dashboard does not exist or has expired.',
					)
				} else {
					error.value = 'server_error'
					errorTitle.value = t('launchpad', 'Error')
					errorDescription.value = t(
						'launchpad',
						'An error occurred loading the shared dashboard.',
					)
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
					{ password },
				)

				if (data.access === true) {
					shareStore.markUnlocked(props.token)
					await loadShare(password)
				} else {
					unlockError.value = t(
						'launchpad',
						'Incorrect password. Please try again.',
					)
				}
			} catch (err) {
				if (err.response?.status === 429) {
					unlockError.value = t('launchpad', 'unlock_throttled')
				} else {
					unlockError.value = t('launchpad', 'Failed to verify password.')
				}
			} finally {
				unlocking.value = false
			}
		}

		/**
		 * Normalise the raw placements into read-only render descriptors,
		 * ordered by grid position. Only content that renders safely for an
		 * anonymous visitor (tiles + static custom widgets) is materialised;
		 * everything else (native Nextcloud widgets, data-backed widgets that
		 * need an authenticated API call) collapses to a "sign-in required"
		 * placeholder so the layout stays honest.
		 */
		onMounted(async () => {
			await loadShare()
		})

		// The start page's sign-in bar: the instance's name from Nextcloud's
		// theming, and the login form with direct=1 (REQ-PSP-003).
		const organisationName = window.OC?.theme?.name || ''
		// The emblem thematiq publishes for the house style, else Nextcloud's logo.
		const capabilities = getCapabilities() || {}
		const emblem = capabilities.nldesign?.logos?.emblem || capabilities.theming?.logo || ''
		const signInHref = signInUrl(generateUrl)

		return {
			// REQ-KIOSK-001: `?kiosk=1` fills the screen, chrome-less.
			kiosk: isKioskMode(),
			organisationName,
			emblem,
			signInHref,
			loading,
			dashboard,
			share,
			placements,
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
	background: var(--color-background-hover, var(--color-main-background));
	color: var(--color-main-text);
}

.public-share-view--kiosk {
	position: fixed;
	inset: 0;
	min-height: 0;
	overflow: hidden;
	padding: 0.75rem;
	background-color: var(--color-main-background);
}

.public-share-view--kiosk :deep(.public-share-view__grid) {
	max-width: none;
}

/* The bar of board LpPubliek: 72px, the emblem, the organisation over the
   page's name, and the way in for staff at the end. */
.public-share-view__start-bar {
	display: flex;
	align-items: center;
	gap: 16px;
	height: 72px;
	padding: 0 32px;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
}

.public-share-view__emblem {
	width: auto;
	height: 44px;
}

.public-share-view__brand {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	line-height: 1.15;
}

.public-share-view__organisation {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.public-share-view__brand-name {
	font-size: 18px;
	font-weight: 700;
}

.public-share-view__sign-in {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	height: 40px;
	padding: 0 16px;
	font-size: 14px;
	font-weight: 600;
}

.public-share-view__loading {
	display: flex;
	justify-content: center;
	padding-top: 4rem;
}

.public-share-view__content {
	box-sizing: border-box;
	width: 100%;
	max-width: 1200px;
	margin: 0 auto;
	padding: 32px 32px 40px;
}

.public-share-view__header {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 20px;
}

.public-share-view__header h1 {
	margin: 0;
	font-size: 28px;
	font-weight: 700;
	line-height: 1.2;
}

.public-share-view__meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 12px;
	margin: 0;
	font-size: 15px;
	color: var(--color-text-maxcontrast);
}

.public-share-view__badge {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 3px 10px;
	border-radius: 12px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 13px;
	font-weight: 600;
}

.public-share-view__widgets-note {
	color: var(--color-text-lighter);
	font-style: italic;
}
</style>
