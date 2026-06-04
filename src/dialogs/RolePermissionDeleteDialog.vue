<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="t('mydash', 'Delete role permission')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>{{ t('mydash', 'Delete role permission for group "{group}"?', { group: groupId }) }}</p>
		</template>
		<template #actions>
			<NcButton type="tertiary" @click="$emit('update:open', false)">
				{{ t('mydash', 'Cancel') }}
			</NcButton>
			<NcButton type="error" @click="$emit('confirm')">
				{{ t('mydash', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
} from '@conduction/nextcloud-vue'

export default {
	name: 'RolePermissionDeleteDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		groupId: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'confirm'],
}
</script>
