/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Read a user's Nextcloud notifications, the way their bell does.
 *
 * LaunchPad publishes notifications through `OCP\Notification\IManager`, and
 * the notifications app serves them back to the recipient over OCS at
 * `/ocs/v2.php/apps/notifications/api/v2/notifications`, already rendered by
 * LaunchPad's own `Notifier` (`lib/Notification/Notifier.php`). So a share
 * notification is not a server-side side effect nobody can see: the
 * recipient can read it, with its rendered subject, message, link and the
 * object it is about.
 *
 * Every LaunchPad dashboard notification carries `object_type: 'dashboard'`
 * and `object_id: <dashboard id>`, so a test that seeds its own dashboard can
 * count exactly the notifications about THAT dashboard, whatever else the
 * account's feed holds.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'

const FEED = '/ocs/v2.php/apps/notifications/api/v2/notifications?format=json'

/** One entry of the notifications OCS feed, with the fields these specs read. */
export interface FeedEntry {
	notification_id: number
	app: string
	user: string
	datetime: string
	object_type: string
	object_id: string
	subject: string
	subjectRich: string
	message: string
	link: string
}

/**
 * The LaunchPad notifications a user holds about one dashboard, oldest first.
 *
 * Skips the calling test when the notifications app is not enabled (the feed
 * answers 404), the same way `app-catalogue.spec.ts` does: without the app
 * nothing can read a notification, and a count of zero would then read as
 * "nothing was published" for the wrong reason.
 *
 * @param api a context acting as the recipient.
 * @param dashboardId the dashboard the notifications are about.
 * @return the matching entries, oldest first.
 */
export async function dashboardNotifications(
	api: APIRequestContext,
	dashboardId: number,
): Promise<FeedEntry[]> {
	const res = await api.get(FEED, { headers: { 'OCS-APIRequest': 'true' } })
	test.skip(
		res.status() === 404,
		'the notifications app is not enabled on this instance',
	)
	expect(res.status(), await res.text()).toBe(200)
	const entries: FeedEntry[] = (await res.json()).ocs?.data ?? []

	return entries
		.filter(
			(entry) =>
				entry.app === 'launchpad'
				&& entry.object_type === 'dashboard'
				&& String(entry.object_id) === String(dashboardId),
		)
		.sort((a, b) => a.notification_id - b.notification_id)
}

/**
 * The `dashboard_shared` notifications among them.
 *
 * The feed serves the rendered text, not the subject id, so the kind is told
 * apart by the copy `Notifier::sharedCopy()` renders: "… shared **…** with you".
 *
 * @param api a context acting as the recipient.
 * @param dashboardId the dashboard the notifications are about.
 * @return the share notifications, oldest first.
 */
export async function shareNotifications(
	api: APIRequestContext,
	dashboardId: number,
): Promise<FeedEntry[]> {
	return (await dashboardNotifications(api, dashboardId)).filter((entry) =>
		/ shared \*\*.*\*\* with you$/.test(entry.subjectRich),
	)
}

/**
 * The `dashboard_ownership_transferred` notifications among them, told apart
 * by the copy `Notifier::transferredCopy()` renders: "**…** is now yours".
 *
 * @param api a context acting as the recipient.
 * @param dashboardId the dashboard the notifications are about.
 * @return the transfer notifications, oldest first.
 */
export async function transferNotifications(
	api: APIRequestContext,
	dashboardId: number,
): Promise<FeedEntry[]> {
	return (await dashboardNotifications(api, dashboardId)).filter((entry) =>
		/^\*\*.*\*\* is now yours$/.test(entry.subjectRich),
	)
}
