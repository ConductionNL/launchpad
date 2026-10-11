<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - The sections of LaunchPad's user settings dialog (the NcAppSettingsDialog
  - CnAppRoot hosts; Personal settings in the start page menu opens it).
  -
  - Every per-person setting lives here. LaunchPad no longer adds a form to
  - Nextcloud's personal settings (Ruben, 11 Oct 2026: user configuration goes
  - into the user settings dialog, app configuration into the admin settings).
  - The LaunchPad profile fields moved here from Nextcloud's personal info page,
  - and the house style of the groups a person manages moved here from
  - thematiq's personal settings. Each section stays out of the dialog when it
  - has nothing to offer, as the old forms did.
  -
  - THE LIBRARY'S OWN SECTIONS ARE REPEATED HERE ON PURPOSE. Filling CnAppRoot's
  - #user-settings slot replaces its fallback content entirely, and nothing
  - reports that: the notifications pane, credentials and the walkthrough replay
  - would have vanished from the dialog in silence. App.vue hands in the
  - walkthrough state CnAppRoot owns.
  -->
<template>
	<ProfileFieldsSettings />

	<GroupHouseStyleSettings />

	<CnNotificationPreferences />

	<!-- The library's own label, as CnAppRoot draws it. -->
	<NcAppSettingsSection
		id="credentials"
		:name="t('nextcloud-vue', 'Credentials')">
		<CnCredentials
			scope="personal"
			appId="launchpad"
			:appName="(manifest && manifest.name) || 'LaunchPad'"
			:appCredentials="(manifest && manifest.credentials) || []" />
	</NcAppSettingsSection>

	<NcAppSettingsSection
		v-if="walkthrough"
		id="cn-walkthrough"
		:name="walkthrough.sectionName">
		<p class="launchpad-user-settings__hint">
			{{ walkthrough.hint }}
		</p>
		<div class="launchpad-user-settings__actions">
			<NcButton
				v-if="walkthrough.inProgress"
				variant="primary"
				data-testid="cn-walkthrough-continue"
				@click="$emit('continueWalkthrough')">
				{{ walkthrough.continueLabel }}
			</NcButton>
			<NcButton
				variant="secondary"
				data-testid="cn-walkthrough-restart"
				@click="$emit('restartWalkthrough')">
				{{ walkthrough.restartLabel }}
			</NcButton>
		</div>
	</NcAppSettingsSection>
</template>

<script>
import { CnCredentials, CnNotificationPreferences } from '@conduction/nextcloud-vue'
import { NcAppSettingsSection, NcButton } from '@nextcloud/vue'
import GroupHouseStyleSettings from './GroupHouseStyleSettings.vue'
import ProfileFieldsSettings from './ProfileFieldsSettings.vue'

/**
 * UserSettings: the sections of LaunchPad's user settings dialog.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
export default {
	name: 'UserSettings',

	components: {
		CnCredentials,
		CnNotificationPreferences,
		GroupHouseStyleSettings,
		NcAppSettingsSection,
		NcButton,
		ProfileFieldsSettings,
	},

	props: {
		/**
		 * The app manifest, for the credentials it declares.
		 */
		manifest: {
			type: Object,
			default: null,
		},

		/**
		 * The walkthrough replay CnAppRoot would have drawn, or null when the
		 * app declares no tour. Labels come from CnAppRoot so the wording stays
		 * the library's.
		 *
		 * @type {{sectionName: string, hint: string, inProgress: boolean, continueLabel: string, restartLabel: string}|null}
		 */
		walkthrough: {
			type: Object,
			default: null,
		},
	},

	emits: ['continueWalkthrough', 'restartWalkthrough'],
}
</script>

<style scoped>
.launchpad-user-settings__hint {
	margin-bottom: calc(var(--default-grid-baseline) * 3);
	color: var(--color-text-maxcontrast);
}

.launchpad-user-settings__actions {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
