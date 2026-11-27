<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	WidgetErrorBoundary — keeps a failing widget inside its own card
	(widgets REQ-WDG-046). An error thrown while a widget's content sets up,
	renders or runs a lifecycle hook is caught here: the card shows "Widget
	not available" with Retry instead of taking the dashboard down with it.
	Errors from event handlers and watchers are left to the app's own error
	handling, as before, so a failed click does not blank a working widget.
-->

<template>
	<div class="launchpad-widget-boundary" :data-failed="failed ? 'true' : 'false'">
		<div v-if="!failed" :key="attempt" class="launchpad-widget-boundary__content">
			<slot />
		</div>
		<NcEmptyContent
			v-else
			class="launchpad-widget-boundary__error"
			role="alert"
			data-testid="widget-error"
			:description="t('launchpad', 'Widget not available')">
			<template #icon>
				<AlertCircleOutline :size="32" />
			</template>
			<template #action>
				<NcButton data-testid="widget-error-retry" @click="retry">
					{{ t('launchpad', 'Retry') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@conduction/nextcloud-vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import { logger } from '../utils/logger.js'

/**
 * Whether a Vue error-info string names a failure to set up, render or run
 * a lifecycle hook, as opposed to an event handler or a watcher.
 *
 * Production builds pass `https://vuejs.org/error-reference/#runtime-<code>`:
 * code 0 is setup, 1 is render, and lifecycle hooks carry their letter key
 * (`bm`, `m`, `bu`, `u`, `ec`, ...). Development builds pass the readable
 * text ("setup function", "render function", "mounted hook").
 *
 * @param {string} info The error info Vue hands to errorCaptured.
 * @return {boolean} Whether the widget failed to show.
 * @spec openspec/specs/widgets/spec.md#requirement-every-widget-shows-its-own-loading-and-error-state-req-wdg-046
 */
export function isShowFailure(info) {
	const text = String(info ?? '')
	const code = text.match(/#runtime-([0-9a-z]+)$/)
	if (code) {
		return code[1] === '0' || code[1] === '1' || /^[a-z]+$/.test(code[1])
	}
	return /setup function|render function| hook$/.test(text)
}

export default {
	name: 'WidgetErrorBoundary',

	components: { AlertCircleOutline, NcButton, NcEmptyContent },

	data() {
		return {
			failed: false,
			attempt: 0,
		}
	},

	/**
	 * Catch a widget's failure to show and keep it in this card.
	 *
	 * @param {Error} error The error.
	 * @param {object} instance The component that threw.
	 * @param {string} info Where it was thrown.
	 * @return {boolean|undefined} false to stop it here; nothing to pass it on.
	 * @spec openspec/specs/widgets/spec.md#requirement-every-widget-shows-its-own-loading-and-error-state-req-wdg-046
	 */
	errorCaptured(error, instance, info) {
		if (!isShowFailure(info)) {
			return undefined
		}
		logger.error('A widget could not be shown:', error)
		this.failed = true
		return false
	},

	methods: {
		/**
		 * Mount the widget's content afresh.
		 *
		 * @return {void}
		 * @spec openspec/specs/widgets/spec.md#requirement-every-widget-shows-its-own-loading-and-error-state-req-wdg-046
		 */
		retry() {
			this.attempt += 1
			this.failed = false
		},
	},
}
</script>

<style scoped>
.launchpad-widget-boundary,
.launchpad-widget-boundary__content {
	display: contents;
}

.launchpad-widget-boundary[data-failed='true'] {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
}
</style>
