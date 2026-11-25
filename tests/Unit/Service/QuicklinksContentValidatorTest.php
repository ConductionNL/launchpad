<?php

/**
 * QuicklinksContentValidator Test
 *
 * The `quicklinks` widget content checks on save: http(s) addresses and
 * relative Nextcloud paths pass; scripted and data URLs, protocol-relative
 * addresses, empty URLs and URLs over 2048 characters are refused.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/quicklinks-widget/spec.md#requirement-req-qlnk-008-url-sanitisation-on-save
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\QuicklinksContentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see QuicklinksContentValidator}.
 */
class QuicklinksContentValidatorTest extends TestCase {

	/**
	 * Content with one link to the given URL.
	 *
	 * @param mixed $url The link URL.
	 *
	 * @return array The content.
	 */
	private static function withUrl(mixed $url): array {
		return ['links' => [['label' => 'Docs', 'url' => $url, 'icon' => '']]];
	}

	/**
	 * URLs the widget saves.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function allowedUrls(): array {
		return [
			'https with a query' => ['https://example.com/page?param=value'],
			'http'               => ['http://intranet.example'],
			'relative Nextcloud' => ['/apps/files/'],
		];
	}

	/**
	 * URLs the widget refuses.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function refusedUrls(): array {
		return [
			'javascript'        => ['javascript:void(0)'],
			'javascript alert'  => ['javascript:alert("xss")'],
			'data'              => ['data:text/html,<img src=x onerror=alert(1)>'],
			'vbscript'          => ['vbscript:msgbox(1)'],
			'protocol-relative' => ['//evil.example/'],
			'empty'             => [''],
			'null'              => [null],
			'too long'          => ['https://example.com/' . str_repeat('a', 2048)],
		];
	}

	/**
	 * An allowed URL passes.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	#[DataProvider('allowedUrls')]
	public function testAnAllowedUrlPasses(string $url): void {
		(new QuicklinksContentValidator())->validate(content: self::withUrl(url: $url));
		$this->addToAssertionCount(1);
	}//end testAnAllowedUrlPasses()

	/**
	 * A refused URL throws "Invalid URL".
	 *
	 * @param mixed $url The URL.
	 *
	 * @return void
	 */
	#[DataProvider('refusedUrls')]
	public function testARefusedUrlIsInvalid(mixed $url): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid URL');
		(new QuicklinksContentValidator())->validate(content: self::withUrl(url: $url));
	}//end testARefusedUrlIsInvalid()

	/**
	 * Content without links passes; links that are not a list are refused.
	 *
	 * @return void
	 */
	public function testTheLinksShape(): void {
		(new QuicklinksContentValidator())->validate(content: []);
		$this->expectException(InvalidArgumentException::class);
		(new QuicklinksContentValidator())->validate(content: ['links' => 'nope']);
	}//end testTheLinksShape()
}//end class
