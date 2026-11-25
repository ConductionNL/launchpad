<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!--
		A section of the user settings dialog (board LpPersoonlijk, Verborgen
		widgets): every widget this person hid on a shared dashboard, with its
		dashboard and "Show again", and "Show all again". It acts on the
		person's own layer only; the shared dashboards stay as they are.
	-->
	<NcAppSettingsSection
		id="launchpad-hidden-widgets"
		:name="t('launchpad', 'Hidden widgets')">
		<div data-testid="launchpad-hidden-widgets">
			<p v-if="loadError" class="hidden-widgets-settings__hint">
				{{ t('launchpad', 'Your hidden widgets could not be loaded.') }}
			</p>
			<p
				v-else-if="loaded && hidden.length === 0"
				class="hidden-widgets-settings__hint"
				data-testid="launchpad-hidden-widgets-none">
				{{ t('launchpad', 'You have not hidden any widgets on shared dashboards.') }}
			</p>
			<template v-else-if="loaded">
				<p class="hidden-widgets-settings__hint" data-testid="launchpad-hidden-widgets-count">
					{{
						n(
							'launchpad',
							'You have hidden %n widget on shared dashboards',
							'You have hidden %n widgets on shared dashboards',
							hidden.length,
						)
					}}
				</p>
				<ul class="hidden-widgets-settings__list">
					<li
						v-for="entry in hidden"
						:key="entry.placementId"
						class="hidden-widgets-settings__item"
						data-testid="launchpad-hidden-widget">
						<span class="hidden-widgets-settings__name">
							<strong>{{ titleOf(entry) }}</strong>
							<span class="hidden-widgets-settings__dashboard">{{ entry.dashboardName }}</span>
						</span>
						<NcButton
							variant="tertiary"
							:disabled="saving"
							data-testid="launchpad-hidden-widget-show"
							@click="showAgain(entry)">
							{{ t('launchpad', 'Show again') }}
						</NcButton>
					</li>
				</ul>
				<NcButton
					variant="secondary"
					:disabled="saving"
					data-testid="launchpad-hidden-widgets-show-all"
					@click="showAll">
					{{ t('launchpad', 'Show all again') }}
				</NcButton>
			</template>
			<NcNoteCard v-if="saveError" type="error">
				{{ t('launchpad', 'Your change could not be saved.') }}
			</NcNoteCard>
		</div>
	</NcAppSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcAppSettingsSection, NcButton, NcNoteCard } from '@nextcloud/vue'
import { resolveWidgetTitle } from '../../utils/widgetTitle.js'

/**
 * HiddenWidgetsSettings: the widgets this person hid on shared dashboards,
 * across every dashboard, a section of the user settings dialog.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
export default {
	name: 'HiddenWidgetsSettings',

	components: { NcAppSettingsSection, NcButton, NcNoteCard },

	data() {
		return {
			hidden: [],
			loaded: false,
			loadError: false,
			saving: false,
			saveError: false,
		}
	},

	/** @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md */
	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * The caller's own hidden-widget endpoint.
		 *
		 * @param {number} [placementId] One hidden placement.
		 * @return {string} The URL.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 		 */
		url(placementId) {
			const base = '/apps/launchpad/api/personal-layer/hidden'
			return generateUrl(placementId === undefined ? base : `${base}/${encodeURIComponent(placementId)}`)
		},

		/**
		 * Read the list.
		 *
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		async load() {
			try {
				const response = await axios.get(this.url())
				this.hidden = Array.isArray(response?.data?.hidden) ? response.data.hidden : []
				this.loadError = false
			} catch {
				this.hidden = []
				this.loadError = true
			} finally {
				this.loaded = true
			}
		},

		/**
		 * The name a person knows the widget by.
		 *
		 * @param {{placement?: object}} entry A hidden widget.
		 * @return {string} Its title.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 		 */
		titleOf(entry) {
			return resolveWidgetTitle(entry?.placement, []) || entry?.placement?.widgetId || ''
		},

		/**
		 * Show one widget again on the person's own view.
		 *
		 * @param {{placementId: number}} entry The hidden widget.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		async showAgain(entry) {
			await this.change(async () => {
				await axios.delete(this.url(entry.placementId))
				this.hidden = this.hidden.filter((item) => item.placementId !== entry.placementId)
			})
		},

		/**
		 * Show every hidden widget again.
		 *
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		async showAll() {
			await this.change(async () => {
				await axios.delete(this.url())
				this.hidden = []
			})
		},

		/**
		 * Run one change, saved at once, and say so when it fails.
		 *
		 * @param {() => Promise<void>} action The change.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 		 */
		async change(action) {
			this.saving = true
			this.saveError = false
			try {
				await action()
			} catch {
				this.saveError = true
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.hidden-widgets-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-block-end: calc(var(--default-grid-baseline) * 2);
}

.hidden-widgets-settings__list {
	list-style: none;
	margin: 0 0 calc(var(--default-grid-baseline) * 3);
	padding: 0;
}

.hidden-widgets-settings__item {
	align-items: center;
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
	justify-content: space-between;
	padding-block: var(--default-grid-baseline);
}

.hidden-widgets-settings__name {
	display: flex;
	flex-direction: column;
}

.hidden-widgets-settings__dashboard {
	color: var(--color-text-maxcontrast);
}
</style>
