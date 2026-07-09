<!--
SPDX-FileCopyrightText: 2026 Conduction B.V.
SPDX-License-Identifier: EUPL-1.2

Password-unlock dialog for password-protected public dashboard shares.
Emits 'unlock' with the submitted password; parent handles the API call.

@spec openspec/changes/dashboard-public-share/specs/dashboard-public-share/spec.md
-->
<template>
	<NcDialog :name="t('launchpad', 'Password required')"
		:can-close="false">
		<template #default>
			<p>{{ t('launchpad', 'This dashboard is password protected. Enter the password to view it.') }}</p>
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
			<NcButton type="primary" :disabled="loading" @click="submit">
				{{ t('launchpad', 'Unlock') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { defineComponent, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'

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
