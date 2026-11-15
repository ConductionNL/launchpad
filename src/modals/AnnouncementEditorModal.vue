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
			<h2>{{ step === 'write' ? t('launchpad', 'New announcement') : t('launchpad', 'Preview') }}</h2>

			<form v-if="step === 'write'" class="announcement-editor__form" @submit.prevent="toPreview">
				<NcSelect
					v-model="form.kind"
					:options="kindOptions"
					:inputLabel="t('launchpad', 'Kind')"
					:reduce="(option) => option.value"
					label="label"
					:clearable="false"
					data-test="editor-kind" />
				<NcTextField v-model="form.title" :label="t('launchpad', 'Title')" :required="true" data-test="editor-title" />
				<NcTextArea v-model="form.body" :label="t('launchpad', 'Text')" data-test="editor-body" />
				<NcTextField v-model="form.category" :label="t('launchpad', 'Category')" data-test="editor-category" />
				<NcSelect
					v-model="form.targetGroups"
					:options="[]"
					:multiple="true"
					:taggable="true"
					:inputLabel="t('launchpad', 'Target groups (empty for everyone)')"
					data-test="editor-groups" />
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
				<NcCheckboxRadioSwitch v-else v-model="form.allowComments" data-test="editor-comments">
					{{ t('launchpad', 'Allow comments') }}
				</NcCheckboxRadioSwitch>
				<label class="announcement-editor__label">
					{{ t('launchpad', 'Publish at (empty for when you publish)') }}
					<input v-model="form.publishAt" type="datetime-local" data-test="editor-publish-at">
				</label>
				<label class="announcement-editor__label">
					{{ form.kind === 'notice' ? t('launchpad', 'Ends at') : t('launchpad', 'Ends at (optional)') }}
					<input v-model="form.expiresAt" type="datetime-local" :required="form.kind === 'notice'" data-test="editor-expires-at">
				</label>
				<div class="announcement-editor__actions">
					<NcButton type="submit" variant="primary" :disabled="busy || form.title.trim() === ''" data-test="editor-preview">
						{{ t('launchpad', 'Preview') }}
					</NcButton>
				</div>
			</form>

			<div v-else class="announcement-editor__preview" data-test="editor-preview-step">
				<NoticeBanner v-if="form.kind === 'notice'" :notice="previewItem" :preview="true" />
				<AnnouncementCard v-else :announcement="previewItem" :preview="true" />
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
				<div class="announcement-editor__actions">
					<NcButton variant="secondary" :disabled="busy" @click="step = 'write'">
						{{ t('launchpad', 'Back') }}
					</NcButton>
					<NcButton variant="primary" :disabled="busy" data-test="editor-publish" @click="publish">
						{{ t('launchpad', 'Publish') }}
					</NcButton>
				</div>
			</div>

			<p v-if="error" class="announcement-editor__error" role="alert" data-test="editor-error">
				{{ error }}
			</p>
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@conduction/nextcloud-vue'
import { n, t } from '@nextcloud/l10n'
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
 * AnnouncementEditorModal — write an announcement, see it as readers will
 * (the same card or banner component), with the number of people its
 * targeting reaches and "Preview as" one person, then publish as a separate
 * step (REQ-ANN-004). Going to the preview saves a draft, which only authors
 * and administrators see.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 */
export default {
	name: 'AnnouncementEditorModal',

	components: { AnnouncementCard, NcButton, NcCheckboxRadioSwitch, NcModal, NcSelect, NcTextArea, NcTextField, NoticeBanner },

	emits: ['close', 'published'],

	data() {
		return {
			step: 'write',
			busy: false,
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
			},
		}
	},

	computed: {
		/** @return {Array<object>} */
		kindOptions() {
			return [
				{ value: 'news', label: t('launchpad', 'News') },
				{ value: 'notice', label: t('launchpad', 'Notice') },
			]
		},

		/** @return {Array<object>} */
		levelOptions() {
			return [
				{ value: 'info', label: t('launchpad', 'Information') },
				{ value: 'warning', label: t('launchpad', 'Warning') },
			]
		},

		/** @return {object} The fields the API takes. */
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
			}
		},

		/** @return {object} The draft as the card or banner renders it. */
		previewItem() {
			return { uuid: this.uuid || 'preview', ...this.payload }
		},
	},

	methods: {
		n,
		t,

		/**
		 * Save the draft and show the preview with its reach.
		 *
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-8
		 */
		async toPreview() {
			if (this.form.kind === 'notice' && !this.form.expiresAt) {
				this.error = t('launchpad', 'A notice needs an end time')
				return
			}
			await this.run(async () => {
				const saved = this.uuid
					? await announcementsApi.update(this.uuid, this.payload)
					: await announcementsApi.create(this.payload)
				this.uuid = saved.uuid
				this.reach = await announcementsApi.reach(this.payload.targetGroups)
				this.step = 'preview'
			})
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

		/** @spec openspec/changes/engagement-announcements/tasks.md#task-8 */
		async publish() {
			await this.run(async () => {
				const published = await announcementsApi.publish(this.uuid)
				this.$emit('published', published)
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

.announcement-editor__form,
.announcement-editor__preview {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.announcement-editor__label {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.announcement-editor__preview-as,
.announcement-editor__actions {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.announcement-editor__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
