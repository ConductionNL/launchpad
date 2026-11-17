<?php

/**
 * ScheduledExportService
 *
 * Create, read, update, delete and enable or disable scheduled exports
 * (Task 3). The objects live in OpenRegister ({@see ScheduledExportStore});
 * who may see or change an export is OpenRegister's object-level access
 * model, applied to the signed-in user (design, Reuse Analysis).
 *
 * Two rules sit on top of plain CRUD:
 *
 * - `nextRunAt` is always computed here from `recurrence` and
 *   `runTimezone` ({@see RecurrenceResolver}), never taken from the caller.
 * - Recipient secrets (SFTP password and key material, a webhook signing
 *   secret) are answered as `********` (REQ-SCH-010). Saving the placeholder
 *   back keeps the stored value, so an edit form round-trips without ever
 *   holding the secret. Only {@see findForRun()} returns them, for the runner
 *   and the dispatcher.
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
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Scheduled-export CRUD with computed next runs and redacted secrets.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 */
class ScheduledExportService {

	public const REDACTED = '********';

	public const DEFAULT_TIMEZONE = 'Europe/Amsterdam';

	/**
	 * The `channelConfig` keys that hold a secret.
	 *
	 * @var list<string>
	 */
	public const SECRET_KEYS = ['password', 'privateKey', 'passphrase', 'signingSecret'];

	/**
	 * Constructor.
	 *
	 * @param ScheduledExportStore $store      OpenRegister reads and writes.
	 * @param RecurrenceResolver   $recurrence Validates rules and computes the next run.
	 */
	public function __construct(
		private readonly ScheduledExportStore $store,
		private readonly RecurrenceResolver $recurrence,
	) {
	}//end __construct()

	/**
	 * Every export the user may see, secrets redacted.
	 *
	 * Visibility is OpenRegister's: the store reads with the signed-in
	 * user's access rules. `$userId` names whose list this is for the
	 * callers; it does not widen what OpenRegister returns.
	 *
	 * @param string $userId The signed-in user.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function findVisible(string $userId): array {
		unset($userId);
		return array_map(
			fn (array $export): array => $this->redact(export: $export),
			$this->store->list(schema: ScheduledExportStore::SCHEMA_EXPORT)
		);
	}//end findVisible()

	/**
	 * One export the user may see, secrets redacted; null when it does not
	 * exist or is not visible to them.
	 *
	 * @param string $id     The export uuid.
	 * @param string $userId The signed-in user.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function findOne(string $id, string $userId): ?array {
		unset($userId);
		$export = $this->store->find(schema: ScheduledExportStore::SCHEMA_EXPORT, uuid: $id);
		if ($export === null) {
			return null;
		}

		return $this->redact(export: $export);
	}//end findOne()

	/**
	 * One export with its secrets, for the runner and the dispatcher only.
	 * Read without a user's access rules: the runner has no session.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function findForRun(string $id): ?array {
		return $this->store->find(schema: ScheduledExportStore::SCHEMA_EXPORT, uuid: $id, rbac: false);
	}//end findForRun()

	/**
	 * Every enabled export whose next run is due, with its secrets, for the
	 * background runner.
	 *
	 * @param DateTimeInterface $now The moment asked about.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function findDue(DateTimeInterface $now): array {
		$due = [];
		foreach ($this->store->list(schema: ScheduledExportStore::SCHEMA_EXPORT, filters: ['enabled' => true], rbac: false) as $export) {
			if (($export['enabled'] ?? true) !== true) {
				continue;
			}

			$next = self::parseTime(value: $export['nextRunAt'] ?? null);
			if ($next !== null && $next <= $now) {
				$due[] = $export;
			}
		}

		return $due;
	}//end findDue()

	/**
	 * Create an export, or update the one with `$id`.
	 *
	 * The recurrence is validated and `nextRunAt` recomputed from it; an
	 * update keeps every property the caller leaves out, and a redacted
	 * secret keeps its stored value.
	 *
	 * @param array<string, mixed> $export The export's properties.
	 * @param string|null          $id     The export to update, null to create.
	 * @param DateTimeInterface    $now    The moment the next run is computed from.
	 *
	 * @return array<string, mixed> The saved export, secrets redacted.
	 *
	 * @throws InvalidArgumentException When a required property is missing, the recurrence is invalid,
	 *                                  or the export to update is not visible.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function save(array $export, ?string $id, DateTimeInterface $now): array {
		$stored = null;
		if ($id !== null) {
			$stored = $this->store->find(schema: ScheduledExportStore::SCHEMA_EXPORT, uuid: $id);
			if ($stored === null) {
				throw new InvalidArgumentException('The scheduled export does not exist.');
			}

			$export = array_replace($stored, $export);
		}

		unset($export['id'], $export['@self'], $export['lastRunAt'], $export['lastRunStatus']);
		if ($stored !== null) {
			foreach (['lastRunAt', 'lastRunStatus'] as $kept) {
				if (array_key_exists($kept, $stored) === true) {
					$export[$kept] = $stored[$kept];
				}
			}
		}

		$problems = $this->problems(export: $export);
		if ($problems !== []) {
			throw new InvalidArgumentException(implode(' ', $problems));
		}

		$export['enabled']     = ($export['enabled'] ?? true) === true;
		$export['runTimezone'] = (string)($export['runTimezone'] ?? self::DEFAULT_TIMEZONE);
		$export['recipients']  = $this->keepSecrets(
			recipients: array_values((array)$export['recipients']),
			stored: array_values((array)($stored['recipients'] ?? []))
		);
		$export['nextRunAt']   = $this->nextRun(export: $export, now: $now);

		$saved = $this->store->save(schema: ScheduledExportStore::SCHEMA_EXPORT, data: $export, uuid: $id);
		return $this->redact(export: $saved);
	}//end save()

	/**
	 * Soft-delete an export the user may see.
	 *
	 * @param string $id     The export uuid.
	 * @param string $userId The signed-in user.
	 *
	 * @return bool False when it does not exist or is not visible.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function delete(string $id, string $userId): bool {
		unset($userId);
		if ($this->store->find(schema: ScheduledExportStore::SCHEMA_EXPORT, uuid: $id) === null) {
			return false;
		}

		return $this->store->delete(schema: ScheduledExportStore::SCHEMA_EXPORT, uuid: $id);
	}//end delete()

	/**
	 * Enable or disable an export. Enabling recomputes the next run from
	 * now, so a schedule that was off does not fire for the windows it
	 * missed; disabling clears it.
	 *
	 * @param string            $id      The export uuid.
	 * @param bool              $enabled Whether it runs.
	 * @param DateTimeInterface $now     The moment of the change.
	 *
	 * @return array<string, mixed>|null The saved export, secrets redacted; null when not visible.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function setEnabled(string $id, bool $enabled, DateTimeInterface $now): ?array {
		$export = $this->store->find(schema: ScheduledExportStore::SCHEMA_EXPORT, uuid: $id);
		if ($export === null) {
			return null;
		}

		$export['enabled']   = $enabled;
		$export['nextRunAt'] = $this->nextRun(export: $export, now: $now);
		unset($export['id'], $export['@self']);

		$saved = $this->store->save(schema: ScheduledExportStore::SCHEMA_EXPORT, data: $export, uuid: $id);
		return $this->redact(export: $saved);
	}//end setEnabled()

	/**
	 * Record a fire on the export: its last run and the next one after it.
	 * Written without a user's access rules, for the runner.
	 *
	 * @param array<string, mixed> $export The export as read for the run.
	 * @param string               $status The run's status.
	 * @param DateTimeInterface    $now    The moment of the fire.
	 *
	 * @return array<string, mixed> The saved export.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function recordFire(array $export, string $status, DateTimeInterface $now): array {
		$id = (string)($export['id'] ?? '');
		$export['lastRunAt']     = DateTimeImmutable::createFromInterface($now)->format(DateTimeInterface::ATOM);
		$export['lastRunStatus'] = $status;
		$export['nextRunAt']     = $this->nextRun(export: $export, now: $now);
		unset($export['id'], $export['@self']);

		return $this->store->save(schema: ScheduledExportStore::SCHEMA_EXPORT, data: $export, uuid: $id, rbac: false);
	}//end recordFire()

	/**
	 * An export with every recipient secret replaced by the placeholder.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
	 */
	public function redact(array $export): array {
		if (is_array($export['recipients'] ?? null) === false) {
			return $export;
		}

		foreach ($export['recipients'] as $index => $recipient) {
			if (is_array($recipient['channelConfig'] ?? null) === false) {
				continue;
			}

			foreach (self::SECRET_KEYS as $key) {
				if (isset($recipient['channelConfig'][$key]) === true && $recipient['channelConfig'][$key] !== '') {
					$export['recipients'][$index]['channelConfig'][$key] = self::REDACTED;
				}
			}
		}

		return $export;
	}//end redact()

	/**
	 * What is wrong with an export, or nothing.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return list<string>
	 */
	private function problems(array $export): array {
		$problems = [];
		if (trim((string)($export['name'] ?? '')) === '') {
			$problems[] = 'An export needs a name.';
		}

		if (in_array($export['subjectType'] ?? null, ['dashboard', 'widget'], true) === false) {
			$problems[] = 'The subject must be a dashboard or a widget.';
		}

		if (trim((string)($export['subjectId'] ?? '')) === '') {
			$problems[] = 'An export needs a subject.';
		}

		if (is_array($export['renderTargets'] ?? null) === false || $export['renderTargets'] === []) {
			$problems[] = 'An export needs at least one render target.';
		}

		if (is_array($export['recipients'] ?? null) === false || $export['recipients'] === []) {
			$problems[] = 'An export needs at least one recipient.';
		}

		if (is_array($export['recurrence'] ?? null) === false) {
			$problems[] = 'An export needs a recurrence.';
			return $problems;
		}

		return array_merge($problems, $this->recurrence->validate(recurrence: $export['recurrence']));
	}//end problems()

	/**
	 * The next run of an enabled export after `$now`, or null when disabled.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param DateTimeInterface    $now    The moment to compute from.
	 *
	 * @return string|null ATOM timestamp.
	 *
	 * @throws InvalidArgumentException When the recurrence or time zone is invalid.
	 */
	private function nextRun(array $export, DateTimeInterface $now): ?string {
		if (($export['enabled'] ?? true) !== true || is_array($export['recurrence'] ?? null) === false) {
			return null;
		}

		return $this->recurrence->nextRunAt(
			recurrence: $export['recurrence'],
			timezone: (string)($export['runTimezone'] ?? self::DEFAULT_TIMEZONE),
			after: $now
		)->format(DateTimeInterface::ATOM);
	}//end nextRun()

	/**
	 * Recipients where every redacted secret is put back from the stored
	 * recipient at the same position on the same channel.
	 *
	 * @param list<mixed> $recipients The recipients as sent.
	 * @param list<mixed> $stored     The recipients as stored.
	 *
	 * @return list<mixed>
	 */
	private function keepSecrets(array $recipients, array $stored): array {
		foreach ($recipients as $index => $recipient) {
			if (is_array($recipient) === false || is_array($recipient['channelConfig'] ?? null) === false) {
				continue;
			}

			$before = [];
			if (is_array($stored[$index] ?? null) === true) {
				$before = $stored[$index];
			}

			$sameTarget = ($before['channel'] ?? null) === ($recipient['channel'] ?? null);
			foreach (self::SECRET_KEYS as $key) {
				if (($recipient['channelConfig'][$key] ?? null) !== self::REDACTED) {
					continue;
				}

				$kept = null;
				if ($sameTarget === true) {
					$kept = $before['channelConfig'][$key] ?? null;
				}

				if ($kept === null) {
					unset($recipients[$index]['channelConfig'][$key]);
					continue;
				}

				$recipients[$index]['channelConfig'][$key] = $kept;
			}
		}//end foreach

		return $recipients;
	}//end keepSecrets()

	/**
	 * An ATOM timestamp as a moment, or null.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return DateTimeImmutable|null
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public static function parseTime(mixed $value): ?DateTimeImmutable {
		if (is_string($value) === false || $value === '') {
			return null;
		}

		$parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
		if ($parsed === false) {
			try {
				return new DateTimeImmutable($value);
			} catch (\Exception) {
				return null;
			}
		}

		return $parsed;
	}//end parseTime()
}//end class
