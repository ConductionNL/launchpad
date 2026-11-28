<?php

/**
 * WidgetPlacement Entity
 *
 * Represents a widget placement on a dashboard.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use stdClass;

/**
 * Widget placement entity for dashboard grid positioning.
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 *      A Nextcloud `Entity` declares one protected property per database
 *      column, so the field count is fixed by the `launchpad_widget_placements`
 *      schema (31 columns: grid geometry, per-breakpoint overrides, style and
 *      content JSON, and the conditional-visibility keys). Grouping them into
 *      value objects would break `Entity`'s column-to-property mapping.
 *
 * @method int getDashboardId()
 * @method void setDashboardId(int $dashboardId)
 * @method string getWidgetId()
 * @method void setWidgetId(string $widgetId)
 * @method int getGridX()
 * @method void setGridX(int $gridX)
 * @method int getGridY()
 * @method void setGridY(int $gridY)
 * @method int getGridWidth()
 * @method void setGridWidth(int $gridWidth)
 * @method int getGridHeight()
 * @method void setGridHeight(int $gridHeight)
 * @method int getIsCompulsory()
 * @method void setIsCompulsory(int $isCompulsory)
 * @method int getIsVisible()
 * @method void setIsVisible(int $isVisible)
 * @method string|null getStyleConfig()
 * @method void setStyleConfig(?string $styleConfig)
 * @method string|null getCustomTitle()
 * @method void setCustomTitle(?string $customTitle)
 * @method int getShowTitle()
 * @method void setShowTitle(int $showTitle)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method string|null getTileType()
 * @method void setTileType(?string $tileType)
 * @method string|null getTileTitle()
 * @method void setTileTitle(?string $tileTitle)
 * @method string|null getTileIcon()
 * @method void setTileIcon(?string $tileIcon)
 * @method string|null getTileIconType()
 * @method void setTileIconType(?string $tileIconType)
 * @method string|null getTileBackgroundColor()
 * @method void setTileBackgroundColor(?string $tileBackgroundColor)
 * @method string|null getTileTextColor()
 * @method void setTileTextColor(?string $tileTextColor)
 * @method string|null getTileLinkType()
 * @method void setTileLinkType(?string $tileLinkType)
 * @method string|null getTileLinkValue()
 * @method void setTileLinkValue(?string $tileLinkValue)
 * @method string|null getCustomIcon()
 * @method void setCustomIcon(?string $customIcon)
 * @method string|null getCalculatedFields()
 * @method void setCalculatedFields(?string $calculatedFields)
 * @method string|null getContent()
 * @method void setContent(?string $content)
 * @method int getRequiresAcknowledgement()
 * @method void setRequiresAcknowledgement(int $requiresAcknowledgement)
 * @method string|null getAcknowledgementPrompt()
 * @method void setAcknowledgementPrompt(?string $acknowledgementPrompt)
 * @method string|null getAcknowledgementDeadline()
 * @method void setAcknowledgementDeadline(?string $acknowledgementDeadline)
 * @method int getReacknowledgeOnChange()
 * @method void setReacknowledgeOnChange(int $reacknowledgeOnChange)
 * @method int getAcknowledgementContentVersion()
 * @method void setAcknowledgementContentVersion(int $acknowledgementContentVersion)
 * @method string|null getAnnouncementKey()
 * @method void setAnnouncementKey(?string $announcementKey)
 * @method int|null getTemplatePlacementId()
 * @method int getVersion()
 * @method void setVersion(int $version)
 * @method void setTemplatePlacementId(?int $templatePlacementId)
 * @method string|null getSyncedLayout()
 * @method void setSyncedLayout(?string $syncedLayout)
 * @method void setRuleMatch(?string $ruleMatch)
 * @method string|null getCreatedAt()
 * @method void setCreatedAt(?string $createdAt)
 * @method string|null getUpdatedAt()
 * @method void setUpdatedAt(?string $updatedAt)
 */
class WidgetPlacement extends Entity implements JsonSerializable {

	/**
	 * One include rule must match for the widget to show (the default).
	 */
	public const RULE_MATCH_ANY = 'any';

	/**
	 * Every include rule must match for the widget to show.
	 */
	public const RULE_MATCH_ALL = 'all';

	/**
	 * The values `ruleMatch` accepts.
	 *
	 * @var array<int, string>
	 */
	public const RULE_MATCHES = [self::RULE_MATCH_ANY, self::RULE_MATCH_ALL];

	/**
	 * The fields a placement update may change: the per-widget update route
	 * and an edit-mode draft save take the same ones (REQ-EDIT-002).
	 *
	 * @var list<string>
	 */
	public const EDITABLE_FIELDS = [
		'gridX',
		'gridY',
		'gridWidth',
		'gridHeight',
		'isVisible',
		'showTitle',
		'customTitle',
		'customIcon',
		'styleConfig',
		'content',
		// The widget's calculated fields (widget-calculated-fields, task 21).
		'calculatedFields',
		'tileTitle',
		'tileIcon',
		'tileIconType',
		'tileBackgroundColor',
		'tileTextColor',
		'tileLinkType',
		'tileLinkValue',
		'requiresAcknowledgement',
		'acknowledgementPrompt',
		'acknowledgementDeadline',
		'reacknowledgeOnChange',
		'acknowledgementContentVersion',
		// Whether all or one include rule must match (REQ-VIS-014).
		'ruleMatch',
	];

	/**
	 * The acknowledgement fields, which need the acknowledgement permission.
	 *
	 * @var list<string>
	 */
	public const ACKNOWLEDGEMENT_FIELDS = [
		'requiresAcknowledgement',
		'acknowledgementPrompt',
		'acknowledgementDeadline',
		'reacknowledgeOnChange',
		'acknowledgementContentVersion',
	];

	/**
	 * The dashboard ID.
	 *
	 * @var integer
	 */
	protected int $dashboardId = 0;

	/**
	 * The widget ID.
	 *
	 * @var string
	 */
	protected string $widgetId = '';

	/**
	 * The grid X position.
	 *
	 * @var integer
	 */
	protected int $gridX = 0;

	/**
	 * The grid Y position.
	 *
	 * @var integer
	 */
	protected int $gridY = 0;

	/**
	 * The grid width.
	 *
	 * @var integer
	 */
	protected int $gridWidth = 4;

	/**
	 * The grid height.
	 *
	 * @var integer
	 */
	protected int $gridHeight = 4;

	/**
	 * Whether the widget is compulsory (SMALLINT 0/1).
	 *
	 * @var integer
	 */
	protected int $isCompulsory = 0;

	/**
	 * Whether the widget is visible (SMALLINT 0/1).
	 *
	 * @var integer
	 */
	protected int $isVisible = 1;

	/**
	 * The style configuration JSON.
	 *
	 * @var string|null
	 */
	protected ?string $styleConfig = null;

	/**
	 * The custom title.
	 *
	 * @var string|null
	 */
	protected ?string $customTitle = null;

	/**
	 * The custom icon.
	 *
	 * @var string|null
	 */
	protected ?string $customIcon = null;

	/**
	 * The calculated fields of the widget, a JSON list of field definitions
	 * (id, name, label, expression, compiledExpression, returnType,
	 * evaluationMode, ...). Null on rows saved before the column existed.
	 *
	 * @var string|null
	 */
	protected ?string $calculatedFields = null;

	/**
	 * Whether to show the title (SMALLINT 0/1).
	 *
	 * @var integer
	 */
	protected int $showTitle = 1;

	/**
	 * The sort order.
	 *
	 * @var integer
	 */
	protected int $sortOrder = 0;

	/**
	 * The tile type.
	 *
	 * @var string|null
	 */
	protected ?string $tileType = null;

	/**
	 * The tile title.
	 *
	 * @var string|null
	 */
	protected ?string $tileTitle = null;

	/**
	 * The tile icon — opaque single-column field.
	 *
	 * Per `dashboard-icons` REQ-ICON-009 the column may hold ANY of the
	 * following three forms; the backend treats the column as opaque
	 * and the frontend `IconRenderer` discriminates at render time via
	 * `isCustomIconUrl()`:
	 *
	 *   - `null` — render the default icon
	 *   - A built-in registry key (e.g. `'Star'`, `'ViewDashboard'`)
	 *   - A URL beginning with `/` or `http` (custom icon uploaded via
	 *     the `resource-uploads` capability)
	 *
	 * Switching between forms is a plain `UPDATE`; no schema migration
	 * is required and no auxiliary discriminator column exists.
	 *
	 * @var string|null
	 */
	protected ?string $tileIcon = null;

	/**
	 * The tile icon type.
	 *
	 * @var string|null
	 */
	protected ?string $tileIconType = null;

	/**
	 * The tile background color.
	 *
	 * @var string|null
	 */
	protected ?string $tileBackgroundColor = null;

	/**
	 * The tile text color.
	 *
	 * @var string|null
	 */
	protected ?string $tileTextColor = null;

	/**
	 * The tile link type.
	 *
	 * @var string|null
	 */
	protected ?string $tileLinkType = null;

	/**
	 * The tile link value.
	 *
	 * @var string|null
	 */
	protected ?string $tileLinkValue = null;

	/**
	 * The custom widget content blob (JSON-encoded).
	 *
	 * Stores the per-type configuration payload collected by AddWidgetModal
	 * for the 17 registry-driven custom widget types (see
	 * `src/constants/widgetRegistry.js`). The JSON shape is type-specific and
	 * mirrors each widget's `defaultContent`. Existing rows created before
	 * this column existed return an empty array via getContentArray().
	 *
	 * @var string|null
	 */
	protected ?string $content = null;

	/**
	 * Whether this placement requires a mandatory-read acknowledgement
	 * (SMALLINT 0/1). When 0 the placement behaves exactly as before this
	 * capability existed. REQ-ACK-001.
	 *
	 * @var integer
	 */
	protected int $requiresAcknowledgement = 0;

	/**
	 * The author-supplied sign-off text shown to the recipient (e.g.
	 * "I have read and understood the 2026 integriteitscode"). Free-text
	 * content, not an i18n key. REQ-ACK-001.
	 *
	 * @var string|null
	 */
	protected ?string $acknowledgementPrompt = null;

	/**
	 * The optional acknowledgement deadline as a `Y-m-d` date string, or
	 * null for no deadline. Presented but never auto-acknowledged
	 * (REQ-ACK-002). REQ-ACK-001.
	 *
	 * @var string|null
	 */
	protected ?string $acknowledgementDeadline = null;

	/**
	 * Whether bumping the content version re-forces delivery for everyone
	 * (SMALLINT 0/1). REQ-ACK-005.
	 *
	 * @var integer
	 */
	protected int $reacknowledgeOnChange = 0;

	/**
	 * The current content version. Authors bump this when the content
	 * materially changes; with `reacknowledgeOnChange = 1` the item
	 * returns to the unacknowledged state for the new version.
	 * REQ-ACK-001 / REQ-ACK-005.
	 *
	 * @var integer
	 */
	protected int $acknowledgementContentVersion = 1;

	/**
	 * The stable announcement identity (UUID) minted the first time
	 * acknowledgement is required and copied to every cloned placement so
	 * all recipients of one announcement share one identity. Null until a
	 * requirement is set. REQ-ACK-001 (design D2).
	 *
	 * @var string|null
	 */
	protected ?string $announcementKey = null;

	/**
	 * The origin key linking this placement back to the source template's
	 * blueprint placement it was cloned from (by that placement's `id`).
	 * Null means "no known template origin" — either a genuinely
	 * user-added placement, or a copy provisioned before this column
	 * existed. Used by {@see \OCA\LaunchPad\Service\TemplateResyncService}
	 * to distinguish template-origin placements (reconciled on re-sync)
	 * from user-added ones (preserved under the `merge` strategy).
	 * REQ-RESYNC-003 / REQ-RESYNC-004.
	 *
	 * @var integer|null
	 */
	protected ?int $templatePlacementId = null;

	/**
	 * The grid layout (`gridX,gridY,gridWidth,gridHeight`) the template gave
	 * this placement when it was last provisioned or re-synced. Null when
	 * unknown (user-added, or written before the column existed). A `merge`
	 * re-sync keeps the owner's layout when the current layout differs from
	 * it (REQ-RESYNC-007).
	 *
	 * @var string|null
	 */
	protected ?string $syncedLayout = null;

	/**
	 * Whether all include rules must match (`all`) or one is enough (`any`).
	 * NULL, from a row written before the column existed, reads as `any`.
	 * REQ-VIS-014.
	 *
	 * @var string|null
	 */
	protected ?string $ruleMatch = self::RULE_MATCH_ANY;

	/**
	 * The per-widget version, raised by one on every save
	 * (building-edit-a-dashboard-together, task 1.2).
	 *
	 * @var integer
	 */
	protected int $version = 0;

	/**
	 * The creation timestamp as string.
	 *
	 * @var string|null
	 */
	protected ?string $createdAt = null;

	/**
	 * The update timestamp as string.
	 *
	 * @var string|null
	 */
	protected ?string $updatedAt = null;

	/**
	 * Constructor
	 *
	 * Registers column types for proper ORM handling.
	 * Note: Boolean columns are SMALLINT in DB (0/1).
	 *
	 * @return void
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'dashboardId', type: 'integer');
		$this->addType(fieldName: 'gridX', type: 'integer');
		$this->addType(fieldName: 'gridY', type: 'integer');
		$this->addType(fieldName: 'gridWidth', type: 'integer');
		$this->addType(fieldName: 'gridHeight', type: 'integer');
		$this->addType(fieldName: 'isCompulsory', type: 'integer');
		// SMALLINT in DB (0/1).
		$this->addType(fieldName: 'isVisible', type: 'integer');
		// SMALLINT in DB (0/1).
		$this->addType(fieldName: 'showTitle', type: 'integer');
		// SMALLINT in DB (0/1).
		$this->addType(fieldName: 'sortOrder', type: 'integer');
		$this->addType(fieldName: 'requiresAcknowledgement', type: 'integer');
		// SMALLINT in DB (0/1).
		$this->addType(fieldName: 'reacknowledgeOnChange', type: 'integer');
		// SMALLINT in DB (0/1).
		$this->addType(fieldName: 'acknowledgementContentVersion', type: 'integer');
		$this->addType(fieldName: 'templatePlacementId', type: 'integer');
		$this->addType(fieldName: 'version', type: 'integer');
	}//end __construct()

	/**
	 * Get style config as array.
	 *
	 * @return array The decoded style configuration.
	 */
	public function getStyleConfigArray(): array {
		if (empty($this->styleConfig) === true) {
			return [];
		}

		$decoded = json_decode(json: $this->styleConfig, associative: true);
		if (is_array($decoded) === true) {
			return $decoded;
		}

		return [];
	}//end getStyleConfigArray()

	/**
	 * Set style config from array.
	 *
	 * @param array $config The style configuration array.
	 *
	 * @return void
	 */
	public function setStyleConfigArray(array $config): void {
		// Entity setters resolve via __call which uses $args[0]; named args
		// would break the magic forwarding (see project memory).
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->setStyleConfig(json_encode($config));
	}//end setStyleConfigArray()

	/**
	 * Get the custom widget content blob as an associative array.
	 *
	 * @return array The decoded content payload, or [] when the column is null
	 *               or holds an unparseable string.
	 */
	public function getContentArray(): array {
		if (empty($this->content) === true) {
			return [];
		}

		$decoded = json_decode(json: $this->content, associative: true);
		if (is_array($decoded) === true) {
			return $decoded;
		}

		return [];
	}//end getContentArray()

	/**
	 * Set the custom widget content blob from an associative array. Every
	 * child of a well or a tabs widget gets a key of its own on the way
	 * (REQ-WDG-050), so an embed token can name it.
	 *
	 * @param array $content The content payload to JSON-encode and persist.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function setContentArray(array $content): void {
		$ownerId = null;
		if (is_int($this->id) === true) {
			$ownerId = $this->id;
		}

		$content = (new ContainerChildKeys())->stamp(content: $content, ownerId: $ownerId);
		// Entity setters resolve via __call which uses $args[0]; named args
		// would break the magic forwarding (see project memory).
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->setContent(json_encode($content));
	}//end setContentArray()

	/**
	 * The calculated fields of the widget as a list of field definitions.
	 *
	 * @return list<array<string, mixed>> The fields, [] when the column is null,
	 *                                    unparseable or not a list.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-21
	 */
	public function getCalculatedFieldsArray(): array {
		if (empty($this->calculatedFields) === true) {
			return [];
		}

		$decoded = json_decode(json: $this->calculatedFields, associative: true);
		if (is_array($decoded) === false || array_is_list($decoded) === false) {
			return [];
		}

		return array_values(array_filter($decoded, static fn (mixed $field): bool => is_array($field) === true));
	}//end getCalculatedFieldsArray()

	/**
	 * Store the calculated fields of the widget.
	 *
	 * @param array<array-key, mixed> $fields The field definitions; keys are dropped, non-array entries skipped.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-21
	 */
	public function setCalculatedFieldsArray(array $fields): void {
		$list = array_values(array_filter($fields, static fn (mixed $field): bool => is_array($field) === true));
		// Entity setters resolve via __call which uses $args[0]; named args
		// would break the magic forwarding (see project memory).
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->setCalculatedFields(json_encode($list));
	}//end setCalculatedFieldsArray()

	/**
	 * Properties whose DB column deliberately differs from the derived name.
	 *
	 * Nextcloud rejects any identifier longer than 30 characters during app
	 * install. Entity's default mapping turns `acknowledgementContentVersion`
	 * into `acknowledgement_content_version`, which is 31 — so the app could not
	 * be installed on a fresh Nextcloud at all (existing installs already had
	 * the column and never re-validated it).
	 *
	 * Aliasing here rather than renaming the property is what keeps this an
	 * internal, non-breaking change: the property, every `lib/` caller, the
	 * `jsonSerialize()` output and the `acknowledgementContentVersion` JSON key
	 * that `src/stores/dashboard.js` and API consumers read all stay identical.
	 * Only the physical column is shortened.
	 *
	 * @var array<string, string> Property name => column name.
	 */
	private const COLUMN_ALIASES = [
		'acknowledgementContentVersion' => 'ack_content_version',
	];

	/**
	 * Map a property name to its column, honouring {@see COLUMN_ALIASES}.
	 *
	 * @param string $property The entity property name.
	 *
	 * @return string The database column name.
	 */
	public function propertyToColumn(string $property): string {
		if (isset(self::COLUMN_ALIASES[$property]) === true) {
			return self::COLUMN_ALIASES[$property];
		}

		return parent::propertyToColumn(property: $property);
	}//end propertyToColumn()

	/**
	 * Map a column name back to its property, honouring {@see COLUMN_ALIASES}.
	 *
	 * Both directions must be overridden: QBMapper writes through
	 * propertyToColumn() but hydrates rows through columnToProperty(), so
	 * overriding only one would load the aliased column into a property that
	 * does not exist and silently lose the value.
	 *
	 * @param string $columnName The database column name.
	 *
	 * @return string The entity property name.
	 */
	public function columnToProperty(string $columnName) {
		$property = array_search($columnName, self::COLUMN_ALIASES, true);
		if ($property !== false) {
			return $property;
		}

		return parent::columnToProperty(columnName: $columnName);
	}//end columnToProperty()

	/**
	 * Whether all include rules must match (`all`) or one is enough (`any`).
	 *
	 * @return string `all` or `any`; anything else stored reads as `any`.
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function getRuleMatch(): string {
		return self::normalRuleMatch(value: $this->ruleMatch);
	}//end getRuleMatch()

	/**
	 * A match mode as stored: `all` stays `all`, everything else is `any`.
	 *
	 * @param mixed $value The mode from a payload or a row.
	 *
	 * @return string `all` or `any`.
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public static function normalRuleMatch(mixed $value): string {
		if ($value === self::RULE_MATCH_ALL) {
			return self::RULE_MATCH_ALL;
		}

		return self::RULE_MATCH_ANY;
	}//end normalRuleMatch()

	/**
	 * Serialize to JSON.
	 *
	 * @return array The serialized widget placement.
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function jsonSerialize(): array {
		$data = $this->placementFields() + $this->acknowledgementFields() + [
			'templatePlacementId' => $this->templatePlacementId,
			'version' => $this->version,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];

		// Include tile configuration if this is a tile.
		if ($this->tileType !== null) {
			$data += $this->tileFields();
		}

		return $data;
	}//end jsonSerialize()

	/**
	 * The widget, grid, style and content fields of the wire format.
	 *
	 * @return array<string, mixed> The fields, in wire order.
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private function placementFields(): array {
		return [
			'id' => $this->getId(),
			'dashboardId' => $this->dashboardId,
			'widgetId' => $this->widgetId,
			'gridX' => $this->gridX,
			'gridY' => $this->gridY,
			'gridWidth' => $this->gridWidth,
			'gridHeight' => $this->gridHeight,
			'isCompulsory' => $this->isCompulsory,
			'isVisible' => $this->isVisible,
			'styleConfig' => self::wireObject(blob: $this->getStyleConfigArray()),
			'customTitle' => $this->customTitle,
			'customIcon' => $this->customIcon,
			'content' => self::wireObject(blob: $this->getContentArray()),
			'calculatedFields' => $this->getCalculatedFieldsArray(),
			'showTitle' => $this->showTitle,
			'sortOrder' => $this->sortOrder,
			'ruleMatch' => $this->getRuleMatch(),
		];
	}//end placementFields()

	/**
	 * An associative JSON blob for the wire.
	 *
	 * The styleConfig and content are associative JSON blobs. An empty
	 * blob decodes to a PHP `[]`, which json_encodes to a JSON array
	 * (`[]`) rather than an object (`{}`). Cast the empty case to stdClass
	 * so the wire format is always an object — clients type these fields
	 * as objects and a stray `[]` is truthy-but-wrong on the frontend.
	 *
	 * @param array<string, mixed> $blob The decoded blob.
	 *
	 * @return array<string, mixed>|stdClass The blob, or an empty object.
	 */
	private static function wireObject(array $blob): array|stdClass {
		if (empty($blob) === false) {
			return $blob;
		}

		return new stdClass();
	}//end wireObject()

	/**
	 * The acknowledgement fields of the wire format.
	 *
	 * @return array<string, mixed> The fields, in wire order.
	 */
	private function acknowledgementFields(): array {
		return [
			'requiresAcknowledgement' => $this->requiresAcknowledgement,
			'acknowledgementPrompt' => $this->acknowledgementPrompt,
			'acknowledgementDeadline' => $this->acknowledgementDeadline,
			'reacknowledgeOnChange' => $this->reacknowledgeOnChange,
			'acknowledgementContentVersion' => $this->acknowledgementContentVersion,
			'announcementKey' => $this->announcementKey,
		];
	}//end acknowledgementFields()

	/**
	 * The tile configuration fields of the wire format.
	 *
	 * @return array<string, mixed> The fields, in wire order.
	 */
	private function tileFields(): array {
		return [
			'tileType' => $this->tileType,
			'tileTitle' => $this->tileTitle,
			'tileIcon' => $this->tileIcon,
			'tileIconType' => $this->tileIconType,
			'tileBackgroundColor' => $this->tileBackgroundColor,
			'tileTextColor' => $this->tileTextColor,
			'tileLinkType' => $this->tileLinkType,
			'tileLinkValue' => $this->tileLinkValue,
		];
	}//end tileFields()
}//end class
