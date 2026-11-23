<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="lp-section-heading" data-testid="section-heading-widget">
		<h2 class="lp-section-heading__title">
			{{ title }}
		</h2>
		<p v-if="description" class="lp-section-heading__description">
			{{ description }}
		</p>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'

/**
 * SectionHeadingWidget: the `section-heading` widget ("Tussenkop", boards
 * LpIntranet and LpWerkplek). A heading over the widgets or app tiles that
 * follow it on the grid: a 20px bold title, one muted line of explanation
 * and a rule below, on the page ground without a card. It spans the grid's
 * full width.
 *
 * @spec openspec/specs/widgets/spec.md#requirement-req-wdg-031-a-section-heading-widget
 */
export default {
	name: 'SectionHeadingWidget',

	props: {
		/**
		 * Persisted content: `{title, description}`.
		 *
		 * @type {{title?: string, description?: string}}
		 */
		content: OBJECT_PROP,
	},

	computed: {
		/**
		 * The heading, or a placeholder while it has none.
		 *
		 * @return {string}
		 * @spec openspec/specs/widgets/spec.md#requirement-req-wdg-031-a-section-heading-widget
		 */
		title() {
			const value = typeof this.content?.title === 'string' ? this.content.title.trim() : ''
			return value || t('launchpad', 'Section heading')
		},

		/**
		 * The line of explanation, or empty.
		 *
		 * @return {string}
		 * @spec openspec/specs/widgets/spec.md#requirement-req-wdg-031-a-section-heading-widget
		 */
		description() {
			return typeof this.content?.description === 'string' ? this.content.description.trim() : ''
		},
	},
}
</script>

<style scoped>
/* Board LpWerkplek: the title over its one line, on the grid's line, the
   rule under both; the one-row cell holds them without clipping the line. */
.lp-section-heading {
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
	gap: 0;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 0 0 4px;
	border-bottom: 1px solid var(--color-border);
}

.lp-section-heading__title {
	margin: 0;
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 700;
	line-height: 1.25;
}

.lp-section-heading__description {
	overflow: hidden;
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.3;
	flex: none;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
