<?php

/**
 * Symfony HeaderUtils stub for unit tests.
 *
 * OCP's DownloadResponse builds its Content-Disposition with Symfony's
 * HeaderUtils, which a booted Nextcloud provides and this suite's OCP
 * bootstrap does not. The `class_exists` guard makes this a no-op where the
 * real class is loaded. Only `makeDisposition()` is used.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Symfony\Component\HttpFoundation;

if (class_exists(HeaderUtils::class) === false) {
	/**
	 * Stub: the one method DownloadResponse calls.
	 */
	final class HeaderUtils {
		public const DISPOSITION_ATTACHMENT = 'attachment';

		/**
		 * @param string $disposition The disposition.
		 * @param string $filename    The file name.
		 * @param string $fallback    The ASCII fallback.
		 *
		 * @return string
		 */
		public static function makeDisposition(string $disposition, string $filename, string $fallback = ''): string {
			return $disposition . '; filename="' . addcslashes($filename, '"\\') . '"';
		}
	}
}
