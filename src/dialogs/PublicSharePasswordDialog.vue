<!--
SPDX-FileCopyrightText: 2026 Conduction B.V.
SPDX-License-Identifier: EUPL-1.2

Password-unlock dialog for password-protected public dashboard shares.
Emits 'unlock' with the submitted password; parent handles the API call.

@spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
-->
<template>
	<NcDialog :name="t('launchpad', 'Password required')" :noClose="true">
		<template #default>
			<p>
				{{
					t(
						'launchpad',
						'This dashboard is password protected. Enter the password to view it.',
					)
				}}
			</p>
			<NcPasswordField
				v-model="passwordInput"
				:label="t('launchpad', 'Password')"
				autocomplete="off"
				@keyup.enter="submit" />
			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
		</template>
		<template #actions>
			<NcButton variant="primary" :disabled="loading" @click="submit">
				{{ t('launchpad', 'Unlock') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { defineComponent, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

export default defineComponent({
	name: 'PublicSharePasswordDialog',

	components: {
		NcButton,
		NcDialog,
		NcNoteCard,
		NcPasswordField,
	},

	props: {
		/** Error message to display (e.g. 'Incorrect password'). Null = no error. */
		error: {
			type: String,
			default: null,
		},

		/** Whether an unlock request is in flight. */
		loading: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['unlock'],

	/**
	 * The password gate's own state: the typed password and the submit path
	 * that hands it to the parent view, which sends it as the unlock
	 * credential. The dialog never talks to the API itself and never stores
	 * the password — REQ-PSHR-005 keeps unlock a server-side check.
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#req-pshr-005
	 * @param {object} props the component props.
	 * @param {object} ctx the setup context (`emit`).
	 * @param {(event: string, ...args: unknown[]) => void} ctx.emit the emit helper.
	 * @return {object} the template bindings.
	 */
	setup(props, { emit }) {
		const passwordInput = ref('')

		const submit = () => {
			if (props.loading === true) return
			emit('unlock', passwordInput.value)
		}

		return { passwordInput, submit, t }
	},
})
</script>
