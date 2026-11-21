<?php

/**
 * CalculatedFieldValidationError
 *
 * A calculated field that cannot be saved: its compiled expression is not a
 * well-formed AST, calls an unknown or disallowed function with the wrong
 * number of arguments, or does not type-check. Carries the 400 envelope
 * `{error, message, ...details}` the client validator produces for the
 * same expression (REQ-CALC-001, REQ-CALC-002, REQ-CALC-009).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\CalculatedFields
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use RuntimeException;

/**
 * A calculated field refused at save time, with its error envelope.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */
class CalculatedFieldValidationError extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $detail The envelope: `error`, `message` and details.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public function __construct(
		private readonly array $detail,
	) {
		parent::__construct(message: (string) ($detail['message'] ?? 'Invalid calculated field'));
	}//end __construct()

	/**
	 * The same error, naming the field it was found in (when it names none yet).
	 *
	 * @param string $field The calculated field's name.
	 *
	 * @return self The error with `field` set.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public function forField(string $field): self {
		if (array_key_exists('field', $this->detail) === true) {
			return $this;
		}

		return new self(detail: array_merge($this->detail, ['field' => $field]));
	}//end forField()

	/**
	 * The error envelope.
	 *
	 * @return array<string,mixed> The detail, `error` and `message` first.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
	 */
	public function getDetail(): array {
		return $this->detail;
	}//end getDetail()
}//end class
