<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- app-link-tile-widget REQ-APPTILE-004 (board LpTegelToevoegen): "Choose
	     the app", one list of the user's Nextcloud apps, the app catalogue
	     with how each signs on and Ask IT, the services found on the server
	     and the ready-made Office tiles. Ticking several adds one tile each. -->
	<div class="tile-app-chooser add-tiles" data-testid="tile-app-chooser">
		<h3 class="add-tiles__title">
			{{ t('launchpad', 'Choose the app') }}
		</h3>
		<NcTextField
			v-model="search"
			:label="t('launchpad', 'Search apps')"
			:showTrailingButton="search !== ''"
			@trailingButtonClick="search = ''" />

		<ul v-if="rows.length > 0" class="add-tiles__rows" data-testid="tile-app-chooser-rows">
			<li
				v-for="row in rows"
				:key="row.choice.key"
				class="add-tiles__row"
				:data-testid="'tile-app-row-' + row.source">
				<label class="add-tiles__check">
					<input
						type="checkbox"
						:checked="row.onDashboard || isChosen(row.choice.key)"
						:disabled="row.onDashboard"
						:data-testid="'tile-app-tick-' + row.choice.key"
						@change="setChosen(row.choice, $event.target.checked)">
					<span
						v-if="row.icon"
						class="tile-app-chooser__icon"
						:style="{ '--tile-app-icon': `url(&quot;${row.icon}&quot;)` }"
						aria-hidden="true" />
					<span class="add-tiles__row-name">{{ row.name }}</span>
				</label>
				<span v-if="row.count" class="tile-app-chooser__count" data-testid="tile-app-count">
					{{ t('launchpad', '{count} new', { count: row.count }) }}
				</span>
				<span class="add-tiles__row-meta" data-testid="tile-app-meta">{{ row.meta }}</span>
				<span v-if="row.onDashboard" class="add-tiles__row-meta" data-testid="tile-app-on-dashboard">
					{{ t('launchpad', 'On this dashboard') }}
				</span>
			</li>
		</ul>
		<p v-else class="add-tiles__empty">
			{{ t('launchpad', 'No apps found') }}
		</p>

		<form
			v-if="requestAppFn && request.open"
			class="add-tiles__request"
			:aria-label="t('launchpad', 'Request app from IT')"
			@submit.prevent="submitRequest">
			<NcTextField v-model="request.name" :label="t('launchpad', 'App name')" :required="true" />
			<NcTextField v-model="request.purpose" :label="t('launchpad', 'What do you need it for?')" />
			<div class="add-tiles__request-actions">
				<span
					v-if="request.message"
					class="add-tiles__message"
					:class="{ 'add-tiles__message--error': request.failed }"
					role="status">{{ request.message }}</span>
				<NcButton variant="tertiary" @click="closeRequestForm">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary" type="submit" :disabled="!canSendRequest">
					{{ t('launchpad', 'Send request') }}
				</NcButton>
			</div>
		</form>
		<div class="add-tiles__request-actions tile-app-chooser__links">
			<NcButton
				v-if="requestAppFn && !request.open"
				variant="tertiary"
				data-testid="tile-app-request"
				@click="openRequestForm">
				{{ t('launchpad', 'Request app from IT') }}
			</NcButton>
			<!-- REQ-BMI-001: every imported folder becomes a Container of link tiles. -->
			<label class="add-tiles__file">
				<input
					type="file"
					accept=".html,.htm,text/html"
					class="add-tiles__file-input"
					data-testid="tile-app-bookmark-file"
					@change="onFile($event.target.files && $event.target.files[0])">
				<span class="add-tiles__file-button">{{ t('launchpad', 'Import bookmarks') }}</span>
			</label>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import {
	catalogueChoice,
	discoveredChoice,
	existingTileKeys,
	foundServiceChoice,
	foundServiceMeta,
	officeChoice,
	signOnKind,
	tilesToAdd,
} from '../utils/addTiles.js'
import { sendAppRequest } from '../utils/appRequest.js'

/**
 * The Request app form when nothing is typed.
 *
 * @return {{open: boolean, name: string, purpose: string, busy: boolean, failed: boolean, message: string}}
 */
function emptyRequest() {
	return { open: false, name: '', purpose: '', busy: false, failed: false, message: '' }
}

/**
 * TileAppChooser — the "Choose the app" list of the App or link tile form
 * (REQ-APPTILE-004, board LpTegelToevoegen). It replaces the former Add tiles
 * dialog (REQ-TILE-043): the same sources in one list, each row ticked to add
 * a tile. A tile the dashboard holds is marked "On this dashboard" and two
 * sources offering the same app add it once. It emits the chosen tiles as
 * `update:chosen`; the form adds them on Save. A bookmarks file goes to the
 * host's bookmark import through `importBookmarks`.
 *
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
 * @spec openspec/changes/app-link-tile-widget/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */
export default {
	name: 'TileAppChooser',

	components: { NcButton, NcTextField },

	props: {
		catalogueApps: { type: Array, default: () => [] },
		discoveredApps: { type: Array, default: () => [] },
		foundServices: { type: Array, default: () => [] },
		officeTiles: { type: Array, default: () => [] },
		placements: { type: Array, default: () => [] },
		requestAppFn: { type: Function, default: null },
	},

	emits: ['update:chosen', 'importBookmarks'],

	data() {
		return {
			search: '',
			chosen: [],
			request: emptyRequest(),
		}
	},

	computed: {
		/**
		 * @return {boolean} Whether the request can be sent.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		canSendRequest() {
			return !this.request.busy && this.request.name.trim() !== ''
		},

		/**
		 * @return {Set<string>} The keys of the tiles on the dashboard.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-add-flow-offers-every-app-as-its-own-tile-req-apptile-001
		 */
		existing() {
			return existingTileKeys(this.placements)
		},

		/**
		 * Every row, in order: the Nextcloud apps, the catalogue, the found
		 * services, Office; one row per app, filtered by the search.
		 *
		 * @return {Array<object>} The rows.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		rows() {
			const apps = this.discoveredApps
				.filter((app) => app && app.appId)
				.map((app) => ({
					source: 'nextcloud',
					name: String(app.name || app.appId),
					icon: app.icon || '',
					count: Number(app.count) > 0 ? Number(app.count) : 0,
					meta: t('launchpad', 'Nextcloud app'),
					choice: discoveredChoice(app),
				}))
			const catalogue = this.catalogueApps.map((app) => ({
				source: 'catalogue',
				name: String(app.name || ''),
				description: String(app.description || ''),
				icon: app.iconType === 'url' ? app.icon || '' : '',
				count: 0,
				meta: this.signOnLabel(app),
				choice: catalogueChoice(app),
			}))
			const services = this.foundServices
				.filter((service) => service && service.name && service.url)
				.map((service) => ({
					source: 'found',
					name: String(service.name),
					icon: '',
					count: 0,
					meta: foundServiceMeta(service),
					choice: foundServiceChoice(service),
				}))
			const office = this.officeTiles
				.filter((pack) => pack && pack.linkType && pack.title)
				.map((pack) => ({
					source: 'office',
					name: String(pack.title),
					icon: pack.iconType === 'url' ? pack.icon || '' : '',
					count: 0,
					meta: t('launchpad', 'Office'),
					choice: officeChoice(pack),
				}))
			const query = this.search.trim().toLowerCase()
			// Two sources offering the same app (the Office pack's Files and
			// the Nextcloud app Files) are one row: the first source wins.
			const seen = new Set()
			return [...apps, ...catalogue, ...services, ...office]
				.filter((row) => {
					if (seen.has(row.choice.key)) {
						return false
					}
					seen.add(row.choice.key)
					return true
				})
				.filter((row) => !query || row.name.toLowerCase().includes(query) || String(row.description || '').toLowerCase().includes(query))
				.map((row) => ({ ...row, onDashboard: this.existing.has(row.choice.key) }))
		},
	},

	methods: {
		t,

		/**
		 * @param {string} key The choice key.
		 * @return {boolean} Whether it is ticked.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		isChosen(key) {
			return this.chosen.some((choice) => choice.key === key)
		},

		/**
		 * Tick or untick a row and tell the form what will be added.
		 *
		 * @param {object} choice The row's choice.
		 * @param {boolean} on Ticked.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		setChosen(choice, on) {
			const without = this.chosen.filter((item) => item.key !== choice.key)
			this.chosen = on ? [...without, choice] : without
			this.$emit('update:chosen', tilesToAdd(this.chosen, this.existing))
		},

		/**
		 * How a catalogue app signs on.
		 *
		 * @param {object} app The catalogue entry.
		 * @return {string}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		signOnLabel(app) {
			const kind = signOnKind(app)
			if (kind === 'sso') {
				return t('launchpad', 'Single sign-on')
			}
			return kind === 'nextcloud'
				? t('launchpad', 'Signs in with your Nextcloud account')
				: t('launchpad', 'Asks for its own sign-in')
		},

		/**
		 * @param {File|undefined} file The bookmarks file.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		onFile(file) {
			if (file) {
				this.$emit('importBookmarks', file)
			}
		},

		/** @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004 */
		openRequestForm() {
			this.request = { ...emptyRequest(), open: true, name: this.search.trim() }
		},

		/** @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004 */
		closeRequestForm() {
			this.request = emptyRequest()
		},

		/**
		 * Ask IT for a missing app (REQ-ACAT-003).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
		 */
		async submitRequest() {
			if (!this.canSendRequest || !this.requestAppFn) {
				return
			}
			this.request.busy = true
			const outcome = await sendAppRequest(this.requestAppFn, this.request.name, this.request.purpose)
			this.request = {
				...this.request,
				busy: false,
				failed: outcome.failed,
				message: outcome.message,
				name: outcome.failed ? this.request.name : '',
				purpose: outcome.failed ? this.request.purpose : '',
			}
		},
	},
}
</script>

<style scoped>
.add-tiles {
	display: flex;
	flex-direction: column;
	gap: 20px;
	box-sizing: border-box;
	width: min(720px, 100%);
	padding: 24px;
}

.add-tiles__title {
	margin: 0;
}

.add-tiles__section {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: 0;
	margin: 0;
	padding: 0;
}

.add-tiles__section-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.add-tiles__rows {
	margin: 0;
	padding: 0;
	list-style: none;
}

.add-tiles__row {
	display: flex;
	gap: 12px;
	align-items: center;
	padding: 6px 0;
	border-bottom: 1px solid var(--color-border);
}

.add-tiles__row-name {
	flex: 1;
	min-width: 0;
	font-weight: 500;
}

.add-tiles__row-meta,
.add-tiles__empty {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.add-tiles__office {
	flex-flow: row wrap;
	gap: 8px 16px;
}

.add-tiles__office .add-tiles__section-title {
	width: 100%;
	margin-bottom: 4px;
}

.add-tiles__check {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	cursor: pointer;
}

.add-tiles__drop {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: center;
	justify-content: center;
	padding: 20px;
	border: 2px dashed var(--color-border-dark);
	border-radius: var(--border-radius-large);
	color: var(--color-text-maxcontrast);
}

.add-tiles__drop--over {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.add-tiles__file {
	position: relative;
	cursor: pointer;
}

.add-tiles__file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
}

.add-tiles__file-button {
	display: inline-block;
	padding: 6px 14px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-element, var(--border-radius-pill));
	color: var(--color-main-text);
}

.add-tiles__file:focus-within .add-tiles__file-button {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.add-tiles__request {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.add-tiles__request-actions,
.add-tiles__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
}

.add-tiles__message {
	margin: 0;
}

.add-tiles__message--error {
	color: var(--color-error-text, var(--color-error));
}

.tile-app-chooser {
	width: 100%;
	padding: 0;
}

.tile-app-chooser__icon {
	display: inline-block;
	width: 20px;
	height: 20px;
	background-color: var(--color-main-text);
	mask: var(--tile-app-icon) center / contain no-repeat;
	-webkit-mask: var(--tile-app-icon) center / contain no-repeat;
}

.tile-app-chooser__count {
	padding: 0 6px;
	border-radius: 10px;
	background: var(--color-primary-element-light);
	font-size: 12px;
	font-weight: 700;
}

.tile-app-chooser__links {
	justify-content: flex-start;
}
</style>
