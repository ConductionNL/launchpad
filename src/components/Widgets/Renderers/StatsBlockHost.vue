<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<CnStatsBlockWidget
		:title="title"
		:data-source="dataSource"
		:countLabel="props.countLabel"
		:variant="props.variant"
		:showZeroCount="props.showZeroCount"
		:horizontal="props.horizontal"
		:route="props.route"
		:iconClass="props.iconClass" />
</template>

<script>
import { CnStatsBlockWidget } from '@conduction/nextcloud-vue'

/**
 * StatsBlockHost — thin LaunchPad wrapper adapting the registry's `content`
 * shape ({ title, dataSource, props:{ countLabel, variant, iconClass, … } })
 * onto CnStatsBlockWidget's separate-prop interface. CnStatsBlockWidget
 * self-fetches its count from `dataSource`; this host only maps content →
 * props, mirroring CnDashboardPage's `getStatsBlockProps`.
 */
export default {
	name: 'StatsBlockHost',

	components: { CnStatsBlockWidget },

	props: {
		/** The placement's content blob — `{ title, dataSource, props }`. */
		content: {
			type: Object,
			default: () => ({}),
		},

		/** The placement record (unused; accepted for the renderer interface). */
		placement: {
			type: Object,
			default: () => ({}),
		},
	},

	computed: {
		/** The card title. */
		title() {
			return this.content.title || ''
		},

		/** The OpenRegister dataSource block CnStatsBlockWidget self-resolves. */
		dataSource() {
			return this.content.dataSource || null
		},

		/** Presentation props (count label, variant, icon, …). */
		props() {
			return this.content.props || {}
		},
	},
}
</script>
