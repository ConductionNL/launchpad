<?php

/**
 * AdminSettingMapper
 *
 * Database mapper for admin setting entities.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * AdminSettingMapper
 *
 * Mapper for admin setting entities.
 *
 * @extends QBMapper<AdminSetting>
 */
class AdminSettingMapper extends QBMapper {
	use ColumnMatchQueries;

	/**
	 * Every stored setting, decoded, read once per mapper instance (one request).
	 *
	 * A Workspace render asks for ~20 settings one by one; each used to be its
	 * own query. Null until the first read; setSetting() (the only writer)
	 * empties it.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $values = null;

	/**
	 * Constructor
	 *
	 * @param IDBConnection $db The database connection.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_admin_settings',
			entityClass: AdminSetting::class
		);
	}//end __construct()

	/**
	 * Find setting by key.
	 *
	 * @param string $key The setting key.
	 *
	 * @return AdminSetting The found setting.
	 *
	 * @throws DoesNotExistException If not found.
	 */
	public function findByKey(string $key): AdminSetting {
		$qb = $this->selectWhere(match: ['setting_key' => $key]);

		return $this->findEntity(query: $qb);
	}//end findByKey()

	/**
	 * Get all settings as key-value array.
	 *
	 * @return array The settings as key-value pairs.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#req-aset-001
	 */
	public function getAllAsArray(): array {
		if ($this->values !== null) {
			return $this->values;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName());

		$entities = $this->findEntities(query: $qb);
		$result = [];

		foreach ($entities as $entity) {
			$result[$entity->getSettingKey()] = $entity->getValueDecoded();
		}

		$this->values = $result;
		return $result;
	}//end getAllAsArray()

	/**
	 * Set a setting value (create or update).
	 *
	 * @param string $key The setting key.
	 * @param mixed $value The setting value.
	 *
	 * @return AdminSetting The created or updated setting.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#req-aset-002
	 */
	public function setSetting(string $key, mixed $value): AdminSetting {
		$this->values = null;
		try {
			$setting = $this->findByKey(key: $key);
			$setting->setValueEncoded($value);
			$setting->setUpdatedAt((new DateTime())->format(format: 'Y-m-d H:i:s'));
			return $this->update(entity: $setting);
		} catch (DoesNotExistException) {
			$setting = new AdminSetting();
			$setting->setSettingKey($key);
			$setting->setValueEncoded($value);
			$setting->setUpdatedAt((new DateTime())->format(format: 'Y-m-d H:i:s'));
			return $this->insert(entity: $setting);
		}
	}//end setSetting()

	/**
	 * Get a setting value with default.
	 *
	 * Answered from the settings read once per request (getAllAsArray).
	 *
	 * @param string $key The setting key.
	 * @param mixed $default The default value.
	 *
	 * @return mixed The setting value or default.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#req-aset-001
	 */
	public function getValue(string $key, mixed $default = null): mixed {
		$values = $this->getAllAsArray();
		if (array_key_exists($key, $values) === true) {
			return $values[$key];
		}

		return $default;
	}//end getValue()
}//end class
