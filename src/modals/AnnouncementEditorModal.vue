<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		:name="t('launchpad', 'New announcement')"
		size="normal"
		@close="$emit('close')">
		<div class="announcement-editor">
			<h3 class="announcement-editor__heading">
				{{
					step === 'edit'
						? t('launchpad', 'New announcement')
						: t('launchpad', 'Preview')
				}}
			</h3>

			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>

			<template v-if="step === 'edit'">
				<NcSelect
					v-model="form.kind"
					:options="kindOptions"
					:reduce="(option) => option.id"
					label="label"
					:inputLabel="t('launchpad', 'Kind')"
					:clearable="false" />
				<NcTextField
					v-model="form.title"
					:label="t('launchpad', 'Title')"
					:maxlength="255"
					required />
				<NcTextArea
					v-model="form.body"
					:label="t('launchpad', 'Text')"
					:maxlength="20000" />
				<NcTextField
					v-model="form.category"
					:label="t('launchpad', 'Category')"
					:maxlength="128" />
				<NcSelect
					v-model="form.targetGroups"
					multiple
					taggable
					:options="form.targetGroups"
					:inputLabel="t('launchpad', 'Target groups')"
					:placeholder="t('launchpad', 'Everyone when empty')" />
				<NcDateTimePickerNative
					id="announcement-publish-at"
					v-model="publishAt"
					type="datetime-local"
					:label="t('launchpad', 'Publish at')" />
				<NcDateTimePickerNative
					id="announcement-expires-at"
					v-model="expiresAt"
					type="datetime-local"
					:label="t('launchpad', 'End time')" />
				<template v-if="form.kind === 'notice'">
					<NcSelect
						v-model="form.level"
						:options="levelOptions"
						:reduce="(option) => option.id"
						label="label"
						:inputLabel="t('launchpad', 'Level')"
						:clearable="false" />
					<NcCheckboxRadioSwitch v-model="form.dismissible">
						{{ t('launchpad', 'Readers may close it') }}
					</NcCheckboxRadioSwitch>
				</template>
				<NcCheckboxRadioSwitch v-else v-model="form.allowComments">
					{{ t('launchpad', 'Readers may comment') }}
				</NcCheckboxRadioSwitch>
			</template>

			<template v-else>
				<NoticeBanner
					v-if="form.kind === 'notice'"
					:notice="previewItem"
					hideDismiss />
				<AnnouncementCard v-else :item="previewItem" preview />
				<p class="announcement-editor__reach">
					{{ t('launchpad', 'People reached: {count}', { count: reach }) }}
				</p>
				<div class="announcement-editor__preview-as">
					<NcTextField
						v-model="previewUserId"
						:label="t('launchpad', 'Preview as (user ID)')" />
					<NcButton
						:disabled="previewUserId.trim() === ''"
						@click="checkPreviewUser">
						{{ t('launchpad', 'Check') }}
					</NcButton>
				</div>
				<p
					v-if="previewAnswer !== null"
					class="announcement-editor__preview-answer"
					role="status">
					{{
						previewAnswer
							? t('launchpad', '{user} sees this announcement', {
									user: previewUserId,
								})
							: t(
									'launchpad',
									'{user} does not see this announcement',
									{ user: previewUserId },
								)
					}}
				</p>
			</template>

			<div class="announcement-editor__actions">
				<NcButton
					v-if="step === 'preview'"
					variant="tertiary"
					@click="step = 'edit'">
					{{ t('launchpad', 'Back to editing') }}
				</NcButton>
				<NcButton
					v-if="step === 'edit'"
					variant="primary"
					:disabled="busy || form.title.trim() === ''"
					@click="toPreview">
					{{ t('launchpad', 'Preview') }}
				</NcButton>
				<NcButton v-else variant="primary" :disabled="busy" @click="publish">
					{{ t('launchpad', 'Publish') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDateTimePickerNative,
	NcModal,
	NcNoteCard,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@nextcloud/vue'
import AnnouncementCard from '../components/Announcements/AnnouncementCard.vue'
import NoticeBanner from '../components/Announcements/NoticeBanner.vue'
import {
	announcementReach,
	createAnnouncement,
	publishAnnouncement,
	updateAnnouncement,
} from '../services/announcements.js'

/**
 * The server's error text, or a general one.
 *
 * @param {Error} error The failure.
 * @return {string} A sentence to show.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 */
function errorText(error) {
	return (
		error?.response?.data?.error
		|| t('launchpad', 'The announcement could not be saved.')
	)
}

/**
 * AnnouncementEditorModal: write an announcement, preview it as readers
 * will see it with its reach and "Preview as", then publish it as a
 * separate step (REQ-ANN-004).
 */
export default {
	name: 'AnnouncementEditorModal',

	components: {
		AnnouncementCard,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePickerNative,
		NcModal,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		NcTextField,
		NoticeBanner,
	},

	emits: ['close', 'published'],

	data() {
		return {
			step: 'edit',
			busy: false,
			error: '',
			uuid: null,
			reach: 0,
			previewUserId: '',
			previewAnswer: null,
			publishAt: null,
			expiresAt: null,
			form: {
				kind: 'news',
				title: '',
				body: '',
				category: '',
				targetGroups: [],
				level: 'info',
				dismissible: true,
				allowComments: true,
			},
		}
	},

	computed: {
		/** @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004 */
		kindOptions() {
			return [
				{ id: 'news', label: t('launchpad', 'News item') },
				{
					id: 'notice',
					label: t('launchpad', 'Notice above the dashboard'),
				},
			]
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
		levelOptions() {
			return [
				{ id: 'info', label: t('launchpad', 'Information') },
				{ id: 'warning', label: t('launchpad', 'Warning') },
			]
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004 */
		payload() {
			return {
				...this.form,
				publishAt: this.publishAt
					? new Date(this.publishAt).toISOString()
					: '',

				expiresAt: this.expiresAt
					? new Date(this.expiresAt).toISOString()
					: '',
			}
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004 */
		previewItem() {
			return {
				uuid: this.uuid || 'preview',
				...this.form,
				likeCount: 0,
				commentCount: 0,
				likedByMe: false,
				publishAt: null,
			}
		},
	},

	methods: {
		t,

		/**
		 * Save the draft and show the preview with its reach.
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
		 */
		async toPreview() {
			this.busy = true
			this.error = ''
			try {
				const saved = this.uuid
					? await updateAnnouncement(this.uuid, this.payload)
					: await createAnnouncement(this.payload)
				this.uuid = saved.uuid
				const answer = await announcementReach(this.form.targetGroups)
				this.reach = answer.reach
				this.previewAnswer = null
				this.step = 'preview'
			} catch (error) {
				this.error = errorText(error)
			} finally {
				this.busy = false
			}
		},

		/**
		 * "Preview as": does the targeting reach this person?
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
		 */
		async checkPreviewUser() {
			this.error = ''
			try {
				const answer = await announcementReach(
					this.form.targetGroups,
					this.previewUserId.trim(),
				)
				this.previewAnswer = answer.reachesPreviewUser === true
			} catch (error) {
				this.previewAnswer = null
				this.error = errorText(error)
			}
		},

		/**
		 * Publish the previewed draft.
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
		 */
		async publish() {
			this.busy = true
			this.error = ''
			try {
				const published = await publishAnnouncement(this.uuid)
				this.$emit('published', published)
				this.$emit('close')
			} catch (error) {
				this.error = errorText(error)
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

.announcement-editor__heading {
	margin: 0;
}

.announcement-editor__reach {
	margin: 0;
	font-weight: bold;
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
</style>
