<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		class="launchpad-modal"
		size="large"
		:name="t('launchpad', 'New announcement')"
		@close="$emit('close')">
		<div class="announcement-editor" data-test="announcement-editor">
			<h2>{{ t('launchpad', 'New announcement') }}</h2>

			<div class="announcement-editor__columns">
				<form
					id="announcement-editor-form"
					class="announcement-editor__form"
					data-test="announcement-editor-form"
					@submit.prevent="submit">
					<NcTextField v-model="form.title" :label="t('launchpad', 'Title')" :required="true" data-test="editor-title" />
					<NcTextArea v-model="form.body" :label="t('launchpad', 'Text')" data-test="editor-body" />
					<NcSelect
						v-model="form.targetGroups"
						:options="groupOptions"
						:multiple="true"
						:reduce="(option) => option.id"
						label="displayName"
						:inputLabel="t('launchpad', 'Target group')"
						:placeholder="t('launchpad', 'Everyone')"
						data-test="editor-groups" />

					<!-- REQ-ANN-006 (board LpAankondiging): the kind as two choice cards. -->
					<fieldset class="announcement-editor__kinds" data-test="editor-kind">
						<legend>{{ t('launchpad', 'Kind') }}</legend>
						<label
							v-for="option in kindOptions"
							:key="option.value"
							class="announcement-editor__kind"
							:class="{ 'announcement-editor__kind--chosen': form.kind === option.value }"
							:data-test="`editor-kind-${option.value}`">
							<input
								v-model="form.kind"
								class="announcement-editor__kind-input"
								type="radio"
								name="announcement-kind"
								:value="option.value">
							<span class="announcement-editor__kind-label">{{ option.label }}</span>
							<span class="announcement-editor__kind-hint">{{ option.hint }}</span>
						</label>
					</fieldset>

					<template v-if="form.kind === 'notice'">
						<NcSelect
							v-model="form.level"
							:options="levelOptions"
							:inputLabel="t('launchpad', 'Level')"
							:reduce="(option) => option.value"
							label="label"
							:clearable="false"
							data-test="editor-level" />
						<NcCheckboxRadioSwitch v-model="form.dismissible" data-test="editor-dismissible">
							{{ t('launchpad', 'Readers may dismiss this notice') }}
						</NcCheckboxRadioSwitch>
					</template>
					<template v-else>
						<NcTextField v-model="form.category" :label="t('launchpad', 'Category')" data-test="editor-category" />
						<!-- REQ-ANN-007: ask the audience to confirm they have read it, before a date. -->
						<NcCheckboxRadioSwitch v-model="form.requiresConfirmation" data-test="editor-requires-confirmation">
							{{ t('launchpad', 'Ask for read confirmation') }}
						</NcCheckboxRadioSwitch>
						<label v-if="form.requiresConfirmation" class="announcement-editor__label">
							{{ t('launchpad', 'Confirm before') }}
							<input v-model="form.confirmBefore" type="date" data-test="editor-confirm-before">
						</label>
						<NcCheckboxRadioSwitch v-model="form.allowComments" data-test="editor-comments">
							{{ t('launchpad', 'Allow comments and following') }}
						</NcCheckboxRadioSwitch>
					</template>

					<div class="announcement-editor__times">
						<label class="announcement-editor__label">
							{{ t('launchpad', 'Publish on (optional)') }}
							<input v-model="form.publishAt" type="datetime-local" data-test="editor-publish-at">
						</label>
						<!-- REQ-ANN-001 (board LpAankondiging): every announcement has an
						     end time, "Show until". -->
						<label class="announcement-editor__label">
							{{ t('launchpad', 'Show until') }}
							<input
								v-model="form.expiresAt"
								type="datetime-local"
								required
								aria-describedby="announcement-editor-show-until-hint"
								data-test="editor-expires-at">
						</label>
					</div>
					<p id="announcement-editor-show-until-hint" class="announcement-editor__hint" data-test="editor-show-until-hint">
						{{ t('launchpad', 'After this time the announcement disappears from every dashboard.') }}
					</p>
				</form>

				<!-- REQ-ANN-006: "This is how it looks", a live preview that follows every field. -->
				<aside class="announcement-editor__preview" :aria-label="t('launchpad', 'This is how it looks')" data-test="editor-preview-pane">
					<h3 class="announcement-editor__preview-title">
						{{ t('launchpad', 'This is how it looks') }}
					</h3>
					<NoticeBanner v-if="form.kind === 'notice'" :notice="previewItem" :preview="true" />
					<AnnouncementCard v-else :announcement="previewItem" :preview="true" />
					<p class="announcement-editor__hint" data-test="editor-preview-note">
						{{ previewNote }}
					</p>
					<p v-if="visibleUntil" data-test="editor-visible-until">
						{{ t('launchpad', 'Visible until {when}', { when: visibleUntil }) }}
					</p>
					<p data-test="editor-reach">
						{{ n('launchpad', 'Reaches {count} person', 'Reaches {count} people', reach, { count: reach }) }}
					</p>
					<form class="announcement-editor__preview-as" @submit.prevent="checkPreviewAs">
						<NcTextField v-model="previewAs" :label="t('launchpad', 'Preview as (user id)')" data-test="editor-preview-as" />
						<NcButton type="submit" variant="secondary" :disabled="previewAs.trim() === ''">
							{{ t('launchpad', 'Check') }}
						</NcButton>
					</form>
					<p v-if="previewAsAnswer !== null" data-test="editor-preview-as-answer">
						{{ previewAsAnswer ? t('launchpad', '{user} sees this announcement', { user: previewAsUser }) : t('launchpad', '{user} does not see this announcement', { user: previewAsUser }) }}
					</p>
				</aside>
			</div>

			<p v-if="error" class="announcement-editor__error" role="alert" data-test="editor-error">
				{{ error }}
			</p>

			<!-- REQ-ANN-006: Cancel and one primary action, Schedule (Publish
			     when Publish on is empty or past). -->
			<div class="announcement-editor__actions">
				<NcButton variant="tertiary" :disabled="busy" data-test="editor-cancel" @click="$emit('close')">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="busy || form.title.trim() === ''"
					data-test="editor-publish"
					@click="submit">
					{{ scheduled ? t('launchpad', 'Schedule') : t('launchpad', 'Publish') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@conduction/nextcloud-vue'
import { getCanonicalLocale, n, t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import AnnouncementCard from '../components/Announcements/AnnouncementCard.vue'
import NoticeBanner from '../components/Announcements/NoticeBanner.vue'
import { announcementsApi } from '../services/announcementsApi.js'
import { logger } from '../utils/logger.js'

/**
 * A `datetime-local` value as an ISO string with the browser's offset, or null when empty.
 *
 * @param {string} value The input value.
 * @return {string|null}
 */
export function toIso(value) {
	if (!value) {
		return null
	}
	const date = new Date(value)
	return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

/**
 * Why the form cannot go on: no end time, or an end time not after the
 * publish time; '' when it can (REQ-ANN-001).
 *
 * @param {string} publishAt The publish time (`datetime-local`), '' for when it is published.
 * @param {string} expiresAt The end time (`datetime-local`).
 * @param {Date} now The current moment, standing in for an empty publish time.
 * @return {string} The refusal, or ''.
 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
export function endTimeProblem(publishAt, expiresAt, now) {
	const end = toIso(expiresAt)
	if (end === null) {
		return t('launchpad', 'Choose until when the announcement shows')
	}
	const start = toIso(publishAt) ?? now.toISOString()
	if (new Date(end) <= new Date(start)) {
		return t('launchpad', 'The end time must be after the publish time')
	}
	return ''
}

/**
 * The "Confirm before" date as the moment it ends: the end of that day in
 * the author's time zone, or null without a date (REQ-ANN-007).
 *
 * @param {string} date The `date` input value, `YYYY-MM-DD`.
 * @return {string|null}
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export function confirmBeforeIso(date) {
	return date ? toIso(`${date}T23:59`) : null
}

/**
 * Why the read confirmation cannot go on: a "Confirm before" date that is
 * not after the publish time; '' when it can (REQ-ANN-007).
 *
 * @param {string} publishAt The publish time (`datetime-local`), '' for when it is published.
 * @param {string|null} confirmBefore The deadline, ISO 8601, or null.
 * @param {Date} now The current moment, standing in for an empty publish time.
 * @return {string} The refusal, or ''.
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export function confirmBeforeProblem(publishAt, confirmBefore, now) {
	if (!confirmBefore) {
		return ''
	}
	const start = toIso(publishAt) ?? now.toISOString()
	return new Date(confirmBefore) <= new Date(start)
		? t('launchpad', 'The confirm-before date must be after the publish time')
		: ''
}

/**
 * The end time as the preview names it, "30 October 17:00" in the
 * reader's language, or '' without one.
 *
 * @param {string} expiresAt The end time (`datetime-local`).
 * @param {string} [locale] BCP 47 locale.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
export function visibleUntilText(expiresAt, locale) {
	const iso = toIso(expiresAt)
	if (iso === null) {
		return ''
	}
	const date = new Date(iso)
	const day = date.toLocaleDateString(locale, { day: 'numeric', month: 'long' })
	const time = date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
	return `${day} ${time}`
}

/**
 * The note under the preview: only the author sees it until the
 * publication time, named when there is one (REQ-ANN-006).
 *
 * @param {string} publishAt The publish time (`datetime-local`), '' for when it is published.
 * @param {string} [locale] BCP 47 locale.
 * @return {string}
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */
export function previewNoteText(publishAt, locale) {
	const when = visibleUntilText(publishAt, locale)
	return when === ''
		? t('launchpad', 'Only you see this preview until you publish the announcement.')
		: t('launchpad', 'Only you see this preview until {when}.', { when })
}

/**
 * The groups the target group picker offers, with the chosen ones that
 * the list does not hold kept, so a stored choice never disappears.
 *
 * @param {Array<object>} groups `{id, displayName}` from the server.
 * @param {Array<string>} chosen The chosen group ids.
 * @return {Array<object>}
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */
export function groupChoices(groups, chosen) {
	const known = new Set(groups.map((group) => group.id))
	return [
		...groups,
		...chosen.filter((id) => !known.has(id)).map((id) => ({ id, displayName: id })),
	]
}

/**
 * AnnouncementEditorModal — one dialog as board LpAankondiging draws it: the
 * form on the left, "This is how it looks" on the right, a live preview of
 * the same card or banner readers get that follows every field without
 * saving, with the number of people the targeting reaches and "Preview as"
 * one person (REQ-ANN-004). It ends with Cancel and one primary action:
 * Schedule, or Publish when Publish on is empty or past (REQ-ANN-006). A
 * news item can ask for a read confirmation before a date; the preview then
 * shows "I have read this" and "Confirm before <date>" (REQ-ANN-007).
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
export default {
	name: 'AnnouncementEditorModal',

	components: { AnnouncementCard, NcButton, NcCheckboxRadioSwitch, NcModal, NcSelect, NcTextArea, NcTextField, NoticeBanner },

	emits: ['close', 'published'],

	data() {
		return {
			busy: false,
			groups: [],
			error: '',
			uuid: '',
			reach: 0,
			previewAs: '',
			previewAsUser: '',
			previewAsAnswer: null,
			form: {
				kind: 'news',
				title: '',
				body: '',
				category: '',
				targetGroups: [],
				level: 'info',
				dismissible: true,
				allowComments: true,
				publishAt: '',
				expiresAt: '',
				requiresConfirmation: false,
				confirmBefore: '',
			},
		}
	},

	computed: {
		/**
		 * The kind as two choice cards (REQ-ANN-006).
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		kindOptions() {
			return [
				{ value: 'news', label: t('launchpad', 'News'), hint: t('launchpad', 'Shown in the Announcements widget.') },
				{ value: 'notice', label: t('launchpad', 'Maintenance notice'), hint: t('launchpad', 'Shown at the top of every dashboard until the end time.') },
			]
		},

		/**
		 * @return {Array<object>} The target group picker's options.
		 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		groupOptions() {
			return groupChoices(this.groups, this.form.targetGroups)
		},

		/**
		 * @return {string} Who sees the preview, and until when.
		 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		previewNote() {
			return previewNoteText(this.form.publishAt, getCanonicalLocale())
		},

		/** @return {Array<object>} */
		levelOptions() {
			return [
				{ value: 'info', label: t('launchpad', 'Information') },
				{ value: 'warning', label: t('launchpad', 'Warning') },
			]
		},

		/**
		 * @return {object} The fields the API takes.
		 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
		 */
		payload() {
			const isNotice = this.form.kind === 'notice'
			return {
				kind: this.form.kind,
				title: this.form.title.trim(),
				body: this.form.body,
				category: this.form.category.trim() || null,
				targetGroups: this.form.targetGroups,
				level: isNotice ? this.form.level : null,
				dismissible: isNotice ? this.form.dismissible : false,
				allowComments: isNotice ? false : this.form.allowComments,
				publishAt: toIso(this.form.publishAt),
				expiresAt: toIso(this.form.expiresAt),
				requiresConfirmation: !isNotice && this.form.requiresConfirmation,
				confirmBefore: !isNotice && this.form.requiresConfirmation ? confirmBeforeIso(this.form.confirmBefore) : null,
			}
		},

		/**
		 * The end time as the preview names it.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
		 */
		visibleUntil() {
			return visibleUntilText(this.form.expiresAt, getCanonicalLocale())
		},

		/**
		 * Whether the publish time lies in the future, so the confirm button
		 * schedules rather than publishes.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
		 */
		scheduled() {
			const start = toIso(this.form.publishAt)
			return start !== null && new Date(start).getTime() > Date.now()
		},

		/** @return {object} The draft as the card or banner renders it. */
		previewItem() {
			return { uuid: this.uuid || 'preview', ...this.payload }
		},
	},

	watch: {
		'form.targetGroups': {
			/** @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006 */
			handler() {
				this.loadReach()
			},

			deep: true,
		},
	},

	/** @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006 */
	mounted() {
		this.loadGroups()
		this.loadReach()
	},

	methods: {
		n,
		t,

		/**
		 * Save the announcement and publish it, or schedule it for its
		 * publish time, in one step (REQ-ANN-006).
		 *
		 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 * @spec openspec/changes/archive/2026-11-24-announcement-show-until/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
		 */
		async submit() {
			if (this.busy || this.form.title.trim() === '') {
				return
			}
			const problem = endTimeProblem(this.form.publishAt, this.form.expiresAt, new Date())
				|| confirmBeforeProblem(this.form.publishAt, this.payload.confirmBefore, new Date())
			if (problem !== '') {
				this.error = problem
				return
			}
			await this.run(async () => {
				const saved = this.uuid
					? await announcementsApi.update(this.uuid, this.payload)
					: await announcementsApi.create(this.payload)
				this.uuid = saved.uuid
				const published = await announcementsApi.publish(this.uuid)
				this.$emit('published', published)
			})
		},

		/**
		 * Count the people the target groups reach; the preview keeps the
		 * last count when the server does not answer.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
		 */
		async loadReach() {
			try {
				this.reach = (await announcementsApi.reach(this.form.targetGroups)) ?? 0
			} catch (error) {
				logger.error('Announcement reach failed:', error)
			}
		},

		/**
		 * Load the groups the target group picker offers.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		async loadGroups() {
			try {
				this.groups = (await announcementsApi.groups()) ?? []
			} catch (error) {
				logger.error('Announcement groups failed:', error)
			}
		},

		/** @spec openspec/changes/engagement-announcements/tasks.md#task-8 */
		async checkPreviewAs() {
			const user = this.previewAs.trim()
			await this.run(async () => {
				const { reachesUser } = await announcementsApi.reachFor(this.payload.targetGroups, user)
				this.previewAsUser = user
				this.previewAsAnswer = reachesUser === true
			})
		},

		/**
		 * Run one API step, showing the server's refusal.
		 *
		 * @param {Function} step The step.
		 * @return {Promise<void>}
		 */
		async run(step) {
			this.busy = true
			this.error = ''
			try {
				await step()
			} catch (error) {
				logger.error('Announcement editor step failed:', error)
				this.error = error?.response?.data?.message ?? error?.response?.data?.error ?? t('launchpad', 'Something went wrong')
			} finally {
				this.busy = false
			}
		},
	},
}
</script>

<style scoped>
.announcement-editor {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
}

.announcement-editor__columns {
	display: grid;
	grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
	gap: 24px;
	align-items: start;
}

.announcement-editor__form,
.announcement-editor__preview {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.announcement-editor__preview {
	padding: 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.announcement-editor__preview-title {
	margin: 0;
	font-size: 1em;
}

.announcement-editor__kinds {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 8px;
	margin: 0;
	padding: 0;
	border: 0;
}

.announcement-editor__kinds legend {
	margin-bottom: 4px;
}

.announcement-editor__kind {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 12px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	cursor: pointer;
}

.announcement-editor__kind--chosen {
	border-color: var(--color-primary-element);
}

.announcement-editor__kind:focus-within {
	outline: 2px solid var(--color-main-text);
	outline-offset: 2px;
}

.announcement-editor__kind-input {
	position: absolute;
	opacity: 0;
	pointer-events: none;
}

.announcement-editor__kind-label {
	font-weight: bold;
}

.announcement-editor__kind-hint {
	color: var(--color-text-maxcontrast);
}

.announcement-editor__times {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 8px;
}

.announcement-editor__label {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.announcement-editor__hint {
	margin: -8px 0 0;
	color: var(--color-text-maxcontrast);
}

.announcement-editor__preview .announcement-editor__hint {
	margin: 0;
}

.announcement-editor__preview-as {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.announcement-editor__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}

.announcement-editor__error {
	color: var(--color-error-text, var(--color-error));
}

@media (max-width: 720px) {
	.announcement-editor__columns,
	.announcement-editor__times {
		grid-template-columns: 1fr;
	}
}
</style>
