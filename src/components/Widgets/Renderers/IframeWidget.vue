<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="iframe-widget" :style="wrapperStyle">
		<div v-if="state === 'loading'" class="iframe-widget__state">
			<NcLoadingIcon :size="32" />
			<span>{{ t('launchpad', 'Loading…') }}</span>
		</div>

		<!-- Fallback card — never a silent blank frame (REQ-IFRAME-004).
		     Conveyed by icon AND text, never colour alone. When the target
		     itself failed it is the outage state (REQ-IFRAME-005): what could
		     not be loaded, the cause and its time, Reload and Open in a new
		     tab. The "Outage" tag and the source line sit in the card head
		     (emitted as `status`). -->
		<div
			v-else-if="state === 'failed'"
			class="iframe-widget__fallback"
			:class="{ 'iframe-widget__fallback--outage': isOutage }"
			role="status"
			:data-testid="isOutage ? 'iframe-widget-outage' : 'iframe-widget-fallback'">
			<AlertCircleOutline :size="32" />
			<span class="iframe-widget__fallback-title">{{ displayTitle }}</span>
			<span class="iframe-widget__fallback-text">{{ isOutage ? outageMessage : fallbackMessage }}</span>
			<span
				v-if="isOutage && outageCause"
				class="iframe-widget__fallback-cause"
				data-testid="iframe-widget-outage-cause">{{ outageCause }}</span>
			<div v-if="hasUrl" class="iframe-widget__fallback-actions">
				<NcButton
					v-if="isOutage"
					variant="secondary"
					data-testid="iframe-widget-reload"
					@click="restart">
					<template #icon>
						<Reload :size="16" />
					</template>
					{{ t('launchpad', 'Reload') }}
				</NcButton>
				<a
					:href="url"
					target="_blank"
					rel="noopener noreferrer"
					class="iframe-widget__fallback-link"
					:aria-label="
						t('launchpad', 'Open {title} in a new tab', {
							title: displayTitle,
						})
					">
					<OpenInNew :size="16" />
					{{ t('launchpad', 'Open in a new tab') }}
				</a>
			</div>
		</div>

		<iframe
			v-if="hasUrl && state !== 'failed' && framableConfirmed"
			ref="frame"
			class="iframe-widget__frame"
			:style="{ display: state === 'ready' ? 'block' : 'none' }"
			:src="url"
			:title="displayTitle"
			:sandbox="sandboxAttr"
			frameborder="0"
			@load="onLoad"
			@error="onError" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import {
	NULLABLE_OBJECT_PROP,
	OBJECT_PROP,
} from '../../../constants/propDefinitions.js'
import { checkIframeFramable } from '../../../services/iframeClient.js'
import {
	embedSourceLine,
	isReport,
	OUTAGE_REASONS,
	outageCauseLine,
} from '../../../utils/embedOutage.js'

/**
 * How long to wait for the iframe's `load` event before treating the embed
 * as failed (REQ-IFRAME-004 "no load event within a timeout"). A target
 * blocking via `X-Frame-Options: DENY` sometimes never fires `load` at all.
 *
 * @type {number}
 */
const LOAD_TIMEOUT_MS = 6000

/**
 * Aspect-ratio token → CSS `aspect-ratio` value. `none` falls back to the
 * configured fixed `height`.
 *
 * @type {Record<string, string>}
 */
const ASPECT_RATIO_MAP = {
	'16:9': '16 / 9',
	'4:3': '4 / 3',
	'1:1': '1 / 1',
	'9:16': '9 / 16',
}

const DEFAULT_HEIGHT = 400

/**
 * The framable check's reason → the widget's failure reason. A host taken
 * off the allow-list is policy, not an outage; a check that could not run
 * is an outage with an unknown cause.
 *
 * @type {Record<string, string>}
 */
const CHECK_REASON_MAP = {
	host_not_allowed: 'not_permitted',
	x_frame_options: 'blocked',
	frame_ancestors: 'blocked',
	unreachable: 'unreachable',
}

/**
 * IframeWidget — the `iframe` dashboard widget type (REQ-IFRAME-001..004).
 *
 * Embeds `content.url` in a sandboxed `<iframe>`. The sandbox attribute is
 * always present and NEVER includes `allow-top-navigation` (stripped again
 * here, defence-in-depth, even though the config form never offers it and
 * the server strips it at save time too). Detects a failed/blocked load two
 * ways: no `load` event within {@link LOAD_TIMEOUT_MS}, or a `load` event
 * whose same-origin document is empty (the shape a browser leaves behind
 * when `X-Frame-Options: DENY` / `frame-ancestors 'none'` refused the
 * frame) — a cross-origin `SecurityError` reading `contentDocument` is the
 * NORMAL signal of a successfully embedded cross-origin target and is
 * treated as success, not failure. Either failure path renders a fallback
 * card with an "Open in new tab" link — never a silent blank frame.
 */
export default {
	name: 'IframeWidget',

	components: {
		NcButton,
		NcLoadingIcon,
		AlertCircleOutline,
		OpenInNew,
		Reload,
	},

	props: {
		/**
		 * Persisted widget content blob:
		 * `{url, title, height, aspect, sandbox, allowListChecked}`.
		 *
		 * @type {object}
		 */
		content: OBJECT_PROP,

		/**
		 * The widget placement (unused directly — the iframe embeds
		 * `content.url` client-side; no per-placement fetch is performed).
		 *
		 * @type {{id?: number|string}|null}
		 */
		// eslint-disable-next-line vue/no-unused-properties -- absorbs WidgetRenderer's uniform binding; see above
		placement: NULLABLE_OBJECT_PROP,

		/**
		 * Milliseconds to wait for the `load` event before treating the
		 * embed as failed. Overridable so unit tests can exercise the
		 * timeout path without a real multi-second wait; production always
		 * uses {@link LOAD_TIMEOUT_MS}.
		 *
		 * @type {number}
		 */
		loadTimeoutMs: {
			type: Number,
			default: LOAD_TIMEOUT_MS,
		},
	},

	emits: ['status'],

	data() {
		return {
			state: 'loading',
			failureReason: '',
			// When the widget saw the failure (REQ-IFRAME-005 "the cause and
			// its time"); null while nothing failed.
			failedAt: null,
			loadTimer: null,
			// The iframe is NOT rendered until the server-side framable check
			// (REQ-IFRAME-003) confirms the target permits framing. Rendering
			// it earlier lets the browser's own "refused to connect" load event
			// — which fires with a null contentDocument, indistinguishable from
			// a successful cross-origin embed — flip the state to 'ready' before
			// the authoritative async check resolves, masking the fallback.
			framableConfirmed: false,
		}
	},

	computed: {
		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		url() {
			return typeof this.content?.url === 'string'
				? this.content.url.trim()
				: ''
		},

		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		hasUrl() {
			return this.url !== ''
		},

		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		displayTitle() {
			const title =
				typeof this.content?.title === 'string'
					? this.content.title.trim()
					: ''
			return title !== '' ? title : t('launchpad', 'Embedded page')
		},

		/**
		 * The sandbox attribute — always present, author-toggled tokens
		 * only, `allow-top-navigation*` is unconditionally stripped
		 * (REQ-IFRAME-004 "the sandbox MUST NEVER include
		 * allow-top-navigation").
		 *
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		sandboxAttr() {
			const tokens = Array.isArray(this.content?.sandbox)
				? this.content.sandbox
				: []
			return tokens
				.filter(
					(token) =>
						typeof token === 'string'
						&& !token.startsWith('allow-top-navigation'),
				)
				.join(' ')
		},

		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		wrapperStyle() {
			const aspect =
				typeof this.content?.aspect === 'string'
					? this.content.aspect
					: 'none'
			const ratio = ASPECT_RATIO_MAP[aspect]
			if (ratio) {
				return { aspectRatio: ratio, height: 'auto' }
			}
			const height = Number(this.content?.height)
			return {
				height: `${Number.isFinite(height) && height > 0 ? height : DEFAULT_HEIGHT}px`,
			}
		},

		/** @spec openspec/specs/iframe-embed-widget/spec.md */
		fallbackMessage() {
			if (!this.hasUrl) {
				return t('launchpad', 'This embed has not been configured yet.')
			}
			if (this.failureReason === 'not_permitted') {
				return t('launchpad', 'This embed is no longer permitted by your administrator.')
			}
			return t('launchpad', 'This page could not be loaded.')
		},

		/**
		 * Whether the failure is an outage of the target itself, as opposed
		 * to an unconfigured embed or one the administrator no longer permits.
		 *
		 * @return {boolean} True when the outage state shows.
		 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		isOutage() {
			return this.state === 'failed' && OUTAGE_REASONS.includes(this.failureReason)
		},

		/**
		 * @return {string} "The report could not be loaded" or the page variant.
		 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		outageMessage() {
			return isReport(this.content)
				? t('launchpad', 'The report could not be loaded')
				: t('launchpad', 'The page could not be loaded')
		},

		/**
		 * @return {string} The cause and its time, or '' when no cause is known.
		 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		outageCause() {
			return outageCauseLine(
				{
					content: this.content,
					reason: this.failureReason,
					failedAt: this.failedAt,
					now: new Date(),
				},
				t,
			)
		},

		/**
		 * What the card head shows for this embed: the source line under the
		 * title and whether the "Outage" tag is up.
		 *
		 * @return {{subtitle: string, outage: boolean}} The head status.
		 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		headStatus() {
			return {
				subtitle: embedSourceLine(this.content, t),
				outage: this.isOutage,
			}
		},
	},

	watch: {
		/**
		 * A re-configured URL must be re-checked from scratch: the previous
		 * target's framable verdict and load-timeout say nothing about the
		 * new one, so the whole REQ-IFRAME-004 loading/fallback cycle is
		 * restarted rather than carried over.
		 *
		 * @spec openspec/specs/iframe-embed-widget/spec.md#req-iframe-004
		 * @return {void}
		 */
		url() {
			this.restart()
		},

		/**
		 * Tell the card head (WidgetWrapper) the source line and the outage
		 * tag whenever either changes.
		 *
		 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		headStatus: {
			handler(status) {
				this.$emit('status', status)
			},

			immediate: true,
			deep: true,
		},
	},

	mounted() {
		this.restart()
	},

	beforeUnmount() {
		this.clearTimer()
	},

	methods: {
		t,

		/**
		 * (Re)start the loading/timeout cycle for the current `url`.
		 *
		 * @return {void}
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		restart() {
			this.clearTimer()
			this.framableConfirmed = false
			this.failedAt = null
			if (!this.hasUrl) {
				this.state = 'failed'
				this.failureReason = 'unconfigured'
				return
			}
			this.state = 'loading'
			this.failureReason = ''

			// REQ-IFRAME-003: the browser cannot detect an X-Frame-Options /
			// frame-ancestors refusal (a blocked frame and a live cross-origin
			// embed both leave contentDocument null), so ask the server first
			// and render the fallback card up front when the target refuses
			// framing — rather than leaving a permanently blank frame.
			const startedFor = this.url
			checkIframeFramable(this.url).then((result) => {
				// A newer url() (watch → restart) supersedes this check.
				if (this.url !== startedFor || this.state !== 'loading') {
					return
				}
				if (result && result.framable === false) {
					this.clearTimer()
					this.fail(CHECK_REASON_MAP[result.reason] ?? 'unknown')
					return
				}
				// Server confirms framing is permitted — ONLY NOW render the
				// iframe. Rendering it before this point would let the browser's
				// own blocked-frame `load` event (null contentDocument, which
				// onLoad cannot distinguish from a real cross-origin embed) flip
				// the state to 'ready' ahead of this authoritative check and
				// mask the fallback.
				this.framableConfirmed = true
				this.clearTimer()
				this.loadTimer = setTimeout(() => {
					if (this.state === 'loading') {
						this.fail('timeout')
					}
				}, this.loadTimeoutMs)
			})
		},

		/**
		 * The iframe's native `load` event fired. Distinguishes a real
		 * successful embed from a same-origin "blocked" placeholder
		 * document (REQ-IFRAME-004 "a load event yielding an
		 * inaccessible/empty document").
		 *
		 * @return {void}
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		onLoad() {
			this.clearTimer()
			if (this.state !== 'loading') {
				return
			}

			let blank = false
			try {
				const frame = this.$refs.frame
				const doc = frame && frame.contentDocument
				if (
					doc
					&& doc.body
					&& doc.body.children.length === 0
					&& (doc.body.textContent || '').trim() === ''
				) {
					blank = true
				}
			} catch {
				// A SecurityError here means the target is cross-origin and
				// DID load — that's the normal signal of success, not a
				// failure to surface.
				blank = false
			}

			if (blank) {
				this.fail('blocked')
				return
			}

			this.state = 'ready'
		},

		/**
		 * Generic load error (network failure, invalid URL) — render the
		 * fallback card, never crash the dashboard.
		 *
		 * @return {void}
		 * @spec openspec/specs/iframe-embed-widget/spec.md
		 */
		onError() {
			this.clearTimer()
			this.fail('error')
		},

		/**
		 * Enter the failed state with a reason and the time it was seen.
		 *
		 * @param {string} reason The failure reason.
		 * @return {void}
		 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		fail(reason) {
			this.state = 'failed'
			this.failureReason = reason
			this.failedAt = new Date()
		},

		/**
		 * Clear the pending load-timeout guard, if any. Without this a timer
		 * armed for a superseded URL would later fire and flip an already
		 * successful embed into the REQ-IFRAME-004 fallback state.
		 *
		 * @spec openspec/specs/iframe-embed-widget/spec.md#req-iframe-004
		 * @return {void}
		 */
		clearTimer() {
			if (this.loadTimer !== null) {
				clearTimeout(this.loadTimer)
				this.loadTimer = null
			}
		},
	},
}
</script>

<style scoped>
.iframe-widget {
	width: 100%;
	position: relative;
	overflow: hidden;
	border-radius: var(--border-radius, 8px);
}

.iframe-widget__frame {
	width: 100%;
	height: 100%;
	border: none;
	display: block;
}

.iframe-widget__state {
	width: 100%;
	height: 100%;
	min-height: 120px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	color: var(--color-text-maxcontrast, var(--color-main-text));
}

.iframe-widget__fallback {
	width: 100%;
	height: 100%;
	min-height: 120px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	padding: 16px;
	text-align: center;
	color: var(--color-text-maxcontrast, var(--color-main-text));
}

.iframe-widget__fallback-title {
	font-weight: 600;
	color: var(--color-main-text);
}

.iframe-widget__fallback-text {
	font-size: 0.85rem;
}

.iframe-widget__fallback-cause {
	font-size: 0.85rem;
	color: var(--color-main-text);
}

.iframe-widget__fallback-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	gap: 12px;
	margin-top: 6px;
}

.iframe-widget__fallback-link {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	color: var(--color-primary-element, var(--color-main-text));
	text-decoration: underline;
}

.iframe-widget__fallback-link:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}
</style>
