/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The notifier refusing a subject it does not know (dashboard-sharing,
 * "Unknown subject throws"). Written for e2e-coverage-programme task 1.6.
 *
 * It was excluded because no HTTP route lets a caller publish a notification
 * with an arbitrary subject. That is true, and it is also why the contract
 * matters: Nextcloud's notification manager hands every notifier every
 * notification and relies on `UnknownNotificationException` to mean "not
 * mine". The suite already runs PHP on the instance with Nextcloud booted
 * (`support/instanceDb.ts`), so this test builds the notification through
 * the instance's own `OCP\Notification\IManager` and hands it to the notifier
 * the app has installed, resolved from the server container as the manager
 * resolves it. Nothing is stubbed: it is the deployed class against the
 * deployed framework.
 *
 * The control prepares a `dashboard_shared` notification through the same
 * path and requires it to render, so a notifier that throws on everything
 * (or a bootstrap that cannot load it) fails the control, not the claim.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-sharing::unknown-subject-throws
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

import { expect, test } from '@playwright/test'
import { instancePhp } from './support/instanceDb.ts'

/** What preparing one notification did. */
interface Prepared {
	/** The class of what was thrown, or null. */
	threw: string | null
	/** The exception message, or the parsed subject when nothing was thrown. */
	text: string
}

/**
 * Prepare one LaunchPad notification on the instance.
 *
 * @param subject the subject.
 * @param params its subject parameters.
 * @return what happened.
 */
async function prepare(subject: string, params: string[]): Promise<Prepared> {
	return instancePhp<Prepared>(
		`
\\OC_App::loadApp('launchpad');
$manager = \\OCP\\Server::get(\\OCP\\Notification\\IManager::class);
$notifier = \\OCP\\Server::get(\\OCA\\LaunchPad\\Notification\\Notifier::class);
$n = $manager->createNotification();
$n->setApp('launchpad')
	->setUser('admin')
	->setDateTime(new \\DateTime())
	->setObject('dashboard', '999999999')
	->setSubject($in['subject'], $in['params']);
try {
	$notifier->prepare($n, 'en');
	$out = ['threw' => null, 'text' => $n->getParsedSubject()];
} catch (\\Throwable $e) {
	$out = ['threw' => get_class($e), 'text' => $e->getMessage()];
}
`,
		{ subject, params },
	)
}

// @e2e dashboard-sharing::unknown-subject-throws
test('the installed notifier refuses an unknown subject with UnknownNotificationException', async () => {
	const known = await prepare('dashboard_shared', ['alice', 'Q3 Plan', 'full'])
	expect(known.threw, `CONTROL: a known subject must render (${known.text})`).toBeNull()
	expect(known.text, 'CONTROL: the known subject names the dashboard').toContain('Q3 Plan')

	const unknown = await prepare('something_else', [])
	expect(
		unknown.threw,
		'an unknown subject must be refused as "not mine", so the manager skips it',
	).toBe('OCP\\Notification\\UnknownNotificationException')
})
