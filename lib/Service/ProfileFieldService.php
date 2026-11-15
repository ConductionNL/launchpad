<?php

/**
 * ProfileFieldService
 *
 * Custom profile fields for the people widget (widgets-people-expertise-and-fields,
 * REQ-PEX-001..004): the administrator's field definitions, the values people
 * fill in themselves or that come from LDAP, a mirror of the searchable
 * standard fields with their visibility scope, and the visibility rule that
 * decides what a viewer may see and match.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\ProfileValue;
use OCA\LaunchPad\Db\ProfileFieldValues;
use OCA\LaunchPad\Db\ProfileValueMapper;
use OCP\Accounts\IAccountManager;
use OCP\IUser;

/**
 * Definitions, values and visibility of custom profile fields.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Definition validation,
 *  self edits, the LDAP sync, the standard-field mirror and the visibility
 *  rule share one data model; splitting them would scatter that rule.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) Same reason: the listener,
 *  the job, both controllers, the people service and the demo installer each
 *  call their own entry point on this one model.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileFieldService {
	/**
	 * Admin setting key holding the field definitions.
	 *
	 * @var string
	 */
	public const SETTING_KEY = 'profile_fields';

	/**
	 * Most custom fields an administrator may define.
	 *
	 * @var int
	 */
	public const MAX_FIELDS = 10;

	/**
	 * Most tags one person may list in one field.
	 *
	 * @var int
	 */
	public const MAX_TAGS = 25;

	/**
	 * Longest value, in characters.
	 *
	 * @var int
	 */
	public const MAX_VALUE_LENGTH = 255;

	/**
	 * Standard fields mirrored for the search, keyed by their row key.
	 *
	 * @var array<string, string>
	 */
	public const MIRRORED_STANDARD_FIELDS = [
		'nc:role' => IAccountManager::PROPERTY_ROLE,
		'nc:headline' => IAccountManager::PROPERTY_HEADLINE,
		'nc:biography' => IAccountManager::PROPERTY_BIOGRAPHY,
	];

	private const TYPES = ['text', 'tags'];
	private const SOURCES = ['self', 'ldap'];
	private const VISIBILITIES = ['everyone', 'groups'];

	/**
	 * Group ids per user, memoised for one request.
	 *
	 * @var array<string, string[]>
	 */
	private array $groupCache = [];

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settingMapper Admin settings store.
	 * @param ProfileValueMapper $valueMapper Profile values store.
	 * @param ProfileSources     $sources The account manager and LDAP provider factory.
	 * @param ProfileFieldTools  $tools The admin template service and logger.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct(
		private readonly AdminSettingMapper $settingMapper,
		private readonly ProfileValueMapper $valueMapper,
		private readonly ProfileSources $sources,
		private readonly ProfileFieldTools $tools,
	) {
	}//end __construct()

	/**
	 * The stored field definitions, cleaned.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getDefinitions(): array {
		$raw = $this->settingMapper->getValue(key: self::SETTING_KEY, default: []);
		if (is_array(value: $raw) === false) {
			return [];
		}

		try {
			return $this->validateDefinitions(raw: $raw);
		} catch (InvalidArgumentException) {
			return [];
		}
	}//end getDefinitions()

	/**
	 * Validate and store the field definitions (REQ-PEX-001).
	 *
	 * @param array $raw Definitions as sent by the admin screen.
	 *
	 * @return array<int, array<string, mixed>> The stored definitions.
	 *
	 * @throws InvalidArgumentException When a definition is invalid.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function saveDefinitions(array $raw): array {
		$definitions = $this->validateDefinitions(raw: $raw);
		$this->settingMapper->setSetting(key: self::SETTING_KEY, value: $definitions);

		return $definitions;
	}//end saveDefinitions()

	/**
	 * Check and normalise a list of definitions.
	 *
	 * @param array $raw Raw definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @throws InvalidArgumentException When a definition is invalid.
	 */
	private function validateDefinitions(array $raw): array {
		if (count(value: $raw) > self::MAX_FIELDS) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_FIELDS . ' profile fields');
		}

		$result = [];
		$seen = [];
		foreach (array_values(array: $raw) as $entry) {
			$definition = $this->validateDefinition(entry: $entry);
			if (isset($seen[$definition['key']]) === true) {
				throw new InvalidArgumentException(message: 'Duplicate field key: ' . $definition['key']);
			}

			$seen[$definition['key']] = true;
			$result[] = $definition;
		}

		return $result;
	}//end validateDefinitions()

	/**
	 * Check and normalise one definition.
	 *
	 * @param mixed $entry Raw definition.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When the definition is invalid.
	 */
	private function validateDefinition(mixed $entry): array {
		if (is_array(value: $entry) === false) {
			throw new InvalidArgumentException(message: 'A profile field must be an object');
		}

		$definition = $this->definitionFrom(entry: $entry, key: self::validKey(entry: $entry));

		return self::withCheckedLdapAttribute(definition: $definition);
	}//end validateDefinition()

	/**
	 * The definition's key, checked: lower case letters, digits or `_`,
	 * starting with a letter, at most 32 characters.
	 *
	 * @param array<string, mixed> $entry Raw definition.
	 *
	 * @return string The key.
	 *
	 * @throws InvalidArgumentException When the key is malformed.
	 */
	private static function validKey(array $entry): string {
		$key = (string)($entry['key'] ?? '');
		if (preg_match(pattern: '/^[a-z][a-z0-9_]{0,31}$/', subject: $key) !== 1) {
			throw new InvalidArgumentException(message: 'Field key must be lower case letters, digits or _: ' . $key);
		}

		return $key;
	}//end validKey()

	/**
	 * The definition's trimmed label, checked: 1 to 64 characters.
	 *
	 * @param array<string, mixed> $entry Raw definition.
	 * @param string               $key   The checked key (for the message).
	 *
	 * @return string The label.
	 *
	 * @throws InvalidArgumentException When the label is empty or too long.
	 */
	private static function validLabel(array $entry, string $key): string {
		$label = trim(string: (string)($entry['label'] ?? ''));
		if ($label === '' || mb_strlen(string: $label) > 64) {
			throw new InvalidArgumentException(message: 'Field label must be 1 to 64 characters: ' . $key);
		}

		return $label;
	}//end validLabel()

	/**
	 * The normalised definition: label, then type, source and visibility
	 * checked in that order; flags default to not searchable and shown.
	 *
	 * @param array<string, mixed> $entry Raw definition.
	 * @param string               $key   The checked key.
	 *
	 * @return array<string, mixed> The definition, LDAP attribute unchecked.
	 *
	 * @throws InvalidArgumentException When a field is invalid.
	 */
	private function definitionFrom(array $entry, string $key): array {
		return [
			'key' => $key,
			'label' => self::validLabel(entry: $entry, key: $key),
			'type' => $this->oneOf(value: $entry['type'] ?? 'text', allowed: self::TYPES, name: 'type'),
			'source' => $this->oneOf(value: $entry['source'] ?? 'self', allowed: self::SOURCES, name: 'source'),
			'ldapAttribute' => trim(string: (string)($entry['ldapAttribute'] ?? '')),
			'searchable' => ($entry['searchable'] ?? false) === true,
			'shownInWidget' => ($entry['shownInWidget'] ?? true) === true,
			'visibility' => $this->oneOf(value: $entry['visibility'] ?? 'everyone', allowed: self::VISIBILITIES, name: 'visibility'),
		];
	}//end definitionFrom()

	/**
	 * An LDAP field must name a valid LDAP attribute; any other field keeps
	 * none.
	 *
	 * @param array<string, mixed> $definition The normalised definition.
	 *
	 * @return array<string, mixed> The definition.
	 *
	 * @throws InvalidArgumentException When an LDAP field has no valid attribute.
	 */
	private static function withCheckedLdapAttribute(array $definition): array {
		if ($definition['source'] === 'ldap'
			&& preg_match(pattern: '/^[A-Za-z][A-Za-z0-9-]{0,63}$/', subject: $definition['ldapAttribute']) !== 1
		) {
			throw new InvalidArgumentException(message: 'An LDAP field needs an LDAP attribute name: ' . $definition['key']);
		}

		if ($definition['source'] !== 'ldap') {
			$definition['ldapAttribute'] = '';
		}

		return $definition;
	}//end withCheckedLdapAttribute()

	/**
	 * Check that a value is one of the allowed strings.
	 *
	 * @param mixed $value The value.
	 * @param string[] $allowed Allowed values.
	 * @param string $name Property name for the message.
	 *
	 * @return string The value.
	 *
	 * @throws InvalidArgumentException When it is not allowed.
	 */
	private function oneOf(mixed $value, array $allowed, string $name): string {
		if (is_string(value: $value) === false || in_array(needle: $value, haystack: $allowed, strict: true) === false) {
			throw new InvalidArgumentException(message: 'Field ' . $name . ' must be one of ' . implode(separator: ', ', array: $allowed));
		}

		return $value;
	}//end oneOf()

	/**
	 * A person's own fields with their values, for the personal settings (REQ-PEX-002).
	 *
	 * @param string $userId The person.
	 *
	 * @return array<int, array<string, mixed>> Definitions with `values` and `readOnly`.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getOwnFields(string $userId): array {
		$byKey = $this->groupValues(rows: $this->valueMapper->findByUsers(userIds: [$userId]))[$userId] ?? [];

		$result = [];
		foreach ($this->getDefinitions() as $definition) {
			$result[] = array_merge(
				$definition,
				[
					'values' => $byKey[$definition['key']] ?? [],
					'readOnly' => $definition['source'] !== 'self',
				]
			);
		}

		return $result;
	}//end getOwnFields()

	/**
	 * Save a person's own values (REQ-PEX-002). LDAP fields are read-only
	 * and ignored; unknown keys are rejected.
	 *
	 * @param string $userId The person.
	 * @param array $values Map of field key to a string (text) or a list of strings (tags).
	 *
	 * @return array<int, array<string, mixed>> The fields after saving.
	 *
	 * @throws InvalidArgumentException When a key or value is invalid.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function saveOwnValues(string $userId, array $values): array {
		$clean = $this->cleanOwnValues(definitions: $this->definitionsByKey(), values: $values);

		foreach ($clean as $key => $list) {
			$this->valueMapper->replaceValues(userId: $userId, field: new ProfileFieldValues(fieldKey: (string)$key, values: $list, source: 'self'));
		}

		return $this->getOwnFields(userId: $userId);
	}//end saveOwnValues()

	/**
	 * The definitions keyed by field key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function definitionsByKey(): array {
		$definitions = [];
		foreach ($this->getDefinitions() as $definition) {
			$definitions[$definition['key']] = $definition;
		}

		return $definitions;
	}//end definitionsByKey()

	/**
	 * Check every submitted value before anything is written; values of
	 * fields the user does not own (LDAP, Nextcloud) are left out.
	 *
	 * @param array<string, array<string, mixed>> $definitions The definitions by key.
	 * @param array<string, mixed>                $values      Key → value (string or list).
	 *
	 * @return array<string, string[]> The cleaned values by key.
	 *
	 * @throws InvalidArgumentException On an unknown field or invalid value.
	 */
	private function cleanOwnValues(array $definitions, array $values): array {
		$clean = [];
		foreach ($values as $key => $value) {
			$definition = $definitions[$key] ?? null;
			if ($definition === null) {
				throw new InvalidArgumentException(message: 'Unknown profile field: ' . $key);
			}

			if ($definition['source'] === 'self') {
				$clean[$key] = $this->cleanValues(definition: $definition, value: $value);
			}
		}

		return $clean;
	}//end cleanOwnValues()

	/**
	 * Turn a submitted value into a clean list of strings.
	 *
	 * @param array $definition The field definition.
	 * @param mixed $value A string, or a list of strings for tags.
	 *
	 * @return string[]
	 *
	 * @throws InvalidArgumentException When the value does not fit the type.
	 */
	private function cleanValues(array $definition, mixed $value): array {
		$result = [];
		foreach (self::valueItems(definition: $definition, value: $value) as $item) {
			$text = self::cleanItem(definition: $definition, item: $item);
			if ($text !== null) {
				// The first spelling of a tag wins.
				$result[mb_strtolower(string: $text)] ??= $text;
			}
		}

		if (count(value: $result) > self::MAX_TAGS) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_TAGS . ' tags: ' . $definition['key']);
		}

		return array_values(array: $result);
	}//end cleanValues()

	/**
	 * The submitted items of a field: a tags field takes a list, any other
	 * field one value.
	 *
	 * @param array<string, mixed> $definition The field definition.
	 * @param mixed                $value      The submitted value.
	 *
	 * @return array<int|string, mixed> The items.
	 *
	 * @throws InvalidArgumentException When a tags field gets no list.
	 */
	private static function valueItems(array $definition, mixed $value): array {
		if ($definition['type'] !== 'tags') {
			return [$value];
		}

		if (is_array(value: $value) === false) {
			throw new InvalidArgumentException(message: 'Tags must be a list: ' . $definition['key']);
		}

		return $value;
	}//end valueItems()

	/**
	 * One submitted item, trimmed; null for a null or blank item.
	 *
	 * @param array<string, mixed> $definition The field definition.
	 * @param mixed                $item       The submitted item.
	 *
	 * @return string|null The text, or null when the item is skipped.
	 *
	 * @throws InvalidArgumentException When the item is not text or too long.
	 */
	private static function cleanItem(array $definition, mixed $item): ?string {
		if ($item === null) {
			return null;
		}

		$text = self::itemText(definition: $definition, item: $item);
		if ($text === '') {
			return null;
		}

		if (mb_strlen(string: $text) > self::MAX_VALUE_LENGTH) {
			throw new InvalidArgumentException(message: 'A value is longer than ' . self::MAX_VALUE_LENGTH . ' characters: ' . $definition['key']);
		}

		return $text;
	}//end cleanItem()

	/**
	 * A submitted item as trimmed text.
	 *
	 * @param array<string, mixed> $definition The field definition.
	 * @param mixed                $item       The submitted, non-null item.
	 *
	 * @return string The trimmed text.
	 *
	 * @throws InvalidArgumentException When the item is not text.
	 */
	private static function itemText(array $definition, mixed $item): string {
		if (is_string(value: $item) === false) {
			throw new InvalidArgumentException(message: 'A value must be text: ' . $definition['key']);
		}

		return trim(string: $item);
	}//end itemText()

	/**
	 * Custom fields shown in the widget that the viewer may see (REQ-PEX-004).
	 *
	 * @param string|null $viewerId The viewer, or null for nobody.
	 * @param string[] $userIds The people on the page.
	 *
	 * @return array<string, array<int, array<string, mixed>>> Per user id: `{key, label, type, values}`.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function visibleFieldsFor(?string $viewerId, array $userIds): array {
		$definitions = array_filter(
			array: $this->getDefinitions(),
			callback: static fn (array $definition): bool => $definition['shownInWidget'] === true
		);
		if ($definitions === [] || $userIds === []) {
			return [];
		}

		$listing = new ProfileFieldListing(
			definitions: $definitions,
			canSee: fn (string $ownerId, array $definition): bool => $this->canSee(viewerId: $viewerId, ownerId: $ownerId, definition: $definition)
		);

		return $listing->fieldsByPerson(grouped: $this->groupValues(rows: $this->valueMapper->findByUsers(userIds: $userIds)));
	}//end visibleFieldsFor()

	/**
	 * People whose searchable fields contain the query and the viewer may
	 * see that field (REQ-PEX-003, REQ-PEX-004).
	 *
	 * @param string|null $viewerId The viewer.
	 * @param string $query The search text.
	 *
	 * @return string[] Matching user ids.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function findMatchingUserIds(?string $viewerId, string $query): array {
		$needle = mb_strtolower(string: trim(string: $query));
		if ($needle === '') {
			return [];
		}

		$definitions = $this->searchableDefinitions();

		$matches = [];
		foreach ($this->valueMapper->search(needle: $needle) as $row) {
			$owner = $row->getUserId();
			if ($this->rowMatchesFor(viewerId: $viewerId, row: $row, definitions: $definitions) === true) {
				$matches[$owner] = true;
			}
		}

		return array_keys(array: $matches);
	}//end findMatchingUserIds()

	/**
	 * The searchable definitions keyed by field key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function searchableDefinitions(): array {
		$definitions = [];
		foreach ($this->getDefinitions() as $definition) {
			if ($definition['searchable'] === true) {
				$definitions[$definition['key']] = $definition;
			}
		}

		return $definitions;
	}//end searchableDefinitions()

	/**
	 * Whether a matching row may count for this viewer.
	 *
	 * @param string|null $viewerId The viewer.
	 * @param ProfileValue $row The matching row.
	 * @param array<string, array<string, mixed>> $definitions Searchable definitions by key.
	 *
	 * @return bool
	 */
	private function rowMatchesFor(?string $viewerId, ProfileValue $row, array $definitions): bool {
		$key = $row->getFieldKey();
		$owner = $row->getUserId();

		if (isset(self::MIRRORED_STANDARD_FIELDS[$key]) === true) {
			return $this->scopeAllows(scope: $row->getScope(), viewerId: $viewerId, ownerId: $owner);
		}

		$definition = $definitions[$key] ?? null;
		if ($definition === null) {
			return false;
		}

		return $this->canSee(viewerId: $viewerId, ownerId: $owner, definition: $definition);
	}//end rowMatchesFor()

	/**
	 * Whether a standard field with this Nextcloud scope is visible to the viewer.
	 * A private field is visible to its owner only; the other scopes reach
	 * every signed-in user of this instance.
	 *
	 * @param string|null $scope The property scope.
	 * @param string|null $viewerId The viewer.
	 * @param string $ownerId The owner.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function scopeAllows(?string $scope, ?string $viewerId, string $ownerId): bool {
		if ($viewerId === null) {
			return false;
		}

		return $viewerId === $ownerId || $scope !== IAccountManager::SCOPE_PRIVATE;
	}//end scopeAllows()

	/**
	 * Whether the viewer may see a custom field of the owner.
	 *
	 * @param string|null $viewerId The viewer.
	 * @param string $ownerId The owner.
	 * @param array $definition The field definition.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function canSee(?string $viewerId, string $ownerId, array $definition): bool {
		if ($viewerId === null) {
			return false;
		}

		if ($viewerId === $ownerId || $definition['visibility'] === 'everyone') {
			return true;
		}

		$shared = array_intersect(
			$this->groupsOf(userId: $viewerId),
			$this->groupsOf(userId: $ownerId)
		);

		return $shared !== [];
	}//end canSee()

	/**
	 * Group ids of a user, memoised.
	 *
	 * @param string $userId The user.
	 *
	 * @return string[]
	 */
	private function groupsOf(string $userId): array {
		if (isset($this->groupCache[$userId]) === false) {
			$this->groupCache[$userId] = $this->tools->adminTemplateService->getUserGroupIdsFor(userId: $userId);
		}

		return $this->groupCache[$userId];
	}//end groupsOf()

	/**
	 * Group rows by user and field key.
	 *
	 * @param ProfileValue[] $rows Rows.
	 *
	 * @return array<string, array<string, string[]>>
	 */
	private function groupValues(array $rows): array {
		$result = [];
		foreach ($rows as $row) {
			$result[$row->getUserId()][$row->getFieldKey()][] = $row->getValue();
		}

		return $result;
	}//end groupValues()

	/**
	 * Mirror a person's searchable standard fields with their scope (REQ-PEX-004).
	 *
	 * @param IUser $user The person.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function mirrorStandardFields(IUser $user): void {
		try {
			$account = $this->sources->accountManager->getAccount(user: $user);
		} catch (Exception $e) {
			$this->tools->logger->debug(message: 'Profile mirror: no account for ' . $user->getUID() . ': ' . $e->getMessage());
			return;
		}

		foreach (self::MIRRORED_STANDARD_FIELDS as $key => $property) {
			$value = '';
			$scope = null;
			try {
				$prop = $account->getProperty(property: $property);
				$value = trim(string: $prop->getValue());
				$scope = $prop->getScope();
			} catch (Exception) {
				$value = '';
			}

			$values = [];
			if ($value !== '') {
				$values = [$value];
			}

			$this->valueMapper->replaceValues(
				userId: $user->getUID(),
				field: new ProfileFieldValues(fieldKey: $key, values: $values, source: 'nextcloud', scope: $scope)
			);
		}//end foreach
	}//end mirrorStandardFields()

	/**
	 * Read a person's LDAP-sourced fields (REQ-PEX-001). Does nothing when
	 * `user_ldap` is not available or the account does not come from LDAP.
	 *
	 * @param IUser $user The person.
	 *
	 * @return int Number of fields synced.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function syncLdapFields(IUser $user): int {
		$definitions = array_filter(
			array: $this->getDefinitions(),
			callback: static fn (array $definition): bool => $definition['source'] === 'ldap'
		);
		if ($definitions === [] || $user->getBackendClassName() !== 'LDAP') {
			return 0;
		}

		if ($this->sources->ldapProviderFactory->isAvailable() === false) {
			return 0;
		}

		return $this->syncFromLdap(user: $user, definitions: $definitions);
	}//end syncLdapFields()

	/**
	 * Copy each LDAP field's attribute values from the directory; a
	 * directory failure is logged and ends the sync.
	 *
	 * @param IUser                            $user        The LDAP user.
	 * @param array<int, array<string, mixed>> $definitions The LDAP field definitions.
	 *
	 * @return int The number of fields synced before any failure.
	 */
	private function syncFromLdap(IUser $user, array $definitions): int {
		$synced = 0;
		try {
			$provider = $this->sources->ldapProviderFactory->getLDAPProvider();
			foreach ($definitions as $definition) {
				$raw = $provider->getMultiValueUserAttribute(uid: $user->getUID(), attribute: $definition['ldapAttribute']);
				$this->valueMapper->replaceValues(userId: $user->getUID(), field: $this->ldapFieldValues(definition: $definition, raw: $raw));
				$synced++;
			}
		} catch (Exception $e) {
			$this->tools->logger->warning(message: 'Profile LDAP sync failed for ' . $user->getUID() . ': ' . $e->getMessage());
		}

		return $synced;
	}//end syncFromLdap()

	/**
	 * The cleaned LDAP values of one field, ready to replace the stored ones.
	 *
	 * @param array<string, mixed> $definition The LDAP field definition.
	 * @param array                $raw        The directory's attribute values.
	 *
	 * @return ProfileFieldValues The field's new values.
	 */
	private function ldapFieldValues(array $definition, array $raw): ProfileFieldValues {
		$values = $this->cleanLdapValues(definition: $definition, raw: $raw);

		return new ProfileFieldValues(fieldKey: $definition['key'], values: $values, source: 'ldap');
	}//end ldapFieldValues()

	/**
	 * Clean the values read from LDAP: a text field keeps its first value.
	 *
	 * @param array $definition The field definition.
	 * @param array $raw Values from LDAP.
	 *
	 * @return string[]
	 */
	private function cleanLdapValues(array $definition, array $raw): array {
		$values = [];
		foreach ($raw as $item) {
			$item = mb_substr(string: trim(string: (string)$item), start: 0, length: self::MAX_VALUE_LENGTH);
			if ($item !== '') {
				$values[mb_strtolower(string: $item)] ??= $item;
			}
		}

		$values = array_values(array: $values);
		if ($definition['type'] === 'text') {
			return array_slice(array: $values, offset: 0, length: 1);
		}

		return array_slice(array: $values, offset: 0, length: self::MAX_TAGS);
	}//end cleanLdapValues()

	/**
	 * Define the demo fields when none are defined yet: an office location
	 * and a cost centre to fill in, and searchable expertise tags. Existing
	 * definitions are never touched.
	 *
	 * @return bool True when the demo fields were added.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function seedDemoDefinitions(): bool {
		if ($this->getDefinitions() !== []) {
			return false;
		}

		$this->saveDefinitions(
			raw: [
				['key' => 'office', 'label' => 'Kantoorlocatie', 'type' => 'text', 'source' => 'self', 'searchable' => true, 'shownInWidget' => true],
				[
					'key' => 'cost_centre',
					'label' => 'Kostenplaats',
					'type' => 'text',
					'source' => 'self',
					'searchable' => false,
					'shownInWidget' => false,
					'visibility' => 'groups',
				],
				['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags', 'source' => 'self', 'searchable' => true, 'shownInWidget' => true],
			]
		);

		return true;
	}//end seedDemoDefinitions()

	/**
	 * Remove every value of a deleted person.
	 *
	 * @param string $userId The person.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function deleteUser(string $userId): void {
		$this->valueMapper->deleteByUser(userId: $userId);
	}//end deleteUser()
}//end class
