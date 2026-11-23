<?php

/**
 * DemoSeedTranslationTest
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DemoSeedTranslation;
use PHPUnit\Framework\TestCase;

/**
 * Translating a demo seed's texts.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedTranslationTest extends TestCase {
	/**
	 * Whole texts are replaced at any depth; identifiers, filters and partial matches are not.
	 *
	 * @return void
	 */
	public function testReplacesWholeTextsOutsideKeptKeys(): void {
		$seed = [
			'dashboards' => [[
				'key'     => 'Vandaag',
				'name'    => 'Vandaag',
				'widgets' => [[
					'widgetId'    => 'stat',
					'customTitle' => 'Vandaag eerst',
					'content'     => [
						'label'  => 'Vandaag in Zuiddrecht',
						'source' => ['filter' => ['status' => 'Vandaag']],
					],
				]],
			]],
			'groups'     => ['Vandaag'],
		];

		$translated = (new DemoSeedTranslation())->apply(seed: $seed, strings: ['Vandaag' => 'Today', 'Vandaag eerst' => 'First things today']);

		$this->assertSame(expected: 'Today', actual: $translated['dashboards'][0]['name']);
		$this->assertSame(expected: 'Vandaag', actual: $translated['dashboards'][0]['key']);
		$this->assertSame(expected: 'First things today', actual: $translated['dashboards'][0]['widgets'][0]['customTitle']);
		$this->assertSame(expected: 'Vandaag in Zuiddrecht', actual: $translated['dashboards'][0]['widgets'][0]['content']['label']);
		$this->assertSame(expected: 'Vandaag', actual: $translated['dashboards'][0]['widgets'][0]['content']['source']['filter']['status']);
		$this->assertSame(expected: ['Vandaag'], actual: $translated['groups']);
	}//end testReplacesWholeTextsOutsideKeptKeys()

	/**
	 * An empty map leaves the seed as it is.
	 *
	 * @return void
	 */
	public function testEmptyMapLeavesTheSeed(): void {
		$seed = ['name' => 'Vandaag'];
		$this->assertSame(expected: $seed, actual: (new DemoSeedTranslation())->apply(seed: $seed, strings: []));
	}//end testEmptyMapLeavesTheSeed()

	/**
	 * A language with a region tries the region first, then the language; junk gives nothing.
	 *
	 * @return void
	 */
	public function testCandidates(): void {
		$this->assertSame(expected: ['en_GB', 'en'], actual: (new DemoSeedTranslation())->candidates(language: 'en-GB'));
		$this->assertSame(expected: ['en'], actual: (new DemoSeedTranslation())->candidates(language: 'en'));
		$this->assertSame(expected: [], actual: (new DemoSeedTranslation())->candidates(language: ''));
		$this->assertSame(expected: [], actual: (new DemoSeedTranslation())->candidates(language: '../../etc/passwd'));
	}//end testCandidates()
}//end class
