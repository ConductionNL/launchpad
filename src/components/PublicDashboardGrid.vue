<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="public-dashboard-grid">
		<div v-if="items.length > 0" class="public-share-view__grid">
			<div
				v-for="item in items"
				:key="item.id"
				class="public-share-view__cell"
				:style="{ gridColumn: 'span ' + Math.min(item.gridWidth, 12) }">
				<!-- Tile — icon + label linking to its target. -->
				<a
					v-if="item.kind === 'tile'"
					class="public-share-view__tile"
					:href="item.tileLink || '#'"
					:style="{
						backgroundColor: item.tileBackgroundColor || undefined,
						color: item.tileTextColor || undefined,
					}">
					<img
						v-if="
							item.tileIcon
							&& /^(data:|https?:|\/)/.test(item.tileIcon)
						"
						class="public-share-view__tile-icon"
						:src="item.tileIcon"
						alt="" />
					<span class="public-share-view__tile-title">{{
						item.title
					}}</span>
				</a>

				<!-- Static custom widgets that render safely for anonymous visitors. -->
				<div v-else class="public-share-view__widget">
					<h2
						v-if="
							item.showTitle && item.title && item.kind !== 'divider'
						"
						class="public-share-view__widget-title">
						{{ item.title }}
					</h2>
					<hr
						v-if="item.kind === 'divider'"
						class="public-share-view__divider" />
					<h3
						v-else-if="item.kind === 'header' || item.kind === 'label'"
						class="public-share-view__widget-heading">
						{{ item.text || item.title }}
					</h3>
					<p
						v-else-if="item.kind === 'text'"
						class="public-share-view__widget-text">
						{{ item.text }}
					</p>
					<img
						v-else-if="item.kind === 'image' && item.url"
						class="public-share-view__widget-image"
						:src="item.url"
						:alt="item.title" />
					<a
						v-else-if="item.kind === 'link' && item.url"
						class="public-share-view__widget-link"
						:href="item.url">
						{{ item.title || item.url }}
					</a>
					<p v-else class="public-share-view__widget-restricted">
						{{
							t(
								'launchpad',
								'This widget is only visible to signed-in users.',
							)
						}}
					</p>
				</div>
			</div>
		</div>
		<p v-else class="public-share-view__empty">
			{{ t('launchpad', 'This dashboard has no publicly viewable content.') }}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

/**
 * PublicDashboardGrid: the read-only, anonymous-safe rendering of a
 * dashboard's placements. Tiles and static widgets (label, header, text,
 * image, link, divider) render; any other widget shows that it is only
 * visible to signed-in users. Shared by the public share page and the
 * kiosk player.
 *
 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'PublicDashboardGrid',

	props: {
		placements: {
			type: Array,
			default: () => [],
		},
	},

	computed: {
		/**
		 * @return {Array<object>} Visible placements in reading order, normalised for display.
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		items() {
			const list = [...this.placements].filter(
				(p) => p.isVisible !== 0 && p.isVisible !== false,
			)
			list.sort((a, b) => a.gridY - b.gridY || a.gridX - b.gridX)
			return list.map((p) => {
				const content =
					p.content && typeof p.content === 'object' ? p.content : {}
				const widgetId = p.widgetId || ''
				const isTile = widgetId.startsWith('tile-') || !!p.tileType
				let kind = 'restricted'
				if (isTile) {
					kind = 'tile'
				} else if (
					['label', 'header', 'text', 'image', 'link', 'divider'].includes(
						widgetId,
					)
				) {
					kind = widgetId
				}
				return {
					id: p.id,
					kind,
					gridWidth: p.gridWidth || 4,
					gridHeight: p.gridHeight || 4,
					title:
						p.customTitle
						|| p.tileTitle
						|| content.label
						|| content.title
						|| '',
					showTitle: p.showTitle !== 0 && p.showTitle !== false,
					tileIcon: p.tileIcon || '',
					tileBackgroundColor: p.tileBackgroundColor || '',
					tileTextColor: p.tileTextColor || '',
					tileLink: p.tileLinkValue || '',
					text: content.text || content.body || '',
					url: content.url || '',
				}
			})
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.public-share-view__grid {
	display: grid;
	grid-template-columns: repeat(12, 1fr);
	gap: 12px;
	max-width: 1200px;
}

.public-share-view__cell {
	min-width: 0;
}

.public-share-view__tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	height: 100%;
	min-height: 96px;
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	text-decoration: none;
	text-align: center;
	font-weight: 600;
}

.public-share-view__tile-icon {
	width: 40px;
	height: 40px;
}

.public-share-view__widget {
	height: 100%;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.public-share-view__widget-title {
	margin: 0 0 8px;
	font-size: 1.05em;
	font-weight: 700;
}

.public-share-view__widget-heading {
	margin: 0;
	font-weight: 700;
}

.public-share-view__widget-text {
	margin: 0;
	white-space: pre-wrap;
}

.public-share-view__widget-image {
	max-width: 100%;
	height: auto;
	border-radius: var(--border-radius);
}

.public-share-view__widget-restricted {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

.public-share-view__divider {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 8px 0;
}

.public-share-view__empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
