<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!--
		A section of the user settings dialog (board LpPersoonlijk,
		Samenvatting per e-mail): how often this person gets launchpad's
		summary email. Off until they change it; saved at once.
	-->
	<NcAppSettingsSection
		id="launchpad-summary-mail"
		:name="t('launchpad', 'Summary by email')">
		<div data-testid="launchpad-summary-mail">
			<p class="summary-mail-settings__hint">
				{{ t('launchpad', 'A mail with new announcements and what changed on your dashboards') }}
			</p>
			<fieldset class="summary-mail-settings__choices">
				<legend>{{ t('launchpad', 'How often?') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="option in options"
					:key="option.value"
					type="radio"
					name="launchpad-summary-mail-frequency"
					:value="option.value"
					:modelValue="frequency"
					:disabled="!loaded || saving"
					:data-testid="`launchpad-summary-mail-${option.value}`"
					@update:modelValue="onChoose">
					{{ option.label }}
					<span v-if="option.hint" class="summary-mail-settings__option-hint">{{ option.hint }}</span>
				</NcCheckboxRadioSwitch>
			</fieldset>
			<NcNoteCard v-if="loaded && !hasEmail" type="warning" data-testid="launchpad-summary-mail-no-email">
				{{ t('launchpad', 'You have no email address, so no summary can be sent to you. Add one in your Nextcloud account.') }}
			</NcNoteCard>
			<NcNoteCard v-if="saveError" type="error">
				{{ t('launchpad', 'Your choice could not be saved.') }}
			</NcNoteCard>
		</div>
	</NcAppSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcAppSettingsSection,
	NcCheckboxRadioSwitch,
	NcNoteCard,
} from '@nextcloud/vue'

/**
 * DigestSettings: the person's summary email frequency (Off, Daily,
 * Weekly), a section of the user settings dialog.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
export default {
	name: 'DigestSettings',

	components: { NcAppSettingsSection, NcCheckboxRadioSwitch, NcNoteCard },

	data() {
		return {
			frequency: 'off',
			hasEmail: true,
			loaded: false,
			saving: false,
			saveError: false,
		}
	},

	computed: {
		/**
		 * The three choices, with the board's explanation under Daily and Weekly.
		 *
		 * @return {Array<{value: string, label: string, hint: string}>} The options.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		options() {
			return [
				{ value: 'off', label: t('launchpad', 'Off'), hint: '' },
				{ value: 'daily', label: t('launchpad', 'Daily'), hint: t('launchpad', 'At 07:30, only when something changed') },
				{ value: 'weekly', label: t('launchpad', 'Weekly'), hint: t('launchpad', 'On Monday at 07:30') },
			]
		},
	},

	/** @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md */
	async mounted() {
		try {
			const response = await axios.get(this.url())
			this.apply(response?.data)
			this.loaded = true
		} catch {
			// Unknown state: keep the choices disabled rather than show one that may lie.
			this.loaded = false
		}
	},

	methods: {
		/**
		 * The caller's own summary email endpoint.
		 *
		 * @return {string} The URL.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		url() {
			return generateUrl('/apps/launchpad/api/summary-mail/me')
		},

		/**
		 * Take the server's answer.
		 *
		 * @param {{frequency?: string, hasEmail?: boolean}|undefined} data The answer.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		apply(data) {
			this.frequency = ['off', 'daily', 'weekly'].includes(data?.frequency) ? data.frequency : 'off'
			this.hasEmail = data?.hasEmail !== false
		},

		/**
		 * Store the choice at once.
		 *
		 * @param {string} frequency Off, daily or weekly.
		 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
		 */
		async onChoose(frequency) {
			const previous = this.frequency
			this.frequency = frequency
			this.saving = true
			this.saveError = false
			try {
				const response = await axios.put(this.url(), { frequency })
				this.apply(response?.data)
			} catch {
				this.frequency = previous
				this.saveError = true
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.summary-mail-settings__hint,
.summary-mail-settings__option-hint {
	color: var(--color-text-maxcontrast);
}

.summary-mail-settings__hint {
	margin-block-end: calc(var(--default-grid-baseline) * 2);
}

.summary-mail-settings__option-hint {
	display: block;
}

.summary-mail-settings__choices {
	border: 0;
	margin: 0;
	padding: 0;
}
</style>
