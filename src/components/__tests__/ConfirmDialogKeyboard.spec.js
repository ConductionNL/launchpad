/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every confirmation launchpad asks, drawn by the real CnConfirmDialog with
 * the texts its host passes: focus moves into the dialog when it opens, and
 * Escape closes it without running the action. The
 * destructive ones keep their red (error) confirm button.
 */

// The suite aliases the package root to a stub, so the real dialog is loaded from the ESM build.
import CnConfirmDialog from '@conduction/nextcloud-vue/dist/esm/dialogs/CnConfirmDialog.vue.js'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

const CONFIRMATIONS = {
	'take over a lock': {
		dialogTitle: 'Take over editing?',
		message:
			'A colleague can no longer save changes to this dashboard. Take over only when you know they have stopped.',
		confirmLabel: 'Take over',
	},
	'restore a version': {
		dialogTitle: 'Restore this version?',
		message:
			'The dashboard goes back to how it was on Oct 30, 2026. How it looks now is saved as a version first, so you can undo this.',
		confirmLabel: 'Restore',
	},
	'reset a personal view': {
		dialogTitle: 'Reset your view?',
		message:
			'Every widget you hid comes back, and the dashboard looks the way its owner set it up.',
		confirmLabel: 'Reset my view',
	},
	'revoke a kiosk playlist': {
		dialogTitle: 'Revoke this playlist?',
		message:
			'Screens showing Lobby stop at their next check, and the link stops working for good.',
		confirmLabel: 'Revoke',
		variant: 'error',
	},
	'remove a demo showcase': {
		dialogTitle: 'Remove showcase dashboard',
		message:
			'Remove the School showcase dashboard for all users? You can reinstall it later.',
		confirmLabel: 'Remove',
		variant: 'error',
	},
	'delete a role permission': {
		dialogTitle: 'Delete role permission',
		message: 'Delete role permission for group "finance"?',
		confirmLabel: 'Delete',
		variant: 'error',
	},
	'delete a role layout default': {
		dialogTitle: 'Delete layout default',
		message: 'Delete layout default for "hr" / "news"?',
		confirmLabel: 'Delete',
		variant: 'error',
	},
	'delete a group dashboard': {
		dialogTitle: 'Delete dashboard',
		message: 'Delete this dashboard? This cannot be undone.',
		confirmLabel: 'Delete',
		variant: 'error',
	},
}

let wrapper = null

// jsdom has no layout: tabbable takes an element without client rects for hidden,
// and the dialog's focus trap would find nothing to focus.
Element.prototype.getClientRects = function () {
	return [{ width: 10, height: 10, top: 0, left: 0, bottom: 10, right: 10 }]
}

afterEach(() => {
	wrapper?.unmount()
	wrapper = null
	document.body.innerHTML = ''
})

/**
 * Wait until the dialog has opened and its focus trap has moved focus.
 *
 * @return {Promise<void>}
 */
async function settle() {
	for (let i = 0; i < 10; i++) {
		await flushPromises()
		await new Promise((resolve) => setTimeout(resolve, 20))
	}
}

describe.each(Object.entries(CONFIRMATIONS))(
	'the confirmation to %s',
	(_name, props) => {
		it('shows its texts and moves focus into the dialog', async () => {
			wrapper = mount(CnConfirmDialog, {
				props,
				attachTo: document.body,
				// The focus trap starts after the enter transition, which a stub never ends.
				global: { stubs: { transition: false } },
			})
			await settle()
			const dialog = document.querySelector('[role="dialog"]')
			expect(dialog).not.toBeNull()
			expect(dialog.textContent).toContain(props.dialogTitle)
			expect(dialog.textContent).toContain(props.message)
			expect(dialog.textContent).toContain(props.confirmLabel)
			expect(dialog.contains(document.activeElement)).toBe(true)
			const confirm = dialog.querySelector(
				'[data-testid="cn-confirm-dialog-confirm"]',
			)
			expect(confirm.className).toContain(
				`button-vue--${props.variant ?? 'primary'}`,
			)
		})

		it('closes on Escape without confirming', async () => {
			wrapper = mount(CnConfirmDialog, {
				props,
				attachTo: document.body,
				// The focus trap starts after the enter transition, which a stub never ends.
				global: { stubs: { transition: false } },
			})
			await settle()
			expect(
				document
					.querySelector('[role="dialog"]')
					.contains(document.activeElement),
			).toBe(true)
			document.activeElement.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
			)
			await settle()
			// The dialog closes after its 300 ms leave animation.
			await new Promise((resolve) => setTimeout(resolve, 400))
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('confirm')).toBeUndefined()
		})
	},
)
