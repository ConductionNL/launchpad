<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="hiddenPlacements.length > 0"
		class="hidden-widgets"
		data-testid="hidden-widgets">
		<NcPopover>
			<template #trigger>
				<NcButton variant="tertiary" data-testid="hidden-widgets-toggle">
					{{
						t('launchpad', 'Hidden ({count})', {
							count: hiddenPlacements.length,
						})
					}}
				</NcButton>
			</template>
			<div class="hidden-widgets__panel">
				<p class="hidden-widgets__intro">
					{{
						t(
							'launchpad',
							'Widgets you hid on this dashboard. Others still see them.',
						)
					}}
				</p>
				<ul class="hidden-widgets__list">
					<li v-for="placement in hiddenPlacements" :key="placement.id">
						<span>{{ titleOf(placement) }}</span>
						<NcButton
							variant="tertiary"
							data-testid="hidden-show-again"
							@click="$emit('showAgain', placement.id)">
							{{ t('launchpad', 'Show again') }}
						</NcButton>
					</li>
				</ul>
				<NcButton
					variant="secondary"
					data-testid="hidden-reset"
					@click="resetOpen = true">
					{{ t('launchpad', 'Reset my view') }}
				</NcButton>
			</div>
		</NcPopover>
		<ResetPersonalViewDialog
			:open="resetOpen"
			@update:open="resetOpen = $event"
			@confirm="confirmReset" />
	</div>
</template>

<script>
import { NcButton, NcPopover } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import ResetPersonalViewDialog from '../../dialogs/ResetPersonalViewDialog.vue'
import { resolveWidgetTitle } from '../../utils/widgetTitle.js'

/**
 * HiddenWidgetsControl: "Hidden (n)" next to the dashboard title. Lists the
 * widgets this person hid, each with "Show again", and "Reset my view"
 * behind a confirmation. Absent when nothing is hidden.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
export default {
	name: 'HiddenWidgetsControl',

	components: {
		NcButton,
		NcPopover,
		ResetPersonalViewDialog,
	},

	props: {
		/** Hidden placements (WidgetPlacement::jsonSerialize rows). */
		hiddenPlacements: {
			type: Array,
			default: () => [],
		},

		/** Widget catalog, for naming Nextcloud dashboard widgets. */
		availableWidgets: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['showAgain', 'reset'],

	/** @spec openspec/specs/dashboards/spec.md */
	data() {
		return { resetOpen: false }
	},

	methods: {
		t,

		/**
		 * The name a person knows the widget by.
		 *
		 * @param {object} placement Hidden placement.
		 * @return {string} Its title, or its widget id when it has none.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		titleOf(placement) {
			return (
				resolveWidgetTitle(placement, this.availableWidgets)
				|| placement.widgetId
				|| ''
			)
		},

		/**
		 * The reset was confirmed.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		confirmReset() {
			this.resetOpen = false
			this.$emit('reset')
		},
	},
}
</script>

<style scoped>
.hidden-widgets__panel {
	padding: 12px;
	max-width: 320px;
}

.hidden-widgets__list {
	margin: 8px 0;
}

.hidden-widgets__list li {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}
</style>
