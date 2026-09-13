<?php

/**
 * StoreService
 *
 * Cross-instance dashboard sharing over OpenRegister's AppHost store plane
 * (ADR-080). Discovery is the engine's: `GenericStoreService` owns the SSRF
 * guard, the redirect refusal, the Bearer-only token transport, the outcome
 * vocabulary and card normalisation. Install is LaunchPad's, because a
 * dashboard lives in LaunchPad's own tables and neither engine install op
 * (`writeObject`, `setAppConfig`) nor a federated configuration bundle can
 * produce one.
 *
 * 🔴 THE ENGINE IS OPTIONAL AND MAY BE NULL. LaunchPad declares no `<app>`
 * dependency on OpenRegister, so it can run without it. Every OpenRegister
 * class here is named as a STRING and instantiated only once the injected
 * client proved non-null, which keeps NC bootstrap fatal-free when OpenRegister
 * is disabled or absent. The degraded state is `not_configured`, the same
 * outcome an unconfigured registry produces, so the store page falls back to
 * its built-in items either way.
 *
 * Implements the `dashboard-store` capability (REQ-STORE-002..007).
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
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Browse a remote dashboard registry and install one of its templates.
 *
 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md
 */
class StoreService {
	/**
	 * Remote schema slug this store reads.
	 *
	 * A registry publishes dashboard templates as objects of this schema. It is
	 * deliberately NOT one of the `openregister.configset` types: a non-empty
	 * `types` list selects the engine's federated configuration path, which
	 * trades registers, schemas and flows rather than dashboards.
	 */
	public const REMOTE_SCHEMA = 'dashboard-template';

	/**
	 * Register segment used when `registry_register` is unset or empty.
	 */
	public const DEFAULT_REGISTER = 'launchpad';

	/**
	 * Card field name to remote object property.
	 *
	 * The engine flattens a remote object to a card using ONLY this map plus
	 * `kind`, so anything a registry sends outside it — a manifest, a
	 * credential-shaped field — never reaches the browser.
	 *
	 * @var array<string, string>
	 */
	public const CARD_FIELDS = [
		'slug' => 'slug',
		'title' => 'title',
		'description' => 'description',
		'category' => 'category',
		'version' => 'version',
		'publisher' => 'publisher',
	];

	/**
	 * Fully-qualified name of the engine's descriptor value object.
	 *
	 * Held as a string so no `OCA\OpenRegister\…` symbol is touched at load
	 * time. Instantiated only after the injected client proved non-null.
	 */
	private const DESCRIPTOR_CLASS = 'OCA\\OpenRegister\\AppHost\\Service\\StoreDescriptor';

	/**
	 * Outcome reported when there is nothing to talk to.
	 */
	public const OUTCOME_NOT_CONFIGURED = 'not_configured';

	/**
	 * Config key holding the registry base URL.
	 */
	public const CONFIG_URL = 'registry_url';

	/**
	 * Config key holding the registry Bearer token.
	 */
	public const CONFIG_TOKEN = 'registry_token';

	/**
	 * Config key holding the remote register segment.
	 */
	public const CONFIG_REGISTER = 'registry_register';

	/**
	 * Constructor.
	 *
	 * @param object|null     $discovery     OpenRegister's `GenericStoreService`, or null when
	 *                                       OpenRegister is unavailable. Untyped on purpose: a
	 *                                       typed parameter would load the class at resolution
	 *                                       time and fatal on an instance without OpenRegister.
	 * @param ImportService   $importService LaunchPad's one and only dashboard importer.
	 * @param IAppConfig      $appConfig     Where the engine reads the registry connection.
	 * @param LoggerInterface $logger        PSR logger — server-side diagnostics only.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly ?object $discovery,
		private readonly ImportService $importService,
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether the discovery engine is present at all.
	 *
	 * @return bool True when OpenRegister supplied a store client.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-002-an-absent-openregister-must-degrade-never-fatal
	 */
	public function isAvailable(): bool {
		return $this->discovery !== null;
	}//end isAvailable()

	/**
	 * Search the configured registry for dashboard templates.
	 *
	 * The engine's outcome is passed through VERBATIM. `store_unreachable` and
	 * `store_invalid_response` are deliberately not collapsed: a misconfigured
	 * registry would otherwise read as an offline one, and the two need
	 * different fixes.
	 *
	 * @param string|null $query Optional free-text search term.
	 * @param string|null $kind  Optional kind filter.
	 *
	 * @return array{outcome: string, cards: array<int, array<string, mixed>>}
	 *
	 * @psalm-suppress MixedMethodCall The discovery client is untyped on purpose.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-003-the-engines-outcome-must-reach-the-caller-unchanged
	 */
	public function search(?string $query = null, ?string $kind = null): array {
		if ($this->discovery === null) {
			return ['outcome' => self::OUTCOME_NOT_CONFIGURED, 'cards' => []];
		}

		$result = $this->discovery->search(
			descriptor: $this->descriptor(),
			query: $query,
			kind: $kind
		);

		if (is_array($result) === false) {
			return ['outcome' => self::OUTCOME_NOT_CONFIGURED, 'cards' => []];
		}

		$cards = ($result['cards'] ?? []);
		if (is_array($cards) === false) {
			$cards = [];
		}

		return [
			'outcome' => (string)($result['outcome'] ?? self::OUTCOME_NOT_CONFIGURED),
			'cards' => $cards,
		];
	}//end search()

	/**
	 * Install one remote dashboard template into this instance.
	 *
	 * The resolved payload is materialised into a `launchpad-export-v1` archive
	 * and handed to {@see ImportService::import()}. LaunchPad gains no second
	 * path from a dashboard payload to rows: the importer that validates an
	 * administrator's uploaded ZIP is the importer that validates this one.
	 *
	 * `preserveUuids` is FALSE, which is what makes a store install additive. A
	 * template published by a foreign instance carries that instance's UUIDs and
	 * one of them can collide with a live local dashboard.
	 *
	 * @param string $slug   The remote item slug.
	 * @param string $userId The installing administrator's UID.
	 *
	 * @return array{success: bool, message: string, components: array<int, array<string, string>>}
	 *
	 * @psalm-suppress MixedMethodCall The discovery client is untyped on purpose.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-005-an-install-must-reuse-importservice-not-reimplement-it
	 */
	public function install(string $slug, string $userId): array {
		if ($this->discovery === null) {
			return $this->failure(message: 'No dashboard registry is available on this instance.');
		}

		$item = $this->discovery->resolve(descriptor: $this->descriptor(), slug: $slug);
		if (is_array($item) === false) {
			return $this->failure(message: 'That template could not be found in the registry.');
		}

		$dashboards = $this->dashboardsFrom(item: $item);
		if ($dashboards === []) {
			return $this->failure(message: 'That template carries no dashboard.');
		}

		try {
			$archivePath = $this->materialise(dashboards: $dashboards, slug: $slug, userId: $userId);
		} catch (Throwable $e) {
			$this->logger->error(
				message: 'Could not build a store archive for ' . $slug . ': ' . $e->getMessage(),
				context: ['app' => Application::APP_ID]
			);
			return $this->failure(message: 'That template could not be prepared for import.');
		}

		try {
			$report = $this->importService->import(
				zipPath: $archivePath,
				preserveUuids: false,
				currentUserId: $userId
			);
		} catch (Throwable $e) {
			// Detail to the log, a plain sentence to the browser. A registry's
			// malformed payload is not the administrator's business to debug.
			$this->logger->error(
				message: 'Store install failed for ' . $slug . ': ' . $e->getMessage(),
				context: ['app' => Application::APP_ID]
			);
			return $this->failure(message: 'That template could not be installed.');
		} finally {
			// Runs on the throwing path too, so a failed install leaves nothing
			// behind in the temp directory.
			$this->discard(path: $archivePath);
		}//end try

		return $this->report(report: $report, dashboards: $dashboards);
	}//end install()

	/**
	 * The registry connection, with the token redacted.
	 *
	 * 🔴 THE TOKEN NEVER LEAVES THE SERVER. The read reports only WHETHER one
	 * is set. Returning it so an admin form could pre-fill would put a
	 * credential in a browser response, and the form does not need it: an empty
	 * submission clears the token and a non-empty one replaces it.
	 *
	 * @return array{registryUrl: string, registryRegister: string, tokenConfigured: bool}
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-007-the-registry-token-must-not-be-readable-back
	 */
	public function getRegistryConfig(): array {
		return [
			'registryUrl' => $this->config(key: self::CONFIG_URL),
			'registryRegister' => $this->config(key: self::CONFIG_REGISTER),
			'tokenConfigured' => ($this->config(key: self::CONFIG_TOKEN) !== ''),
		];
	}//end getRegistryConfig()

	/**
	 * Write the registry connection.
	 *
	 * Each value is optional: a null leaves the stored key untouched, which is
	 * what lets a form change the URL without resubmitting the token. An empty
	 * string is a real value and CLEARS the key.
	 *
	 * @param string|null $registryUrl      Registry base URL, or null to leave it.
	 * @param string|null $registryToken    Bearer token, or null to leave it.
	 * @param string|null $registryRegister Remote register segment, or null to leave it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-007-the-registry-token-must-not-be-readable-back
	 */
	public function updateRegistryConfig(
		?string $registryUrl = null,
		?string $registryToken = null,
		?string $registryRegister = null,
	): void {
		if ($registryUrl !== null) {
			$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_URL, trim($registryUrl));
		}

		if ($registryToken !== null) {
			$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_TOKEN, trim($registryToken));
		}

		if ($registryRegister !== null) {
			$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_REGISTER, trim($registryRegister));
		}
	}//end updateRegistryConfig()

	/**
	 * Build the engine descriptor for this app's store.
	 *
	 * Only ever called once `$this->discovery` proved non-null, which is what
	 * guarantees the OpenRegister class is loadable here.
	 *
	 * @return object The engine's StoreDescriptor.
	 *
	 * @throws RuntimeException When OpenRegister supplied a client but not a descriptor class.
	 *
	 * @psalm-suppress MixedMethodCall Instantiated from a class string by design.
	 */
	private function descriptor(): object {
		$class = self::DESCRIPTOR_CLASS;
		if (class_exists($class) === false) {
			throw new RuntimeException(message: 'OpenRegister store descriptor is unavailable.');
		}

		return new $class(
			appId: Application::APP_ID,
			schema: self::REMOTE_SCHEMA,
			defaultRegister: self::DEFAULT_REGISTER,
			cardFields: self::CARD_FIELDS,
			types: []
		);
	}//end descriptor()

	/**
	 * Pull the dashboard payloads out of a resolved registry item.
	 *
	 * A registry may store the payload as a nested list or as a JSON string, so
	 * both are accepted. Anything else yields an empty list, and the caller
	 * refuses the install rather than importing an empty archive.
	 *
	 * @param array<string, mixed> $item The resolved remote object.
	 *
	 * @return array<int, array<string, mixed>> The dashboard payloads.
	 */
	private function dashboardsFrom(array $item): array {
		$raw = ($item['dashboards'] ?? null);
		if (is_string($raw) === true) {
			$raw = json_decode(json: $raw, associative: true);
		}

		if (is_array($raw) === false) {
			return [];
		}

		$dashboards = [];
		foreach ($raw as $payload) {
			if (is_array($payload) === true && (string)($payload['uuid'] ?? '') !== '') {
				$dashboards[] = $payload;
			}
		}

		return $dashboards;
	}//end dashboardsFrom()

	/**
	 * Write the payloads into a `launchpad-export-v1` archive.
	 *
	 * The shape mirrors {@see ExportService} exactly, because
	 * {@see ImportService::validateZipStructure()} is what reads it back.
	 *
	 * @param array<int, array<string, mixed>> $dashboards The dashboard payloads.
	 * @param string                           $slug       The remote item slug, for the manifest.
	 * @param string                           $userId     The installing UID, for the manifest.
	 *
	 * @return string Path to the temporary archive.
	 *
	 * @throws RuntimeException When the archive cannot be allocated or opened.
	 */
	private function materialise(array $dashboards, string $slug, string $userId): string {
		$tempPath = tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-store-');
		if ($tempPath === false) {
			throw new RuntimeException(message: 'Could not allocate a temporary file for the install.');
		}

		$zip = new ZipArchive();
		if ($zip->open(filename: $tempPath, flags: ZipArchive::OVERWRITE) !== true) {
			unlink(filename: $tempPath);
			throw new RuntimeException(message: 'Could not open the store archive for writing.');
		}

		$manifest = [
			'schemaVersion' => ImportService::SCHEMA_VERSION,
			'exportedAt' => gmdate(format: 'Y-m-d\TH:i:s\Z'),
			'exportedBy' => $userId,
			'launchpadVersion' => 'launchpad/v1',
			'scope' => 'dashboard',
			'dashboardCount' => count($dashboards),
			'includedAssets' => [],
			'sourceSlug' => $slug,
		];

		$zip->addFromString(name: 'manifest.json', content: $this->encode(payload: $manifest));

		foreach ($dashboards as $payload) {
			$zip->addFromString(
				name: 'dashboards/' . (string)$payload['uuid'] . '.json',
				content: $this->encode(payload: $payload)
			);
		}

		$zip->addFromString(name: 'metadata-fields.json', content: $this->encode(payload: []));
		$zip->close();

		return $tempPath;
	}//end materialise()

	/**
	 * Turn the importer's result into the store page's per-component report.
	 *
	 * `CnStorePage` reads `components[].status` and names every entry that is
	 * not `installed`, so a partial install states which dashboard did not
	 * arrive instead of reporting a flat failure.
	 *
	 * @param array<string, mixed>             $report     The importer's result.
	 * @param array<int, array<string, mixed>> $dashboards The payloads that were offered.
	 *
	 * @return array{success: bool, message: string, components: array<int, array<string, string>>}
	 */
	private function report(array $report, array $dashboards): array {
		$imported = (int)($report['importedDashboardCount'] ?? 0);

		$components = [];
		$index = 0;
		foreach ($dashboards as $payload) {
			$name = (string)($payload['name'] ?? $payload['uuid'] ?? 'dashboard');

			$status = 'refused';
			if ($index < $imported) {
				$status = 'installed';
			}

			$components[] = ['schema' => $name, 'status' => $status];
			$index++;
		}

		$message = 'Installed ' . (string)$imported . ' of ' . (string)count($dashboards) . '.';

		return [
			'success' => ($imported > 0),
			'message' => $message,
			'components' => $components,
		];
	}//end report()

	/**
	 * Remove a temporary archive, tolerating one that is already gone.
	 *
	 * @param string $path The archive path.
	 *
	 * @return void
	 */
	private function discard(string $path): void {
		if (file_exists(filename: $path) === true) {
			unlink(filename: $path);
		}
	}//end discard()

	/**
	 * Read one registry config value.
	 *
	 * @param string $key The config key.
	 *
	 * @return string The trimmed value, or the empty string.
	 */
	private function config(string $key): string {
		return trim($this->appConfig->getValueString(Application::APP_ID, $key, ''));
	}//end config()

	/**
	 * JSON-encode an archive entry.
	 *
	 * @param array<mixed> $payload The payload.
	 *
	 * @return string The encoded JSON.
	 */
	private function encode(array $payload): string {
		$encoded = json_encode(value: $payload, flags: (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		if ($encoded === false) {
			return '{}';
		}

		return $encoded;
	}//end encode()

	/**
	 * A refusal carrying no components.
	 *
	 * @param string $message The sentence the administrator reads.
	 *
	 * @return array{success: bool, message: string, components: array<int, array<string, string>>}
	 */
	private function failure(string $message): array {
		return ['success' => false, 'message' => $message, 'components' => []];
	}//end failure()
}//end class
