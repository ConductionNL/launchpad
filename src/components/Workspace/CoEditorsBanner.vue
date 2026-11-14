<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="editors.length > 0"
		class="launchpad-co-editors"
		role="status"
		data-testid="co-editors-banner">
		<NcNoteCard type="info">
			<p>{{ message }}</p>
			<div class="launchpad-co-editors__who">
				<span class="launchpad-co-editors__label">{{ t('launchpad', 'Who else is editing?') }}</span>
				<ul class="launchpad-co-editors__list">
					<li
						v-for="(editor, index) in editors"
						:key="index"
						class="launchpad-co-editors__initials"
						:title="editor.displayName"
						:aria-label="editor.displayName"
						data-testid="co-editor">
						{{ initialsOf(editor.displayName) }}
					</li>
				</ul>
			</div>
		</NcNoteCard>
	</div>
</template>

<script>
import { NcNoteCard } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { LIST_PROP } from '../../constants/propDefinitions.js'

/**
 * The initials of a display name: the first letter of its first and last
 * word, upper-cased; one letter for a one-word name.
 *
 * @param {string} name The display name.
 * @return {string}
 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
 */
export function initialsOf(name) {
	const words = String(name ?? '').trim().split(/\s+/).filter(Boolean)
	if (words.length === 0) {
		return '?'
	}
	const first = words[0].charAt(0)
	const last = words.length > 1 ? words[words.length - 1].charAt(0) : ''
	return (first + last).toUpperCase()
}

/**
 * The clock time of an ISO date-time in the browser's own zone, as HH:MM.
 *
 * @param {string} since The ISO date-time.
 * @return {string} The time, or the input when it cannot be read.
 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
 */
export function clockTime(since) {
	const date = new Date(since)
	if (Number.isNaN(date.getTime())) {
		return String(since ?? '')
	}
	return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

/**
 * CoEditorsBanner: in edit mode, who else edits this dashboard and since
 * when, with their initials (building-edit-a-dashboard-together, LpBewerken).
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */
export default {
	name: 'CoEditorsBanner',

	components: {
		NcNoteCard,
	},

	props: {
		/** The colleagues editing, `{displayName, since}`, longest first. */
		editors: LIST_PROP,
	},

	computed: {
		/**
		 * @return {string} The line about the colleague editing longest.
		 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
		 */
		message() {
			const first = this.editors[0] ?? {}
			return t(
				'launchpad',
				'{name} is also editing this dashboard, since {time}. Your changes are not overwritten.',
				{ name: first.displayName ?? '', time: clockTime(first.since) },
			)
		},
	},

	methods: {
		t,
		initialsOf,
	},
}
</script>

<style scoped>
.launchpad-co-editors {
	margin: 0 8px 8px;
}

.launchpad-co-editors__who {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.launchpad-co-editors__list {
	display: flex;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.launchpad-co-editors__initials {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 28px;
	padding: 0 4px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: var(--font-size-small, 12px);
	font-weight: bold;
}
</style>
