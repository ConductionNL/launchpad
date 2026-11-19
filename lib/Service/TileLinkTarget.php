<?php

/**
 * TileLinkTarget
 *
 * The three ways a tile can open, and the order in which a tile's own choice,
 * its dashboard's default and the link type decide between them.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Link-target values and their resolution (launcher-tile-open-behaviour D1, D2).
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */
class TileLinkTarget {

	/**
	 * Open in the same tab.
	 */
	public const SAME_TAB = 'same-tab';

	/**
	 * Open in a new tab.
	 */
	public const NEW_TAB = 'new-tab';

	/**
	 * Open in the panel beside the tiles.
	 */
	public const SIDE_PANEL = 'side-panel';

	/**
	 * Every accepted value.
	 */
	public const VALUES = [self::SAME_TAB, self::NEW_TAB, self::SIDE_PANEL];

	/**
	 * A link target as given, or null when none is given.
	 *
	 * Null and the empty string mean "not set". Anything else that is not one
	 * of the three values is refused.
	 *
	 * @param mixed  $value The value.
	 * @param string $field The field name, for the refusal message.
	 *
	 * @return string|null
	 *
	 * @throws InvalidArgumentException When the value is not a link target.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public static function validated(mixed $value, string $field='linkTarget'): ?string {
		if ($value === null || $value === '') {
			return null;
		}

		if (is_string($value) === false || in_array($value, self::VALUES, true) === false) {
			throw new InvalidArgumentException(
				message: $field . ' must be one of ' . implode(', ', self::VALUES)
			);
		}

		return $value;
	}//end validated()

	/**
	 * How a tile opens: its own target, else the dashboard default, else the
	 * rule from before the setting existed (a web address in a new tab, an
	 * app or internal path in the same tab).
	 *
	 * @param string|null $tileTarget       The tile's own target.
	 * @param string|null $dashboardDefault The dashboard's default.
	 * @param string|null $linkType         The tile's link type (`url`, `app`, ...).
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public static function resolve(?string $tileTarget, ?string $dashboardDefault, ?string $linkType): string {
		foreach ([$tileTarget, $dashboardDefault] as $candidate) {
			if ($candidate !== null && in_array($candidate, self::VALUES, true) === true) {
				return $candidate;
			}
		}

		if ($linkType === 'url') {
			return self::NEW_TAB;
		}

		return self::SAME_TAB;
	}//end resolve()
}//end class
