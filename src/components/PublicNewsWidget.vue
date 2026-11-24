<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="public-news-widget" data-test="public-news-widget">
		<NcLoadingIcon v-if="loading" :size="24" />
		<p v-else-if="failed" class="public-news-widget__state">
			{{ t('launchpad', 'News is currently unavailable.') }}
		</p>
		<p v-else-if="items.length === 0" class="public-news-widget__state">
			{{ t('launchpad', 'No news yet.') }}
		</p>
		<ul v-else class="public-news-widget__list">
			<li
				v-for="item in items"
				:key="item.uuid"
				class="public-news-widget__item"
				data-test="public-news-item">
				<header class="public-news-widget__meta">
					<span v-if="item.category">{{ item.category }}</span>
					<time v-if="item.publishAt" :datetime="item.publishAt">
						{{ formatDate(item.publishAt) }}
					</time>
				</header>
				<h4 class="public-news-widget__title">
					{{ item.title }}
				</h4>
				<p v-if="item.body" class="public-news-widget__body">
					{{ item.body }}
				</p>
			</li>
		</ul>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcLoadingIcon } from '@nextcloud/vue'

/**
 * PublicNewsWidget: the news (announcements) widget as an anonymous
 * visitor sees it on a public link or kiosk screen. It reads the
 * published announcements for everyone through the token-scoped read and
 * lists them read-only, without likes or comments (REQ-PSHR-011).
 *
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
export default {
	name: 'PublicNewsWidget',

	components: { NcLoadingIcon },

	props: {
		/** The placement id the read is for. */
		placementId: {
			type: [Number, String],
			required: true,
		},

		/** The token-scoped read: placement id to `{widget, data}`. */
		readWidget: {
			type: Function,
			required: true,
		},
	},

	data() {
		return {
			loading: true,
			failed: false,
			items: [],
		}
	},

	mounted() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Load the news items; a failed read shows that news is unavailable.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
		 */
		async load() {
			this.loading = true
			this.failed = false
			try {
				const result = await this.readWidget(this.placementId)
				const list = result?.data?.announcements
				this.items = Array.isArray(list) ? list : []
			} catch {
				this.items = []
				this.failed = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * A publish date in the visitor's locale.
		 *
		 * @param {string} value The ISO date.
		 * @return {string} The date, or '' when it does not parse.
		 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
		 */
		formatDate(value) {
			const date = new Date(value)
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},
	},
}
</script>

<style scoped>
.public-news-widget__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.public-news-widget__item + .public-news-widget__item {
	margin-top: 12px;
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
}

.public-news-widget__meta {
	display: flex;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.public-news-widget__title {
	margin: 4px 0 0;
	font-weight: 700;
}

.public-news-widget__body {
	margin: 4px 0 0;
}

.public-news-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
