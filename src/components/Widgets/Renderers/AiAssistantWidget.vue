<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="ai-assistant" data-testid="ai-assistant">
		<p
			v-if="available === false"
			class="ai-assistant__unavailable"
			role="status"
			data-testid="ai-assistant-unavailable">
			{{
				t(
					'launchpad',
					'The assistant needs the Hermiq app, which is not available here.',
				)
			}}
		</p>
		<template v-else>
			<ol
				v-if="!summaryMode"
				class="ai-assistant__messages"
				aria-live="polite">
				<li
					v-for="(message, index) in chat.messages"
					:key="index"
					:class="`ai-assistant__message ai-assistant__message--${message.role}`">
					{{ message.content }}
				</li>
				<li
					v-if="chat.isStreaming"
					class="ai-assistant__message ai-assistant__message--assistant">
					{{ chat.currentText || t('launchpad', 'Thinking…') }}
				</li>
			</ol>
			<p
				v-else-if="lastAnswer"
				class="ai-assistant__summary"
				aria-live="polite">
				{{ lastAnswer }}
			</p>
			<p
				v-if="chat.error"
				class="ai-assistant__error"
				role="alert"
				data-testid="ai-assistant-error">
				{{ chat.error.message }}
			</p>
			<form
				v-if="!summaryMode"
				class="ai-assistant__ask"
				@submit.prevent="ask">
				<label class="hidden-visually" :for="inputId">{{
					t('launchpad', 'Your question')
				}}</label>
				<input
					:id="inputId"
					v-model="question"
					type="text"
					:disabled="available !== true || chat.isStreaming"
					:placeholder="t('launchpad', 'Ask about this dashboard')"
					data-testid="ai-assistant-input" />
				<button
					type="submit"
					:disabled="
						available !== true
						|| chat.isStreaming
						|| question.trim() === ''
					"
					data-testid="ai-assistant-send">
					{{ t('launchpad', 'Ask') }}
				</button>
			</form>
			<button
				v-else
				type="button"
				:disabled="available !== true || chat.isStreaming"
				data-testid="ai-assistant-summarise"
				@click="summarise">
				{{ t('launchpad', 'Summarise this dashboard') }}
			</button>
		</template>
	</div>
</template>

<script>
import { chatHealthUrl, useAiChatStream } from '@conduction/nextcloud-vue'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { useDashboardStore } from '../../../stores/dashboard.js'

/** The fleet's chat engine. */
export const CHAT_APP_ID = 'hermiq'

let nextId = 0

/**
 * The open dashboard's uuid, for the chat context.
 *
 * @return {string|null} The uuid, or null outside the workspace page.
 */
function activeDashboardUuid() {
	try {
		return useDashboardStore().activeDashboard?.uuid ?? null
	} catch {
		return null
	}
}

/**
 * AiAssistantWidget: ask a question about the dashboard and read the answer
 * as it streams. Every question goes to Hermiq through nextcloud-vue's
 * useAiChatStream (POST /apps/hermiq/api/chat/stream, falling back to
 * /api/chat/send), with the dashboard as context. launchpad carries no model
 * client and no prompt in code. When Hermiq's health probe does not answer,
 * the input is disabled and says so. A placement under six cells shows one
 * "Summarise this dashboard" button instead of the chat.
 *
 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
 */
export default {
	name: 'AiAssistantWidget',

	props: {
		/** Persisted content: `{agentUuid?}`. */
		content: {
			type: Object,
			default: () => ({}),
		},

		/** The placement (`gridWidth`, `gridHeight`). */
		placement: {
			type: Object,
			default: null,
		},
	},

	/** @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md */
	data() {
		return {
			available: null,
			question: '',
			inputId: `ai-assistant-${nextId++}`,
			chat: useAiChatStream(null, {
				chatAppId: CHAT_APP_ID,
				context: {
					appId: 'launchpad',
					pageKind: 'dashboard',
					objectUuid: activeDashboardUuid(),
					registerSlug: null,
					schemaSlug: null,
					fileId: null,
					route:
						typeof window !== 'undefined'
							? window.location.pathname
							: '',
				},
			}),
		}
	},

	computed: {
		/**
		 * @return {boolean} Whether the placement is too small for a chat.
		 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
		 */
		summaryMode() {
			const cells =
				Number(this.placement?.gridWidth ?? 4)
				* Number(this.placement?.gridHeight ?? 4)
			return cells < 6
		},

		/**
		 * @return {string} The last answer, for summary mode.
		 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
		 */
		lastAnswer() {
			const answers = this.chat.messages.filter((m) => m.role === 'assistant')
			return this.chat.isStreaming
				? this.chat.currentText
				: (answers[answers.length - 1]?.content ?? '')
		},
	},

	/** @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md */
	async mounted() {
		try {
			await axios.get(chatHealthUrl(CHAT_APP_ID), { timeout: 5000 })
			this.available = true
		} catch {
			this.available = false
		}
	},

	methods: {
		t,

		/**
		 * Send the typed question.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
		 */
		async ask() {
			const question = this.question.trim()
			if (question === '' || this.available !== true) {
				return
			}
			this.question = ''
			try {
				await this.chat.send(question, {
					agentUuid: this.content?.agentUuid || '',
				})
			} catch {
				// chat.error carries the message the widget shows.
			}
		},

		/**
		 * Ask for a summary of the dashboard (summary mode).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/launchpad-ai-dashboard-assistant/spec.md
		 */
		async summarise() {
			if (this.available !== true) {
				return
			}
			try {
				await this.chat.send(
					t('launchpad', 'Summarise this dashboard for me.'),
					{ agentUuid: this.content?.agentUuid || '' },
				)
			} catch {
				// chat.error carries the message the widget shows.
			}
		},
	},
}
</script>

<style scoped>
.ai-assistant {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
}

.ai-assistant__messages {
	flex: 1;
	overflow-y: auto;
	margin: 0;
	padding: 0;
	list-style: none;
}

.ai-assistant__message {
	margin: 4px 0;
	padding: 6px 8px;
	border-radius: var(--border-radius-large);
	white-space: pre-wrap;
}

.ai-assistant__message--user {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.ai-assistant__message--assistant {
	background: var(--color-background-dark);
}

.ai-assistant__ask {
	display: flex;
	gap: 6px;
}

.ai-assistant__ask input {
	flex: 1;
}

.ai-assistant__error {
	color: var(--color-error-text);
}

.ai-assistant__unavailable {
	color: var(--color-text-maxcontrast);
}
</style>
