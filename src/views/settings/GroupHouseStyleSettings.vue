<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!--
		A section of the user settings dialog. It stays out of the dialog unless
		thematiq is installed and this person manages a group that may choose
		its own house style, as thematiq's old personal settings form did.
	-->
	<NcAppSettingsSection
		v-if="groups.length > 0"
		id="launchpad-group-house-style"
		:name="t('launchpad', 'House style of my groups')">
		<p class="group-house-style__description">
			{{
				t(
					'launchpad',
					'You manage these groups. Choose the house style their members see. An administrator decides which house styles you can choose from.',
				)
			}}
		</p>
		<fieldset
			v-for="group in groups"
			:key="group.group"
			class="group-house-style__group"
			:data-testid="`group-house-style-${group.group}`">
			<legend class="group-house-style__legend">
				{{ group.displayName }}
			</legend>
			<NcCheckboxRadioSwitch
				v-for="set in group.allowedTokenSets"
				:key="set.id"
				type="radio"
				:name="`launchpad-group-house-style-${group.group}`"
				:value="set.id"
				:modelValue="group.tokenSet"
				:disabled="savingGroup === group.group"
				@update:modelValue="choose(group, set.id)">
				<span
					v-if="set.primaryColor"
					class="group-house-style__swatch"
					aria-hidden="true"
					:style="{ backgroundColor: set.primaryColor }" />
				{{ set.name }}
				<span class="group-house-style__contrast">
					({{ contrastText(set.wcagLevel) }})
				</span>
			</NcCheckboxRadioSwitch>
		</fieldset>
		<p
			class="group-house-style__status"
			role="status"
			aria-live="polite"
			data-testid="group-house-style-status">
			{{ status }}
		</p>
	</NcAppSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcAppSettingsSection, NcCheckboxRadioSwitch } from '@nextcloud/vue'

/**
 * The thematiq app id. Its API serves the house style of the groups this
 * person manages (per-group theming); LaunchPad only draws the choice.
 */
const THEMATIQ = 'thematiq'

/**
 * GroupHouseStyleSettings: a group subadmin picks the house style of a
 * delegated group from the sets an administrator allows. One radio group per
 * group; a choice saves at once and members see it on their next page load.
 * Moved here from thematiq's personal settings form (11 Oct 2026: no user
 * configuration pages).
 *
 * @spec exclude the requirement is thematiq's per-group-theming spec; LaunchPad only draws its API in the user settings dialog
 */
export default {
	name: 'GroupHouseStyleSettings',

	components: { NcAppSettingsSection, NcCheckboxRadioSwitch },

	data() {
		return {
			groups: [],
			savingGroup: '',
			status: '',
		}
	},

	/** @spec exclude the requirement is thematiq's per-group-theming spec; LaunchPad only draws its API in the user settings dialog */
	async mounted() {
		if (!window.OC?.appswebroots?.[THEMATIQ]) {
			return
		}
		try {
			const response = await axios.get(
				generateUrl(`/apps/${THEMATIQ}/api/my-groups/house-style`),
			)
			this.groups = response?.data?.groups || []
		} catch {
			// No section rather than a broken one: the person manages no group
			// thematiq can answer for.
			this.groups = []
		}
	},

	methods: {
		/**
		 * The contrast result of a set, in words.
		 *
		 * @param {string|null} level The WCAG level, or null.
		 * @return {string} The text.
		 * @spec exclude the requirement is thematiq's per-group-theming spec; LaunchPad only draws its API in the user settings dialog
		 */
		contrastText(level) {
			if (level) {
				return t('launchpad', 'Contrast: WCAG {level}', { level })
			}
			return t('launchpad', 'Contrast: below WCAG AA')
		},

		/**
		 * Save a group's house style. A refused save keeps the set that was
		 * active and says why.
		 *
		 * @param {object} group The group.
		 * @param {string} setId The chosen set.
		 * @spec exclude the requirement is thematiq's per-group-theming spec; LaunchPad only draws its API in the user settings dialog
		 */
		async choose(group, setId) {
			if (setId === group.tokenSet) {
				return
			}
			this.status = ''
			this.savingGroup = group.group
			try {
				await axios.post(
					generateUrl(
						`/apps/${THEMATIQ}/api/my-groups/{group}/house-style`,
						{ group: group.group },
					),
					{ tokenSet: setId },
				)
				group.tokenSet = setId
				this.status = t(
					'launchpad',
					'Saved. Members of {group} see the new house style on their next page.',
					{ group: group.displayName },
				)
			} catch (err) {
				this.status =
					err?.response?.data?.error
					|| t('launchpad', 'The house style could not be saved.')
			} finally {
				this.savingGroup = ''
			}
		},
	},
}
</script>

<style scoped>
.group-house-style__description {
	margin-bottom: calc(var(--default-grid-baseline) * 3);
	color: var(--color-text-maxcontrast);
}

.group-house-style__group {
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.group-house-style__legend {
	font-weight: 600;
}

.group-house-style__swatch {
	display: inline-block;
	width: 1em;
	height: 1em;
	margin-inline-end: 0.5em;
	vertical-align: middle;
	border: 1px solid var(--color-border-dark);
}

.group-house-style__contrast {
	color: var(--color-text-maxcontrast);
}
</style>
