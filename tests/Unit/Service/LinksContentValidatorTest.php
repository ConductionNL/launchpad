<?php

/**
 * LinksContentValidator Test
 *
 * The `links` widget content checks on save: http(s) addresses, root-relative
 * paths and a single `../` step pass; scripted, data and file URLs, `../`
 * chains, empty URLs and a column count outside 1 to 6 are refused.
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
 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-007-url-sanitisation-and-validation
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\LinksContentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LinksContentValidator}.
 */
class LinksContentValidatorTest extends TestCase {

	/**
	 * Content with one link to the given URL.
	 *
	 * @param mixed $url   The link URL.
	 * @param array $extra Further content keys.
	 *
	 * @return array The content.
	 */
	private static function withUrl(mixed $url, array $extra=[]): array {
		return $extra + ['sections' => [['title' => 'S', 'links' => [['label' => 'L', 'url' => $url]]]]];
	}//end withUrl()

	/**
	 * Content that is accepted.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function validContent(): array {
		return [
			'https'             => [self::withUrl(url: 'https://example.com/page')],
			'http'              => [self::withUrl(url: 'http://example.com/page')],
			'root-relative'     => [self::withUrl(url: '/apps/files/list')],
			'single ../ step'   => [self::withUrl(url: '../other')],
			'no sections'       => [['columns' => 3]],
			'section, no links' => [['sections' => [['title' => 'Empty', 'links' => []]]]],
			'columns 1'         => [self::withUrl(url: '/x', extra: ['columns' => 1])],
			'columns 6'         => [self::withUrl(url: '/x', extra: ['columns' => 6])],
		];
	}//end validContent()

	/**
	 * Content that is refused.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function invalidContent(): array {
		return [
			'javascript'        => [self::withUrl(url: 'javascript:alert("xss")')],
			'data'              => [self::withUrl(url: 'data:text/html,<script>alert(1)</script>')],
			'file'              => [self::withUrl(url: 'file:///etc/passwd')],
			'empty'             => [self::withUrl(url: '')],
			'null'              => [self::withUrl(url: null)],
			'../ chain'         => [self::withUrl(url: '../../../etc/passwd')],
			'root-relative ..'  => [self::withUrl(url: '/apps/../../etc')],
			'protocol-relative' => [self::withUrl(url: '//evil.example/x')],
			'columns 7'         => [self::withUrl(url: '/x', extra: ['columns' => 7])],
			'columns 0'         => [self::withUrl(url: '/x', extra: ['columns' => 0])],
			'columns text'      => [self::withUrl(url: '/x', extra: ['columns' => 'many'])],
			'sections not list' => [['sections' => 'x']],
		];
	}//end invalidContent()

	/**
	 * Accepted content passes.
	 *
	 * @param array $content The content.
	 *
	 * @return void
	 */
	#[DataProvider('validContent')]
	public function testAcceptedContentPasses(array $content): void {
		(new LinksContentValidator())->validate(content: $content);
		$this->addToAssertionCount(count: 1);
	}//end testAcceptedContentPasses()

	/**
	 * Refused content throws.
	 *
	 * @param array $content The content.
	 *
	 * @return void
	 */
	#[DataProvider('invalidContent')]
	public function testRefusedContentThrows(array $content): void {
		$this->expectException(exception: InvalidArgumentException::class);
		(new LinksContentValidator())->validate(content: $content);
	}//end testRefusedContentThrows()

	/**
	 * An empty URL says a URL is required.
	 *
	 * @return void
	 */
	public function testAnEmptyUrlSaysItIsRequired(): void {
		$this->expectExceptionMessage(message: 'Link URL is required');
		(new LinksContentValidator())->validate(content: self::withUrl(url: ' '));
	}//end testAnEmptyUrlSaysItIsRequired()
}//end class
