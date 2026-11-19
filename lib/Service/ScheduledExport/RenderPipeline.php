<?php

/**
 * RenderPipeline
 *
 * One render of an export for one filter context (tasks.md task 4,
 * REQ-SCH-003): one artefact per render target, PDF and PNG from the page,
 * CSV and XLSX from the subject's tables. A fire's artefacts are written to
 * the export creator's Files under `Apps/LaunchPad/Exports/{exportId}/`
 * (design D3); a preview returns them without writing.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use DateTimeInterface;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use Throwable;

/**
 * Renders the targets of an export.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-4
 */
class RenderPipeline {

	public const EXPORT_FOLDER = 'Apps/LaunchPad/Exports';

	/**
	 * Constructor.
	 *
	 * @param SubjectTables    $tables  The subject's tabular data.
	 * @param TabularWriter    $writer  CSV and XLSX.
	 * @param HeadlessRenderer $browser PDF and PNG.
	 * @param RenderServices   $services Files, URLs and the clock.
	 */
	public function __construct(
		private readonly SubjectTables $tables,
		private readonly TabularWriter $writer,
		private readonly HeadlessRenderer $browser,
		private readonly RenderServices $services,
	) {
	}//end __construct()

	/**
	 * Render every target and store the artefacts in the creator's Files.
	 *
	 * @param array<string, mixed> $export        The export.
	 * @param array<string, mixed> $filterContext The filter context of this render.
	 *
	 * @return list<RenderResult>
	 *
	 * @throws RenderException When a target cannot be rendered or stored.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-4
	 */
	public function render(array $export, array $filterContext): array {
		$folder = $this->folder(export: $export);

		return array_map(function (RenderResult $result) use ($folder): RenderResult {
			try {
				$file = $folder->newFile($this->freeName(folder: $folder, name: $result->filename), $result->content);
			} catch (Throwable) {
				throw new RenderException('The ' . strtoupper($result->format) . ' could not be stored in Files');
			}

			return new RenderResult(format: $result->format, filename: $file->getName(), content: $result->content, storagePath: $file->getPath());
		}, $this->preview(export: $export, filterContext: $filterContext));
	}//end render()

	/**
	 * Render every target without storing anything (REQ-SCH-009 Preview).
	 *
	 * @param array<string, mixed> $export        The export.
	 * @param array<string, mixed> $filterContext The filter context of this render.
	 *
	 * @return list<RenderResult>
	 *
	 * @throws RenderException When a target cannot be rendered.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
	 */
	public function preview(array $export, array $filterContext): array {
		$now     = $this->services->time->now();
		$tables  = null;
		$results = [];
		foreach ((array)($export['renderTargets'] ?? []) as $target) {
			$format  = (string)($target['format'] ?? '');
			$options = (array)($target['formatOptions'] ?? []);
			if (in_array($format, ['csv', 'xlsx'], true) === true) {
				$tables ??= $this->tables->tables(export: $export, filterContext: $filterContext);
			}

			$content = match ($format) {
				'csv' => $this->writer->csv(tables: $tables, options: $options),
				'xlsx' => $this->writer->xlsx(tables: $tables, cover: [
					'name' => (string)($export['name'] ?? ''),
					'runAt' => $now->format(DateTimeInterface::ATOM),
					'sheetPerWidget' => ($options['sheetPerWidget'] ?? true) !== false,
				]),
				'pdf', 'png' => $this->browser->capture(url: $this->pageUrl(export: $export, filterContext: $filterContext), format: $format, options: $options),
				default => throw new RenderException('Unknown render format: ' . $format),
			};

			$name      = $this->filename(pattern: (string)($target['filenamePattern'] ?? '{name}-{date}.{ext}'), export: $export, format: $format);
			$results[] = new RenderResult(format: $format, filename: $name, content: $content);
		}//end foreach

		return $results;
	}//end preview()

	/**
	 * A file name from a target's pattern: `{name}`, `{date}`, `{recipient}`
	 * and `{ext}`, with path separators and control characters removed.
	 *
	 * @param string               $pattern The pattern.
	 * @param array<string, mixed> $export  The export.
	 * @param string               $format  The format, its extension.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-4
	 */
	public function filename(string $pattern, array $export, string $format): string {
		$name = strtr($pattern, [
			'{name}' => (string)($export['name'] ?? 'export'),
			'{date}' => $this->services->time->now()->format('Y-m-d'),
			'{recipient}' => (string)($export['recipientLabel'] ?? ''),
			'{ext}' => $format,
		]);
		$name = trim((string)preg_replace('/[\/\\\\\x00-\x1F]+/', '-', $name), " .-");
		if ($name === '') {
			$name = 'export';
		}

		if (str_ends_with(strtolower($name), '.' . $format) === false) {
			$name .= '.' . $format;
		}

		return $name;
	}//end filename()

	/**
	 * The page the browser prints: the dashboard, with the filters as a query.
	 *
	 * @param array<string, mixed> $export        The export.
	 * @param array<string, mixed> $filterContext The filters.
	 *
	 * @return string
	 */
	private function pageUrl(array $export, array $filterContext): string {
		$query = ['export' => '1', 'subject' => (string)($export['subjectType'] ?? ''), 'id' => (string)($export['subjectId'] ?? '')];
		if ($filterContext !== []) {
			$query['filters'] = (string)json_encode($filterContext);
		}

		return $this->services->urls->linkToRouteAbsolute('launchpad.page.index') . '?' . http_build_query($query);
	}//end pageUrl()

	/**
	 * The export's folder in its creator's Files, created when missing.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return Folder
	 *
	 * @throws RenderException When the creator has no Files area.
	 */
	private function folder(array $export): Folder {
		try {
			$folder = $this->services->rootFolder->getUserFolder((string)($export['createdBy'] ?? ''));
			foreach (explode('/', self::EXPORT_FOLDER . '/' . (string)($export['id'] ?? 'unknown')) as $part) {
				try {
					$next = $folder->get($part);
				} catch (NotFoundException) {
					$next = $folder->newFolder($part);
				}

				if (($next instanceof Folder) === false) {
					throw new RenderException('The export folder is not a folder');
				}

				$folder = $next;
			}
		} catch (RenderException $e) {
			throw $e;
		} catch (Throwable) {
			throw new RenderException('The export folder could not be opened in Files');
		}

		return $folder;
	}//end folder()

	/**
	 * The name itself, or `name (2).ext` and onward when it is taken: a fire never overwrites.
	 *
	 * @param Folder $folder The folder.
	 * @param string $name   The wanted name.
	 *
	 * @return string
	 */
	private function freeName(Folder $folder, string $name): string {
		$dot  = strrpos($name, '.');
		$stem = $name;
		$ext  = '';
		if ($dot !== false) {
			$stem = substr($name, 0, $dot);
			$ext  = substr($name, $dot);
		}

		$try  = $name;
		for ($n = 2; $folder->nodeExists($try) === true; $n++) {
			$try = $stem . ' (' . $n . ')' . $ext;
		}

		return $try;
	}//end freeName()
}//end class
