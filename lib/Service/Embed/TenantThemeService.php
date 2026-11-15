<?php

/**
 * TenantThemeService
 *
 * Tenant themes (REQ-EMB-005, REQ-EMB-010 10.2): validated before anything
 * is stored — custom-property names and values, :root-only custom CSS, an
 * https logo, and WCAG AA contrast (4.5:1) of the primary and text colours
 * on the surface — and turned into the :root block an embed injects.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

use OCA\LaunchPad\Entity\TenantTheme;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Creates, updates and renders tenant themes.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
 */
class TenantThemeService {

	/**
	 * The smallest contrast ratio accepted for text (WCAG 2.1 AA).
	 *
	 * @var float
	 */
	public const MIN_CONTRAST = 4.5;

	/**
	 * The colours checked against `--c-surface`, with how the message names them.
	 *
	 * @var array<string, string>
	 */
	private const CONTRAST_PAIRS = ['--c-primary' => 'Primary', '--c-text' => 'Text'];

	/**
	 * Constructor.
	 *
	 * @param EmbedObjectStore     $store     The register objects.
	 * @param TenantThemeValidator $validator CSS and contrast checks.
	 * @param ITimeFactory         $time      The clock.
	 */
	public function __construct(
		private readonly EmbedObjectStore $store,
		private readonly TenantThemeValidator $validator,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * Create a theme.
	 *
	 * @param array<string, mixed> $input  {name, cssVariables, logoUrl?, customCss?}.
	 * @param string               $userId The admin.
	 *
	 * @return array<string, mixed> The stored theme, or a refusal {status: error, error, message, fieldErrors}.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	public function createTheme(array $input, string $userId): array {
		$refusal = $this->refusalFor(input: $input);
		if ($refusal !== null) {
			return $refusal;
		}

		$theme = TenantTheme::fromObject(
			object: [
				'createdBy' => $userId,
				'createdAt' => gmdate('Y-m-d\TH:i:s\Z', $this->time->getTime()),
			] + self::fields(input: $input)
		);

		return $this->store->save(schema: EmbedObjectStore::THEMES, object: $theme->toObject());
	}//end createTheme()

	/**
	 * Update a theme; every token using it follows at once (REQ-EMB-005 5.7).
	 *
	 * @param string               $id    The theme id.
	 * @param array<string, mixed> $input The full new theme.
	 *
	 * @return array<string, mixed>|null The stored theme, a refusal, or null when there is no such theme.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	public function updateTheme(string $id, array $input): ?array {
		$current = $this->getTheme(id: $id);
		if ($current === null) {
			return null;
		}

		$refusal = $this->refusalFor(input: $input);
		if ($refusal !== null) {
			return $refusal;
		}

		$theme = TenantTheme::fromObject(
			object: ['id' => $current->id, 'createdBy' => $current->createdBy, 'createdAt' => $current->createdAt] + self::fields(input: $input)
		);

		return $this->store->save(schema: EmbedObjectStore::THEMES, object: $theme->toObject());
	}//end updateTheme()

	/**
	 * A theme, or null.
	 *
	 * @param string $id The theme id.
	 *
	 * @return TenantTheme|null
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-11
	 */
	public function getTheme(string $id): ?TenantTheme {
		$object = $this->store->find(schema: EmbedObjectStore::THEMES, id: $id);
		if ($object === null) {
			return null;
		}

		return TenantTheme::fromObject(object: $object);
	}//end getTheme()

	/**
	 * Every theme, with how many tokens use it (to warn before deleting).
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-11
	 */
	public function listThemes(): array {
		$usage = [];
		foreach ($this->store->findBy(schema: EmbedObjectStore::TOKENS) as $token) {
			$themeId = (string)($token['tenantThemeId'] ?? '');
			if ($themeId !== '') {
				$usage[$themeId] = (($usage[$themeId] ?? 0) + 1);
			}
		}

		$themes = [];
		foreach ($this->store->findBy(schema: EmbedObjectStore::THEMES) as $object) {
			$theme    = TenantTheme::fromObject(object: $object);
			$themes[] = $theme->toObject() + ['id' => $theme->id, 'tokenCount' => ($usage[$theme->id] ?? 0)];
		}

		return $themes;
	}//end listThemes()

	/**
	 * The CSS an embed injects for a theme: its variables and custom CSS on
	 * :root; '' without a theme, so the platform default applies (5.4).
	 *
	 * @param string|null $themeId The token's theme.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	public function rootCss(?string $themeId): string {
		if ($themeId === null || $themeId === '') {
			return '';
		}

		$theme = $this->getTheme(id: $themeId);
		if ($theme === null) {
			return '';
		}

		$css = $this->validator->rootBlock(cssVariables: $theme->cssVariables);
		if ($theme->customCss !== '' && $this->validator->validateCss(customCss: $theme->customCss) === null) {
			$css = trim($css . "\n" . trim($theme->customCss));
		}

		return $css;
	}//end rootCss()

	/**
	 * Why a theme is refused, or null.
	 *
	 * @param array<string, mixed> $input The theme.
	 *
	 * @return array<string, mixed>|null
	 */
	private function refusalFor(array $input): ?array {
		if (trim((string)($input['name'] ?? '')) === '') {
			return self::refusal(error: 'invalid_name', message: 'Give the theme a name.', field: 'name', code: 'invalid_name');
		}

		$variables = ($input['cssVariables'] ?? []);
		if (is_array($variables) === false) {
			return self::refusal(
				error: 'invalid_cssVariables',
				message: 'CSS variables are an object of --name: value.',
				field: 'cssVariables',
				code: 'invalid_cssVariables'
			);
		}

		foreach ($variables as $name => $value) {
			if (is_string($name) === false || preg_match('~^--[a-zA-Z0-9_-]+$~', $name) !== 1
				|| is_scalar($value) === false || preg_match('~[;{}<>\\\\]~', (string)$value) === 1
			) {
				return self::refusal(
					error: 'invalid_cssVariables',
					message: 'A CSS variable is named --name and its value holds no ; { } < > or \\.',
					field: 'cssVariables.' . (string)$name,
					code: 'invalid_cssVariables'
				);
			}
		}

		$css = $this->validator->validateCss(customCss: (string)($input['customCss'] ?? ''));
		if ($css !== null) {
			return self::refusal(error: 'invalid_customCss', message: (string)$css['message'], field: 'customCss', code: 'non_root_selector');
		}

		$logo = ($input['logoUrl'] ?? null);
		if ($logo !== null && $logo !== '' && (is_string($logo) === false || preg_match('~^https://[^\s"\'<>]+$~', $logo) !== 1)) {
			return self::refusal(error: 'invalid_logoUrl', message: 'The logo is an https URL.', field: 'logoUrl', code: 'invalid_logoUrl');
		}

		return $this->contrastRefusal(variables: $variables);
	}//end refusalFor()

	/**
	 * Why the colours fail WCAG AA on the surface, or null.
	 *
	 * @param array<string, mixed> $variables The CSS variables.
	 *
	 * @return array<string, mixed>|null
	 */
	private function contrastRefusal(array $variables): ?array {
		$surface = (string)($variables['--c-surface'] ?? '');
		if ($surface === '') {
			return null;
		}

		foreach (self::CONTRAST_PAIRS as $name => $label) {
			$colour = (string)($variables[$name] ?? '');
			$ratio  = $this->validator->contrastRatio(foreground: $colour, background: $surface);
			if ($ratio === null || $ratio >= self::MIN_CONTRAST) {
				continue;
			}

			// Rounded down, so a ratio just under 4.5 never reads as 4.5.
			$shown = number_format((floor($ratio * 10) / 10), 1);

			return self::refusal(
				error: 'insufficient_contrast',
				message: sprintf('%s colour %s against surface %s has contrast ratio %s:1. WCAG AA requires 4.5:1 for text.', $label, $colour, $surface, $shown),
				field: 'cssVariables.' . $name,
				code: 'insufficient_contrast'
			);
		}

		return null;
	}//end contrastRefusal()

	/**
	 * The stored fields of a validated theme.
	 *
	 * @param array<string, mixed> $input The theme.
	 *
	 * @return array<string, mixed>
	 */
	private static function fields(array $input): array {
		return [
			'name'         => trim((string)$input['name']),
			'cssVariables' => (array)($input['cssVariables'] ?? []),
			'logoUrl'      => ($input['logoUrl'] ?? null),
			'customCss'    => trim((string)($input['customCss'] ?? '')),
		];
	}//end fields()

	/**
	 * A refusal in the REQ-EMB-005 5.3 shape.
	 *
	 * @param string $error   The error code.
	 * @param string $message The message.
	 * @param string $field   The field at fault.
	 * @param string $code    The field's error code.
	 *
	 * @return array<string, mixed>
	 */
	private static function refusal(string $error, string $message, string $field, string $code): array {
		return ['status' => 'error', 'error' => $error, 'message' => $message, 'fieldErrors' => [$field => $code]];
	}//end refusal()
}//end class
