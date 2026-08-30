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
				<span class="public-share-view__badge">{{
					t('launchpad', 'Read-only view')
				}}</span>
			</header>
			<p v-if="dashboard.description" class="public-share-view__description">
				{{ dashboard.description }}
			</p>

			<div v-if="items.length > 0" class="public-share-view__grid">
				<div
					v-for="item in items"
					:key="item.id"
					class="public-share-view__cell"
					:style="{ gridColumn: 'span ' + Math.min(item.gridWidth, 12) }">
					<!-- Tile — icon + label linking to its target. -->
					<a
						v-if="item.kind === 'tile'"
						class="public-share-view__tile"
						:href="item.tileLink || '#'"
						:style="{
							backgroundColor: item.tileBackgroundColor || undefined,
							color: item.tileTextColor || undefined,
						}">
						<img
							v-if="
								item.tileIcon
								&& /^(data:|https?:|\/)/.test(item.tileIcon)
							"
							class="public-share-view__tile-icon"
							:src="item.tileIcon"
							alt="" />
						<span class="public-share-view__tile-title">{{
							item.title
						}}</span>
					</a>

					<!-- Static custom widgets that render safely for anonymous visitors. -->
					<div v-else class="public-share-view__widget">
						<h2
							v-if="
								item.showTitle
								&& item.title
								&& item.kind !== 'divider'
							"
							class="public-share-view__widget-title">
							{{ item.title }}
						</h2>
						<hr
							v-if="item.kind === 'divider'"
							class="public-share-view__divider" />
						<h3
							v-else-if="
								item.kind === 'header' || item.kind === 'label'
							"
							class="public-share-view__widget-heading">
							{{ item.text || item.title }}
						</h3>
						<p
							v-else-if="item.kind === 'text'"
							class="public-share-view__widget-text">
							{{ item.text }}
						</p>
						<img
							v-else-if="item.kind === 'image' && item.url"
							class="public-share-view__widget-image"
							:src="item.url"
							:alt="item.title" />
						<a
							v-else-if="item.kind === 'link' && item.url"
							class="public-share-view__widget-link"
							:href="item.url">
							{{ item.title || item.url }}
						</a>
						<p v-else class="public-share-view__widget-restricted">
							{{
								t(
									'launchpad',
									'This widget is only visible to signed-in users.',
								)
							}}
						</p>
					</div>
				</div>
			</div>
			<p v-else class="public-share-view__empty">
				{{
					t(
						'launchpad',
						'This dashboard has no publicly viewable content.',
					)
				}}
			</p>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, defineComponent, onMounted, ref } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import PublicSharePasswordDialog from '../dialogs/PublicSharePasswordDialog.vue'
import { usePublicShareStore } from '../stores/publicShares.js'

const baseUrl = generateUrl('/apps/launchpad')

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
		const items = computed(() => {
			const list = [...placements.value].filter(
				(p) => p.isVisible !== 0 && p.isVisible !== false,
			)
			list.sort((a, b) => a.gridY - b.gridY || a.gridX - b.gridX)
			return list.map((p) => {
				const content =
					p.content && typeof p.content === 'object' ? p.content : {}
				const widgetId = p.widgetId || ''
				const isTile = widgetId.startsWith('tile-') || !!p.tileType
				let kind = 'restricted'
				if (isTile) {
					kind = 'tile'
				} else if (
					['label', 'header', 'text', 'image', 'link', 'divider'].includes(
						widgetId,
					)
				) {
					kind = widgetId
				}
				return {
					id: p.id,
					kind,
					gridWidth: p.gridWidth || 4,
					gridHeight: p.gridHeight || 4,
					title:
						p.customTitle
						|| p.tileTitle
						|| content.label
						|| content.title
						|| '',
					showTitle: p.showTitle !== 0 && p.showTitle !== false,
					tileIcon: p.tileIcon || '',
					tileBackgroundColor: p.tileBackgroundColor || '',
					tileTextColor: p.tileTextColor || '',
					tileLink: p.tileLinkValue || '',
					text: content.text || content.body || '',
					url: content.url || '',
				}
			})
		})

		onMounted(async () => {
			await loadShare()
		})

		return {
			loading,
			dashboard,
			share,
			items,
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

.public-share-view__grid {
	display: grid;
	grid-template-columns: repeat(12, 1fr);
	gap: 12px;
	max-width: 1200px;
}

.public-share-view__cell {
	min-width: 0;
}

.public-share-view__tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	height: 100%;
	min-height: 96px;
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	text-decoration: none;
	text-align: center;
	font-weight: 600;
}

.public-share-view__tile-icon {
	width: 40px;
	height: 40px;
}

.public-share-view__widget {
	height: 100%;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.public-share-view__widget-title {
	margin: 0 0 8px;
	font-size: 1.05em;
	font-weight: 700;
}

.public-share-view__widget-heading {
	margin: 0;
	font-weight: 700;
}

.public-share-view__widget-text {
	margin: 0;
	white-space: pre-wrap;
}

.public-share-view__widget-image {
	max-width: 100%;
	height: auto;
	border-radius: var(--border-radius);
}

.public-share-view__widget-restricted {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

.public-share-view__divider {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 8px 0;
}

.public-share-view__empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
