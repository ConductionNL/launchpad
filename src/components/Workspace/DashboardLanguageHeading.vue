<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<header
		v-if="translation"
		class="dashboard-language-heading"
		data-testid="dashboard-language-heading">
		<h2 class="dashboard-language-heading__name">
			{{ translation.name || dashboard?.name }}
		</h2>
		<p
			v-if="translation.description"
			class="dashboard-language-heading__description">
			{{ translation.description }}
		</p>
		<p
			v-if="isFallback"
			class="dashboard-language-heading__note"
			data-testid="dashboard-language-fallback">
			{{ t('launchpad', 'Shown in the primary language') }}
		</p>
	</header>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * DashboardLanguageHeading: for a dashboard with more than one language
 * version, the name and description in the reader's Nextcloud language,
 * from the server's resolver, with a note when the primary is shown
 * because no version matches. A dashboard with one language renders
 * nothing and asks nothing, so it looks as before.
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */
export default {
	name: 'DashboardLanguageHeading',

	props: {
		/** The active dashboard (`uuid`, `name`, `hasVariants`). */
		dashboard: {
			type: Object,
			default: null,
		},
	},

	/** @spec openspec/specs/dashboard-language-content/spec.md */
	data() {
		return { translation: null, isFallback: false }
	},

	watch: {
		dashboard: {
			immediate: true,
			/**
			 * Resolve the language whenever another dashboard opens.
			 *
			 * @param {object|null} dashboard The active dashboard.
			 * @spec openspec/specs/dashboard-language-content/spec.md
			 */
			handler(dashboard) {
				this.load(dashboard)
			},
		},
	},

	methods: {
		t,

		/**
		 * Ask the resolver for the reader's version.
		 *
		 * @param {object|null} dashboard The active dashboard.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-language-content/spec.md
		 */
		async load(dashboard) {
			if (!dashboard?.uuid || dashboard.hasVariants !== true) {
				this.translation = null
				this.isFallback = false
				return
			}
			try {
				const { data } = await api.getResolvedDashboard(dashboard.uuid)
				this.translation = data?.translation ?? null
				this.isFallback = data?.isFallback === true
			} catch (error) {
				logger.warn(
					'[DashboardLanguageHeading] could not resolve the language',
					error,
				)
				this.translation = null
				this.isFallback = false
			}
		},
	},
}
</script>

<style scoped>
.dashboard-language-heading {
	margin: 0 8px 8px;
}

.dashboard-language-heading__name {
	margin: 0;
	font-size: 1.4em;
}

.dashboard-language-heading__description {
	margin: 4px 0 0;
}

.dashboard-language-heading__note {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
