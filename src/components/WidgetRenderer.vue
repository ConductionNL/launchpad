<!--
  - SPDX-FileCopyrightText: 2024 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="widget-renderer" :class="{ 'widget-renderer--flush': isFullBleed }">
		<!-- Registry-driven custom widget (label, text, image, link, header,
		     divider, files, people, quicklinks, news, video, calendar, links,
		     menu, container, tile, nc-widget). The placement's content blob
		     is forwarded so each renderer reads its own type-specific shape
		     without further branching here. -->
		<component
			:is="registryEntry.renderer"
			v-if="registryEntry"
			:content="normalizedContent"
			:placement="placement"
			v-bind="rendererProps" />

		<!-- Custom Tile Widget (legacy path: widgetId === 'tile-{id}') -->
		<TileWidget
			v-else-if="isTileWidget && tileData"
			:tile="tileData" />

		<!-- API Widget V1 or V2 - Use NcDashboardWidget -->
		<template v-else-if="isApiWidget">
			<NcDashboardWidget
				:items="widgetItems"
				:show-more-url="widget.widgetUrl"
				:loading="loading || itemsLoading"
				:round-icons="widget.itemIconsRound">
				<template #empty-content>
					<NcEmptyContent
						v-if="emptyContentMessage"
						:description="emptyContentMessage">
						<template #icon>
							<span :class="widget.iconClass" />
						</template>
					</NcEmptyContent>
				</template>
			</NcDashboardWidget>
		</template>

		<!-- Legacy Widget - Mount via callback -->
		<div v-else-if="!loading" ref="legacyWidgetContainer" class="widget-renderer__legacy" />

		<!-- Loading state for unknown widget types -->
		<div v-else-if="loading" class="widget-renderer__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Unknown widget type -->
		<NcEmptyContent
			v-else
			:description="t('launchpad', 'Widget not available')">
			<template #icon>
				<AlertCircleOutline :size="48" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcDashboardWidget, NcEmptyContent, NcLoadingIcon } from '@conduction/nextcloud-vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import { mapActions, storeToRefs } from 'pinia'
import { useWidgetStore } from '../stores/widgets.js'
import { useTileStore } from '../stores/tiles.js'
import { widgetBridge } from '../services/widgetBridge.js'
import TileWidget from './TileWidget.vue'
import { getWidgetTypeEntry } from '../constants/widgetRegistry.js'
import { buildWidgetDataProvide, buildRendererExtraProps } from '../services/widgetDataAdapters.js'

export default {
	name: 'WidgetRenderer',

	components: {
		NcDashboardWidget,
		NcEmptyContent,
		NcLoadingIcon,
		AlertCircleOutline,
		TileWidget,
	},

	props: {
		widget: {
			type: Object,
			default: null,
		},
		placement: {
			type: Object,
			required: true,
		},
	},

	/**
	 * Provide the data-source adapters the nc-vue data widgets inject
	 * (`cnPeopleSource` / `cnSpendAnalyticsSource`), bridging them to
	 * launchpad's existing endpoints/services so the shared renderers stay
	 * app-agnostic. News uses the `itemsEndpoint` prop instead (see
	 * `rendererProps`).
	 *
	 * @return {object} the injected data-source adapters.
	 */
	provide() {
		return buildWidgetDataProvide(() => this.placement?.id)
	},

	data() {
		return {
			loading: false, // Start false, will be set to true for API widgets only
			itemsLoading: false,
			refreshInterval: null,
			// Local reactive data for widget items.
			localWidgetItemsData: { items: [], loading: false },
		}
	},

	computed: {
		/**
		 * Widget `content` blob, guaranteed to be a plain object.
		 *
		 * The backend (`WidgetPlacement::jsonSerialize()`) emits `{}` for an
		 * unset content column, so a plain `|| {}` fallback covers the
		 * remaining null/undefined cases.
		 *
		 * @return {object} the content object (empty when unset).
		 *
		 * @spec openspec/specs/widgets/spec.md
		 */
		normalizedContent() {
			return this.placement?.content || {}
		},

		/**
		 * Resolve the registry entry for this placement's widget type.
		 * Returns null when the widgetId is not a registry-driven custom
		 * type — falling back to the existing tile / API-widget / legacy
		 * branches. The registry filters out entries with a null `form`,
		 * but this dispatcher only needs `renderer`, so we go through
		 * `getWidgetTypeEntry` to keep types like `nc-widget` (renderer-only
		 * proxy) flowing through this branch as well.
		 */
		/** @spec openspec/specs/widgets/spec.md */
		registryEntry() {
			const widgetId = this.placement?.widgetId
			if (typeof widgetId !== 'string' || widgetId === '') {
				return null
			}
			const entry = getWidgetTypeEntry(widgetId)
			if (!entry || !entry.renderer) {
				return null
			}
			return entry
		},

		/**
		 * Full-bleed widget types paint their own edge-to-edge surface (banner
		 * image/colour, divider rule) and must not be inset by the renderer's
		 * default 16px padding — otherwise a header banner leaves a gap inside
		 * its cell. Other widgets keep the padding for breathing room.
		 *
		 * @spec openspec/specs/widgets/spec.md
		 * @return {boolean} true when the widget should render edge-to-edge.
		 */
		isFullBleed() {
			return ['header', 'image', 'divider'].includes(this.placement?.widgetId)
		},

		/**
		 * Per-widget-type extra props bound onto the registry renderer.
		 * nc-vue's CnNewsWidget pulls items from a consumer-supplied
		 * `itemsEndpoint` builder pointing at launchpad's news endpoint.
		 *
		 * @return {object} extra props for `<component :is>` (empty for most types).
		 */
		rendererProps() {
			return buildRendererExtraProps(this.placement?.widgetId)
		},

		/** @spec openspec/specs/widgets/spec.md */
		isTileWidget() {
			if (this.placement.widgetId && this.placement.widgetId.startsWith('tile-')) {
				return true
			}
			// Inline tiles carry their config on the placement (tileType set)
			// and may use a non-`tile-` widgetId — the export/import and demo-
			// showcase format tags them `mydash-tile`. Treat any placement
			// with a tileType as a tile so those render too.
			return Boolean(this.placement.tileType)
		},

		/** @spec openspec/specs/widgets/spec.md */
		tileId() {
			if (!this.isTileWidget) return null
			return parseInt(this.placement.widgetId.replace('tile-', ''))
		},

		/** @spec openspec/specs/widgets/spec.md */
		tileData() {
			if (!this.isTileWidget) return null
			// Inline tile: the config lives on the placement itself
			// (export/import + demo-showcase format), so build the tile
			// object directly instead of resolving it from the tile store.
			if (this.placement.tileType) {
				return {
					id: this.placement.id,
					title: this.placement.tileTitle,
					icon: this.placement.tileIcon,
					iconType: this.placement.tileIconType,
					backgroundColor: this.placement.tileBackgroundColor,
					textColor: this.placement.tileTextColor,
					linkType: this.placement.tileLinkType,
					linkValue: this.placement.tileLinkValue,
				}
			}
			// Referenced tile: resolve the Tile entity from the store by id.
			const { tiles } = storeToRefs(useTileStore())
			return tiles.value.find(t => t.id === this.tileId)
		},

		isApiWidgetV2() {
			return this.widget?.itemApiVersions?.includes(2)
		},

		isApiWidgetV1() {
			return this.widget?.itemApiVersions?.includes(1)
		},

		isApiWidget() {
			return this.isApiWidgetV1 || this.isApiWidgetV2
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetItemsData() {
			// Return local reactive data that is updated by watcher.
			return this.localWidgetItemsData
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetItems() {
			const items = this.widgetItemsData.items || []
			console.log('[WidgetRenderer] widgetItems computed:', {
				widgetId: this.widget?.id,
				rawItems: items,
				itemsLength: items.length,
				widgetItemsData: this.widgetItemsData,
			})
			// Pass through all original item fields and add NcDashboardWidgetItem
			// prop aliases on top. This ensures custom widget fields are preserved
			// while mapping standard Nextcloud API fields (title, subtitle, link,
			// iconUrl, sinceId) to the prop names NcDashboardWidgetItem expects
			// (mainText, subText, targetUrl, avatarUrl, id).
			// NcDashboardWidget keys its item list by `item.id`. Some API
			// widgets (e.g. recommendations) reuse a shared `sinceId` across
			// rows, so build a compound key from stable per-row data
			// (sinceId + id + targetUrl/title) to stay unique. The key is
			// anchored on stable values — never the array index alone — so a
			// reorder of the upstream items moves DOM nodes instead of tearing
			// them down. `index` is only a last-resort fallback when a row
			// carries no stable identifying field at all.
			return items.map((item, index) => ({
				...item,
				id: `${item.sinceId || ''}-${item.id || ''}-${item.link || item.targetUrl || item.title || index}`,
				targetUrl: item.link || item.targetUrl || '',
				avatarUrl: item.iconUrl || item.avatarUrl || '',
				avatarUsername: item.avatarUsername || '',
				overlayIconUrl: item.overlayIconUrl || '',
				mainText: item.title || item.mainText || '',
				subText: item.subtitle || item.subText || '',
			}))
		},

		/** @spec openspec/specs/widgets/spec.md */
		emptyContentMessage() {
			return this.widgetItemsData.emptyContentMessage || ''
		},
	},

	watch: {
		widget: {
			immediate: false, // Don't run immediately, wait for mounted
			/** @spec openspec/specs/widgets/spec.md */
			handler(newWidget) {
				console.log('[WidgetRenderer] widget watch triggered:', newWidget?.id, newWidget)
				if (newWidget || this.isTileWidget) {
					this.initWidget()
				}
			},
		},
		placement: {
			immediate: false, // Don't run immediately
			/** @spec openspec/specs/widgets/spec.md */
			handler() {
				console.log('[WidgetRenderer] placement watch triggered:', this.placement)
				if (this.isTileWidget) {
					this.loading = false
				}
			},
		},
	},

	/** @spec openspec/specs/widgets/spec.md */
	mounted() {
		// Initialize widget after component is mounted and refs are available
		console.log('[WidgetRenderer] mounted hook called')
		// Set up store subscription.
		this.setupStoreSubscription()
		// Registry-driven custom widgets render their own template directly
		// from `placement.content` and never need the API/legacy bootstrap
		// paths — skip initWidget() so we don't fire a `loadWidgetItems`
		// fetch keyed on a Nextcloud-Dashboard widget id that doesn't exist.
		if (this.registryEntry) {
			return
		}
		if (this.widget || this.isTileWidget) {
			this.initWidget()
		}
	},

	/** @spec openspec/specs/widgets/spec.md */
	beforeDestroy() {
		if (this.refreshInterval) {
			clearInterval(this.refreshInterval)
		}
		// Clean up store subscription.
		if (this.unsubscribe) {
			this.unsubscribe()
		}
	},

	methods: {
		...mapActions(useWidgetStore, ['loadWidgetItems', 'refreshWidgetItems']),

		/** @spec openspec/specs/widgets/spec.md */
		setupStoreSubscription() {
			// Subscribe to store changes.
			const widgetStore = useWidgetStore()

			this.unsubscribe = widgetStore.$subscribe((mutation, state) => {
				// Check if our widget's items were updated.
				if (this.widget?.id && state.widgetItems[this.widget.id]) {
					const newData = state.widgetItems[this.widget.id]
					console.log('[WidgetRenderer] Store subscription fired for:', this.widget.id, newData)
					this.localWidgetItemsData = { ...newData }
				}
			})
		},

		/** @spec openspec/specs/widgets/spec.md */
		updateLocalWidgetItems() {
			if (!this.widget?.id) return
			const widgetStore = useWidgetStore()
			const data = widgetStore.widgetItems[this.widget.id]
			if (data) {
				console.log('[WidgetRenderer] updateLocalWidgetItems:', this.widget.id, data)
				this.localWidgetItemsData = { ...data }
			}
		},

		/** @spec openspec/specs/widgets/spec.md */
		async initWidget() {
			console.log('[WidgetRenderer] initWidget called:', {
				widgetId: this.widget?.id,
				isTileWidget: this.isTileWidget,
				isApiWidget: this.isApiWidget,
				isApiWidgetV1: this.isApiWidgetV1,
				isApiWidgetV2: this.isApiWidgetV2,
				itemApiVersions: this.widget?.itemApiVersions,
				fullWidget: this.widget,
			})

			if (!this.widget && !this.isTileWidget) {
				this.loading = false
				return
			}

			// Tiles don't need initialization.
			if (this.isTileWidget) {
				this.loading = false
				return
			}

			console.log('[WidgetRenderer] Initializing widget:', this.widget.id, this.widget)

			// Only show loading for API widgets
			// Legacy widgets render themselves, so we don't need a loading state
			const isLegacy = !this.isApiWidget
			if (!isLegacy) {
				this.loading = true
			}

			try {
				if (this.isApiWidget) {
					console.log('[WidgetRenderer] Detected as API widget')
					// Load widget items from API (supports both v1 and v2).
					await this.loadWidgetItems([this.widget.id])
					// Explicitly sync local data from store after loading.
					this.updateLocalWidgetItems()

					// Set up auto-refresh if widget supports it.
					if (this.widget.reloadInterval && this.widget.reloadInterval > 0) {
						this.setupAutoRefresh(this.widget.reloadInterval)
					}
				} else {
					console.log('[WidgetRenderer] Legacy widget detected:', this.widget.id)
					// Legacy widget - mount via callback.
					// Wait for DOM to be ready
					await this.$nextTick()
					// Give it a bit more time for the ref to be available
					await new Promise(resolve => setTimeout(resolve, 50))
					this.mountLegacyWidget()
				}
			} catch (error) {
				console.error('Failed to initialize widget:', error)
			} finally {
				if (!isLegacy) {
					this.loading = false
				}
			}
		},

		/** @spec openspec/specs/widgets/spec.md */
		mountLegacyWidget() {
			if (!this.$refs.legacyWidgetContainer) {
				console.error('[WidgetRenderer] No legacyWidgetContainer ref found!')
				return
			}

			console.log('[WidgetRenderer] Mounting legacy widget:', this.widget.id, 'Container:', this.$refs.legacyWidgetContainer)

			// Widget scripts are loaded with defer, so we need to wait for them
			// to register their callbacks. Try multiple times with increasing delays.
			const tryMount = (attempt = 0, maxAttempts = 20) => {
				console.log(`[WidgetRenderer] Mount attempt ${attempt + 1}/${maxAttempts} for:`, this.widget.id)

				// Check if callback is registered
				if (widgetBridge.hasWidgetCallback(this.widget.id)) {
					console.log('[WidgetRenderer] Callback found! Mounting:', this.widget.id)
					// Pass widget data to the bridge so callbacks can access it
					widgetBridge.mountWidget(this.widget.id, this.$refs.legacyWidgetContainer, this.widget)
					console.log('[WidgetRenderer] After mountWidget, container innerHTML length:', this.$refs.legacyWidgetContainer?.innerHTML.length)
				} else if (attempt < maxAttempts) {
					// Try again after a short delay
					const delay = Math.min(100 * (attempt + 1), 1000) // Exponential backoff up to 1s
					console.log(`[WidgetRenderer] Callback not found yet, retrying in ${delay}ms...`)
					setTimeout(() => tryMount(attempt + 1, maxAttempts), delay)
				} else {
					console.error('[WidgetRenderer] Failed to mount widget after', maxAttempts, 'attempts:', this.widget.id)
					console.log('[WidgetRenderer] Available callbacks:', widgetBridge.getRegisteredWidgetIds())
				}
			}

			// Start trying immediately
			this.$nextTick(() => {
				tryMount()
			})
		},

		/** @spec openspec/specs/widgets/spec.md */
		setupAutoRefresh(intervalSeconds) {
			if (this.refreshInterval) {
				clearInterval(this.refreshInterval)
			}

			this.refreshInterval = setInterval(() => {
				this.itemsLoading = true
				this.refreshWidgetItems(this.widget.id).finally(() => {
					this.itemsLoading = false
				})
			}, intervalSeconds * 1000)
		},
	},
}
</script>

<style scoped>
.widget-renderer {
	height: 100%;
	padding: 16px;
}

/* Full-bleed widgets (header banner, image, divider) paint edge-to-edge. */
.widget-renderer--flush {
	padding: 0;
}

.widget-renderer__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
}

.widget-renderer__legacy {
	height: 100%;
}
</style>
