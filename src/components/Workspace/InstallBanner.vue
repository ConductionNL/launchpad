<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="shown"
		class="launchpad-install-banner"
		role="region"
		:aria-label="t('launchpad', 'Put LaunchPad on your home screen')"
		data-testid="install-banner">
		<NcNoteCard type="info">
			<p class="launchpad-install-banner__title">
				<strong>{{ t('launchpad', 'Put LaunchPad on your home screen') }}</strong>
			</p>
			<p>{{ t('launchpad', 'Then your workplace opens with one tap, like an app') }}</p>
			<p
				v-if="showSteps"
				class="launchpad-install-banner__steps"
				role="status"
				data-testid="install-banner-steps">
				{{ t('launchpad', 'In your browser, choose Share and then Add to Home Screen.') }}
			</p>
			<div class="launchpad-install-banner__actions">
				<NcButton
					variant="tertiary"
					data-testid="install-banner-not-now"
					@click="notNow">
					{{ t('launchpad', 'Not now') }}
				</NcButton>
				<NcButton
					variant="primary"
					data-testid="install-banner-install"
					@click="install">
					{{ t('launchpad', 'Install') }}
				</NcButton>
			</div>
		</NcNoteCard>
	</div>
</template>

<script>
import { NcButton, NcNoteCard } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import {
	clearPrompt,
	installPrompt,
	isStandalone,
	readNotNowAt,
	shouldShowInstallBanner,
	writeNotNowAt,
} from '../../utils/installPrompt.js'

/**
 * The browser storage, or null where reading it throws.
 *
 * @return {Storage|null}
 */
function browserStorage() {
	try {
		return window.localStorage
	} catch {
		return null
	}
}

/**
 * InstallBanner: on a phone, offers to put LaunchPad on the home screen
 * (board LpMobiel). Install opens the browser's prompt, or shows the steps
 * where the browser has none; Not now hides it on this device for 30 days.
 *
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export default {
	name: 'InstallBanner',

	components: {
		NcButton,
		NcNoteCard,
	},

	props: {
		/** Whether this is the kiosk view: no banner there. */
		kiosk: {
			type: Boolean,
			default: false,
		},
		/** Whether this is a public page: no banner there. */
		isPublic: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			width: window.innerWidth,
			standalone: isStandalone(window),
			notNowAt: readNotNowAt(browserStorage()),
			showSteps: false,
		}
	},

	computed: {
		/**
		 * Whether the banner shows now.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
		 */
		shown() {
			return shouldShowInstallBanner({
				width: this.width,
				standalone: this.standalone,
				kiosk: this.kiosk,
				isPublic: this.isPublic,
				notNowAt: this.notNowAt,
				now: Date.now(),
			})
		},
	},

	mounted() {
		window.addEventListener('resize', this.onResize)
		window.addEventListener('appinstalled', this.onInstalled)
	},

	beforeUnmount() {
		window.removeEventListener('resize', this.onResize)
		window.removeEventListener('appinstalled', this.onInstalled)
	},

	methods: {
		t,

		/**
		 * Follow the viewport width.
		 *
		 * @return {void}
		 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
		 */
		onResize() {
			this.width = window.innerWidth
		},

		/**
		 * The page was installed: the banner goes.
		 *
		 * @return {void}
		 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
		 */
		onInstalled() {
			this.standalone = true
		},

		/**
		 * Open the browser's install prompt, or show the steps where the
		 * browser offers none.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
		 */
		async install() {
			const prompt = installPrompt()
			if (!prompt || typeof prompt.prompt !== 'function') {
				this.showSteps = true
				return
			}
			clearPrompt()
			try {
				await prompt.prompt()
				const choice = prompt.userChoice ? await prompt.userChoice : null
				if (choice && choice.outcome === 'accepted') {
					this.standalone = true
				}
			} catch {
				this.showSteps = true
			}
		},

		/**
		 * Hide the banner on this device for 30 days.
		 *
		 * @return {void}
		 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
		 */
		notNow() {
			const now = Date.now()
			writeNotNowAt(browserStorage(), now)
			this.notNowAt = now
		},
	},
}
</script>

<style scoped>
.launchpad-install-banner {
	margin: 0 0 12px;
}

.launchpad-install-banner__title {
	margin: 0 0 4px;
}

.launchpad-install-banner__steps {
	margin-top: 8px;
}

.launchpad-install-banner__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
	margin-top: 8px;
}
</style>
