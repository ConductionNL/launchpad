<?php

/**
 * EmbedUsageEvent
 *
 * One `embed_usage_event`, built so it cannot carry personal data (D5,
 * REQ-EMB-008): the host origin (scheme and host only), the browser family
 * and major version, the sha256 of the user agent, a viewport bucket, the
 * status code and the latency. No IP address, no full user agent, no
 * end-user identifier, and no path or query of the host page.
 * @category  Entity
 * @package   OCA\LaunchPad\Entity
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

namespace OCA\LaunchPad\Entity;


/**
 * Builds a PII-free usage event from what a request carried.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
 */
final class EmbedUsageEvent {

	/**
	 * The event types an embed records.
	 *
	 * @var list<string>
	 */
	public const TYPES = ['pageView', 'widgetRendered', 'filterApplied', 'drillDown', 'export', 'rateLimited'];

	/**
	 * Widths below this are `small`.
	 *
	 * @var integer
	 */
	private const SMALL_BELOW = 768;

	/**
	 * Widths below this (and not small) are `medium`.
	 *
	 * @var integer
	 */
	private const MEDIUM_BELOW = 1200;

	/**
	 * The stored event.
	 *
	 * @param string      $tokenId       The token.
	 * @param string      $eventType     One of TYPES (anything else is a pageView).
	 * @param string|null $origin        The Origin header.
	 * @param string|null $referer       The Referer header, used only for its origin.
	 * @param string|null $userAgent     The User-Agent header, kept only as family, major and hash.
	 * @param string|null $viewport      A width, "WxH" or a bucket name, from the SDK.
	 * @param int         $status        The response status code.
	 * @param int         $latencyMs     The response latency.
	 * @param string      $timestamp     ISO 8601.
	 * @param string|null $correlationId A request correlation id.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	public static function build(
		string $tokenId,
		string $eventType,
		?string $origin,
		?string $referer,
		?string $userAgent,
		?string $viewport,
		int $status,
		int $latencyMs,
		string $timestamp,
		?string $correlationId=null,
	): array {
		$type = 'pageView';
		if (in_array($eventType, self::TYPES, true) === true) {
			$type = $eventType;
		}

		$hash = null;
		if ($userAgent !== null && $userAgent !== '') {
			$hash = hash('sha256', $userAgent);
		}

		return [
			'tokenId'            => $tokenId,
			'eventType'          => $type,
			'hostOrigin'         => self::hostOrigin(origin: $origin, referer: $referer),
			'userAgent'          => self::browser(userAgent: (string)$userAgent),
			'userAgentHash'      => $hash,
			'viewportSize'       => self::viewportBucket(viewport: $viewport),
			'timestamp'          => $timestamp,
			'responseStatusCode' => $status,
			'responseLatencyMs'  => max(0, $latencyMs),
			'correlationId'      => $correlationId,
		];
	}//end build()

	/**
	 * The origin of the host page: the Origin header, else scheme and host of
	 * the Referer; null when neither is a web origin.
	 *
	 * @param string|null $origin  The Origin header.
	 * @param string|null $referer The Referer header.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	public static function hostOrigin(?string $origin, ?string $referer): ?string {
		foreach ([$origin, $referer] as $candidate) {
			if ($candidate === null || $candidate === '' || $candidate === 'null') {
				continue;
			}

			$parts = parse_url($candidate);
			$scheme = strtolower((string)($parts['scheme'] ?? ''));
			if (in_array($scheme, ['http', 'https'], true) === false || (string)($parts['host'] ?? '') === '') {
				continue;
			}

			$port = '';
			if (isset($parts['port']) === true) {
				$port = ':' . $parts['port'];
			}

			return $scheme . '://' . strtolower((string)$parts['host']) . $port;
		}

		return null;
	}//end hostOrigin()

	/**
	 * Browser family and major version, e.g. "Firefox 125"; "Other" when unknown.
	 *
	 * @param string $userAgent The User-Agent header.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	public static function browser(string $userAgent): string {
		// Order matters: Edge and Opera also say Chrome, Chrome also says Safari.
		$families = [
			'Edge'    => '#Edg(?:e|A|iOS)?/(\d+)#',
			'Opera'   => '#OPR/(\d+)#',
			'Firefox' => '#(?:Firefox|FxiOS)/(\d+)#',
			'Chrome'  => '#(?:Chrome|CriOS)/(\d+)#',
			'Safari'  => '#Version/(\d+)[.\d]* (?:Mobile/\S+ )?Safari/#',
		];
		foreach ($families as $family => $pattern) {
			if (preg_match($pattern, $userAgent, $match) === 1) {
				return $family . ' ' . $match[1];
			}
		}

		return 'Other';
	}//end browser()

	/**
	 * A viewport bucket: small, medium, large or unknown.
	 *
	 * @param string|null $viewport A width, "WxH" or a bucket name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	public static function viewportBucket(?string $viewport): string {
		$viewport = strtolower(trim((string)$viewport));
		if (in_array($viewport, ['small', 'medium', 'large'], true) === true) {
			return $viewport;
		}

		if (preg_match('#^(\d{1,5})(?:x\d{1,5})?$#', $viewport, $match) !== 1 || (int)$match[1] === 0) {
			return 'unknown';
		}

		$width = (int)$match[1];
		if ($width < self::SMALL_BELOW) {
			return 'small';
		}

		if ($width < self::MEDIUM_BELOW) {
			return 'medium';
		}

		return 'large';
	}//end viewportBucket()
}//end class
