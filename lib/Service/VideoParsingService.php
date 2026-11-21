<?php

/**
 * VideoParsingService
 *
 * Parses a video URL a user typed into the video widget form (REQ-VID-003,
 * REQ-VID-005): it checks that the input is an http(s) URL, recognises a
 * YouTube, Vimeo or PeerTube address, extracts the video id and returns the
 * canonical embed URL the widget stores.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Turns a raw video URL into a source type, a video id and an embed URL.
 *
 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
 */
class VideoParsingService {
	/**
	 * The error for input that is not an http(s) URL.
	 */
	public const INVALID_URL = 'Invalid URL format';

	/**
	 * The error for a URL that is no YouTube, Vimeo or PeerTube video.
	 */
	public const UNSUPPORTED = 'Unsupported video source';

	/**
	 * Hosts that serve YouTube videos.
	 */
	private const YOUTUBE_HOSTS = [
		'youtube.com',
		'www.youtube.com',
		'm.youtube.com',
		'youtube-nocookie.com',
		'www.youtube-nocookie.com',
	];

	/**
	 * Parse a video URL.
	 *
	 * @param string $videoUrl The URL as typed.
	 *
	 * @return array{isValid: bool, error?: string, sourceType?: string, videoId?: string, canonicalUrl?: string}
	 *
	 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
	 */
	public function parse(string $videoUrl): array {
		$url   = trim($videoUrl);
		$parts = $this->urlParts(url: $url);
		if ($parts === null) {
			return ['isValid' => false, 'error' => self::INVALID_URL];
		}

		$parsed = ($this->parseYouTube(parts: $parts) ?? $this->parseVimeo(parts: $parts) ?? $this->parsePeerTube(parts: $parts));
		if ($parsed === null) {
			return ['isValid' => false, 'error' => self::UNSUPPORTED];
		}

		return ['isValid' => true] + $parsed;
	}//end parse()

	/**
	 * The host, path and query of an http(s) URL, or null for anything else.
	 *
	 * @param string $url The trimmed input.
	 *
	 * @return array{host: string, path: string, query: array<string, mixed>}|null
	 */
	private function urlParts(string $url): ?array {
		if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
			return null;
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		$host   = strtolower((string) parse_url($url, PHP_URL_HOST));
		if (in_array($scheme, ['http', 'https'], true) === false || $host === '') {
			return null;
		}

		$query = [];
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

		return [
			'host'  => $host,
			'path'  => (string) parse_url($url, PHP_URL_PATH),
			'query' => $query,
		];
	}//end urlParts()

	/**
	 * A YouTube watch, short-link, shorts or embed URL, with a `t` offset
	 * kept as `?start=`.
	 *
	 * @param array{host: string, path: string, query: array<string, mixed>} $parts The URL parts.
	 *
	 * @return array{sourceType: string, videoId: string, canonicalUrl: string}|null
	 */
	private function parseYouTube(array $parts): ?array {
		$videoId = null;
		if ($parts['host'] === 'youtu.be') {
			$videoId = explode('/', ltrim($parts['path'], '/'))[0];
		} else if (in_array($parts['host'], self::YOUTUBE_HOSTS, true) === true) {
			$videoId = $this->youTubePathId(path: $parts['path'], query: $parts['query']);
		}

		if ($videoId === null || preg_match('/^[A-Za-z0-9_-]+$/', $videoId) !== 1) {
			return null;
		}

		$canonical = 'https://www.youtube.com/embed/'.$videoId;
		$start     = $this->offsetSeconds(value: ($parts['query']['t'] ?? $parts['query']['start'] ?? null));
		if ($start > 0) {
			$canonical .= '?start='.$start;
		}

		return ['sourceType' => 'youtube', 'videoId' => $videoId, 'canonicalUrl' => $canonical];
	}//end parseYouTube()

	/**
	 * The video id in a youtube.com path or `v` query parameter.
	 *
	 * @param string               $path  The URL path.
	 * @param array<string, mixed> $query The query parameters.
	 *
	 * @return string|null
	 */
	private function youTubePathId(string $path, array $query): ?string {
		if ($path === '/watch' && is_string($query['v'] ?? null) === true) {
			return $query['v'];
		}

		if (preg_match('#^/(?:embed|shorts|live)/([^/]+)#', $path, $match) === 1) {
			return $match[1];
		}

		return null;
	}//end youTubePathId()

	/**
	 * A vimeo.com or player.vimeo.com URL.
	 *
	 * @param array{host: string, path: string, query: array<string, mixed>} $parts The URL parts.
	 *
	 * @return array{sourceType: string, videoId: string, canonicalUrl: string}|null
	 */
	private function parseVimeo(array $parts): ?array {
		$pattern = null;
		if (in_array($parts['host'], ['vimeo.com', 'www.vimeo.com'], true) === true) {
			$pattern = '#^/(\d+)/?$#';
		} else if ($parts['host'] === 'player.vimeo.com') {
			$pattern = '#^/video/(\d+)/?$#';
		}

		if ($pattern === null || preg_match($pattern, $parts['path'], $match) !== 1) {
			return null;
		}

		return [
			'sourceType'   => 'vimeo',
			'videoId'      => $match[1],
			'canonicalUrl' => 'https://player.vimeo.com/video/'.$match[1],
		];
	}//end parseVimeo()

	/**
	 * A PeerTube watch (`/w/<id>`, `/videos/watch/<id>`) or embed URL on any
	 * instance, rewritten to its embed form.
	 *
	 * @param array{host: string, path: string, query: array<string, mixed>} $parts The URL parts.
	 *
	 * @return array{sourceType: string, videoId: string, canonicalUrl: string}|null
	 */
	private function parsePeerTube(array $parts): ?array {
		if (preg_match('#^/(?:w|videos/watch|videos/embed)/([A-Za-z0-9-]+)/?$#', $parts['path'], $match) !== 1) {
			return null;
		}

		return [
			'sourceType'   => 'peertube',
			'videoId'      => $match[1],
			'canonicalUrl' => 'https://'.$parts['host'].'/videos/embed/'.$match[1],
		];
	}//end parsePeerTube()

	/**
	 * A YouTube time offset (`120`, `120s`, `2m`, `1h2m3s`) in seconds.
	 *
	 * @param mixed $value The `t` or `start` parameter.
	 *
	 * @return int The offset, 0 when absent or unreadable.
	 */
	private function offsetSeconds(mixed $value): int {
		if (is_string($value) === false || $value === '') {
			return 0;
		}

		if (ctype_digit($value) === true) {
			return (int) $value;
		}

		if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $match) !== 1) {
			return 0;
		}

		return ((int) ($match[1] ?? 0) * 3600) + ((int) ($match[2] ?? 0) * 60) + (int) ($match[3] ?? 0);
	}//end offsetSeconds()
}//end class
