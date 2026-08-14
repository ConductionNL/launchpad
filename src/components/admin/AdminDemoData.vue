<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-demo-showcases">
		<h3>{{ t('launchpad', 'Demo data showcases') }}</h3>

		<p class="launchpad-demo-showcases__hint">
			{{
				t(
					'launchpad',
					'Install bundled example dashboards to give users a working starting point. Each showcase is created as a group-shared dashboard visible to all users; you can uninstall it at any time.',
				)
			}}
		</p>

		<div v-if="loading" class="launchpad-demo-showcases__loading">
			{{ t('launchpad', 'Loading showcases…') }}
		</div>

		<div v-else-if="loadError" class="launchpad-demo-showcases__error">
			{{ loadError }}
		</div>

		<template v-else>
			<NcNoteCard
				v-if="actionError"
				type="error"
				class="launchpad-demo-showcases__action-error"
				data-test="showcase-action-error">
				{{ actionError }}
			</NcNoteCard>

			<div class="launchpad-demo-showcases__grid">
				<div
					v-for="showcase in showcases"
					:key="showcase.id"
					class="launchpad-demo-showcases__card"
					:data-test="'showcase-card-' + showcase.id">
					<div class="launchpad-demo-showcases__thumb">
						<img
							v-if="showcase.thumbnailUrl"
							:src="showcase.thumbnailUrl"
							:alt="showcase.name"
							@error="onThumbError($event)" />
						<ViewDashboard v-else :size="64" />
					</div>

					<div class="launchpad-demo-showcases__body">
						<div class="launchpad-demo-showcases__title-row">
							<strong class="launchpad-demo-showcases__title">{{
								showcase.name
							}}</strong>
							<span class="launchpad-demo-showcases__lang-badge">{{
								showcase.language.toUpperCase()
							}}</span>
						</div>

						<p class="launchpad-demo-showcases__desc">
							{{ showcase.description }}
						</p>

						<div
							v-if="warnings[showcase.id]"
							class="launchpad-demo-showcases__warning">
							{{
								t(
									'launchpad',
									'Installed but skipped widgets: {list}',
									{ list: warnings[showcase.id].join(', ') },
								)
							}}
						</div>

						<div class="launchpad-demo-showcases__actions">
							<NcButton
								v-if="!showcase.isInstalled"
								type="primary"
								:disabled="busy[showcase.id]"
								:data-test="'showcase-install-' + showcase.id"
								@click="install(showcase)">
								{{
									busy[showcase.id]
										? t('launchpad', 'Installing…')
										: t('launchpad', 'Install')
								}}
							</NcButton>
							<NcButton
								v-else
								type="error"
								:disabled="busy[showcase.id]"
								:data-test="'showcase-uninstall-' + showcase.id"
								@click="confirmUninstall(showcase)">
								{{
									busy[showcase.id]
										? t('launchpad', 'Uninstalling…')
										: t('launchpad', 'Uninstall')
								}}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</template>

		<DemoShowcaseUninstallDialog
			:open="uninstallTarget !== null"
			:showcaseName="uninstallTarget ? uninstallTarget.name : ''"
			@update:open="uninstallTarget = null"
			@confirm="onUninstallConfirm" />
	</div>
</template>

<script>
import { NcButton } from '@conduction/nextcloud-vue'
import { NcNoteCard } from '@nextcloud/vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import DemoShowcaseUninstallDialog from '../../dialogs/DemoShowcaseUninstallDialog.vue'
import { api } from '../../services/api.js'

export default {
	name: 'AdminDemoData',

	components: {
		NcButton,
		DemoShowcaseUninstallDialog,
		NcNoteCard,
		ViewDashboard,
	},

	data() {
		return {
			loading: false,
			// Blocks the card grid — when the list itself fails to load
			// there is nothing to show.
			loadError: '',
			// Shown as a dismissible note card above the grid so a failed
			// install/uninstall does not hide the cards and retrying stays
			// possible.
			actionError: '',
			showcases: [],
			// The showcase the uninstall confirmation is open for, or null.
			uninstallTarget: null,
			busy: {},
			warnings: {},
		}
	},

	mounted() {
		this.fetch()
	},

	methods: {
		/** @spec openspec/specs/demo-data-showcases/spec.md */
		async fetch() {
			this.loading = true
			this.loadError = ''
			try {
				const response = await api.listDemoShowcases()
				this.showcases = response.data || []
			} catch (err) {
				this.loadError = this.t(
					'launchpad',
					'Could not load demo showcases. Please try again.',
				)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Install a showcase, marking its row busy for the duration.
		 *
		 * @param {object} showcase The showcase to install.
		 * @spec openspec/specs/demo-data-showcases/spec.md
		 */
		async install(showcase) {
			this.busy[showcase.id] = true
			delete this.warnings[showcase.id]
			this.actionError = ''
			try {
				const response = await api.installDemoShowcase(showcase.id)
				const skipped = (response.data && response.data.skippedWidgets) || []
				if (skipped.length > 0) {
					this.warnings[showcase.id] = skipped
				}

				await this.fetch()
			} catch (err) {
				if (err.response && err.response.status === 404) {
					this.actionError = this.t('launchpad', 'Showcase not found.')
				} else if (err.response && err.response.status === 403) {
					this.actionError = this.t(
						'launchpad',
						'You need admin privileges to install showcases.',
					)
				} else {
					this.actionError = this.t(
						'launchpad',
						'Could not install showcase. Please try again.',
					)
				}
			} finally {
				this.busy[showcase.id] = false
			}
		},

		/**
		 * Uninstall a showcase after an explicit confirmation, since it
		 * removes the dashboard for every user.
		 *
		 * @param {object} showcase The showcase to remove.
		 * @spec openspec/specs/demo-data-showcases/spec.md
		 */
		confirmUninstall(showcase) {
			this.uninstallTarget = showcase
		},

		/**
		 * Uninstall the confirmed showcase.
		 *
		 * Split out of `confirmUninstall` because the confirmation is no
		 * longer a synchronous `window.confirm()` that could be awaited
		 * inline — the dialog resolves on a later tick, so the target is
		 * held in `uninstallTarget` until the user answers.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/demo-data-showcases/spec.md
		 */
		async onUninstallConfirm() {
			const showcase = this.uninstallTarget
			this.uninstallTarget = null
			if (showcase === null) {
				return
			}

			this.busy[showcase.id] = true
			this.actionError = ''
			try {
				await api.uninstallDemoShowcase(showcase.id)
				delete this.warnings[showcase.id]
				await this.fetch()
			} catch (err) {
				this.actionError = this.t(
					'launchpad',
					'Could not uninstall showcase. Please try again.',
				)
			} finally {
				this.busy[showcase.id] = false
			}
		},

		/**
		 * Hide a broken thumbnail so the card falls back to its icon.
		 *
		 * @param {Event} event The image's error event.
		 * @spec openspec/specs/demo-data-showcases/spec.md
		 */
		onThumbError(event) {
			// Hide broken images gracefully — fall back to the icon.
			event.target.style.display = 'none'
		},
	},
}
</script>

<style scoped>
.launchpad-demo-showcases__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.launchpad-demo-showcases__loading,
.launchpad-demo-showcases__error {
	padding: 16px;
	color: var(--color-text-maxcontrast);
}

.launchpad-demo-showcases__error {
	color: var(--color-error);
}

.launchpad-demo-showcases__action-error {
	margin-top: 16px;
}

.launchpad-demo-showcases__grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 16px;
	margin-top: 16px;
}

.launchpad-demo-showcases__card {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	background: var(--color-main-background);
	display: flex;
	flex-direction: column;
}

.launchpad-demo-showcases__thumb {
	width: 100%;
	aspect-ratio: 3 / 2;
	background: var(--color-background-dark);
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}

.launchpad-demo-showcases__thumb img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.launchpad-demo-showcases__body {
	padding: 12px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	flex: 1 1 auto;
}

.launchpad-demo-showcases__title-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.launchpad-demo-showcases__title {
	font-size: 1rem;
}

.launchpad-demo-showcases__lang-badge {
	font-size: 0.75rem;
	font-weight: 600;
	padding: 2px 6px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.launchpad-demo-showcases__desc {
	font-size: 0.875rem;
	color: var(--color-text-maxcontrast);
	flex: 1 1 auto;
}

.launchpad-demo-showcases__warning {
	font-size: 0.75rem;
	padding: 6px 8px;
	background: var(--color-warning-hover, rgba(255, 200, 0, 0.15));
	border-radius: var(--border-radius);
}

.launchpad-demo-showcases__actions {
	display: flex;
	justify-content: flex-end;
}
</style>
