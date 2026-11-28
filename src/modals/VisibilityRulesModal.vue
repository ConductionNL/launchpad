<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal"
		size="normal"
		:name="t('launchpad', 'Who sees this widget?')"
		@close="$emit('close')">
		<div class="visibility-rules" data-test="visibility-rules-modal">
			<!-- LpZichtbaarheid: "<widget> · <dashboard>" small above the title
			     (REQ-CVUI-006), as the other launchpad dialogs draw it. -->
			<header class="visibility-rules__head">
				<p
					v-if="subtitle"
					class="visibility-rules__subtitle launchpad-modal__kicker"
					data-test="visibility-rules-subtitle">
					{{ subtitle }}
				</p>
				<h2 class="visibility-rules__title launchpad-modal__title" data-test="visibility-rules-title">
					{{ t('launchpad', 'Who sees this widget?') }}
				</h2>
			</header>

			<ConditionalVisibilityEditor
				:placementId="placementId"
				:availableGroups="offeredGroups"
				:ruleMatch="ruleMatch"
				:draft="draft"
				data-test="conditional-visibility-editor"
				@ruleAdded="$emit('ruleAdded')"
				@ruleUpdated="$emit('ruleUpdated')"
				@ruleRemoved="$emit('ruleRemoved')"
				@ruleMatchChanged="$emit('ruleMatchChanged', $event)" />
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
 * VisibilityRulesModal — "Who sees this widget?" (board LpZichtbaarheid,
 * conditional-visibility-editor REQ-CVUI-001 and REQ-CVUI-006). Titled with
 * the widget and the dashboard under it; opens from the widget's menu and
 * its right-click menu in edit mode, and hosts `ConditionalVisibilityEditor`
 * as its single section body; all rule builder/list/preview behaviour lives
 * there.
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

		/** The widget's title, shown under the dialog title. */
		widgetTitle: {
			type: String,
			default: '',
		},

		/** The dashboard's name, shown after the widget's title. */
		dashboardName: {
			type: String,
			default: '',
		},

		/** How the widget's include rules combine: `any` or `all`. */
		ruleMatch: {
			type: String,
			default: 'any',
		},

		/** REQ-EDIT-001: the open edit-mode draft the dialog writes, or null. */
		draft: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'ruleAdded', 'ruleUpdated', 'ruleRemoved', 'ruleMatchChanged'],

	data() {
		return {
			loadedGroups: [],
		}
	},

	computed: {
		/**
		 * The line under the title: "<widget> · <dashboard>", or whichever
		 * of the two is known.
		 *
		 * @return {string} the line, empty when neither is known.
		 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		subtitle() {
			return [this.widgetTitle, this.dashboardName].filter((part) => part).join(' · ')
		},

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

.visibility-rules__head {
	display: flex;
	flex-direction: column;
	gap: 2px;
	margin: 0 0 20px;
	padding-inline-end: 32px;
}
</style>
