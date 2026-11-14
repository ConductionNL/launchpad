<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - The panel beside the tiles that a `side-panel` tile opens in
  - (launcher-tile-open-behaviour D3, D4). One panel at a time; opening
  - another tile replaces it. Escape or the close button closes it and
  - returns focus to the tile. An outside address is framed only when the
  - server's framing check passes (embed allow-list, X-Frame-Options);
  - otherwise the panel shows a card with "Open in new tab".
  -
  - @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
-->

<template>
	<aside
		v-if="panel.isOpen"
		ref="root"
		class="tile-side-panel"
		:aria-label="panel.title"
		data-test="tile-side-panel"
		tabindex="-1"
		@keydown.esc.stop="panel.close()">
		<header class="tile-side-panel__header">
			<h2 class="tile-side-panel__title">
				{{ panel.title }}
			</h2>
			<a
				class="tile-side-panel__external"
				:href="panel.url"
				target="_blank"
				rel="noopener noreferrer">
				{{ t('launchpad', 'Open in new tab') }}
			</a>
			<NcButton
				variant="tertiary"
				:aria-label="t('launchpad', 'Close')"
				data-test="tile-side-panel-close"
				@click="panel.close()">
				<template #icon>
					<CloseIcon :size="20" />
				</template>
			</NcButton>
		</header>

		<div
			v-if="state === 'refused'"
			class="tile-side-panel__fallback"
			data-test="tile-side-panel-fallback">
			<p>{{ t('launchpad', 'This page could not be loaded.') }}</p>
			<a
				:href="panel.url"
				target="_blank"
				rel="noopener noreferrer">
				{{ t('launchpad', 'Open in new tab') }}
			</a>
		</div>
		<p v-else-if="state === 'checking'" class="tile-side-panel__checking">
			{{ t('launchpad', 'Loading…') }}
		</p>
		<iframe
			v-else
			:key="panel.url"
			class="tile-side-panel__frame"
			:src="panel.url"
			:title="panel.title"
			data-test="tile-side-panel-frame"
			referrerpolicy="strict-origin-when-cross-origin" />
	</aside>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { checkIframeFramable } from '../../services/iframeClient.js'
import { useTilePanelStore } from '../../stores/tilePanel.js'

export default {
	name: 'TileSidePanel',

	components: {
		NcButton,
		CloseIcon,
	},

	setup() {
		return { panel: useTilePanelStore() }
	},

	data() {
		return {
			/** `checking`, `ready` or `refused`. */
			state: 'ready',
		}
	},

	watch: {
		/**
		 * Each newly opened address is checked before it is framed, unless it
		 * is on this Nextcloud.
		 *
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		'panel.url': {
			immediate: true,
			handler(url) {
				this.check(url)
			},
		},
	},

	methods: {
		t,

		/**
		 * Decide whether the address may be framed, then move focus into the panel.
		 *
		 * @param {string|null} url The address, or null when closed.
		 * @return {Promise<void>}
		 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		async check(url) {
			if (!url) {
				return
			}
			if (this.panel.sameOrigin) {
				this.state = 'ready'
			} else {
				this.state = 'checking'
				const result = await checkIframeFramable(url)
				// A newer tile replaced this one while the check ran.
				if (this.panel.url !== url) {
					return
				}
				this.state = result && result.framable === true ? 'ready' : 'refused'
			}
			await this.$nextTick()
			this.$refs.root?.focus?.()
		},
	},
}
</script>

<style scoped>
.tile-side-panel {
	display: flex;
	flex-direction: column;
	width: min(50%, 720px);
	min-width: 320px;
	border-inline-start: 1px solid var(--color-border);
	background: var(--color-main-background);
}

.tile-side-panel:focus {
	outline: none;
}

.tile-side-panel:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.tile-side-panel__header {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 8px 4px 16px;
	border-bottom: 1px solid var(--color-border);
}

.tile-side-panel__title {
	flex: 1;
	margin: 0;
	font-size: var(--default-font-size);
	font-weight: bold;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.tile-side-panel__external,
.tile-side-panel__fallback a {
	color: var(--color-primary-element);
	text-decoration: underline;
}

.tile-side-panel__frame {
	flex: 1;
	width: 100%;
	border: 0;
}

.tile-side-panel__fallback,
.tile-side-panel__checking {
	padding: 24px 16px;
	color: var(--color-text-maxcontrast);
}

/* Below 768px the panel covers the grid instead of sharing the row. */
@media (max-width: 767px) {
	.tile-side-panel {
		position: fixed;
		inset: 0;
		z-index: 1000;
		width: 100%;
		min-width: 0;
		border-inline-start: 0;
	}
}
</style>
