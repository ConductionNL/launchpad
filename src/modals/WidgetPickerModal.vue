<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- widget-picker-board-sync REQ-WDG-040: one 720 px dialog, titled with
	     the dashboard over "Add widget": the search, the kinds and, to their
	     right, the cards in two columns under three headings; Cancel and
	     Add widget at the foot. A card is chosen by a click and placed with
	     Add widget. -->
	<NcModal
		v-if="open"
		class="launchpad-modal widget-picker-modal"
		size="large"
		:name="t('launchpad', 'Add widget')"
		@close="$emit('close')">
		<div class="widget-picker">
			<div class="widget-picker__heading">
				<p v-if="dashboardName" class="widget-picker__dashboard">
					{{ dashboardName }}
				</p>
				<h2 class="widget-picker__title">
					{{ t('launchpad', 'Add widget') }}
				</h2>
			</div>

			<div class="widget-picker__search">
				<NcTextField
					v-model="searchQuery"
					:label="t('launchpad', 'Search widgets')"
					:placeholder="t('launchpad', 'Search widgets…')"
					:showTrailingButton="searchQuery !== ''"
					trailingButtonIcon="close"
					@trailingButtonClick="searchQuery = ''">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>

			<div class="widget-picker__body">
				<!-- REQ-WDG-041: the kinds, each with its count. -->
				<nav class="widget-picker__kinds" :aria-label="t('launchpad', 'Kinds of widget')">
					<button
						v-for="kind in kinds"
						:key="kind"
						type="button"
						class="widget-picker__kind"
						:class="{ 'widget-picker__kind--chosen': chosenKind === kind }"
						:aria-pressed="chosenKind === kind ? 'true' : 'false'"
						:data-testid="`widget-picker-kind-${kind}`"
						@click="chosenKind = kind">
						<span class="widget-picker__kind-label">{{ kindLabel(kind) }}</span>
						<span class="widget-picker__kind-count">{{ counts[kind] }}</span>
					</button>
				</nav>

				<div class="widget-picker__list">
					<section
						v-for="section in visibleSections"
						:key="section.id"
						class="widget-picker__section"
						:data-testid="`widget-picker-section-${section.id}`">
						<h3 class="widget-picker__section-title">
							{{ section.label }}
						</h3>
						<div class="widget-picker__grid">
							<button
								v-for="card in section.cards"
								:key="card.key"
								type="button"
								class="widget-picker__widget"
								:class="{
									'widget-picker__widget--chosen': chosenKey === card.key,
									'widget-picker__widget--disabled': card.disabled,
								}"
								:aria-pressed="chosenKey === card.key ? 'true' : 'false'"
								:aria-disabled="card.disabled ? 'true' : 'false'"
								:data-testid="card.testid"
								@click="choose(card)"
								@dblclick="place(card)">
								<span
									v-if="card.icon.kind === 'tile' || card.icon.kind === 'own'"
									class="widget-picker__widget-icon widget-picker__tile-swatch"
									:class="{ 'widget-picker__tile-swatch--own': card.icon.kind === 'own' }"
									:style="card.icon.kind === 'tile' ? { '--widget-picker-tile-colour': tileColour(card.icon.appId) } : {}"
									aria-hidden="true">
									<Plus v-if="card.icon.kind === 'own'" :size="18" />
									<img
										v-else-if="card.icon.url"
										:src="card.icon.url"
										alt=""
										class="widget-picker__tile-image">
									<span
										v-else-if="card.icon.className"
										:class="card.icon.className"
										class="widget-picker__tile-image" />
									<span
										v-else-if="card.icon.mask"
										class="widget-picker__tile-glyph"
										:style="{ '--widget-picker-tile-icon': `url(&quot;${card.icon.mask}&quot;)` }" />
								</span>
								<img
									v-else-if="card.icon.url"
									:src="card.icon.url"
									alt=""
									class="widget-picker__widget-icon">
								<span
									v-else-if="card.icon.className"
									:class="card.icon.className"
									class="widget-picker__widget-icon"
									aria-hidden="true" />
								<ViewDashboardOutline
									v-else
									:size="24"
									class="widget-picker__widget-icon widget-picker__widget-icon--type" />
								<span class="widget-picker__widget-info">
									<span class="widget-picker__widget-title">{{ card.title }}</span>
									<span class="widget-picker__widget-line">{{ card.line }}</span>
									<span v-if="card.disabled" class="widget-picker__widget-badge widget-picker__widget-badge--refused">
										{{ t('launchpad', 'Not allowed for your role') }}
									</span>
									<span v-else-if="card.legacy" class="widget-picker__widget-badge">
										{{ t('launchpad', 'Older widget type') }}
									</span>
									<span v-if="card.badge" class="widget-picker__widget-badge">
										{{ card.badge }}
									</span>
								</span>
								<Check
									v-if="chosenKey === card.key"
									:size="20"
									class="widget-picker__widget-action widget-picker__widget-action--check" />
							</button>
						</div>
					</section>

					<NcEmptyContent
						v-if="visibleSections.length === 0"
						:description="t('launchpad', 'No widgets found')">
						<template #icon>
							<Magnify :size="48" />
						</template>
					</NcEmptyContent>

					<section
						v-if="requestAppFn"
						class="widget-picker__section widget-picker__section--request"
						data-testid="widget-picker-request">
						<NcButton
							v-if="!requestFormOpen"
							variant="tertiary"
							@click="openRequestForm">
							{{ t('launchpad', 'Can\'t find your app?') }}
						</NcButton>
						<form
							v-else
							class="widget-picker__request"
							:aria-label="t('launchpad', 'Ask IT for an app')"
							@submit.prevent="submitRequest">
							<h4 class="widget-picker__request-title">
								{{ t('launchpad', 'Ask IT for an app') }}
							</h4>
							<NcTextField
								v-model="requestAppName"
								:label="t('launchpad', 'App name')"
								:required="true" />
							<NcTextField
								v-model="requestPurpose"
								:label="t('launchpad', 'What do you need it for?')" />
							<p
								v-if="requestMessage"
								class="widget-picker__request-message"
								:class="{ 'widget-picker__request-message--error': requestFailed }"
								role="status">
								{{ requestMessage }}
							</p>
							<div class="widget-picker__request-actions">
								<NcButton
									variant="primary"
									type="submit"
									:disabled="requestBusy || requestAppName.trim() === ''">
									{{ t('launchpad', 'Send request') }}
								</NcButton>
								<NcButton variant="tertiary" @click="closeRequestForm">
									{{ t('launchpad', 'Cancel') }}
								</NcButton>
							</div>
						</form>
					</section>
				</div>
			</div>

			<div class="widget-picker__actions">
				<NcButton data-testid="widget-picker-cancel" @click="$emit('close')">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					data-testid="widget-picker-add"
					:disabled="!chosenCard"
					@click="place(chosenCard)">
					{{ t('launchpad', 'Add widget') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { appTileColourValue } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcModal, NcTextField } from '@nextcloud/vue'
import Check from 'vue-material-design-icons/Check.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import { FLAG_PROP, LIST_PROP, TEXT_PROP } from '../constants/propDefinitions.js'
import { countByKind, filterCards, kindLabel, KINDS, pickerCards, sectionLabel, SECTIONS } from '../utils/widgetPickerCards.js'

/**
 * WidgetPickerModal — "Add widget" as board LpWidgetKiezen draws it
 * (widget-picker-board-sync): the search, the kinds with their counts, the
 * cards in two columns under "Widgets from Launchpad", "App tiles" and
 * "From other Nextcloud apps", a grey card for what the role may not add and
 * Cancel and Add widget at the foot. Each card still places through the
 * event it always did (`add`, `addCustom`, `addApp`, `addCatalogueApp`,
 * `addOfficePack`), now when Add widget is pressed.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md
 * @spec openspec/specs/widgets/spec.md#requirement-widget-picker-req-wdg-010
 */
export default {
	name: 'WidgetPickerModal',

	components: {
		NcButton,
		NcModal,
		NcTextField,
		NcEmptyContent,
		Magnify,
		Plus,
		Check,
		ViewDashboardOutline,
	},

	props: {
		open: FLAG_PROP,

		widgets: {
			type: Array,
			required: true,
		},

		placedWidgetIds: LIST_PROP,

		/**
		 * The apps this server runs for the user (`GET /api/tiles/discover`):
		 * `[{ appId, name, href, icon }]`.
		 */
		discoveredApps: LIST_PROP,

		/** The discovered app ids already on the dashboard. */
		placedAppIds: LIST_PROP,

		/**
		 * The widget types Launchpad builds itself (text, image, news and
		 * more): `[{ type, title }]`. Picking one opens its form on that type.
		 */
		customTypes: LIST_PROP,

		/**
		 * The app catalogue entries the user's groups may see
		 * (`GET /api/catalogue`): `[{ uuid, name, description, icon, iconType, linkType, linkValue }]`.
		 */
		catalogueApps: LIST_PROP,

		/** The dashboard the widget goes on, shown over the title. */
		dashboardName: TEXT_PROP,

		/**
		 * The widget ids the viewer's role may add (initial state
		 * `allowedWidgets`, REQ-RFP-010); `null` for no restriction.
		 */
		allowedWidgets: {
			type: Array,
			default: null,
		},

		/**
		 * Sends a request for a missing app: `({ appName, purpose }) => Promise`.
		 * Without it the "Can't find your app?" form is not offered.
		 */
		requestAppFn: {
			type: Function,
			default: null,
		},
	},

	emits: ['close', 'add', 'addApp', 'addCatalogueApp', 'addCustom', 'addOfficePack'],

	data() {
		return {
			searchQuery: '',
			chosenKind: 'all',
			chosenKey: '',
			requestFormOpen: false,
			requestAppName: '',
			requestPurpose: '',
			requestBusy: false,
			requestMessage: '',
			requestFailed: false,
		}
	},

	computed: {
		/**
		 * The kinds in the column.
		 *
		 * @return {Array<string>}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
		 */
		kinds() {
			return KINDS
		},

		/**
		 * Every card the picker offers.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md
		 */
		cards() {
			return pickerCards({
				customTypes: this.customTypes,
				officePack: true,
				catalogueApps: this.catalogueApps,
				discoveredApps: this.discoveredApps,
				widgets: this.widgets,
				allowedWidgets: this.allowedWidgets,
				placedWidgetIds: this.placedWidgetIds,
				placedAppIds: this.placedAppIds,
			}, t)
		},

		/**
		 * The number of cards per kind.
		 *
		 * @return {Record<string, number>}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
		 */
		counts() {
			return countByKind(this.cards)
		},

		/**
		 * The cards of the chosen kind that match the search.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
		 */
		filteredCards() {
			return filterCards(this.cards, this.chosenKind, this.searchQuery)
		},

		/**
		 * The sections that have a card to show, in board order.
		 *
		 * @return {Array<{id: string, label: string, cards: Array<object>}>}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
		 */
		visibleSections() {
			return SECTIONS
				.map((id) => ({ id, label: sectionLabel(id, t), cards: this.filteredCards.filter((card) => card.section === id) }))
				.filter((section) => section.cards.length > 0)
		},

		/**
		 * The chosen card, while it is still shown and allowed.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
		 */
		chosenCard() {
			return this.filteredCards.find((card) => card.key === this.chosenKey && !card.disabled) ?? null
		},
	},

	watch: {
		/**
		 * Clear the search, the kind and the choice when the picker closes,
		 * so the next open starts from every widget.
		 *
		 * @param {boolean} isOpen Whether the picker is now open.
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
		 */
		open(isOpen) {
			if (!isOpen) {
				this.searchQuery = ''
				this.chosenKind = 'all'
				this.chosenKey = ''
				this.closeRequestForm()
			}
		},
	},

	methods: {
		t,

		/**
		 * The column's label of a kind.
		 *
		 * @param {string} kind The kind.
		 * @return {string}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-kinds-with-counts-req-wdg-041
		 */
		kindLabel(kind) {
			return kindLabel(kind, t)
		},

		/**
		 * The colour an app's tile gets when placed, for its card.
		 *
		 * @param {string} appId The app id.
		 * @return {string} A CSS colour value.
		 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-the-add-flow-offers-every-app-as-its-own-tile-req-apptile-001
		 */
		tileColour(appId) {
			return appTileColourValue('', appId)
		},

		/**
		 * Choose a card; a card the role may not add cannot be chosen.
		 *
		 * @param {object} card The card.
		 * @return {void}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-widgets-the-role-may-not-add-req-wdg-043
		 */
		choose(card) {
			if (card.disabled) {
				return
			}
			this.chosenKey = card.key
		},

		/**
		 * Place a card through its own event. The picker closes after a
		 * Nextcloud widget or an app tile; for a Launchpad type or the office
		 * pack the host opens the form or closes it itself.
		 *
		 * @param {object|null} card The card.
		 * @return {void}
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-the-widget-picker-as-lpwidgetkiezen-draws-it-req-wdg-040
		 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md#requirement-app-tiles-in-the-picker-req-wdg-042
		 */
		place(card) {
			if (!card || card.disabled) {
				return
			}
			const { event, arg } = card.action
			if (arg === undefined) {
				this.$emit(event)
			} else {
				this.$emit(event, arg)
			}
			this.chosenKey = ''
			if (event === 'add' || event === 'addApp' || event === 'addCatalogueApp') {
				this.$emit('close')
			}
		},

		/**
		 * Open the request form, starting from what the user searched for.
		 *
		 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
		 */
		openRequestForm() {
			this.requestFormOpen = true
			this.requestAppName = this.searchQuery.trim()
			this.requestPurpose = ''
			this.requestMessage = ''
			this.requestFailed = false
		},

		/**
		 * Close the request form and forget what was typed.
		 *
		 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
		 */
		closeRequestForm() {
			this.requestFormOpen = false
			this.requestAppName = ''
			this.requestPurpose = ''
			this.requestMessage = ''
			this.requestFailed = false
		},

		/**
		 * Send the request and say how it went: sent, refused because the
		 * user has too many open requests, or not sent.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
		 */
		async submitRequest() {
			const appName = this.requestAppName.trim()
			if (appName === '' || this.requestBusy) {
				return
			}
			this.requestBusy = true
			this.requestMessage = ''
			try {
				await this.requestAppFn({ appName, purpose: this.requestPurpose.trim() })
				this.requestFailed = false
				this.requestMessage = t('launchpad', 'Your request was sent to IT')
				this.requestAppName = ''
				this.requestPurpose = ''
			} catch (error) {
				this.requestFailed = true
				this.requestMessage = error?.response?.data?.code === 'request_limit'
					? t('launchpad', 'You have 10 open requests. Wait for an answer first.')
					: t('launchpad', 'Your request could not be sent. Try again later.')
			} finally {
				this.requestBusy = false
			}
		},
	},
}
</script>

<style scoped>
/* REQ-WDG-040: the dialog is 720 px wide (narrower on a small screen). */
.widget-picker-modal :deep(.modal-container) {
	width: 720px;
	max-width: calc(100vw - 32px);
}

.widget-picker {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-height: 80vh;
	box-sizing: border-box;
}

.widget-picker__heading {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.widget-picker__dashboard {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.widget-picker__title {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
}

.widget-picker__search {
	flex-shrink: 0;
}

.widget-picker__body {
	flex: 1;
	min-height: 0;
	display: flex;
	gap: 16px;
}

.widget-picker__kinds {
	flex: 0 0 168px;
	display: flex;
	flex-direction: column;
	gap: 2px;
	overflow-y: auto;
}

.widget-picker__kind {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	padding: 8px 10px;
	border: none;
	border-radius: var(--border-radius-large);
	background: transparent;
	color: var(--color-main-text);
	text-align: start;
	cursor: pointer;
}

.widget-picker__kind:hover,
.widget-picker__kind:focus-visible {
	background: var(--color-background-hover);
}

.widget-picker__kind--chosen {
	background: var(--color-primary-element-light);
	font-weight: 600;
}

.widget-picker__kind-count {
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.widget-picker__list {
	flex: 1;
	min-width: 0;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.widget-picker__section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.widget-picker__section-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.widget-picker__grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px;
}

@media (max-width: 600px) {
	.widget-picker__body {
		flex-direction: column;
	}

	.widget-picker__kinds {
		flex: 0 0 auto;
		flex-direction: row;
		flex-wrap: wrap;
	}

	.widget-picker__grid {
		grid-template-columns: minmax(0, 1fr);
	}
}

.widget-picker__widget {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 12px;
	border: 2px solid var(--color-border);
	background: var(--color-main-background);
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: background var(--animation-quick) ease, border-color var(--animation-quick) ease;
	text-align: start;
	width: 100%;
	min-width: 0;
}

.widget-picker__widget:hover,
.widget-picker__widget:focus-visible {
	background: var(--color-background-hover);
}

.widget-picker__widget--chosen {
	border-color: var(--color-primary-element);
}

/* REQ-WDG-043: what the role may not add is a grey card. */
.widget-picker__widget--disabled {
	cursor: not-allowed;
	opacity: 0.55;
	filter: grayscale(1);
}

.widget-picker__widget--disabled:hover {
	background: var(--color-main-background);
}

.widget-picker__widget-icon {
	width: 32px;
	height: 32px;
	flex-shrink: 0;
}

.widget-picker__widget-icon--type {
	color: var(--color-primary-element);
}

.widget-picker__tile-swatch {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius, 8px);
	background-color: var(--widget-picker-tile-colour);
	color: #fff;
}

.widget-picker__tile-swatch--own {
	border: 2px dashed var(--color-border-dark);
	background-color: transparent;
	color: var(--color-main-text);
}

.widget-picker__tile-image {
	width: 55%;
	height: 55%;
	object-fit: contain;
	filter: brightness(0) invert(1);
}

.widget-picker__tile-glyph {
	width: 55%;
	height: 55%;
	background-color: currentColor;
	mask: var(--widget-picker-tile-icon) center / contain no-repeat;
	-webkit-mask: var(--widget-picker-tile-icon) center / contain no-repeat;
}

.widget-picker__widget-info {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.widget-picker__widget-title {
	font-weight: 600;
	color: var(--color-main-text);
	overflow-wrap: anywhere;
}

.widget-picker__widget-line,
.widget-picker__widget-badge {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.widget-picker__widget-badge--refused {
	font-weight: 600;
}

.widget-picker__widget-action {
	flex-shrink: 0;
}

.widget-picker__widget-action--check {
	color: var(--color-primary-element);
}

.widget-picker__section--request {
	align-items: flex-start;
}

.widget-picker__request {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 100%;
}

.widget-picker__request-title {
	margin: 0;
	font-weight: 600;
}

.widget-picker__request-actions {
	display: flex;
	gap: 8px;
}

.widget-picker__request-message {
	margin: 0;
	color: var(--color-success-text);
}

.widget-picker__request-message--error {
	color: var(--color-error-text);
}

.widget-picker__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	flex-shrink: 0;
}

/* WCAG 2.2 AA SC 2.3.3 (Animation from Interactions). Honour the OS-level
   "reduce motion" setting by making the hover transition instantaneous
   instead of removing the state change — the colour still updates, so the
   affordance is intact; only the interpolation between the two colours is
   dropped. `0.01ms` rather than `0` keeps `transitionend` firing for any
   listener that depends on it. */
@media (prefers-reduced-motion: reduce) {
	.widget-picker__widget {
		transition-duration: 0.01ms;
	}
}
</style>
