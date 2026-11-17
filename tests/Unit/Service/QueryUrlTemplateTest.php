<?php

/**
 * QueryUrlTemplateTest
 *
 * The https `{query}` address template check shared by the quick-search
 * fallback (REQ-QSEARCH-004) and the search shortcuts (REQ-SPX-001).
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\QueryUrlTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryUrlTemplate::class)]
class QueryUrlTemplateTest extends TestCase {
	/**
	 * Templates and whether they are valid.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function templates(): array {
		return [
			'https with query'        => ['https://duckduckgo.com/?q={query}', true],
			'query in the path'       => ['https://example.org/search/{query}', true],
			'empty'                   => ['', false],
			'whitespace'              => ['   ', false],
			'no placeholder'          => ['https://example.org/search?q=x', false],
			'http'                    => ['http://example.org/search?q={query}', false],
			'javascript scheme'       => ['javascript:alert({query})', false],
			'no host'                 => ['https://?q={query}', false],
			'placeholder only'        => ['{query}', false],
		];
	}//end templates()

	/**
	 * Each template validates as expected.
	 *
	 * @param string $template The candidate template.
	 * @param bool   $expected Whether it is valid.
	 *
	 * @return void
	 */
	#[DataProvider('templates')]
	public function testIsValid(string $template, bool $expected): void {
		$this->assertSame(expected: $expected, actual: QueryUrlTemplate::isValid(value: $template));
	}//end testIsValid()
}//end class
