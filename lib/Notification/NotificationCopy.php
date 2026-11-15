<?php

/**
 * NotificationCopy
 *
 * The rendered subject and message text of one LaunchPad notification.
 *
 * @category  Notification
 * @package   OCA\LaunchPad\Notification
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Notification;

/**
 * Rendered notification text: rich subject, plain subject and message.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md#requirement-notifier-renders-share-and-ownership-transfer-subjects-req-share-011
 */
final class NotificationCopy {

	/**
	 * The subject with markdown emphasis.
	 *
	 * @var string
	 */
	public string $richSubject = '';

	/**
	 * The plain-text subject.
	 *
	 * @var string
	 */
	public string $parsedSubject = '';

	/**
	 * The message, used for both the rich and the plain variant.
	 *
	 * @var string
	 */
	public string $message = '';
}//end class
