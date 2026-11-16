<?php

/**
 * ConnectorVault
 *
 * Delivery through the connector app's credential vault (design D5,
 * REQ-SCH-010): a recipient with `openconnectorSourceId` is sent through
 * that source, so the credential stays in the vault and the call lands in
 * the connector's call log. There is no fallback to local credentials:
 * without the connector the delivery fails.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use OCA\LaunchPad\Support\FleetAppId;
use OCP\IGroupManager;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * Sends a request through a connector source.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
 * @SuppressWarnings(PHPMD.StaticAccess) FleetAppId resolves the connector app under either of its names.
 */
class ConnectorVault {

	private const CONNECTOR_APP = 'integriq';

	private const CALL_SERVICE = 'Service\\CallService';

	private const OBJECT_SERVICE = 'OCA\OpenRegister\Service\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container    Resolves the connector's call service and the source object.
	 * @param IGroupManager      $groupManager Tells whether the export's creator is an administrator.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * Send through a source; the returned id is the connector's call-log entry.
	 *
	 * @param string               $sourceId The source uuid.
	 * @param array<string, mixed> $request  `endpoint`, `method`, `body` and `headers`.
	 * @param string               $owner    The export's creator; the source is used under their access.
	 *
	 * @return string The call-log id.
	 *
	 * @throws DeliveryException When the vault is unavailable, the source is unknown or not the creator's, or the call fails.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
	 */
	public function send(string $sourceId, array $request, string $owner): string {
		$calls = FleetAppId::getService(container: $this->container, canonical: self::CONNECTOR_APP, relative: self::CALL_SERVICE);
		if ($calls === null || method_exists($calls, 'call') === false) {
			throw new DeliveryException('The credential vault is not available');
		}

		$source = $this->source(sourceId: $sourceId, owner: $owner);

		try {
			$log = $calls->call(
				source: $source,
				endpoint: (string)($request['endpoint'] ?? ''),
				method: (string)($request['method'] ?? 'POST'),
				config: ['body' => $request['body'] ?? '', 'headers' => $request['headers'] ?? []]
			);
		} catch (Throwable $e) {
			throw new DeliveryException('The credential vault call failed: ' . $e->getMessage());
		}

		return self::reference(log: $log);
	}//end send()

	/**
	 * The vault source by id, under the export creator's access.
	 *
	 * The delivery runs in the background without a session, so the source is
	 * read without the session's checks and then held to the creator: only a
	 * source the creator owns, or any source when the creator is an
	 * administrator. Any other source reads as unknown.
	 *
	 * @param string $sourceId The source's id.
	 * @param string $owner    The export's creator.
	 *
	 * @return mixed The source.
	 *
	 * @throws DeliveryException When it does not exist, cannot be read, or is not the creator's.
	 */
	private function source(string $sourceId, string $owner): mixed {
		try {
			$source = $this->container->get(self::OBJECT_SERVICE)->find(id: $sourceId, _rbac: false);
		} catch (Throwable) {
			$source = null;
		}

		if ($source === null || $this->mayUse(source: $source, owner: $owner) === false) {
			throw new DeliveryException('Unknown credential vault source: ' . $sourceId);
		}

		return $source;
	}//end source()

	/**
	 * Whether the export's creator may deliver through the source.
	 *
	 * @param mixed  $source The source.
	 * @param string $owner  The export's creator.
	 *
	 * @return bool
	 */
	private function mayUse(mixed $source, string $owner): bool {
		if ($owner === '') {
			return false;
		}

		if ($this->groupManager->isAdmin(userId: $owner) === true) {
			return true;
		}

		if (is_object($source) === false || method_exists($source, 'getOwner') === false) {
			return false;
		}

		return (string)$source->getOwner() === $owner;
	}//end mayUse()

	/**
	 * The call log's reference, or `vault` when it carries none.
	 *
	 * @param mixed $log What the vault call returned.
	 *
	 * @return string The reference.
	 *
	 * @throws DeliveryException When the call answered an HTTP error.
	 */
	private static function reference(mixed $log): string {
		$data = [];
		if (is_object($log) === true && method_exists($log, 'jsonSerialize') === true) {
			$data = (array)$log->jsonSerialize();
		}

		$status = (int)($data['response']['statusCode'] ?? $data['statusCode'] ?? 200);
		if ($status >= 400) {
			throw new DeliveryException('HTTP ' . $status . ' through the credential vault');
		}

		return (string)($data['@self']['id'] ?? $data['uuid'] ?? $data['id'] ?? 'vault');
	}//end reference()
}//end class
