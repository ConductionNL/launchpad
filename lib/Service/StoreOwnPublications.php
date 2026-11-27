<?php

/**
 * StoreOwnPublications
 *
 * Which register items this instance published itself, so their card on the
 * marketplace reads "Own publication" ("Eigen publicatie", REQ-STORE-013).
 * The slugs are kept as a JSON list in the app config.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IL10N;

/**
 * Remember and mark this instance's own publications.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
 */
class StoreOwnPublications {
	/**
	 * App config key holding the slugs this instance published (JSON list).
	 */
	public const CONFIG_KEY = 'store_published';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Holds the slugs.
	 * @param IL10N      $l10n      The viewer's language, for the card label.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * Mark the cards this instance published, so they read "Own publication".
	 *
	 * @param array<int, array<string, mixed>> $cards The search's cards.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function annotate(array $cards): array {
		$own = array_flip($this->published());
		foreach ($cards as $index => $card) {
			if (isset($own[(string)($card['slug'] ?? '')]) === true) {
				$cards[$index]['ownPublication'] = true;
				$cards[$index]['typeName'] = $this->l10n->t('Own publication');
			}
		}

		return $cards;
	}//end annotate()

	/**
	 * Remember a slug this instance published.
	 *
	 * @param string $slug The published slug.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function remember(string $slug): void {
		$published = $this->published();
		if (in_array($slug, $published, true) === true) {
			return;
		}

		$published[] = $slug;
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY, (string)json_encode($published));
	}//end remember()

	/**
	 * The slugs this instance published.
	 *
	 * @return list<string>
	 */
	private function published(): array {
		$decoded = json_decode(
			json: $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY, '[]'),
			associative: true
		);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(array_filter(array_map('strval', $decoded), static fn (string $slug): bool => $slug !== ''));
	}//end published()
}//end class
