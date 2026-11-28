<?php

/**
 * UsageTelemetryService
 *
 * Embed usage telemetry (REQ-EMB-008): writes one PII-free
 * `embed_usage_event` per request unless the organisation turned telemetry
 * off, and answers the usage report of a token with aggregates only — by
 * day, host origin, viewport, browser, interaction and status — never a row
 * per visitor. Turning telemetry on or off is written to the audit log.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Entity\EmbedUsageEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Records embed usage and reports it in aggregate.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
 */
class UsageTelemetryService {

	/**
	 * The app setting that turns telemetry on (default) or off.
	 *
	 * @var string
	 */
	public const SETTING = 'embed_telemetry_enabled';

	/**
	 * The report window, in days.
	 *
	 * @var integer
	 */
	public const REPORT_DAYS = 7;

	/**
	 * How many browsers the report lists.
	 *
	 * @var integer
	 */
	private const TOP_BROWSERS = 10;

	/**
	 * Constructor.
	 *
	 * @param EmbedObjectStore $store      The register objects.
	 * @param IAppConfig       $appConfig  Holds the telemetry setting.
	 * @param ITimeFactory     $time       The clock.
	 * @param IEventDispatcher $dispatcher Carries the audit event.
	 * @param LoggerInterface  $logger     Logs an event that could not be written.
	 */
	public function __construct(
		private readonly EmbedObjectStore $store,
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $time,
		private readonly IEventDispatcher $dispatcher,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether usage events are written.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
	 */
	public function isEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::SETTING, true);
	}//end isEnabled()

	/**
	 * Turn telemetry on or off, and audit the change.
	 *
	 * @param bool   $enabled Whether to write usage events.
	 * @param string $userId  The admin.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
	 */
	public function setEnabled(bool $enabled, string $userId): void {
		$before = $this->isEnabled();
		$this->appConfig->setValueBool(Application::APP_ID, self::SETTING, $enabled);
		if ($before === $enabled) {
			return;
		}

		$state = 'off';
		if ($enabled === true) {
			$state = 'on';
		}

		$this->dispatcher->dispatchTyped(
			new CriticalActionPerformedEvent('LaunchPad embed usage telemetry turned %s by %s', [$state, $userId])
		);
	}//end setEnabled()

	/**
	 * Write one usage event; nothing when telemetry is off. A write failure
	 * is logged and never reaches the caller (the embed still renders).
	 *
	 * @param string      $tokenId   The token.
	 * @param string      $eventType One of EmbedUsageEvent::TYPES.
	 * @param string|null $origin    The Origin header.
	 * @param string|null $referer   The Referer header.
	 * @param string|null $userAgent The User-Agent header.
	 * @param string|null $viewport  The viewport the SDK reported.
	 * @param int         $status    The response status.
	 * @param int         $latencyMs The response latency.
	 *
	 * @return array<string, mixed>|null The stored event, null when none was written.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	public function record(
		string $tokenId,
		string $eventType,
		?string $origin,
		?string $referer,
		?string $userAgent,
		?string $viewport,
		int $status,
		int $latencyMs,
	): ?array {
		if ($this->isEnabled() === false) {
			return null;
		}

		$event = EmbedUsageEvent::build(
			tokenId: $tokenId,
			eventType: $eventType,
			origin: $origin,
			referer: $referer,
			userAgent: $userAgent,
			viewport: $viewport,
			status: $status,
			latencyMs: $latencyMs,
			timestamp: gmdate('Y-m-d\TH:i:s\Z', $this->time->getTime())
		);

		try {
			return $this->store->save(schema: EmbedObjectStore::USAGE, object: $event);
		} catch (Throwable $failure) {
			$this->logger->warning('[UsageTelemetryService] A usage event could not be written: ' . $failure->getMessage(), ['token' => $tokenId]);
			return null;
		}
	}//end record()

	/**
	 * A token's usage over the last seven days, in aggregate only.
	 *
	 * @param string      $tokenId   The token.
	 * @param string|null $revokedAt When the token was revoked, to count use after it.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-29
	 */
	public function report(string $tokenId, ?string $revokedAt=null): array {
		$now   = $this->time->getTime();
		$since = gmdate('Y-m-d', $now - ((self::REPORT_DAYS - 1) * 86400));

		$byDate = [];
		for ($day = (self::REPORT_DAYS - 1); $day >= 0; $day--) {
			$byDate[gmdate('Y-m-d', $now - ($day * 86400))] = 0;
		}

		$origins      = [];
		$viewports    = ['small' => 0, 'medium' => 0, 'large' => 0, 'unknown' => 0];
		$browsers     = [];
		$interactions = ['filterApplied' => 0, 'drillDown' => 0, 'export' => 0];
		$statuses     = [];
		$pageViews    = 0;
		$rateLimited  = 0;
		$afterRevoke  = 0;
		$last         = null;

		foreach ($this->store->findBy(schema: EmbedObjectStore::USAGE, filters: ['tokenId' => $tokenId]) as $event) {
			$timestamp = (string)($event['timestamp'] ?? '');
			$date      = substr($timestamp, 0, 10);
			if ($date < $since) {
				continue;
			}

			$type   = (string)($event['eventType'] ?? '');
			$status = (int)($event['responseStatusCode'] ?? 0);
			if ($last === null || $timestamp > $last) {
				$last = $timestamp;
			}

			if ($revokedAt !== null && $timestamp > $revokedAt) {
				$afterRevoke++;
			}

			$statuses[(string)$status] = (($statuses[(string)$status] ?? 0) + 1);
			if ($status === 429 || $type === 'rateLimited') {
				$rateLimited++;
			}

			if (isset($interactions[$type]) === true) {
				$interactions[$type]++;
				continue;
			}

			if ($type !== 'pageView') {
				continue;
			}

			$pageViews++;
			if (isset($byDate[$date]) === true) {
				$byDate[$date]++;
			}

			$origin           = (string)($event['hostOrigin'] ?? 'unknown');
			$origins[$origin] = (($origins[$origin] ?? 0) + 1);
			$viewport         = (string)($event['viewportSize'] ?? 'unknown');
			if (isset($viewports[$viewport]) === false) {
				$viewport = 'unknown';
			}

			$viewports[$viewport]++;
			$browser            = (string)($event['userAgent'] ?? 'Other');
			$browsers[$browser] = (($browsers[$browser] ?? 0) + 1);
		}//end foreach

		arsort($origins);
		arsort($browsers);
		ksort($statuses);

		return [
			'tokenId'          => $tokenId,
			'days'             => self::REPORT_DAYS,
			'telemetryEnabled' => $this->isEnabled(),
			'summary'          => ['pageViews' => $pageViews, 'lastActivity' => $last, 'usedAfterRevocation' => $afterRevoke],
			'byDate'           => self::series(counts: $byDate),
			'byHostOrigin'     => self::series(counts: $origins),
			'byViewport'       => $viewports,
			'byBrowser'        => self::series(counts: array_slice($browsers, 0, self::TOP_BROWSERS, true)),
			'interactions'     => $interactions,
			'rateLimited'      => $rateLimited,
			'byStatus'         => $statuses,
		];
	}//end report()

	/**
	 * Counts as a chart series: [{label, count}].
	 *
	 * @param array<string|int, int> $counts Label => count.
	 *
	 * @return list<array{label: string, count: int}>
	 */
	private static function series(array $counts): array {
		$series = [];
		foreach ($counts as $label => $count) {
			$series[] = ['label' => (string)$label, 'count' => $count];
		}

		return $series;
	}//end series()
}//end class
