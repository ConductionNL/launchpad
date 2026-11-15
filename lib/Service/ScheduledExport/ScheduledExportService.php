<?php

/**
 * ScheduledExportService
 *
 * CRUD of scheduled exports (tasks.md task 3) over the `scheduled_export`
 * schema in OpenRegister: who may see an export (its owner, or an
 * administrator), validation of what is saved, `nextRunAt` recomputed
 * whenever the recurrence or time zone changes (REQ-SCH-001), and channel
 * secrets encrypted at rest and redacted on every read (REQ-SCH-010).
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
use DateTimeZone;
use InvalidArgumentException;
use OCA\LaunchPad\Service\RecurrenceResolver;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\Security\ICrypto;
use Throwable;

/**
 * Visibility, validation, scheduling and secret handling of exports.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Validation and secret sealing of one export document.
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)     save() merges, validates, seals and reschedules in one pass.
 */
class ScheduledExportService {

	/**
	 * What a secret reads as through the API.
	 */
	public const REDACTED = '********';

	/**
	 * The `channelConfig` keys that hold a secret.
	 */
	public const SECRET_KEYS = ['password', 'privateKey', 'signingSecret'];

	public const DEFAULT_TIMEZONE = 'Europe/Amsterdam';

	private const ENCRYPTED_PREFIX = 'enc:';

	private const FORMATS = ['pdf', 'png', 'csv', 'xlsx'];

	private const CHANNELS = ['email', 'nextcloudFiles', 'webhook', 'sftp'];

	/**
	 * Fields the runner owns; a save by a user never sets them.
	 */
	private const RUNNER_FIELDS = ['nextRunAt', 'lastRunAt', 'lastRunStatus', 'createdBy'];

	/**
	 * Constructor.
	 *
	 * @param ExportObjectStore  $store      The OpenRegister object store.
	 * @param RecurrenceResolver $recurrence Computes `nextRunAt`.
	 * @param ICrypto            $crypto     Encrypts channel secrets at rest.
	 * @param IGroupManager      $groups     Answers whether a user is an administrator.
	 * @param ITimeFactory       $time       The clock.
	 */
	public function __construct(
		private readonly ExportObjectStore $store,
		private readonly RecurrenceResolver $recurrence,
		private readonly ICrypto $crypto,
		private readonly IGroupManager $groups,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * The exports a user may see: their own, or every export for an administrator.
	 *
	 * @param string $userId The user.
	 *
	 * @return list<array<string, mixed>> The exports, secrets redacted.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function findVisible(string $userId): array {
		$filters = [];
		if ($this->groups->isAdmin($userId) === false) {
			$filters['@self'] = ['owner' => $userId];
		}

		$exports = $this->store->findAll(schema: ExportObjectStore::SCHEMA_EXPORT, filters: $filters);
		$visible = array_filter($exports, fn (array $export): bool => $this->isVisibleTo(export: $export, userId: $userId));

		return array_values(array_map(fn (array $export): array => $this->redact(export: $export), $visible));
	}//end findVisible()

	/**
	 * One export the user may see.
	 *
	 * @param string $id     The export uuid.
	 * @param string $userId The user.
	 *
	 * @return array<string, mixed> The export, secrets redacted.
	 *
	 * @throws DoesNotExistException When it does not exist or is not the user's to see.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function findOne(string $id, string $userId): array {
		return $this->redact(export: $this->stored(id: $id, userId: $userId));
	}//end findOne()

	/**
	 * Create an export, or update one the user may see. A partial update is
	 * merged onto the stored export; a secret sent back as the redacted
	 * placeholder keeps the stored secret.
	 *
	 * @param array<string, mixed> $export The export; an `id` updates that export.
	 * @param string               $userId The user saving it.
	 *
	 * @return array<string, mixed> The saved export, secrets redacted.
	 *
	 * @throws DoesNotExistException    When the `id` is not an export the user may see.
	 * @throws InvalidArgumentException When the export is not valid.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function save(array $export, string $userId): array {
		$existing = null;
		if (is_string($export['id'] ?? null) === true && $export['id'] !== '') {
			$existing = $this->stored(id: $export['id'], userId: $userId);
		}

		foreach (self::RUNNER_FIELDS as $field) {
			unset($export[$field]);
		}

		$merged = self::withDefaults(export: array_merge($existing ?? [], $export));
		$merged['createdBy'] = $existing['createdBy'] ?? $userId;

		$errors = $this->validate(export: $merged);
		if ($errors !== []) {
			throw new InvalidArgumentException(implode(' ', $errors));
		}

		$merged['recipients'] = $this->sealSecrets(recipients: $merged['recipients'], stored: $existing['recipients'] ?? []);
		if (self::needsReschedule(existing: $existing, merged: $merged) === true) {
			$merged['nextRunAt'] = $this->nextRunAfter(export: $merged, after: $this->time->now());
		}

		return $this->redact(export: $this->store->save(schema: ExportObjectStore::SCHEMA_EXPORT, data: $merged));
	}//end save()

	/**
	 * The export with `enabled` defaulting to true and the run time zone to the default.
	 *
	 * @param array<string, mixed> $export The export being saved.
	 *
	 * @return array<string, mixed>
	 */
	private static function withDefaults(array $export): array {
		$export['enabled'] = ($export['enabled'] ?? true) !== false;
		if (is_string($export['runTimezone'] ?? null) === false || $export['runTimezone'] === '') {
			$export['runTimezone'] = self::DEFAULT_TIMEZONE;
		}

		return $export;
	}//end withDefaults()

	/**
	 * Whether the next run must be computed again: a new export, a changed
	 * recurrence or time zone, or no next run yet.
	 *
	 * @param array<string, mixed>|null $existing The stored export, null for a new one.
	 * @param array<string, mixed>      $merged   The export being saved.
	 *
	 * @return bool
	 */
	private static function needsReschedule(?array $existing, array $merged): bool {
		if ($existing === null || empty($existing['nextRunAt']) === true) {
			return true;
		}

		return ($existing['recurrence'] ?? null) !== $merged['recurrence']
			|| ($existing['runTimezone'] ?? null) !== $merged['runTimezone'];
	}//end needsReschedule()

	/**
	 * Soft-delete an export the user may see.
	 *
	 * @param string $id     The export uuid.
	 * @param string $userId The user.
	 *
	 * @return void
	 *
	 * @throws DoesNotExistException When it is not an export the user may see.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function delete(string $id, string $userId): void {
		$this->stored(id: $id, userId: $userId);
		$this->store->delete(uuid: $id);
	}//end delete()

	/**
	 * Switch an export on or off.
	 *
	 * @param string $id      The export uuid.
	 * @param bool   $enabled Whether it fires on schedule.
	 * @param string $userId  The user.
	 *
	 * @return array<string, mixed> The saved export.
	 *
	 * @throws DoesNotExistException When it is not an export the user may see.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function setEnabled(string $id, bool $enabled, string $userId): array {
		return $this->save(export: ['id' => $id, 'enabled' => $enabled], userId: $userId);
	}//end setEnabled()

	/**
	 * The stored export with its secrets decrypted, for the dispatcher only.
	 *
	 * @param string $id The export uuid.
	 *
	 * @return array<string, mixed>|null The export, or null when it does not exist.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
	 */
	public function forDispatch(string $id): ?array {
		$export = $this->store->find(schema: ExportObjectStore::SCHEMA_EXPORT, uuid: $id);
		if ($export === null) {
			return null;
		}

		$reveal = fn (string $value): string => $this->reveal(value: $value);
		$export['recipients'] = array_map(fn ($recipient) => $this->mapSecrets(recipient: $recipient, map: $reveal), $export['recipients'] ?? []);
		return $export;
	}//end forDispatch()

	/**
	 * The enabled exports whose `nextRunAt` has passed.
	 *
	 * @param DateTimeInterface $now The moment of the check.
	 *
	 * @return list<array<string, mixed>> The due exports, as stored.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function due(DateTimeInterface $now): array {
		$exports = $this->store->findAll(schema: ExportObjectStore::SCHEMA_EXPORT, filters: ['enabled' => true]);

		return array_values(array_filter($exports, static function (array $export) use ($now): bool {
			$next = false;
			if (is_string($export['nextRunAt'] ?? null) === true) {
				$next = strtotime($export['nextRunAt']);
			}

			return ($export['enabled'] ?? true) !== false && $next !== false && $next <= $now->getTimestamp();
		}));
	}//end due()

	/**
	 * Record a fire on the export: its outcome and, for a scheduled fire, the next run.
	 *
	 * @param array<string, mixed> $export The export as stored.
	 * @param array<string, mixed> $fields `lastRunAt`, `lastRunStatus` and optionally `nextRunAt`.
	 *
	 * @return array<string, mixed> The saved export.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function recordFire(array $export, array $fields): array {
		$stored = $this->store->find(schema: ExportObjectStore::SCHEMA_EXPORT, uuid: (string)($export['id'] ?? '')) ?? $export;
		$keep   = array_intersect_key($fields, array_flip(['lastRunAt', 'lastRunStatus', 'nextRunAt']));

		return $this->store->save(schema: ExportObjectStore::SCHEMA_EXPORT, data: array_merge($stored, $keep));
	}//end recordFire()

	/**
	 * The export's next fire strictly after a moment, as an ATOM string.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param DateTimeInterface    $after  The moment to look past.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
	 */
	public function nextRunAfter(array $export, DateTimeInterface $after): string {
		$timezone = (string)($export['runTimezone'] ?? self::DEFAULT_TIMEZONE);
		return $this->recurrence->nextRunAt(recurrence: (array)$export['recurrence'], timezone: $timezone, after: $after)->format(DateTimeInterface::ATOM);
	}//end nextRunAfter()

	/**
	 * Whether a user may see an export: its owner, its creator, or an administrator.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param string               $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-21
	 */
	public function isVisibleTo(array $export, string $userId): bool {
		if ($userId === '') {
			return false;
		}

		if (($export['owner'] ?? null) === $userId || ($export['createdBy'] ?? null) === $userId) {
			return true;
		}

		return $this->groups->isAdmin($userId);
	}//end isVisibleTo()

	/**
	 * What is wrong with an export, or nothing.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return list<string> One sentence per problem.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function validate(array $export): array {
		$errors = array_merge(
			self::identityErrors(export: $export),
			self::listErrors(items: $export['renderTargets'] ?? null, rule: ['key' => 'format', 'allowed' => self::FORMATS, 'label' => 'render target']),
			self::listErrors(items: $export['recipients'] ?? null, rule: ['key' => 'channel', 'allowed' => self::CHANNELS, 'label' => 'recipient'])
		);

		if (is_array($export['recurrence'] ?? null) === false) {
			return array_merge($errors, ['The recurrence is required.']);
		}

		try {
			new DateTimeZone((string)($export['runTimezone'] ?? self::DEFAULT_TIMEZONE));
		} catch (Throwable) {
			$errors[] = 'The time zone is not a valid IANA time zone.';
		}

		return array_merge($errors, $this->recurrence->validate(recurrence: $export['recurrence']));
	}//end validate()

	/**
	 * Problems with the export's name, subject type and subject.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return list<string>
	 */
	private static function identityErrors(array $export): array {
		$errors = [];
		$name   = $export['name'] ?? null;
		if (is_string($name) === false || trim($name) === '' || mb_strlen($name) > 255) {
			$errors[] = 'The name is required and at most 255 characters.';
		}

		if (in_array($export['subjectType'] ?? null, ['dashboard', 'widget'], true) === false) {
			$errors[] = 'The subject type must be dashboard or widget.';
		}

		if (is_string($export['subjectId'] ?? null) === false || $export['subjectId'] === '') {
			$errors[] = 'The subject is required.';
		}

		return $errors;
	}//end identityErrors()

	/**
	 * Problems with a non-empty list of objects that each carry an allowed value under a key.
	 *
	 * @param mixed                                                    $items The list.
	 * @param array{key: string, allowed: list<string>, label: string} $rule  The discriminating key,
	 *                                                                        its allowed values and
	 *                                                                        what one item is called.
	 *
	 * @return list<string>
	 */
	private static function listErrors(mixed $items, array $rule): array {
		if (is_array($items) === false || $items === [] || array_is_list($items) === false) {
			return ['At least one ' . $rule['label'] . ' is required.'];
		}

		foreach ($items as $item) {
			if (is_array($item) === false || in_array($item[$rule['key']] ?? null, $rule['allowed'], true) === false) {
				return ['Every ' . $rule['label'] . ' needs a ' . $rule['key'] . ' of ' . implode(', ', $rule['allowed']) . '.'];
			}
		}

		return [];
	}//end listErrors()

	/**
	 * The stored export, refused as not found unless the user may see it.
	 *
	 * @param string $id     The export uuid.
	 * @param string $userId The user.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws DoesNotExistException When it does not exist or is not the user's to see.
	 */
	private function stored(string $id, string $userId): array {
		$export = $this->store->find(schema: ExportObjectStore::SCHEMA_EXPORT, uuid: $id);
		if ($export === null || $this->isVisibleTo(export: $export, userId: $userId) === false) {
			throw new DoesNotExistException('Scheduled export not found');
		}

		return $export;
	}//end stored()

	/**
	 * Recipients with every new secret encrypted, and a redacted placeholder
	 * replaced by the stored secret of the recipient at the same position.
	 *
	 * @param list<array<string, mixed>> $recipients The recipients being saved.
	 * @param list<array<string, mixed>> $stored     The recipients as stored.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function sealSecrets(array $recipients, array $stored): array {
		foreach ($recipients as $index => $recipient) {
			$config = [];
			if (is_array($recipient['channelConfig'] ?? null) === true) {
				$config = $recipient['channelConfig'];
			}

			$previous = [];
			if (is_array($stored[$index]['channelConfig'] ?? null) === true) {
				$previous = $stored[$index]['channelConfig'];
			}

			$recipients[$index]['channelConfig'] = $this->sealConfig(config: $config, previous: $previous);
		}

		return $recipients;
	}//end sealSecrets()

	/**
	 * One channel config with every new secret encrypted, and a redacted
	 * placeholder replaced by the stored secret (or dropped without one).
	 *
	 * @param array<string, mixed> $config   The config being saved.
	 * @param array<string, mixed> $previous The config as stored.
	 *
	 * @return array<string, mixed>
	 */
	private function sealConfig(array $config, array $previous): array {
		foreach (self::SECRET_KEYS as $key) {
			$value = $config[$key] ?? null;
			if (is_string($value) === false || $value === '') {
				continue;
			}

			if ($value === self::REDACTED) {
				unset($config[$key]);
				if (is_string($previous[$key] ?? null) === true) {
					$config[$key] = $previous[$key];
				}

				continue;
			}

			if (str_starts_with($value, self::ENCRYPTED_PREFIX) === false) {
				$config[$key] = self::ENCRYPTED_PREFIX . $this->crypto->encrypt($value);
			}
		}

		return $config;
	}//end sealConfig()

	/**
	 * The export as the API shows it: every secret replaced by the placeholder.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return array<string, mixed>
	 */
	private function redact(array $export): array {
		$redacted = static fn (): string => self::REDACTED;
		$export['recipients'] = array_map(fn ($recipient) => $this->mapSecrets(recipient: $recipient, map: $redacted), $export['recipients'] ?? []);
		return $export;
	}//end redact()

	/**
	 * A recipient with a function applied to each secret it holds.
	 *
	 * @param mixed    $recipient The recipient.
	 * @param callable $map       Turns a stored secret into what is returned.
	 *
	 * @return mixed
	 */
	private function mapSecrets(mixed $recipient, callable $map): mixed {
		if (is_array($recipient) === false || is_array($recipient['channelConfig'] ?? null) === false) {
			return $recipient;
		}

		foreach (self::SECRET_KEYS as $key) {
			if (is_string($recipient['channelConfig'][$key] ?? null) === true && $recipient['channelConfig'][$key] !== '') {
				$recipient['channelConfig'][$key] = $map($recipient['channelConfig'][$key]);
			}
		}

		return $recipient;
	}//end mapSecrets()

	/**
	 * A stored secret decrypted; a value that was never encrypted is returned as is.
	 *
	 * @param string $value The stored value.
	 *
	 * @return string
	 */
	private function reveal(string $value): string {
		if (str_starts_with($value, self::ENCRYPTED_PREFIX) === false) {
			return $value;
		}

		return $this->crypto->decrypt(substr($value, strlen(self::ENCRYPTED_PREFIX)));
	}//end reveal()
}//end class
