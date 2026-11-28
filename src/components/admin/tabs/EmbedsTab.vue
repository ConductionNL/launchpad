<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="embeds-tab" data-test="embeds-tab">
		<p class="embeds-tab__intro">
			{{ t('launchpad', 'Embed tokens let other websites show a dashboard or widget without a Nextcloud login. Each token names what it shows, which sites may frame it and what viewers may do.') }}
		</p>
		<div class="embeds-tab__section">
			<EmbedTokenManager :defaults="defaults" />
		</div>
		<div class="embeds-tab__section">
			<TenantThemeManager />
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import EmbedTokenManager from '../embed/EmbedTokenManager.vue'
import TenantThemeManager from '../embed/TenantThemeManager.vue'
import { embedDefaultsFrom } from '../../../utils/embedToken.js'

/**
 * EmbedsTab — Beheer ▸ Embeds: the embed token manager and the tenant
 * theme manager (embedded-analytics task 40). The telemetry switch stays
 * under Operations. A link from a dashboard or widget (`embedType`,
 * `embedId`, `embedDashboard`) opens the create form for that subject.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 */
export default {
	name: 'EmbedsTab',

	components: {
		EmbedTokenManager,
		TenantThemeManager,
	},

	data() {
		return {
			defaults: embedDefaultsFrom(typeof window === 'undefined' ? '' : window.location.search),
		}
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.embeds-tab {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.embeds-tab__intro {
	color: var(--color-text-maxcontrast);
	max-width: 760px;
}

.embeds-tab__section {
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}
</style>
