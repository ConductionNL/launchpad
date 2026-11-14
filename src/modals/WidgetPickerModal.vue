<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal"
		size="normal"
		:name="t('launchpad', 'Add widget')"
		@close="$emit('close')">
		<div class="widget-picker">
			<h2 class="widget-picker__title">
				{{ t('launchpad', 'Add widget') }}
			</h2>

			<div class="widget-picker__search">
				<NcTextField
					v-model="searchQuery"
					:label="t('launchpad', 'Search widgets')"
					:placeholder="t('launchpad', 'Search widgets…')"
					:showTrailingButton="searchQuery !== ''"
					trailingButtonIcon="close"
					@trailingButtonClick="searchQuery = ''">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>

			<div class="widget-picker__list">
				<section
					v-if="filteredCatalogueApps.length > 0"
					class="widget-picker__section widget-picker__section--first"
					data-testid="widget-picker-catalogue">
					<h3 class="widget-picker__section-title">
						{{ t('launchpad', 'From the app catalogue') }}
					</h3>
					<p class="widget-picker__section-description">
						{{ t('launchpad', 'Apps your organisation approved. One click places one as a tile.') }}
					</p>
					<button
						v-for="app in filteredCatalogueApps"
						:key="app.uuid"
						type="button"
						class="widget-picker__widget"
						@click="$emit('addCatalogueApp', app)">
						<img
							v-if="app.icon && app.iconType === 'url'"
							:src="app.icon"
							alt=""
							class="widget-picker__widget-icon widget-picker__app-icon">
						<span
							v-else-if="app.icon"
							:class="app.icon"
							class="widget-picker__widget-icon"
							aria-hidden="true" />
						<div class="widget-picker__widget-info">
							<span class="widget-picker__widget-title">{{ app.name }}</span>
							<span v-if="app.description" class="widget-picker__widget-badge">
								{{ app.description }}
							</span>
						</div>
						<Plus :size="20" class="widget-picker__widget-action" />
					</button>
				</section>

				<button
					v-for="widget in filteredWidgets"
					:key="widget.id"
					class="widget-picker__widget"
					:class="{ 'widget-picker__widget--placed': isPlaced(widget.id) }"
					@click="addWidget(widget)">
					<img
						v-if="widget.iconUrl"
						:src="widget.iconUrl"
						:alt="widget.title"
						class="widget-picker__widget-icon" />
					<span
						v-else-if="widget.iconClass"
						:class="widget.iconClass"
						class="widget-picker__widget-icon" />
					<div class="widget-picker__widget-info">
						<span class="widget-picker__widget-title">{{
							widget.title
						}}</span>
						<span
							v-if="isPlaced(widget.id)"
							class="widget-picker__widget-badge">
							{{ t('launchpad', 'Already added') }}
						</span>
					</div>
					<Plus
						v-if="!isPlaced(widget.id)"
						:size="20"
						class="widget-picker__widget-action" />
					<Check
						v-else
						:size="20"
						class="widget-picker__widget-action widget-picker__widget-action--check" />
				</button>

				<section
					v-if="filteredApps.length > 0"
					class="widget-picker__section"
					data-testid="widget-picker-discovered">
					<h3 class="widget-picker__section-title">
						{{ t('launchpad', 'Discovered on this server') }}
					</h3>
					<p class="widget-picker__section-description">
						{{ t('launchpad', 'Apps this server runs for you. One click places one as a tile.') }}
					</p>
					<button
						v-for="app in filteredApps"
						:key="app.appId"
						type="button"
						class="widget-picker__widget"
						@click="$emit('addApp', app)">
						<img
							v-if="app.icon"
							:src="app.icon"
							alt=""
							class="widget-picker__widget-icon widget-picker__app-icon">
						<div class="widget-picker__widget-info">
							<span class="widget-picker__widget-title">{{ app.name }}</span>
							<span
								v-if="placedAppIds.includes(app.appId)"
								class="widget-picker__widget-badge">
								{{ t('launchpad', 'On this dashboard') }}
							</span>
						</div>
						<Plus :size="20" class="widget-picker__widget-action" />
					</button>
				</section>

				<NcEmptyContent
					v-if="filteredWidgets.length === 0 && filteredApps.length === 0 && filteredCatalogueApps.length === 0"
					:description="t('launchpad', 'No widgets found')">
					<template #icon>
						<Magnify :size="48" />
					</template>
				</NcEmptyContent>

				<section
					v-if="requestAppFn"
					class="widget-picker__section"
					data-testid="widget-picker-request">
					<NcButton
						v-if="!requestFormOpen"
						variant="tertiary"
						@click="openRequestForm">
						{{ t('launchpad', 'Can\'t find your app?') }}
					</NcButton>
					<form
						v-else
						class="widget-picker__request"
						:aria-label="t('launchpad', 'Ask IT for an app')"
						@submit.prevent="submitRequest">
						<h3 class="widget-picker__section-title">
							{{ t('launchpad', 'Ask IT for an app') }}
						</h3>
						<NcTextField
							v-model="requestAppName"
							:label="t('launchpad', 'App name')"
							:required="true" />
						<NcTextField
							v-model="requestPurpose"
							:label="t('launchpad', 'What do you need it for?')" />
						<p
							v-if="requestMessage"
							class="widget-picker__request-message"
							:class="{ 'widget-picker__request-message--error': requestFailed }"
							role="status">
							{{ requestMessage }}
						</p>
						<div class="widget-picker__request-actions">
							<NcButton
								variant="primary"
								type="submit"
								:disabled="requestBusy || requestAppName.trim() === ''">
								{{ t('launchpad', 'Send request') }}
							</NcButton>
							<NcButton variant="tertiary" @click="closeRequestForm">
								{{ t('launchpad', 'Cancel') }}
							</NcButton>
						</div>
					</form>
				</section>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcModal, NcTextField } from '@nextcloud/vue'
import Check from 'vue-material-design-icons/Check.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { FLAG_PROP, LIST_PROP } from '../constants/propDefinitions.js'

export default {
	name: 'WidgetPickerModal',

	components: {
		NcButton,
		NcModal,
		NcTextField,
		NcEmptyContent,
		Magnify,
		Plus,
		Check,
	},

	props: {
		open: FLAG_PROP,

		widgets: {
			type: Array,
			required: true,
		},

		placedWidgetIds: LIST_PROP,

		/**
		 * The apps this server runs for the user (`GET /api/tiles/discover`):
		 * `[{ appId, name, href, icon }]`.
		 */
		discoveredApps: LIST_PROP,

		/** The discovered app ids already on the dashboard. */
		placedAppIds: LIST_PROP,

		/**
		 * The app catalogue entries the user's groups may see
		 * (`GET /api/catalogue`): `[{ uuid, name, description, icon, iconType, linkType, linkValue }]`.
		 */
		catalogueApps: LIST_PROP,

		/**
		 * Sends a request for a missing app: `({ appName, purpose }) => Promise`.
		 * Without it the "Can't find your app?" form is not offered.
		 */
		requestAppFn: {
			type: Function,
			default: null,
		},
	},

	emits: ['close', 'add', 'addApp', 'addCatalogueApp'],

	data() {
		return {
			searchQuery: '',
			requestFormOpen: false,
			requestAppName: '',
			requestPurpose: '',
			requestBusy: false,
			requestMessage: '',
			requestFailed: false,
		}
	},

	computed: {
		/** @spec openspec/specs/widgets/spec.md */
		filteredWidgets() {
			if (!this.searchQuery) {
				return this.sortedWidgets
			}
			const query = this.searchQuery.toLowerCase()
			return this.sortedWidgets.filter((w) =>
				w.title.toLowerCase().includes(query),
			)
		},

		/** @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-the-add-flow-lists-the-apps-this-server-runs-for-the-user-req-disc-001 */
		filteredApps() {
			const query = this.searchQuery.toLowerCase()
			return this.discoveredApps.filter((app) => !query || String(app.name).toLowerCase().includes(query))
		},

		/**
		 * The catalogue entries matching the search box, by name or description.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-add-catalogue-apps-as-tiles-from-the-add-flow-req-acat-002
		 */
		filteredCatalogueApps() {
			const query = this.searchQuery.toLowerCase()
			return this.catalogueApps.filter((app) => !query
				|| String(app.name).toLowerCase().includes(query)
				|| String(app.description || '').toLowerCase().includes(query))
		},

		/** @spec openspec/specs/widgets/spec.md */
		sortedWidgets() {
			return [...this.widgets].sort((a, b) => {
				const aPlaced = this.isPlaced(a.id)
				const bPlaced = this.isPlaced(b.id)
				if (aPlaced !== bPlaced) {
					return aPlaced ? 1 : -1
				}
				return (a.order || 0) - (b.order || 0)
			})
		},
	},

	watch: {
		/**
		 * Clear the search box when the picker closes, so the next open
		 * starts from the full widget list.
		 *
		 * @param {boolean} isOpen Whether the picker is now open.
		 * @spec openspec/specs/widgets/spec.md
		 */
		open(isOpen) {
			if (!isOpen) {
				this.searchQuery = ''
				this.closeRequestForm()
			}
		},
	},

	methods: {
		t,
		isPlaced(widgetId) {
			return this.placedWidgetIds.includes(widgetId)
		},

		/**
		 * Ask the host to place the chosen widget.
		 *
		 * @param {object} widget The picked widget definition; only `id` is
		 *   forwarded.
		 * @spec openspec/specs/widgets/spec.md
		 */
		addWidget(widget) {
			this.$emit('add', widget.id)
		},

		/**
		 * Open the request form, starting from what the user searched for.
		 *
		 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
		 */
		openRequestForm() {
			this.requestFormOpen = true
			this.requestAppName = this.searchQuery.trim()
			this.requestPurpose = ''
			this.requestMessage = ''
			this.requestFailed = false
		},

		/** Close the request form and forget what was typed. */
		closeRequestForm() {
			this.requestFormOpen = false
			this.requestAppName = ''
			this.requestPurpose = ''
			this.requestMessage = ''
			this.requestFailed = false
		},

		/**
		 * Send the request and say how it went: sent, refused because the
		 * user has too many open requests, or not sent.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
		 */
		async submitRequest() {
			const appName = this.requestAppName.trim()
			if (appName === '' || this.requestBusy) {
				return
			}
			this.requestBusy = true
			this.requestMessage = ''
			try {
				await this.requestAppFn({ appName, purpose: this.requestPurpose.trim() })
				this.requestFailed = false
				this.requestMessage = t('launchpad', 'Your request was sent to IT')
				this.requestAppName = ''
				this.requestPurpose = ''
			} catch (error) {
				this.requestFailed = true
				this.requestMessage = error?.response?.data?.code === 'request_limit'
					? t('launchpad', 'You have 10 open requests. Wait for an answer first.')
					: t('launchpad', 'Your request could not be sent. Try again later.')
			} finally {
				this.requestBusy = false
			}
		},
	},
}
</script>

<style scoped>
.widget-picker {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-height: 80vh;
}

.widget-picker__title {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
}

.widget-picker__search {
	flex-shrink: 0;
}

.widget-picker__list {
	flex: 1;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.widget-picker__widget {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px;
	border: none;
	background: transparent;
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: background var(--animation-quick) ease;
	text-align: left;
	width: 100%;
}

.widget-picker__widget:hover,
.widget-picker__widget:focus {
	background: var(--color-background-hover);
}

.widget-picker__widget--placed {
	opacity: 0.6;
}

.widget-picker__widget-icon {
	width: 32px;
	height: 32px;
	flex-shrink: 0;
}

.widget-picker__widget-info {
	flex: 1;
	min-width: 0;
}

.widget-picker__widget-title {
	display: block;
	font-weight: 500;
	color: var(--color-main-text);
}

.widget-picker__widget-badge {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.widget-picker__section {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-top: 12px;
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
}

.widget-picker__section-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.widget-picker__section-description {
	margin: 0 0 4px;
	color: var(--color-text-maxcontrast);
}

.widget-picker__section--first {
	margin-top: 0;
	padding-top: 0;
	border-top: none;
}

.widget-picker__request {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.widget-picker__request-actions {
	display: flex;
	gap: 8px;
}

.widget-picker__request-message {
	margin: 0;
	color: var(--color-success-text);
}

.widget-picker__request-message--error {
	color: var(--color-error-text);
}

.widget-picker__widget-action {
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
}

.widget-picker__widget-action--check {
	color: var(--color-success);
}

/* WCAG 2.2 AA SC 2.3.3 (Animation from Interactions). Honour the OS-level
   "reduce motion" setting by making the hover transition instantaneous
   instead of removing the state change — the colour still updates, so the
   affordance is intact; only the interpolation between the two colours is
   dropped. `0.01ms` rather than `0` keeps `transitionend` firing for any
   listener that depends on it. */
@media (prefers-reduced-motion: reduce) {
	.widget-picker__widget {
		transition-duration: 0.01ms;
	}
}
</style>
