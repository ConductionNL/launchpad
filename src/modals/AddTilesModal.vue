<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-model:show="isOpen"
		class="launchpad-modal add-tiles-modal"
		:name="t('launchpad', 'Add tiles')"
		size="normal"
		@close="close">
		<div class="add-tiles" data-testid="add-tiles">
			<h2 class="add-tiles__title">
				{{ t('launchpad', 'Add tiles') }}
			</h2>

			<!-- 1. App catalogue (REQ-TILE-043) -->
			<section class="add-tiles__section" data-testid="add-tiles-catalogue">
				<h3 class="add-tiles__section-title">
					{{ t('launchpad', 'App catalogue') }}
				</h3>
				<NcTextField
					v-model="search"
					:label="t('launchpad', 'Search apps')"
					:showTrailingButton="search !== ''"
					@trailingButtonClick="search = ''" />
				<ul v-if="catalogueRows.length > 0" class="add-tiles__rows">
					<li
						v-for="row in catalogueRows"
						:key="row.choice.key"
						class="add-tiles__row"
						data-testid="add-tiles-catalogue-row">
						<span class="add-tiles__row-name">{{ row.app.name }}</span>
						<span class="add-tiles__row-meta">{{ signOnLabel(row.app) }}</span>
						<span v-if="row.onDashboard" class="add-tiles__row-meta">
							{{ t('launchpad', 'On your dashboard') }}
						</span>
						<NcButton
							v-else
							:variant="isChosen(row.choice.key) ? 'primary' : 'secondary'"
							:aria-pressed="isChosen(row.choice.key) ? 'true' : 'false'"
							:aria-label="t('launchpad', 'Add {name}', { name: row.app.name })"
							@click="toggle(row.choice)">
							{{ isChosen(row.choice.key) ? t('launchpad', 'Added') : t('launchpad', 'Add') }}
						</NcButton>
					</li>
				</ul>
				<p v-else class="add-tiles__empty">
					{{ t('launchpad', 'No apps found') }}
				</p>

				<form
					v-if="requestAppFn && request.open"
					class="add-tiles__request"
					:aria-label="t('launchpad', 'Ask IT for an app')"
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
				<NcButton
					v-else-if="requestAppFn"
					variant="tertiary"
					data-testid="add-tiles-request"
					@click="openRequestForm">
					{{ t('launchpad', 'Ask IT for an app') }}
				</NcButton>
			</section>

			<!-- 2. Found on this server (REQ-TILE-044): the apps of the
			     person's own app menu that have no tile yet. -->
			<section
				v-if="foundRows.length > 0"
				class="add-tiles__section"
				data-testid="add-tiles-found">
				<h3 class="add-tiles__section-title">
					{{ t('launchpad', 'Found on this server') }}
				</h3>
				<ul class="add-tiles__rows">
					<li
						v-for="row in foundRows"
						:key="row.choice.key"
						class="add-tiles__row"
						data-testid="add-tiles-found-row">
						<span class="add-tiles__row-name">{{ row.app.name }}</span>
						<span class="add-tiles__row-meta">{{ t('launchpad', 'Running') }}</span>
						<NcButton
							:variant="isChosen(row.choice.key) ? 'primary' : 'secondary'"
							:aria-pressed="isChosen(row.choice.key) ? 'true' : 'false'"
							:aria-label="t('launchpad', 'Add {name}', { name: row.app.name })"
							@click="toggle(row.choice)">
							{{ isChosen(row.choice.key) ? t('launchpad', 'Added') : t('launchpad', 'Add as tile') }}
						</NcButton>
					</li>
				</ul>
			</section>

			<!-- 3. Ready-made tiles for Office, from the office tile pack. -->
			<fieldset
				v-if="officeRows.length > 0"
				class="add-tiles__section add-tiles__office"
				data-testid="add-tiles-office">
				<legend class="add-tiles__section-title">
					{{ t('launchpad', 'Ready-made tiles for Office') }}
				</legend>
				<label
					v-for="row in officeRows"
					:key="row.choice.key"
					class="add-tiles__check">
					<input
						type="checkbox"
						:checked="row.onDashboard || isChosen(row.choice.key)"
						:disabled="row.onDashboard"
						:data-testid="'add-tiles-office-' + row.choice.key"
						@change="setChosen(row.choice, $event.target.checked)">
					{{ row.pack.title }}
				</label>
			</fieldset>

			<!-- 4. Import bookmarks: hands the file to the bookmark import. -->
			<section class="add-tiles__section" data-testid="add-tiles-bookmarks">
				<h3 class="add-tiles__section-title">
					{{ t('launchpad', 'Import bookmarks') }}
				</h3>
				<div
					class="add-tiles__drop"
					:class="{ 'add-tiles__drop--over': dragOver }"
					data-testid="add-tiles-drop"
					@dragover.prevent="dragOver = true"
					@dragleave="dragOver = false"
					@drop.prevent="onDrop">
					<span>{{ t('launchpad', 'Drop the bookmarks file your browser exported here') }}</span>
					<label class="add-tiles__file">
						<input
							type="file"
							accept=".html,.htm,text/html"
							class="add-tiles__file-input"
							data-testid="add-tiles-bookmark-file"
							@change="onFile($event.target.files && $event.target.files[0])">
						<span class="add-tiles__file-button">{{ t('launchpad', 'Choose file') }}</span>
					</label>
				</div>
			</section>

			<p
				v-if="error"
				class="add-tiles__message add-tiles__message--error"
				role="alert">
				{{ error }}
			</p>

			<div class="add-tiles__actions">
				<NcButton @click="close">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="chosenTiles.length === 0 || busy"
					data-testid="add-tiles-confirm"
					@click="submit">
					{{ addLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcModal, NcTextField } from '@nextcloud/vue'
import {
	catalogueChoice,
	discoveredChoice,
	existingTileKeys,
	officeChoice,
	signOnKind,
	tilesToAdd,
} from '../utils/addTiles.js'
import { sendAppRequest } from '../utils/appRequest.js'

/**
 * The Ask IT form when nothing is typed.
 *
 * @return {{open: boolean, name: string, purpose: string, busy: boolean, failed: boolean, message: string}}
 */
function emptyRequest() {
	return { open: false, name: '', purpose: '', busy: false, failed: false, message: '' }
}

/**
 * AddTilesModal — "Add tiles" as board LpTegelToevoegen draws it
 * (REQ-TILE-043): the app catalogue with search, sign-on kind per row and
 * Ask IT for an app; the apps found on this server that have no tile
 * (REQ-TILE-044); ready-made Office tiles to tick; and the bookmark import.
 * One primary button says how many tiles it adds. The dialog never places a
 * tile twice: a tile the dashboard holds is shown as such, and two sections
 * offering the same app add it once.
 *
 * The host does the API work: it passes the lists and `addTiles(choices)`,
 * and opens its bookmark import when the dialog emits `importBookmarks`.
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
 */
export default {
	name: 'AddTilesModal',

	components: { NcButton, NcModal, NcTextField },

	props: {
		/** Whether the dialog is open. */
		open: { type: Boolean, default: false },
		/** The catalogue entries the person's groups may see (`GET /api/catalogue`). */
		catalogueApps: { type: Array, default: () => [] },
		/** The apps of the person's app menu (`GET /api/tiles/discover`). */
		discoveredApps: { type: Array, default: () => [] },
		/** The office tile pack (`GET /api/office-tiles`). */
		officeTiles: { type: Array, default: () => [] },
		/** The active dashboard's placements, to leave out tiles it holds. */
		placements: { type: Array, default: () => [] },
		/** `async ({appName, purpose})`: ask IT for an app; absent hides the request. */
		requestAppFn: { type: Function, default: null },
		/** `async (choices)`: place the chosen tiles. */
		addTiles: { type: Function, required: true },
	},

	emits: ['close', 'importBookmarks', 'added'],

	data() {
		return {
			search: '',
			chosen: [],
			busy: false,
			error: '',
			dragOver: false,
			request: emptyRequest(),
		}
	},

	computed: {
		/**
		 * Two-way `show` for NcModal: closing it closes the dialog.
		 *
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		isOpen: {
			/**
			 * @return {boolean} whether the dialog is open.
			 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
			 */
			get() {
				return this.open
			},

			/**
			 * @param {boolean} value the new `show`.
			 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
			 */
			set(value) {
				if (!value) {
					this.close()
				}
			},
		},

		/**
		 * Whether the request can be sent: a name, and nothing in flight.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		canSendRequest() {
			return !this.request.busy && this.request.name.trim() !== ''
		},

		/**
		 * The keys of the tiles the dashboard holds.
		 *
		 * @return {Set<string>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		existing() {
			return existingTileKeys(this.placements)
		},

		/**
		 * The catalogue rows matching the search, by name or description.
		 *
		 * @return {Array<{app: object, choice: object, onDashboard: boolean}>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		catalogueRows() {
			const query = this.search.trim().toLowerCase()
			return this.catalogueApps
				.filter((app) => !query
					|| String(app.name || '').toLowerCase().includes(query)
					|| String(app.description || '').toLowerCase().includes(query))
				.map((app) => {
					const choice = catalogueChoice(app)
					return { app, choice, onDashboard: this.existing.has(choice.key) }
				})
		},

		/**
		 * The found apps without a tile on the dashboard.
		 *
		 * @return {Array<{app: object, choice: object}>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
		 */
		foundRows() {
			return this.discoveredApps
				.filter((app) => app && app.appId)
				.map((app) => ({ app, choice: discoveredChoice(app) }))
				.filter((row) => !this.existing.has(row.choice.key))
		},

		/**
		 * The office pack tiles to tick.
		 *
		 * @return {Array<{pack: object, choice: object, onDashboard: boolean}>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		officeRows() {
			return this.officeTiles
				.filter((pack) => pack && pack.linkType && pack.title)
				.map((pack) => {
					const choice = officeChoice(pack)
					return { pack, choice, onDashboard: this.existing.has(choice.key) }
				})
		},

		/**
		 * The tiles the button adds: once each, none the dashboard holds.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		chosenTiles() {
			return tilesToAdd(this.chosen, this.existing)
		},

		/**
		 * "Add 3 tiles".
		 *
		 * @return {string}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		addLabel() {
			const count = this.chosenTiles.length
			if (count === 0) {
				return t('launchpad', 'Add tiles')
			}
			if (count === 1) {
				return t('launchpad', 'Add 1 tile')
			}
			return t('launchpad', 'Add {count} tiles', { count })
		},
	},

	watch: {
		/**
		 * Start afresh each time the dialog opens.
		 *
		 * @param {boolean} value whether it is open.
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		open(value) {
			if (value) {
				this.reset()
			}
		},
	},

	methods: {
		t,

		/**
		 * Whether a choice is chosen.
		 *
		 * @param {string} key The choice key.
		 * @return {boolean}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		isChosen(key) {
			return this.chosen.some((choice) => choice.key === key)
		},

		/**
		 * Choose or drop a choice.
		 *
		 * @param {{key: string}} choice The choice.
		 * @param {boolean} on Whether it is chosen.
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		setChosen(choice, on) {
			const without = this.chosen.filter((item) => item.key !== choice.key)
			this.chosen = on ? [...without, choice] : without
		},

		/**
		 * Flip a choice.
		 *
		 * @param {{key: string}} choice The choice.
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		toggle(choice) {
			this.setChosen(choice, !this.isChosen(choice.key))
		},

		/**
		 * The sign-on line of a catalogue row.
		 *
		 * @param {object} app The catalogue entry.
		 * @return {string}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
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
		 * A dropped bookmarks file.
		 *
		 * @param {DragEvent} event The drop.
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		onDrop(event) {
			this.dragOver = false
			this.onFile(event?.dataTransfer?.files?.[0])
		},

		/**
		 * Hand a bookmarks file to the bookmark import.
		 *
		 * @param {File|undefined} file The file.
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		onFile(file) {
			if (file) {
				this.$emit('importBookmarks', file)
			}
		},

		/**
		 * Place the chosen tiles, then close.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		async submit() {
			const tiles = this.chosenTiles
			if (tiles.length === 0 || this.busy) {
				return
			}
			this.busy = true
			this.error = ''
			try {
				await this.addTiles(tiles)
				this.$emit('added', tiles)
				this.close()
			} catch {
				this.error = t('launchpad', 'The tiles could not be added.')
			} finally {
				this.busy = false
			}
		},

		/**
		 * Open the request form, starting from the search.
		 *
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		openRequestForm() {
			this.request = { ...emptyRequest(), open: true, name: this.search.trim() }
		},

		/**
		 * Close the request form.
		 *
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		closeRequestForm() {
			this.request = emptyRequest()
		},

		/**
		 * Ask IT for the app and say how it went.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
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

		/**
		 * Forget the choices and the forms.
		 *
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		reset() {
			this.search = ''
			this.chosen = []
			this.error = ''
			this.closeRequestForm()
		},

		/**
		 * Close the dialog.
		 *
		 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-the-add-tiles-dialog-req-tile-043
		 */
		close() {
			this.reset()
			this.$emit('close')
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
</style>
