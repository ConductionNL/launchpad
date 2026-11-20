<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal"
		size="normal"
		:name="t('launchpad', 'Visibility rules')"
		@close="$emit('close')">
		<div class="visibility-rules" data-test="visibility-rules-modal">
			<h2 class="visibility-rules__title">
				{{ t('launchpad', 'Visibility rules') }}
			</h2>

			<ConditionalVisibilityEditor
				:placementId="placementId"
				:availableGroups="offeredGroups"
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
import { FLAG_PROP, LIST_PROP } from '../constants/propDefinitions.js'
import { api } from '../services/api.js'

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
		open: FLAG_PROP,

		/** Optional group-id list for the group-rule picker. */
		availableGroups: LIST_PROP,
	},

	emits: ['close', 'ruleAdded', 'ruleUpdated', 'ruleRemoved'],

	data() {
		return {
			loadedGroups: [],
		}
	},

	computed: {
		/**
		 * The groups the pickers offer: the passed list, else the instance's groups.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-002-per-rule-row-editor-for-all-four-rule-types
		 */
		offeredGroups() {
			return this.availableGroups.length > 0 ? this.availableGroups : this.loadedGroups
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Read the groups on opening when none were passed.
			 *
			 * @param {boolean} isOpen whether the modal is shown.
			 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-002-per-rule-row-editor-for-all-four-rule-types
			 */
			handler(isOpen) {
				if (isOpen && this.availableGroups.length === 0) {
					this.loadGroups()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Read every Nextcloud group as `{id, displayName}`. The list is
		 * admin-only; anyone else keeps an empty list, and a stored rule
		 * still shows the groups it names.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-002-per-rule-row-editor-for-all-four-rule-types
		 */
		async loadGroups() {
			try {
				const res = await api.getAdminGroups()
				const allKnown = res?.data?.allKnown
				this.loadedGroups = Array.isArray(allKnown) ? allKnown : []
			} catch {
				this.loadedGroups = []
			}
		},
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
