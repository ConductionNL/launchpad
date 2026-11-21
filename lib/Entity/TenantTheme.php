<?php

/**
 * TenantTheme
 *
 * One `tenant_theme` object: the CSS variables, logo and :root-only custom
 * CSS an embed takes from the host's brand. Several tokens may share one.
 * @category  Entity
 * @package   OCA\LaunchPad\Entity
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

namespace OCA\LaunchPad\Entity;


/**
 * A tenant theme record.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
 */
final class TenantTheme {

	/**
	 * Constructor.
	 *
	 * @param string                $id           The object id ('' before the first save).
	 * @param string                $name         The name.
	 * @param array<string, string> $cssVariables Custom property => value.
	 * @param string|null           $logoUrl      The logo, https.
	 * @param string                $customCss    :root-only CSS.
	 * @param string                $createdBy    The admin.
	 * @param string                $createdAt    ISO 8601.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly array $cssVariables,
		public readonly ?string $logoUrl,
		public readonly string $customCss,
		public readonly string $createdBy,
		public readonly string $createdAt,
	) {
	}//end __construct()

	/**
	 * A record from a stored object; non-string variables are dropped.
	 *
	 * @param array<string, mixed> $object The stored object.
	 *
	 * @return self
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public static function fromObject(array $object): self {
		$variables = [];
		foreach ((array)($object['cssVariables'] ?? []) as $name => $value) {
			if (is_string($name) === true && is_scalar($value) === true) {
				$variables[$name] = (string)$value;
			}
		}

		$logo = null;
		if (is_string($object['logoUrl'] ?? null) === true && $object['logoUrl'] !== '') {
			$logo = $object['logoUrl'];
		}

		return new self(
			id: (string)($object['id'] ?? ''),
			name: (string)($object['name'] ?? ''),
			cssVariables: $variables,
			logoUrl: $logo,
			customCss: (string)($object['customCss'] ?? ''),
			createdBy: (string)($object['createdBy'] ?? ''),
			createdAt: (string)($object['createdAt'] ?? ''),
		);
	}//end fromObject()

	/**
	 * The object to store; `id` only once it has one.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function toObject(): array {
		$object = [
			'name'         => $this->name,
			'cssVariables' => $this->cssVariables,
			'logoUrl'      => $this->logoUrl,
			'customCss'    => $this->customCss,
			'createdBy'    => $this->createdBy,
			'createdAt'    => $this->createdAt,
		];
		if ($this->id !== '') {
			$object = ['id' => $this->id] + $object;
		}

		return $object;
	}//end toObject()
}//end class
