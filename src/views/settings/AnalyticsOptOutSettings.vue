<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!--
		A section of the user settings dialog. It is left out while an
		administrator has turned analytics off for everyone: there is
		nothing to opt out of then (REQ-ANLT-UI-001).
	-->
	<NcAppSettingsSection
		v-if="enabled"
		id="launchpad-analytics-opt-out"
		:name="t('launchpad', 'Statistics')">
		<NcCheckboxRadioSwitch
			type="switch"
			:modelValue="counted"
			:disabled="saving"
			data-testid="launchpad-count-my-visits"
			@update:modelValue="onToggle">
			{{ t('launchpad', 'Count my visits') }}
		</NcCheckboxRadioSwitch>
		<p class="analytics-opt-out__hint">
			{{
				t(
					'launchpad',
					'When this is off, your dashboard visits and tile clicks are left out of the view counts.',
				)
			}}
		</p>
		<NcNoteCard v-if="saveError" type="error">
			{{ t('launchpad', 'Your choice could not be saved.') }}
		</NcNoteCard>
	</NcAppSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import {
	NcAppSettingsSection,
	NcCheckboxRadioSwitch,
	NcNoteCard,
} from '@nextcloud/vue'

/**
 * AnalyticsOptOutSettings: the person's own "Count my visits" switch, a
 * section of the user settings dialog. On by default; off stores
 * `analytics_optout = true` for the signed-in person only.
 *
 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
 */
export default {
	name: 'AnalyticsOptOutSettings',

	components: { NcAppSettingsSection, NcCheckboxRadioSwitch, NcNoteCard },

	data() {
		return {
			enabled: false,
			counted: true,
			saving: false,
			saveError: false,
		}
	},

	/** @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001 */
	async mounted() {
		try {
			const response = await axios.get(this.url())
			this.apply(response?.data)
		} catch {
			// Unknown state: show nothing rather than a switch that may lie.
			this.enabled = false
		}
	},

	methods: {
		/**
		 * The caller's own opt-out endpoint.
		 *
		 * @return {string} The URL.
		 */
		url() {
			return generateUrl('/apps/launchpad/api/analytics/me')
		},

		/**
		 * Take the server's answer: whether analytics is on, and the opt-out.
		 *
		 * @param {{enabled?: boolean, optedOut?: boolean}|undefined} data The answer.
		 */
		apply(data) {
			this.enabled = data?.enabled === true
			this.counted = data?.optedOut !== true
		},

		/**
		 * Store the switch: counting on stores `optedOut: false`, off stores `true`.
		 *
		 * @param {boolean} counted Whether the person's visits are counted.
		 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
		 */
		async onToggle(counted) {
			const previous = this.counted
			this.counted = counted
			this.saving = true
			this.saveError = false
			try {
				const response = await axios.put(this.url(), { optedOut: !counted })
				this.apply(response?.data)
			} catch {
				this.counted = previous
				this.saveError = true
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.analytics-opt-out__hint {
	color: var(--color-text-maxcontrast);
	margin-block-start: calc(var(--default-grid-baseline) * 2);
}
</style>
