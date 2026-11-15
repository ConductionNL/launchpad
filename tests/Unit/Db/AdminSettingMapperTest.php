<?php

/**
 * AdminSettingMapperTest
 *
 * Unit tests for AdminSettingMapper: key lookup, the decoded settings map,
 * the update-or-insert write and the default for an absent key.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\AdminSettingMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\AdminSetting
 */
class AdminSettingMapperTest extends TestCase {

	/**
	 * A setting is looked up by its key column.
	 *
	 * @return void
	 */
	public function testFindByKeyMatchesTheKeyColumn(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'setting_key' => 'theme', 'setting_value' => '"dark"']];

		$setting = (new AdminSettingMapper($recorder->connection()))->findByKey('theme');

		$this->assertSame('theme', $setting->getSettingKey());
		$this->assertContains('qb1.from("launchpad_admin_settings", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("setting_key", ":p1", null)', $recorder->calls);
	}//end testFindByKeyMatchesTheKeyColumn()

	/**
	 * An absent key is a DoesNotExistException from the lookup.
	 *
	 * @return void
	 */
	public function testFindByKeyOfAnAbsentKeyThrows(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->expectException(DoesNotExistException::class);

		(new AdminSettingMapper($recorder->connection()))->findByKey('missing');
	}//end testFindByKeyOfAnAbsentKeyThrows()

	/**
	 * Every row is returned keyed by its setting key, with the JSON value decoded.
	 *
	 * @return void
	 */
	public function testGetAllAsArrayDecodesEveryValue(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [
			['id' => 1, 'setting_key' => 'theme', 'setting_value' => '"dark"'],
			['id' => 2, 'setting_key' => 'limits', 'setting_value' => '{"tiles":12}'],
			['id' => 3, 'setting_key' => 'unset', 'setting_value' => null],
		];

		$all = (new AdminSettingMapper($recorder->connection()))->getAllAsArray();

		$this->assertSame(['theme' => 'dark', 'limits' => ['tiles' => 12], 'unset' => null], $all);
	}//end testGetAllAsArrayDecodesEveryValue()

	/**
	 * A stored value is returned decoded; an absent key answers the default.
	 *
	 * @return void
	 */
	public function testGetValueFallsBackToTheDefault(): void {
		$found = new QueryRecorder(test: $this);
		$found->rows = [['id' => 1, 'setting_key' => 'limit', 'setting_value' => '25']];
		$this->assertSame(25, (new AdminSettingMapper($found->connection()))->getValue('limit', 10));

		$absent = new QueryRecorder(test: $this);
		$this->assertSame(10, (new AdminSettingMapper($absent->connection()))->getValue('limit', 10));
	}//end testGetValueFallsBackToTheDefault()

	/**
	 * Writing an existing key updates that row with the encoded value.
	 *
	 * @return void
	 */
	public function testSetSettingUpdatesAnExistingKey(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 4, 'setting_key' => 'theme', 'setting_value' => '"light"']];

		$setting = (new AdminSettingMapper($recorder->connection()))->setSetting('theme', ['mode' => 'dark']);

		$this->assertSame('{"mode":"dark"}', $setting->getSettingValue());
		$this->assertSame(['mode' => 'dark'], $setting->getValueDecoded());
		$this->assertNotNull($setting->getUpdatedAt());
		$this->assertContains('qb2.update("launchpad_admin_settings", null)', $recorder->calls);
		$this->assertContains('qb2.set("setting_value", ":p2")', $recorder->calls);
		$this->assertContains('qb2.expr.eq("id", ":p4", null)', $recorder->calls);
		$this->assertSame([], preg_grep('/\.insert\(/', $recorder->calls));
	}//end testSetSettingUpdatesAnExistingKey()

	/**
	 * Writing a new key inserts a row carrying the key and the encoded value.
	 *
	 * @return void
	 */
	public function testSetSettingInsertsANewKey(): void {
		$recorder = new QueryRecorder(test: $this);

		$setting = (new AdminSettingMapper($recorder->connection()))->setSetting('limit', 25);

		$this->assertSame('limit', $setting->getSettingKey());
		$this->assertSame('25', $setting->getSettingValue());
		$this->assertNotNull($setting->getUpdatedAt());
		$this->assertContains('qb2.insert("launchpad_admin_settings")', $recorder->calls);
	}//end testSetSettingInsertsANewKey()
}//end class
