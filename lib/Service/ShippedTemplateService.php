<?php

/**
 * ShippedTemplateService
 *
 * Lists and installs the admin templates LaunchPad ships with. A shipped
 * template is data: one file per template under `data/templates/<id>.json`,
 * holding a version number and one dashboard in the shape an export writes
 * (`dashboards/<uuid>.json` of a `launchpad-export-v1` archive).
 *
 * WHY IT GOES THROUGH THE IMPORTER. Installing wraps the definition in an
 * export archive and hands it to {@see ImportService}. A shipped template and
 * a file an administrator uploads therefore take one path, and a field the
 * importer drops is dropped for both, where a second builder would hide it.
 *
 * WHAT INSTALLING DOES NOT DO. It adds an admin template. It does not hand it
 * to anybody: which groups get it, and whether it is the default, is the
 * administrator's call, made here through the options or afterwards on the
 * Templates page.
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
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\DashboardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Dashboard\IManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use RuntimeException;
use ZipArchive;

/**
 * List and install the templates shipped in `data/templates`.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
 */
class ShippedTemplateService {
	/**
	 * App config key prefix: `shipped_template_<id>` holds the UUID of the
	 * template the last install created. Empty means not installed.
	 *
	 * @var string
	 */
	public const CONFIG_PREFIX = 'shipped_template_';

	/**
	 * App config key prefix for the version that install carried.
	 *
	 * @var string
	 */
	public const CONFIG_VERSION_PREFIX = 'shipped_template_version_';

	/**
	 * The templates LaunchPad ships, by id. A fixed list rather than a
	 * directory scan, so the admin page and the command show the same
	 * templates in the same order on every instance.
	 *
	 * @var array<int, string>
	 */
	public const SHIPPED_IDS = ['mijn-werkdag'];

	/**
	 * Optional data-directory override (test seam).
	 *
	 * @var string|null
	 */
	private ?string $dataDirOverride = null;

	/**
	 * Constructor.
	 *
	 * @param ImportService        $importService   Reads the archive the definition is wrapped in.
	 * @param AdminTemplateService $templateService Sets the groups and the default flag.
	 * @param DashboardMapper      $dashboardMapper Looks up an earlier install.
	 * @param IAppConfig           $appConfig       Remembers what was installed.
	 * @param IManager             $dashboardMgr    Nextcloud's dashboard widget registry.
	 * @param IGroupManager        $groupManager    Checks that a named group exists.
	 */
	public function __construct(
		private readonly ImportService $importService,
		private readonly AdminTemplateService $templateService,
		private readonly DashboardMapper $dashboardMapper,
		private readonly IAppConfig $appConfig,
		private readonly IManager $dashboardMgr,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * Point the service at another definitions directory (tests only).
	 *
	 * @param string $path Absolute path to a directory of `<id>.json` files.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	public function setDataDirForTesting(string $path): void {
		$this->dataDirOverride = $path;
	}//end setDataDirForTesting()

	/**
	 * Every shipped template, with whether it is installed here.
	 *
	 * @return array<int, array<string, mixed>> One descriptor per template.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	public function listTemplates(): array {
		$result = [];
		foreach (self::SHIPPED_IDS as $id) {
			$definition = $this->readDefinition(templateId: $id);
			$dashboard = $definition['dashboard'];
			$installedUuid = $this->findInstalledUuid(templateId: $id);

			$installedVersion = null;
			if ($installedUuid !== '') {
				$installedVersion = $this->appConfig->getValueInt(
					Application::APP_ID,
					self::CONFIG_VERSION_PREFIX . $id,
					0
				);
			}

			$result[] = [
				'id' => $id,
				'name' => (string)$dashboard['name'],
				'description' => (string)($dashboard['description'] ?? ''),
				'language' => (string)($definition['language'] ?? 'en'),
				'version' => (int)$definition['templateVersion'],
				'widgetCount' => count($dashboard['widgets']),
				'isInstalled' => $installedUuid !== '',
				'installedUuid' => $installedUuid,
				'installedVersion' => $installedVersion,
				'missingWidgets' => $this->findMissingWidgets(widgets: $dashboard['widgets']),
			];
		}//end foreach

		return $result;
	}//end listTemplates()

	/**
	 * Install a shipped template as an admin template.
	 *
	 * Already installed and not forced: nothing is added, but the groups and
	 * the default flag are still applied, so a deploy script can run twice.
	 * Forced: a fresh copy is added and becomes the recorded install. The
	 * earlier one stays, because users' dashboards made from it point at it.
	 *
	 * @param string             $templateId   The shipped template id.
	 * @param array<int, string> $targetGroups Groups whose members get the template.
	 *                                         Empty leaves the definition's groups.
	 * @param bool               $makeDefault  Make it the default template for everyone.
	 * @param bool               $force        Add a fresh copy even when installed.
	 * @param string             $userId       Who runs the install (for the archive).
	 *
	 * @return array{templateId:string, uuid:string, id:int, version:int, alreadyInstalled:bool,
	 *               targetGroups:array<int,string>, isDefault:bool, missingWidgets:array<int,string>}
	 *
	 * @throws InvalidArgumentException When the id or a group is unknown.
	 * @throws RuntimeException When the definition or the import fails.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	public function install(
		string $templateId,
		array $targetGroups = [],
		bool $makeDefault = false,
		bool $force = false,
		string $userId = 'cli',
	): array {
		$definition = $this->readDefinition(templateId: $templateId);

		// A mistyped group would target nobody and still report success.
		foreach ($targetGroups as $group) {
			if ($this->groupManager->groupExists(gid: $group) === false) {
				throw new InvalidArgumentException(message: 'Unknown group: ' . $group);
			}
		}

		$alreadyInstalled = false;
		$uuid = $this->findInstalledUuid(templateId: $templateId);
		if ($uuid !== '' && $force === false) {
			$alreadyInstalled = true;
			$dashboardId = (int)$this->dashboardMapper->findByUuid(uuid: $uuid)->getId();
		}

		if ($alreadyInstalled === false) {
			$created = $this->importDefinition(definition: $definition, userId: $userId);
			$uuid = $created['uuid'];
			$dashboardId = $created['id'];
			$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_PREFIX . $templateId, $uuid);
			$this->appConfig->setValueInt(
				Application::APP_ID,
				self::CONFIG_VERSION_PREFIX . $templateId,
				(int)$definition['templateVersion']
			);
		}

		$update = [];
		if ($targetGroups !== []) {
			$update['targetGroups'] = array_values(array: $targetGroups);
		}

		if ($makeDefault === true) {
			$update['isDefault'] = true;
		}

		$template = $this->dashboardMapper->find(id: $dashboardId);
		if ($update !== []) {
			$template = $this->templateService->updateTemplate(id: $dashboardId, data: $update);
		}

		return [
			'templateId' => $templateId,
			'uuid' => $uuid,
			'id' => $dashboardId,
			'version' => (int)$definition['templateVersion'],
			'alreadyInstalled' => $alreadyInstalled,
			'targetGroups' => $template->getTargetGroupsArray(),
			'isDefault' => $template->getIsDefault() === 1,
			'missingWidgets' => $this->findMissingWidgets(widgets: $definition['dashboard']['widgets']),
		];
	}//end install()

	/**
	 * Read and check one shipped definition.
	 *
	 * @param string $templateId The shipped template id.
	 *
	 * @return array{templateVersion:int, dashboard:array<string, mixed>, language?:string}
	 *
	 * @throws InvalidArgumentException When the id is not a shipped template.
	 * @throws RuntimeException When the file is missing or malformed.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	public function readDefinition(string $templateId): array {
		if (in_array(needle: $templateId, haystack: self::SHIPPED_IDS, strict: true) === false) {
			throw new InvalidArgumentException(
				message: 'Unknown template: ' . $templateId
					. '. Shipped templates: ' . implode(separator: ', ', array: self::SHIPPED_IDS)
			);
		}

		$dir = ($this->dataDirOverride ?? dirname(path: __DIR__, levels: 2) . '/data/templates');
		$path = $dir . '/' . $templateId . '.json';

		$decoded = null;
		if (is_readable(filename: $path) === true) {
			$decoded = json_decode(json: (string)file_get_contents(filename: $path), associative: true);
		}

		// A packaging error must be loud: an empty definition would install
		// an empty template and report success.
		if (is_array($decoded) === false
			|| is_int($decoded['templateVersion'] ?? null) === false
			|| is_array($decoded['dashboard'] ?? null) === false
			|| is_string($decoded['dashboard']['uuid'] ?? null) === false
			|| is_string($decoded['dashboard']['name'] ?? null) === false
			|| is_array($decoded['dashboard']['widgets'] ?? null) === false
			|| $decoded['dashboard']['widgets'] === []
		) {
			throw new RuntimeException(message: 'Template definition missing or malformed: ' . $path);
		}

		return $decoded;
	}//end readDefinition()

	/**
	 * The UUID of the installed copy, or '' when there is none.
	 *
	 * A recorded UUID whose template was deleted counts as not installed, so
	 * the template can be added again without `--force`.
	 *
	 * @param string $templateId The shipped template id.
	 *
	 * @return string The UUID, or an empty string.
	 */
	private function findInstalledUuid(string $templateId): string {
		$uuid = $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_PREFIX . $templateId, '');
		if ($uuid === '') {
			return '';
		}

		try {
			$this->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return '';
		}

		return $uuid;
	}//end findInstalledUuid()

	/**
	 * The Nextcloud dashboard widgets the template shows that no app on this
	 * instance registers. Such a widget is still placed, so the template has
	 * one shape everywhere; the list tells the administrator which app is
	 * missing.
	 *
	 * @param array<int, mixed> $widgets The definition's widgets.
	 *
	 * @return array<int, string> Widget ids nothing here registers.
	 */
	private function findMissingWidgets(array $widgets): array {
		$registered = [];
		foreach ($this->dashboardMgr->getWidgets() as $widget) {
			$registered[$widget->getId()] = true;
		}

		$missing = [];
		foreach ($widgets as $widget) {
			if (is_array($widget) === false || ($widget['widgetId'] ?? '') !== 'nc-widget') {
				continue;
			}

			$proxied = (string)($widget['content']['widgetId'] ?? '');
			if ($proxied !== '' && isset($registered[$proxied]) === false) {
				$missing[] = $proxied;
			}
		}

		return $missing;
	}//end findMissingWidgets()

	/**
	 * Wrap the definition in an export archive and import it.
	 *
	 * @param array<string, mixed> $definition The shipped definition.
	 * @param string               $userId     Who runs the install.
	 *
	 * @return array{sourceUuid:string, uuid:string, id:int} The template created.
	 *
	 * @throws RuntimeException When the archive cannot be built or the import skips it.
	 */
	private function importDefinition(array $definition, string $userId): array {
		$zipPath = $this->buildArchive(dashboard: $definition['dashboard']);

		try {
			$result = $this->importService->import(
				zipPath: $zipPath,
				preserveUuids: false,
				currentUserId: $userId
			);
		} finally {
			if (file_exists(filename: $zipPath) === true) {
				unlink(filename: $zipPath);
			}
		}

		if ($result['dashboards'] === []) {
			$reason = (string)($result['errors'][0]['message'] ?? 'the import created nothing');
			throw new RuntimeException(message: 'Template not installed: ' . $reason);
		}

		return $result['dashboards'][0];
	}//end importDefinition()

	/**
	 * Write a one-dashboard `launchpad-export-v1` archive to a temp file.
	 *
	 * @param array<string, mixed> $dashboard The dashboard payload.
	 *
	 * @return string Path of the archive.
	 *
	 * @throws RuntimeException When the archive cannot be written.
	 */
	private function buildArchive(array $dashboard): string {
		$zipPath = tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-template-');
		$zip = new ZipArchive();
		if ($zipPath === false || $zip->open(filename: $zipPath, flags: ZipArchive::OVERWRITE) !== true) {
			throw new RuntimeException(message: 'Could not write the template archive.');
		}

		$flags = (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$zip->addFromString(
			name: 'manifest.json',
			content: (string)json_encode(
				value: ['schemaVersion' => 1, 'scope' => 'dashboard', 'dashboardCount' => 1],
				flags: $flags
			)
		);
		$zip->addFromString(
			name: 'dashboards/' . $dashboard['uuid'] . '.json',
			content: (string)json_encode(value: $dashboard, flags: $flags)
		);
		$zip->close();

		return $zipPath;
	}//end buildArchive()
}//end class
