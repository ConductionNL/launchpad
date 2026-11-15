<?php

/**
 * AttentionSourceService
 *
 * Collects what the user's apps say needs attention. An app declares its
 * attention items in one data file, `appinfo/attention.json`; this service
 * reads that file for every app enabled for the user, checks it, translates
 * its strings with the app's own translations and hands the list to the
 * "First today" widget.
 *
 * WHAT THIS DOES NOT DO. It runs no count and reads no object. The widget asks
 * OpenRegister in the browser, as the signed-in user, so OpenRegister's access
 * rules decide what that user may count.
 *
 * WHY A FILE. The apps already write these declarations for their own "First
 * today" card, but inside their JavaScript bundle, where LaunchPad cannot read
 * them. A file needs no class from the app, so a renamed or half-installed app
 * cannot break this service, and it cannot go quiet the way a `class_exists`
 * lookup does. See `openspec/changes/cross-app-attention-feed/design.md`.
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

use InvalidArgumentException;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Read and check the attention declarations of the user's apps.
 *
 * @spec openspec/specs/attention-feed/spec.md#req-att-001
 */
class AttentionSourceService {
	/**
	 * Where an app keeps its declaration, relative to the app folder.
	 *
	 * @var string
	 */
	public const DECLARATION_PATH = '/appinfo/attention.json';

	/**
	 * Checks a declaration. It holds no state and has no dependencies, so it
	 * is constructed here rather than injected.
	 *
	 * @var AttentionDeclarationValidator
	 */
	private readonly AttentionDeclarationValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param IAppManager     $appManager  Knows which apps are enabled for a user and where they live.
	 * @param IFactory        $l10nFactory Gives each app's own translations.
	 * @param LoggerInterface $logger      Logger.
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
	) {
		$this->validator = new AttentionDeclarationValidator();
	}//end __construct()

	/**
	 * Every attention item the user's apps declare.
	 *
	 * @param IUser $user The signed-in user.
	 *
	 * @return array{sources: array<int, array<string, mixed>>, invalid: array<int, array<string, string>>}
	 *
	 * @spec openspec/specs/attention-feed/spec.md#req-att-001
	 */
	public function collect(IUser $user): array {
		$sources = [];
		$invalid = [];

		$appIds = $this->appManager->getEnabledAppsForUser(user: $user);
		sort(array: $appIds);

		foreach ($appIds as $appId) {
			$raw = $this->readDeclaration(appId: $appId);
			if ($raw === null) {
				continue;
			}

			$appName = $this->resolveAppName(appId: $appId);

			try {
				$items = $this->validator->check(appId: $appId, raw: $raw);
			} catch (InvalidArgumentException $e) {
				// One app's mistake must not take the list down, and it must
				// not pass for "this app has nothing": it is named.
				$invalid[] = ['appId' => $appId, 'appName' => $appName, 'message' => $e->getMessage()];
				$this->logger->warning(
					message: 'Invalid attention declaration',
					context: ['app' => $appId, 'reason' => $e->getMessage()]
				);
				continue;
			}

			$l10n = $this->l10nFactory->get($appId);
			foreach ($items as $item) {
				$sources[] = $this->present(appId: $appId, appName: $appName, item: $item, l10n: $l10n);
			}
		}//end foreach

		return ['sources' => $sources, 'invalid' => $invalid];
	}//end collect()

	/**
	 * Read an app's declaration file, or null when the app has none.
	 *
	 * @param string $appId The app id.
	 *
	 * @return string|null The file's contents.
	 */
	private function readDeclaration(string $appId): ?string {
		try {
			$path = $this->appManager->getAppPath($appId) . self::DECLARATION_PATH;
		} catch (AppPathNotFoundException) {
			return null;
		}

		if (is_file(filename: $path) === false || is_readable(filename: $path) === false) {
			return null;
		}

		$raw = file_get_contents(filename: $path);
		if ($raw === false) {
			return null;
		}

		return $raw;
	}//end readDeclaration()

	/**
	 * The app's display name, or its id when it has none.
	 *
	 * @param string $appId The app id.
	 *
	 * @return string The name to show.
	 */
	private function resolveAppName(string $appId): string {
		$info = $this->appManager->getAppInfo($appId);
		$name = ($info['name'] ?? null);
		if (is_string($name) === true && $name !== '') {
			return $name;
		}

		return $appId;
	}//end resolveAppName()

	/**
	 * Shape one item for the widget, in the user's language.
	 *
	 * @param string               $appId   The declaring app.
	 * @param string               $appName Its display name.
	 * @param array<string, mixed> $item    The checked item.
	 * @param IL10N                $l10n    The declaring app's translations.
	 *
	 * @return array<string, mixed> The source as the widget reads it.
	 */
	private function present(string $appId, string $appName, array $item, IL10N $l10n): array {
		$item['appId'] = $appId;
		$item['appName'] = $appName;
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$item['title'] = $l10n->t($item['title']);
		$item['reason'] = $l10n->t($item['reason']);
		$item['action']['label'] = $l10n->t($item['action']['label']);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $item;
	}//end present()
}//end class
