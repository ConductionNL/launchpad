<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="connections-widget-form">
		<p class="connections-widget-form__hint">
			{{ t('launchpad', 'Lists the outside connections the widgets on this dashboard use, with the state the Integrations app holds for each. Each person sees what OpenRegister lets them see.') }}
		</p>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'

/**
 * ConnectionsWidgetForm: the `connections` widget has no settings; the
 * form says what it shows.
 *
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export default {
	name: 'ConnectionsWidgetForm',

	props: {
		/** The placement being edited, or `null` in create mode. */
		// eslint-disable-next-line vue/no-unused-properties
		editingWidget: NULLABLE_OBJECT_PROP,
		/** Initial content values, used when not editing. */
		// eslint-disable-next-line vue/no-unused-properties
		value: objectProp({}),
	},

	emits: ['update:content'],

	/** @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections */
	mounted() {
		this.$emit('update:content', {})
	},

	methods: {
		t,

		/**
		 * Nothing to validate.
		 *
		 * @return {string[]} No errors.
		 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.connections-widget-form__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
