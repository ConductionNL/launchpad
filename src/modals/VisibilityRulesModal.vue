<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		size="normal"
		:name="t('launchpad', 'Visibility rules')"
		@close="$emit('close')">
		<div class="visibility-rules" data-test="visibility-rules-modal">
			<h2 class="visibility-rules__title">
				{{ t('launchpad', 'Visibility rules') }}
			</h2>

			<ConditionalVisibilityEditor
				:placementId="placementId"
				:availableGroups="availableGroups"
				data-test="conditional-visibility-editor"
				@ruleAdded="$emit('ruleAdded')"
				@ruleUpdated="$emit('ruleUpdated')"
				@ruleRemoved="$emit('ruleRemoved')" />
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import ConditionalVisibilityEditor from '../components/Widgets/ConditionalVisibilityEditor.vue'

/**
 * VisibilityRulesModal — the placement's "Visibility" settings surface
 * (conditional-visibility-editor spec, REQ-CVUI-001). Opens from the widget
 * context menu and hosts `ConditionalVisibilityEditor` as its single
 * section body; all rule builder/list/preview behaviour lives there.
 */
export default {
	name: 'VisibilityRulesModal',

	components: {
		NcModal,
		ConditionalVisibilityEditor,
	},

	props: {
		/** Placement id whose rules are being edited. */
		placementId: {
			type: [Number, String],
			default: null,
		},

		/** Whether the modal is shown. */
		open: {
			type: Boolean,
			default: false,
		},

		/** Optional group-id list for the group-rule picker. */
		availableGroups: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'ruleAdded', 'ruleUpdated', 'ruleRemoved'],

	methods: {
		t,
	},
}
</script>

<style scoped>
.visibility-rules {
	padding: 24px;
}

.visibility-rules__title {
	margin: 0 0 8px;
}
</style>
