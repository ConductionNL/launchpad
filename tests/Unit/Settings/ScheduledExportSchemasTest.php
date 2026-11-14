<?php

/**
 * ScheduledExportSchemasTest
 *
 * The register descriptor carries the four scheduled-export schemas
 * (scheduled-exports task 1) and the launchpad register lists them.
 *
 * @category  Test
 * @package   Unit\Settings
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

namespace Unit\Settings;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */
#[CoversNothing]
class ScheduledExportSchemasTest extends TestCase {

	/**
	 * The decoded register descriptor.
	 *
	 * @return array<string, mixed>
	 */
	private static function register(): array {
		return json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/launchpad_register.json'), true, 512, JSON_THROW_ON_ERROR);
	}//end register()

	/**
	 * Every schema is declared and listed on the register.
	 *
	 * @return void
	 */
	public function testTheFourSchemasAreDeclaredAndRegistered(): void {
		$register = self::register();
		$names    = ['scheduled_export', 'render_target', 'recipient', 'scheduled_export_run'];
		foreach ($names as $name) {
			$this->assertSame($name, $register['components']['schemas'][$name]['slug'] ?? null, $name);
		}

		$this->assertSame(['dashboard', ...$names], $register['components']['registers']['launchpad']['schemas']);
	}//end testTheFourSchemasAreDeclaredAndRegistered()

	/**
	 * The export holds the fields the runner and editor rely on, with their enums.
	 *
	 * @return void
	 */
	public function testTheExportSchemaCarriesItsRuleTargetsAndRecipients(): void {
		$schema = self::register()['components']['schemas']['scheduled_export'];

		$this->assertSame(['name', 'subjectType', 'subjectId', 'recurrence', 'renderTargets', 'recipients'], $schema['required']);
		$this->assertSame(['weekly', 'monthly', 'daily', 'cron'], $schema['properties']['recurrence']['properties']['type']['enum']);
		$this->assertSame(['pdf', 'png', 'csv', 'xlsx'], $schema['properties']['renderTargets']['items']['properties']['format']['enum']);
		$this->assertSame(['email', 'nextcloudFiles', 'webhook', 'sftp'], $schema['properties']['recipients']['items']['properties']['channel']['enum']);
		$this->assertSame(1, $schema['properties']['recipients']['minItems']);
		$this->assertSame(30, $schema['properties']['retention']['properties']['keepArtefactsForDays']['default']);
		$this->assertSame(365, $schema['properties']['retention']['properties']['keepAuditForDays']['default']);
	}//end testTheExportSchemaCarriesItsRuleTargetsAndRecipients()

	/**
	 * A recipient has no field that holds a password or secret inline.
	 *
	 * @return void
	 */
	public function testARecipientHoldsNoInlineSecret(): void {
		$recipient = self::register()['components']['schemas']['recipient'];
		$json      = strtolower((string)json_encode($recipient['properties']));
		foreach (['"password"', '"secret"', '"privatekey"', '"apikey"'] as $field) {
			$this->assertStringNotContainsString($field, $json);
		}

		$this->assertStringContainsString('never inline', $recipient['properties']['channelConfig']['description']);
	}//end testARecipientHoldsNoInlineSecret()

	/**
	 * A run-row records each delivery's attempts and status, including the dead-letter state.
	 *
	 * @return void
	 */
	public function testTheRunSchemaRecordsDeliveriesAndArtefactHashes(): void {
		$run = self::register()['components']['schemas']['scheduled_export_run'];
		$this->assertSame(['pending', 'retrying', 'success', 'failed-permanent'], $run['properties']['deliveries']['items']['properties']['status']['enum']);
		$this->assertSame('^[a-f0-9]{64}$', $run['properties']['renderArtefacts']['items']['properties']['sha256']['pattern']);
		$this->assertSame(['schedule', 'manual'], $run['properties']['triggeredBy']['enum']);
	}//end testTheRunSchemaRecordsDeliveriesAndArtefactHashes()
}//end class
