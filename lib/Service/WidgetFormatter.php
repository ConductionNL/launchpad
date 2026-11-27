<?php

/**
 * WidgetFormatter
 *
 * Service for formatting Nextcloud widgets into API response arrays.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IOptionWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\IWidget;
use Psr\Log\LoggerInterface;

/**
 * Service for formatting Nextcloud widgets into API response arrays.
 */
class WidgetFormatter {
	/**
	 * The placeholder shapes a widget may declare (REQ-FPS-009).
	 */
	public const PLACEHOLDER_SHAPES = ['rows', 'lines', 'block', 'tiles', 'counter', 'heading'];

	/**
	 * The lowest and highest placeholder count a widget may declare.
	 */
	public const PLACEHOLDER_COUNT_MIN = 1;
	public const PLACEHOLDER_COUNT_MAX = 24;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface|null $logger Notes a dropped placeholder declaration at debug level.
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-1
	 */
	public function __construct(
		private readonly ?LoggerInterface $logger = null,
	) {
	}//end __construct()

	/**
	 * Format a widget for API response.
	 *
	 * @param IWidget $widget The widget to format.
	 * @param string $userId The current user ID.
	 *
	 * @return array The formatted widget data.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-32
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 */
	public function format(IWidget $widget, string $userId): array {
		$data = $this->buildBaseData(widget: $widget);

		$this->applyIconUrl(widget: $widget, data: $data);
		$this->applyApiVersions(widget: $widget, data: $data);
		$this->applyButtons(
			widget: $widget,
			userId: $userId,
			data: $data
		);
		$this->applyOptions(widget: $widget, data: $data);
		$this->applyReloadInterval(widget: $widget, data: $data);

		return $data;
	}//end format()

	/**
	 * Build base widget data array.
	 *
	 * @param IWidget $widget The widget.
	 *
	 * @return array The base data.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-1
	 */
	private function buildBaseData(IWidget $widget): array {
		$data = [
			'id' => $widget->getId(),
			'title' => $widget->getTitle(),
			'order' => $widget->getOrder(),
			'iconClass' => $widget->getIconClass(),
			'iconUrl' => null,
			'widgetUrl' => $widget->getUrl(),
			'itemIconsRound' => false,
			'itemApiVersions' => [],
			'reloadInterval' => 0,
			'buttons' => [],
		];

		$placeholder = $this->readPlaceholder(widget: $widget);
		if ($placeholder !== null) {
			$data['placeholder'] = $placeholder;
		}

		return $data;
	}//end buildBaseData()

	/**
	 * Read the placeholder shape a widget declares through a duck-typed `getPlaceholder()`.
	 *
	 * The value is a shape name or `['shape' => <name>, 'count' => 1..24]`. A widget without the
	 * method declares nothing; an invalid value is dropped with a debug log naming the widget id,
	 * so the widget falls back to rows (REQ-FPS-009).
	 *
	 * @param IWidget $widget The widget.
	 *
	 * @return string|array|null The validated declaration, or null when there is none.
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-1
	 */
	private function readPlaceholder(IWidget $widget): string|array|null {
		if (method_exists($widget, 'getPlaceholder') === false) {
			return null;
		}

		try {
			$declared = $widget->getPlaceholder();
		} catch (\Throwable $e) {
			$this->dropPlaceholder(widget: $widget, reason: $e->getMessage());
			return null;
		}

		$valid = $this->validatePlaceholder(declared: $declared);
		if ($valid === null) {
			$this->dropPlaceholder(widget: $widget, reason: 'not a valid placeholder declaration');
		}

		return $valid;
	}//end readPlaceholder()

	/**
	 * Validate a declared placeholder value.
	 *
	 * @param mixed $declared The value `getPlaceholder()` returned.
	 *
	 * @return string|array|null The value as it may be carried, or null when it is invalid.
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-1
	 */
	private function validatePlaceholder(mixed $declared): string|array|null {
		if (is_string($declared) === true) {
			if (in_array($declared, self::PLACEHOLDER_SHAPES, true) === true) {
				return $declared;
			}

			return null;
		}

		if (is_array($declared) === false
			|| is_string($declared['shape'] ?? null) === false
			|| in_array($declared['shape'], self::PLACEHOLDER_SHAPES, true) === false
		) {
			return null;
		}

		if (array_key_exists('count', $declared) === false) {
			return ['shape' => $declared['shape']];
		}

		if ($this->isValidCount(count: $declared['count']) === false) {
			return null;
		}

		return ['shape' => $declared['shape'], 'count' => $declared['count']];
	}//end validatePlaceholder()

	/**
	 * Whether a declared placeholder count is an integer from 1 to 24.
	 *
	 * @param mixed $count The declared count.
	 *
	 * @return boolean
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-1
	 */
	private function isValidCount(mixed $count): bool {
		return is_int($count) === true
			&& $count >= self::PLACEHOLDER_COUNT_MIN
			&& $count <= self::PLACEHOLDER_COUNT_MAX;
	}//end isValidCount()

	/**
	 * Log a dropped placeholder declaration at debug level.
	 *
	 * @param IWidget $widget The widget whose declaration is dropped.
	 * @param string  $reason Why it is dropped.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-1
	 */
	private function dropPlaceholder(IWidget $widget, string $reason): void {
		$this->logger?->debug(
			'LaunchPad: placeholder of widget {widget} dropped: {reason}',
			['widget' => $widget->getId(), 'reason' => $reason, 'app' => 'launchpad']
		);
	}//end dropPlaceholder()

	/**
	 * Apply icon URL if widget supports it.
	 *
	 * @param IWidget $widget The widget.
	 * @param array $data The data array (passed by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 */
	private function applyIconUrl(IWidget $widget, array &$data): void {
		if ($widget instanceof IIconWidget) {
			$data['iconUrl'] = $widget->getIconUrl();
		}
	}//end applyIconUrl()

	/**
	 * Apply API versions supported by the widget.
	 *
	 * @param IWidget $widget The widget.
	 * @param array $data The data array (passed by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 */
	private function applyApiVersions(
		IWidget $widget,
		array &$data,
	): void {
		if ($widget instanceof IAPIWidget) {
			$data['itemApiVersions'][] = 1;
		}

		if ($widget instanceof IAPIWidgetV2) {
			$data['itemApiVersions'][] = 2;
		}
	}//end applyApiVersions()

	/**
	 * Apply button configuration if widget supports it.
	 *
	 * @param IWidget $widget The widget.
	 * @param string $userId The user ID.
	 * @param array $data The data array (passed by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 */
	private function applyButtons(
		IWidget $widget,
		string $userId,
		array &$data,
	): void {
		if ($widget instanceof IButtonWidget === false) {
			return;
		}

		$buttons = $widget->getWidgetButtons(userId: $userId);
		$data['buttons'] = array_map(
			callback: function ($btn) {
				return [
					'type' => $btn->getType(),
					'text' => $btn->getText(),
					'link' => $btn->getLink(),
				];
			},
			array: $buttons
		);
	}//end applyButtons()

	/**
	 * Apply widget options if supported.
	 *
	 * @param IWidget $widget The widget.
	 * @param array $data The data array (passed by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 */
	private function applyOptions(IWidget $widget, array &$data): void {
		if ($widget instanceof IOptionWidget) {
			$options = $widget->getWidgetOptions();
			$data['itemIconsRound'] = $options->withRoundItemIcons();
		}
	}//end applyOptions()

	/**
	 * Apply reload interval if widget supports it.
	 *
	 * @param IWidget $widget The widget.
	 * @param array $data The data array (passed by reference).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-widgets/tasks.md#task-1
	 */
	private function applyReloadInterval(
		IWidget $widget,
		array &$data,
	): void {
		if ($widget instanceof IReloadableWidget) {
			$data['reloadInterval'] = $widget->getReloadInterval();
		}
	}//end applyReloadInterval()
}//end class
