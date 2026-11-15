<?php

/**
 * EvaluationError
 *
 * A calculated field that cannot be evaluated on a row: a division by zero,
 * a value of the wrong kind, an invalid date. The message is the same one the
 * client-side evaluator throws, so both sides report a failure alike.
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
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use RuntimeException;

/**
 * A run-time failure of a calculated field.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-005-identical-results-between-evaluators
 */
class EvaluationError extends RuntimeException {
}//end class
