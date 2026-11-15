<?php

/**
 * StorageFigures
 *
 * The platform's own storage figures, read from the file cache and the register tables (REQ-DWMS-010).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\StorageReport
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\StorageReport;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Throwable;

/**
 * Reads what the platform already records, never walking files or objects.
 *
 * Every figure is the folder size the file cache keeps at the root of a
 * folder (the number Nextcloud's own quota uses), or a row count the
 * database answers. A read that fails answers empty (or null for the
 * register counts) rather than a guess.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-010-storage-use-per-workspace-and-service-over-time
 */
class StorageFigures {

	/**
	 * The folders of a home storage reported as services, by their path.
	 */
	public const HOME_SERVICES = [
		'files'          => 'files',
		'files_trashbin' => 'trash',
		'files_versions' => 'versions',
	];

	/**
	 * The tables whose rows can name an uploaded launchpad resource.
	 */
	public const REFERENCE_TABLES = [
		'launchpad_dashboards',
		'launchpad_widget_placements',
		'launchpad_tiles',
		'launchpad_admin_settings',
		'launchpad_dash_versions',
		'launchpad_dash_translations',
		'launchpad_kiosk_playlists',
		'launchpad_news',
		'launchpad_role_layout_def',
	];

	/**
	 * Rows read per page while looking for references.
	 */
	private const PAGE = 1000;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * Bytes in each person's files, by user id.
	 *
	 * @return array<string,int>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public function homeUsage(): array {
		$usage = [];
		foreach ($this->homeFolders(paths: ['files']) as $row) {
			$user = self::userOfStorage(storageId: (string) $row['storage_id']);
			if ($user !== null) {
				$usage[$user] = (($usage[$user] ?? 0) + max(0, (int) $row['size']));
			}
		}

		return $usage;
	}//end homeUsage()

	/**
	 * Bytes per service: files, trash and versions over every home, and each app's app data.
	 *
	 * @return array<string,int>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public function serviceUsage(): array {
		$usage = [];
		foreach ($this->homeFolders(paths: array_keys(self::HOME_SERVICES)) as $row) {
			$service = self::HOME_SERVICES[(string) $row['path']] ?? null;
			if ($service !== null && self::userOfStorage(storageId: (string) $row['storage_id']) !== null) {
				$usage[$service] = (($usage[$service] ?? 0) + max(0, (int) $row['size']));
			}
		}

		foreach ($this->select(pathLike: 'appdata_%/%', notLike: 'appdata_%/%/%') as $row) {
			$name = basename((string) $row['path']);
			if ($name !== '') {
				$usage['app:' . $name] = (($usage['app:' . $name] ?? 0) + max(0, (int) $row['size']));
			}
		}

		ksort($usage);
		return $usage;
	}//end serviceUsage()

	/**
	 * The entries at the top of every trash bin: their owner, name (ending in `.d<deleted at>`) and bytes.
	 *
	 * @return list<array{user:string,name:string,bytes:int}>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.3
	 */
	public function trashEntries(): array {
		$entries = [];
		foreach ($this->select(pathLike: 'files_trashbin/files/%', notLike: 'files_trashbin/files/%/%') as $row) {
			$user = self::userOfStorage(storageId: (string) $row['storage_id']);
			if ($user !== null) {
				$entries[] = ['user' => $user, 'name' => (string) $row['name'], 'bytes' => max(0, (int) $row['size'])];
			}
		}

		return $entries;
	}//end trashEntries()

	/**
	 * The files uploaded through launchpad's resource pipeline: name, bytes and last change.
	 *
	 * @return list<array{name:string,bytes:int,mtime:int}>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.3
	 */
	public function uploads(): array {
		$uploads = [];
		foreach ($this->select(pathLike: 'appdata_%/launchpad/resources/%', notLike: 'appdata_%/launchpad/resources/%/%') as $row) {
			$uploads[] = ['name' => (string) $row['name'], 'bytes' => max(0, (int) $row['size']), 'mtime' => (int) ($row['mtime'] ?? 0)];
		}

		return $uploads;
	}//end uploads()

	/**
	 * Every uploaded resource name that some launchpad row still names.
	 *
	 * @return array<string,true>|null The names, or null when a table could not be read (then nothing counts as orphaned).
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.3
	 */
	public function referencedUploads(): ?array {
		$names = [];
		try {
			foreach (self::REFERENCE_TABLES as $table) {
				$offset = 0;
				do {
					$query = $this->db->getQueryBuilder();
					$query->select('*')->from($table)->setFirstResult($offset)->setMaxResults(self::PAGE);
					$result = $query->executeQuery();
					$rows   = $result->fetchAll();
					$result->closeCursor();
					foreach ($rows as $row) {
						$names += self::resourceNames(text: (string) json_encode($row));
					}

					$offset += self::PAGE;
				} while (count($rows) === self::PAGE);
			}
		} catch (Throwable) {
			return null;
		}

		return $names;
	}//end referencedUploads()

	/**
	 * Objects per register, by register title; null without OpenRegister.
	 *
	 * @return array<string,int>|null
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public function registerCounts(): ?array {
		try {
			$query = $this->db->getQueryBuilder();
			$query->select('r.title')
				->selectAlias($query->func()->count('o.id'), 'objects')
				->from('openregister_objects', 'o')
				->innerJoin('o', 'openregister_registers', 'r', $query->expr()->eq('o.register', 'r.id'))
				->groupBy('r.title');
			$result = $query->executeQuery();
			$rows   = $result->fetchAll();
			$result->closeCursor();
		} catch (Throwable) {
			return null;
		}

		$counts = [];
		foreach ($rows as $row) {
			$counts[(string) $row['title']] = (int) $row['objects'];
		}

		ksort($counts);
		return $counts;
	}//end registerCounts()

	/**
	 * The resource file names a text names.
	 *
	 * @param string $text The text, such as a row as JSON.
	 *
	 * @return array<string,true>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.3
	 */
	public static function resourceNames(string $text): array {
		preg_match_all('/resource_[A-Za-z0-9.]+\.[A-Za-z0-9]+/', $text, $found);
		return array_fill_keys($found[0], true);
	}//end resourceNames()

	/**
	 * The user a home storage belongs to, or null for any other storage.
	 *
	 * @param string $storageId The storage id, such as `home::anna` or `object::user:anna`.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	public static function userOfStorage(string $storageId): ?string {
		foreach (['home::', 'object::user:'] as $prefix) {
			if (str_starts_with($storageId, $prefix) === true && strlen($storageId) > strlen($prefix)) {
				return substr($storageId, strlen($prefix));
			}
		}

		return null;
	}//end userOfStorage()

	/**
	 * Home-storage folders at these paths, with their storage id and size.
	 *
	 * @param list<string> $paths The paths, relative to the storage root.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function homeFolders(array $paths): array {
		try {
			$query = $this->db->getQueryBuilder();
			$query->select('f.path', 'f.size', 's.id AS storage_id')
				->from('filecache', 'f')
				->innerJoin('f', 'storages', 's', $query->expr()->eq('f.storage', 's.numeric_id'))
				->where($query->expr()->in('f.path', $query->createNamedParameter($paths, IQueryBuilder::PARAM_STR_ARRAY)));
			$result = $query->executeQuery();
			$rows   = $result->fetchAll();
			$result->closeCursor();
		} catch (Throwable) {
			return [];
		}

		return array_values($rows);
	}//end homeFolders()

	/**
	 * File-cache rows whose path matches one pattern and not another, with their storage id.
	 *
	 * @param string $pathLike The LIKE pattern to match.
	 * @param string $notLike  The LIKE pattern to leave out (deeper paths).
	 *
	 * @return list<array<string,mixed>>
	 */
	private function select(string $pathLike, string $notLike): array {
		try {
			$query = $this->db->getQueryBuilder();
			$query->select('f.path', 'f.name', 'f.size', 'f.mtime', 's.id AS storage_id')
				->from('filecache', 'f')
				->innerJoin('f', 'storages', 's', $query->expr()->eq('f.storage', 's.numeric_id'))
				->where($query->expr()->like('f.path', $query->createNamedParameter($pathLike)))
				->andWhere($query->expr()->notLike('f.path', $query->createNamedParameter($notLike)));
			$result = $query->executeQuery();
			$rows   = $result->fetchAll();
			$result->closeCursor();
		} catch (Throwable) {
			return [];
		}

		return array_values($rows);
	}//end select()
}//end class
