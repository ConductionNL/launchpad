<?php

/**
 * VideoParsingServiceTest
 *
 * The video URL parser (REQ-VID-003, REQ-VID-005): every YouTube and Vimeo
 * form becomes its canonical embed URL, a PeerTube watch URL its embed form,
 * and anything that is not an http(s) video URL is refused with its reason.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\VideoParsingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\VideoParsingService
 */
class VideoParsingServiceTest extends TestCase {

	/**
	 * Every accepted URL with its source, id and canonical URL.
	 *
	 * @return array<string, array{string, string, string, string}>
	 */
	public static function validUrls(): array {
		return [
			'youtube watch'         => ['https://www.youtube.com/watch?v=ABC123', 'youtube', 'ABC123', 'https://www.youtube.com/embed/ABC123'],
			'youtube short link'    => ['https://youtu.be/ABC123', 'youtube', 'ABC123', 'https://www.youtube.com/embed/ABC123'],
			'youtube with t=30s'    => ['https://youtube.com/watch?v=ABC123&t=30s', 'youtube', 'ABC123', 'https://www.youtube.com/embed/ABC123?start=30'],
			'youtube with t=1m30s'  => ['https://youtu.be/ABC123?t=1m30s', 'youtube', 'ABC123', 'https://www.youtube.com/embed/ABC123?start=90'],
			'youtube embed'         => ['https://www.youtube.com/embed/ABC123?start=120', 'youtube', 'ABC123', 'https://www.youtube.com/embed/ABC123?start=120'],
			'youtube shorts'        => ['https://m.youtube.com/shorts/ABC_12-3', 'youtube', 'ABC_12-3', 'https://www.youtube.com/embed/ABC_12-3'],
			'vimeo'                 => ['https://vimeo.com/12345', 'vimeo', '12345', 'https://player.vimeo.com/video/12345'],
			'vimeo player'          => ['https://player.vimeo.com/video/12345', 'vimeo', '12345', 'https://player.vimeo.com/video/12345'],
			'peertube watch'        => ['https://peertube.example.com/w/abc123', 'peertube', 'abc123', 'https://peertube.example.com/videos/embed/abc123'],
			'peertube videos/watch' => ['https://tube.example.org/videos/watch/9c9de5e8-0a1e-484a-b099-e80766180a6d', 'peertube', '9c9de5e8-0a1e-484a-b099-e80766180a6d', 'https://tube.example.org/videos/embed/9c9de5e8-0a1e-484a-b099-e80766180a6d'],
			'surrounding spaces'    => ['  https://youtu.be/ABC123  ', 'youtube', 'ABC123', 'https://www.youtube.com/embed/ABC123'],
		];
	}//end validUrls()

	#[DataProvider('validUrls')]
	public function testAVideoUrlIsParsedToItsCanonicalEmbed(string $input, string $source, string $videoId, string $canonical): void {
		$this->assertSame(
			['isValid' => true, 'sourceType' => $source, 'videoId' => $videoId, 'canonicalUrl' => $canonical],
			(new VideoParsingService())->parse(videoUrl: $input)
		);
	}

	/**
	 * Inputs that are not an http(s) URL.
	 *
	 * @return array<string, array{string}>
	 */
	public static function notUrls(): array {
		return [
			'free text'   => ['not a valid URL'],
			'no scheme'   => ['www.youtube.com/watch?v=ABC123'],
			'javascript'  => ['javascript:alert(1)'],
			'ftp'         => ['ftp://example.com/video.mp4'],
			'empty'       => [''],
		];
	}//end notUrls()

	#[DataProvider('notUrls')]
	public function testInputThatIsNoUrlIsRefusedAsAnInvalidFormat(string $input): void {
		$this->assertSame(['isValid' => false, 'error' => 'Invalid URL format'], (new VideoParsingService())->parse(videoUrl: $input));
	}

	public function testAUrlThatIsNoKnownVideoIsRefusedAsUnsupported(): void {
		foreach (['https://example.com/page', 'https://www.youtube.com/watch', 'https://vimeo.com/channels/staffpicks'] as $input) {
			$this->assertSame(['isValid' => false, 'error' => 'Unsupported video source'], (new VideoParsingService())->parse(videoUrl: $input), $input);
		}
	}
}//end class
