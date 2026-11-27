<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	MoreMenuFooter: the foot of the More menu, under both columns (board LpMeer,
	REQ-SHELL-017): "Powered by" with the sendent and Conduction logos, each a
	link that opens in a new tab with `rel="noopener noreferrer"`.
-->

<template>
	<footer class="lp-more-menu-footer" data-testid="more-menu-footer">
		<span class="lp-more-menu-footer__caption">
			{{ t('launchpad', 'Powered by') }}
		</span>
		<a
			class="lp-more-menu-footer__link"
			href="https://sendent.com"
			target="_blank"
			rel="noopener noreferrer"
			aria-label="sendent">
			<img :src="sendentLogo" alt="sendent" class="lp-more-menu-footer__image">
		</a>
		<a
			class="lp-more-menu-footer__link"
			href="https://conduction.nl"
			target="_blank"
			rel="noopener noreferrer"
			aria-label="Conduction">
			<img
				:src="conductionLogo"
				alt="Conduction"
				class="lp-more-menu-footer__image lp-more-menu-footer__image--invert">
		</a>
	</footer>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'

export default {
	name: 'MoreMenuFooter',

	computed: {
		/**
		 * @return {string} The sendent logo.
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-5
		 */
		sendentLogo() {
			return generateFilePath('launchpad', 'img', 'sendent-logo.png')
		},

		/**
		 * @return {string} The Conduction logo (a white wordmark).
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-5
		 */
		conductionLogo() {
			return generateFilePath('launchpad', 'img', 'conduction-logo.png')
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.lp-more-menu-footer {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12px;
	padding: 10px 16px;
	border-top: 1px solid var(--color-border, #e0e0e0);
}

.lp-more-menu-footer__caption {
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0.5px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast, #757575);
}

.lp-more-menu-footer__link {
	display: inline-flex;
	align-items: center;
	padding: 2px 4px;
	border-radius: 4px;
	transition: background-color 0.15s ease;
}

.lp-more-menu-footer__link:hover,
.lp-more-menu-footer__link:focus-visible {
	background: var(--color-background-hover, #f5f5f5);
}

.lp-more-menu-footer__image {
	height: 16px;
	width: auto;
	object-fit: contain;
}

/* The Conduction wordmark is white: invert it on light themes only. */
.lp-more-menu-footer__image--invert {
	filter: var(--background-invert-if-bright, none);
}

@media (prefers-reduced-motion: reduce) {
	.lp-more-menu-footer__link {
		transition-duration: 0.01ms;
	}
}
</style>
