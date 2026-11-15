<!--
SPDX-FileCopyrightText: 2026 Conduction B.V.
SPDX-License-Identifier: EUPL-1.2

The page an embed iframe shows (embedded-analytics task 23): the widget or
dashboard its token names, read-only or with the interactions the token
allows, without navigation chrome. The subject, scope and filters come from
`/embed/{subjectType}/{subjectId}/data`; the tenant theme's :root block is
already on the page.

@spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
-->
<template>
	<main class="embed-view" :aria-busy="loading ? 'true' : 'false'">
		<div v-if="loading" class="embed-view__loading">
			<NcLoadingIcon :size="32" :name="t('launchpad', 'Loading')" />
		</div>
		<NcEmptyContent
			v-else-if="error"
			:name="t('launchpad', 'This embed is not available')"
			:description="errorText" />
		<template v-else-if="payload">
			<h1 v-if="title" class="embed-view__title">
				{{ title }}
			</h1>
			<PublicDashboardGrid :placements="placements" />
		</template>
	</main>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PublicDashboardGrid from '../components/PublicDashboardGrid.vue'
import { useEmbedContext } from '../composables/useEmbedContext.js'

/**
 * Fetch the subject with the token as a Bearer credential.
 *
 * @param {string} url The data URL.
 * @param {string} token The embed token.
 * @param {object} params Query parameters.
 * @return {Promise<object>}
 */
async function loadSubject(url, token, params) {
	const { data } = await axios.get(url, { headers: { Authorization: `Bearer ${token}` }, params })
	return data
}

export default {
	name: 'EmbedView',
	components: { NcEmptyContent, NcLoadingIcon, PublicDashboardGrid },

	setup() {
		const context = useEmbedContext({ win: window, load: loadSubject })
		let stop = () => {}
		onMounted(() => {
			stop = context.start()
		})
		onBeforeUnmount(() => stop())

		const title = computed(() => context.payload.value?.dashboard?.name || '')
		const errorText = computed(() => {
			switch (context.error.value?.error) {
			case 'token_expired':
				return t('launchpad', 'The link to this content has expired.')
			case 'token_revoked':
				return t('launchpad', 'The link to this content has been withdrawn.')
			case 'origin_mismatch':
				return t('launchpad', 'This content cannot be shown on this website.')
			default:
				return t('launchpad', 'The content could not be loaded.')
			}
		})

		return {
			loading: context.loading,
			error: context.error,
			payload: context.payload,
			placements: context.placements,
			title,
			errorText,
		}
	},
}
</script>

<style scoped>
.embed-view {
	padding: 8px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.embed-view__loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.embed-view__title {
	font-size: 1.25rem;
	margin: 0 0 8px;
}
</style>
