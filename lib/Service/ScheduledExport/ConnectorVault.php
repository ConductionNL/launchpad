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
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * Sends a request through a connector source.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
 */
class ConnectorVault {

	private const CONNECTOR_APP = 'integriq';

	private const CALL_SERVICE = 'Service\\CallService';

	private const OBJECT_SERVICE = 'OCA\OpenRegister\Service\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves the connector's call service and the source object.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * Send through a source; the returned id is the connector's call-log entry.
	 *
	 * @param string               $sourceId The source uuid.
	 * @param array<string, mixed> $request  `endpoint`, `method`, `body` and `headers`.
	 *
	 * @return string The call-log id.
	 *
	 * @throws DeliveryException When the vault is unavailable, the source is unknown, or the call fails.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
	 */
	public function send(string $sourceId, array $request): string {
		$calls = FleetAppId::getService(container: $this->container, canonical: self::CONNECTOR_APP, relative: self::CALL_SERVICE);
		if ($calls === null || method_exists($calls, 'call') === false) {
			throw new DeliveryException('The credential vault is not available');
		}

		try {
			$source = $this->container->get(self::OBJECT_SERVICE)->find(id: $sourceId, _rbac: false);
		} catch (Throwable) {
			$source = null;
		}

		if ($source === null) {
			throw new DeliveryException('Unknown credential vault source: ' . $sourceId);
		}

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

		$data = [];
		if (is_object($log) === true && method_exists($log, 'jsonSerialize') === true) {
			$data = (array)$log->jsonSerialize();
		}

		$status = (int)($data['response']['statusCode'] ?? $data['statusCode'] ?? 200);
		if ($status >= 400) {
			throw new DeliveryException('HTTP ' . $status . ' through the credential vault');
		}

		return (string)($data['@self']['id'] ?? $data['uuid'] ?? $data['id'] ?? 'vault');
	}//end send()
}//end class
