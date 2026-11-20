<?php

/**
 * VideoContent
 *
 * The stored shape of a video widget's content blob.
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

/**
 * Fills a video widget's playback defaults and keeps autoplay muted.
 *
 * @spec openspec/specs/video-widget/spec.md#req-vid-002-store-video-widget-configuration-per-placement
 */
final class VideoContent {
	/**
	 * The widget id of a video placement.
	 */
	public const WIDGET_ID = 'video';

	/**
	 * The playback fields a stored video placement always carries.
	 */
	private const DEFAULTS = [
		'autoplay'    => false,
		'muted'       => true,
		'loop'        => false,
		'controls'    => true,
		'aspectRatio' => '16:9',
		'posterUrl'   => null,
	];

	/**
	 * The content to store for a placement of the given widget.
	 *
	 * Content of any other widget is returned unchanged. For a video, missing
	 * playback fields get their defaults and autoplay forces muted on, since
	 * browsers refuse to autoplay unmuted media.
	 *
	 * @param string               $widgetId The placement's widget id.
	 * @param array<string, mixed> $content  The content as submitted.
	 *
	 * @return array<string, mixed> The content to store.
	 *
	 * @spec openspec/specs/video-widget/spec.md#req-vid-008-respect-autoplay-muting-and-loop-settings
	 */
	public static function forWidget(string $widgetId, array $content): array {
		if ($widgetId !== self::WIDGET_ID) {
			return $content;
		}

		$content += self::DEFAULTS;
		if ($content['autoplay'] === true) {
			$content['muted'] = true;
		}

		return $content;
	}//end forWidget()
}//end class
