<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="t('mydash', 'Delete layout default')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>{{ t('mydash', 'Delete layout default for "{group}" / "{widget}"?', { group: groupId, widget: widgetId }) }}</p>
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
	name: 'RoleLayoutDefaultDeleteDialog',

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
		widgetId: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'confirm'],
}
</script>
