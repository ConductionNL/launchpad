<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="sentence"
		class="launchpad-phone-hidden"
		role="status"
		data-testid="phone-hidden-notice">
		<NcNoteCard type="info">
			<p class="launchpad-phone-hidden__text">
				<span data-testid="phone-hidden-sentence">{{ sentence }}</span>
				<NcButton
					variant="tertiary"
					class="launchpad-phone-hidden__which"
					:aria-expanded="explained ? 'true' : 'false'"
					data-testid="phone-hidden-which"
					@click="explained = !explained">
					{{ t('launchpad', 'Which?') }}
				</NcButton>
			</p>
			<p
				v-if="explained"
				class="launchpad-phone-hidden__why"
				data-testid="phone-hidden-why">
				{{ t('launchpad', 'Some widgets need a larger screen, so a phone hides them. On a tablet or computer you see them as usual.') }}
			</p>
		</NcNoteCard>
	</div>
</template>

<script>
import { NcButton, NcNoteCard } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { phoneHiddenSentence } from '../../utils/phoneHiddenWidgets.js'

/**
 * PhoneHiddenNotice: on a phone, says how many widgets are hidden and names
 * them (three, then "and N more"), with "Which?" explaining why. It offers
 * no desktop layout (board LpMobiel).
 *
 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
export default {
	name: 'PhoneHiddenNotice',

	components: {
		NcButton,
		NcNoteCard,
	},

	props: {
		/** The titles of the widgets the phone hides. */
		titles: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			explained: false,
		}
	},

	computed: {
		/**
		 * The notice sentence, empty when nothing is hidden.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
		 */
		sentence() {
			return phoneHiddenSentence(this.titles)
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.launchpad-phone-hidden {
	margin: 0 0 12px;
}

.launchpad-phone-hidden__text,
.launchpad-phone-hidden__why {
	margin: 0;
}

.launchpad-phone-hidden__why {
	margin-top: 4px;
}
</style>
