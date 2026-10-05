<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\AttentionDeclarationValidator;
use OCA\LaunchPad\Service\AttentionSourceService;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The attention feed's server half (REQ-ATT-001): which apps are read, what a
 * valid declaration becomes, and what happens to a broken one.
 *
 * The declarations are real files in real app folders under a temp directory.
 * The four valid ones are the files the proposal asks dossiq, pipelinq,
 * decidiq and learniq to add, so the contract is tested with its first users.
 */
class AttentionSourceServiceTest extends TestCase {
	private string $root;

	/** @var array<int, string> Apps enabled for the user. */
	private array $enabled = [];

	/** @var array<string, array<string, string>> Per app: English => translated. */
	private array $translations = [];

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/launchpad-attention-' . bin2hex(random_bytes(4));
		mkdir($this->root);
	}

	protected function tearDown(): void {
		foreach (glob($this->root . '/*/appinfo/attention.json') ?: [] as $file) {
			unlink($file);
		}
		foreach (glob($this->root . '/*/appinfo') ?: [] as $dir) {
			rmdir($dir);
		}
		foreach (glob($this->root . '/*') ?: [] as $dir) {
			rmdir($dir);
		}
		rmdir($this->root);
		parent::tearDown();
	}

	/**
	 * Install an app folder, with or without a declaration.
	 *
	 * @param string $appId The app id.
	 * @param array<string, mixed>|string|null $declaration Decoded file, raw text, or none.
	 */
	private function installApp(string $appId, array|string|null $declaration, bool $enabled = true): void {
		mkdir($this->root . '/' . $appId . '/appinfo', 0777, true);
		if ($declaration !== null) {
			file_put_contents(
				$this->root . '/' . $appId . '/appinfo/attention.json',
				is_string($declaration) ? $declaration : json_encode($declaration)
			);
		}
		if ($enabled === true) {
			$this->enabled[] = $appId;
		}
	}

	private function makeService(): AttentionSourceService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getEnabledAppsForUser')->willReturnCallback(fn (): array => $this->enabled);
		$appManager->method('getAppPath')->willReturnCallback(
			function (string $appId): string {
				if (is_dir($this->root . '/' . $appId) === false) {
					throw new AppPathNotFoundException('no ' . $appId);
				}
				return $this->root . '/' . $appId;
			}
		);
		$appManager->method('getAppInfo')->willReturnCallback(
			static fn (string $appId): ?array => $appId === 'nameless' ? null : ['name' => ucfirst($appId)]
		);

		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturnCallback(
			function (string $appId): IL10N {
				$l10n = $this->createMock(IL10N::class);
				$l10n->method('t')->willReturnCallback(
					fn (string $text): string => $this->translations[$appId][$text] ?? $text
				);
				return $l10n;
			}
		);

		return new AttentionSourceService(
			appManager: $appManager,
			l10nFactory: $factory,
			logger: new NullLogger(),
		);
	}

	/**
	 * The declaration the design asks dossiq to ship.
	 *
	 * @return array<string, mixed>
	 */
	private static function dossiq(): array {
		return ['version' => 1, 'items' => [[
			'id' => 'cases-past-deadline',
			'title' => 'Cases past their deadline',
			'reason' => '{value} of your cases are past their deadline or end today.',
			'severity' => 'error',
			'source' => ['register' => 'dossiq', 'schema' => 'case', 'filter' => [
				'assignee' => '@me',
				'isFinalStatus' => false,
				'statusHiddenInLists' => false,
				'isDraft' => false,
				'deadline[lt]' => '@today+1d',
			]],
			'action' => ['label' => 'Open these cases', 'path' => '/cases'],
		]]];
	}

	/**
	 * A minimal valid item, to break one field at a time.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 *
	 * @return array<string, mixed>
	 */
	private static function item(array $overrides = []): array {
		return array_replace([
			'id' => 'open-things',
			'title' => 'Open things',
			'reason' => '{value} things are open.',
			'source' => ['register' => 'reg', 'schema' => 'thing'],
			'action' => ['label' => 'Open them', 'path' => '/things'],
		], $overrides);
	}

	public function testAnEnabledAppWithADeclarationIsOffered(): void {
		$this->installApp('dossiq', self::dossiq());
		$this->translations['dossiq'] = [
			'Cases past their deadline' => 'Zaken over de termijn',
			'{value} of your cases are past their deadline or end today.'
				=> '{value} van uw zaken zijn over de termijn of lopen vandaag af.',
			'Open these cases' => 'Deze zaken openen',
		];

		$result = $this->makeService()->collect($this->createMock(IUser::class));

		self::assertSame([], $result['invalid']);
		self::assertSame([[
			'id' => 'cases-past-deadline',
			'title' => 'Zaken over de termijn',
			'reason' => '{value} van uw zaken zijn over de termijn of lopen vandaag af.',
			'severity' => 'error',
			'source' => ['register' => 'dossiq', 'schema' => 'case', 'filter' => [
				'assignee' => '@me',
				'isFinalStatus' => false,
				'statusHiddenInLists' => false,
				'isDraft' => false,
				'deadline[lt]' => '@today+1d',
			]],
			'op' => 'gt',
			'value' => 0,
			'action' => ['label' => 'Deze zaken openen', 'path' => '/apps/dossiq/cases'],
			'appId' => 'dossiq',
			'appName' => 'Dossiq',
		]], $result['sources']);
	}

	public function testAnAppWithoutTheFileDoesNotAppear(): void {
		$this->installApp('files', null);
		// Enabled, but with no folder this instance can resolve.
		$this->enabled[] = 'pipelinq';
		$this->installApp('dossiq', self::dossiq());

		$result = $this->makeService()->collect($this->createMock(IUser::class));

		self::assertSame(['dossiq'], array_column($result['sources'], 'appId'));
		self::assertSame([], $result['invalid']);
	}

	public function testOnlyAppsEnabledForTheUserAreRead(): void {
		$this->installApp('dossiq', self::dossiq());
		$decidiq = self::dossiq();
		$decidiq['items'][0]['id'] = 'decisions-open-for-voting';
		$this->installApp('decidiq', $decidiq, enabled: false);

		$result = $this->makeService()->collect($this->createMock(IUser::class));

		self::assertSame(['dossiq'], array_column($result['sources'], 'appId'));
		self::assertSame([], $result['invalid']);
	}

	public function testABrokenDeclarationIsReportedAndTheOthersStillWork(): void {
		$this->installApp('dossiq', self::dossiq());
		$this->installApp('learniq', ['version' => 1, 'items' => [
			self::item(),
			self::item(['id' => 'late', 'source' => [
				'register' => 'learniq',
				'schema' => 'Assignment',
				'filter' => ['deadline' => ['lt' => '@today']],
			]]),
		]]);
		$this->installApp('nameless', '{not json');

		$result = $this->makeService()->collect($this->createMock(IUser::class));

		self::assertSame(['dossiq'], array_column($result['sources'], 'appId'), 'no learniq item is offered in part');
		self::assertCount(2, $result['invalid']);
		self::assertSame('learniq', $result['invalid'][0]['appId']);
		self::assertSame('Learniq', $result['invalid'][0]['appName']);
		self::assertStringContainsString('item "late"', $result['invalid'][0]['message']);
		self::assertStringContainsString('filter "deadline"', $result['invalid'][0]['message']);
		self::assertStringContainsString('"deadline[lt]"', $result['invalid'][0]['message']);
		self::assertSame('nameless', $result['invalid'][1]['appId']);
		self::assertSame('nameless', $result['invalid'][1]['appName'], 'an app without a name shows its id');
		self::assertStringContainsString('not valid JSON', $result['invalid'][1]['message']);
	}

	/**
	 * Every rule of REQ-ATT-001, one broken field at a time, with the reason
	 * the other app's developer will read.
	 *
	 * @return array<string, array{0: array<string, mixed>|string, 1: string}>
	 */
	public static function brokenDeclarations(): array {
		$one = static fn (array $overrides): array => ['version' => 1, 'items' => [self::item($overrides)]];

		return [
			'no version' => [['items' => []], '"version": 1'],
			'a later version' => [['version' => 2, 'items' => []], '"version": 1'],
			'items is an object' => [['version' => 1, 'items' => ['a' => self::item()]], '"items" must be a list'],
			'items missing' => [['version' => 1], '"items" must be a list'],
			'eleven items' => [
				['version' => 1, 'items' => array_map(
					static fn (int $i): array => self::item(['id' => 'i' . $i]),
					range(1, 11)
				)],
				'at most 10 items, found 11',
			],
			'an item that is a text' => [['version' => 1, 'items' => ['x']], 'item 0 must be an object'],
			'an id with capitals' => [$one(['id' => 'OpenThings']), '"id" must be'],
			'the same id twice' => [['version' => 1, 'items' => [self::item(), self::item()]], 'declared twice'],
			'no title' => [$one(['title' => ' ']), '"title" must be a text'],
			'no reason' => [$one(['reason' => null]), '"reason" must be a text'],
			'no action label' => [$one(['action' => ['path' => '/things']]), '"action.label" must be a text'],
			'an unknown severity' => [$one(['severity' => 'critical']), '"severity" must be'],
			'an unknown operator' => [$one(['op' => 'contains']), '"op" must be'],
			'a value that is a text' => [$one(['value' => '3']), '"value" must be a number'],
			'no register' => [$one(['source' => ['schema' => 'thing']]), '"source.register" must be a slug'],
			'a schema with a slash' => [
				$one(['source' => ['register' => 'reg', 'schema' => 'a/b']]),
				'"source.schema" must be a slug',
			],
			'a filter that is a list' => [
				$one(['source' => ['register' => 'reg', 'schema' => 'thing', 'filter' => ['a', 'b']]]),
				'"source.filter" must be an object',
			],
			'a nested operator' => [
				$one(['source' => ['register' => 'reg', 'schema' => 'thing', 'filter' => ['due' => ['lt' => '@today']]]]),
				'filter "due"',
			],
			'a list holding an object' => [
				$one(['source' => ['register' => 'reg', 'schema' => 'thing', 'filter' => ['status' => [['a' => 1]]]]]),
				'filter "status"',
			],
			'a path that climbs out' => [$one(['action' => ['label' => 'x', 'path' => '/../settings/admin']]), '"action.path"'],
			'a path to another host' => [$one(['action' => ['label' => 'x', 'path' => '//evil.example/x']]), '"action.path"'],
			'a path with a query' => [$one(['action' => ['label' => 'x', 'path' => '/things?a=1']]), '"action.path"'],
			'a path with a fragment' => [$one(['action' => ['label' => 'x', 'path' => '/things#a']]), '"action.path"'],
			'a path without a slash' => [$one(['action' => ['label' => 'x', 'path' => 'things']]), '"action.path"'],
			'a full address' => [$one(['action' => ['label' => 'x', 'path' => 'https://evil.example']]), '"action.path"'],
		];
	}

	/**
	 * @dataProvider brokenDeclarations
	 *
	 * @param array<string, mixed>|string $declaration The broken file.
	 * @param string $reason What the refusal must say.
	 */
	public function testEveryRuleOfTheDeclarationIsChecked(array|string $declaration, string $reason): void {
		$this->installApp('broken', $declaration);

		$result = $this->makeService()->collect($this->createMock(IUser::class));

		self::assertSame([], $result['sources']);
		self::assertCount(1, $result['invalid']);
		self::assertStringContainsString($reason, $result['invalid'][0]['message']);
	}

	/**
	 * The control for the provider above: the item it breaks is valid when
	 * left alone, so each case fails for the field it names and no other.
	 */
	public function testTheMinimalItemIsValidAndGetsItsDefaults(): void {
		$validator = new AttentionDeclarationValidator();

		$items = $validator->check(appId: 'things', raw: (string)json_encode(['version' => 1, 'items' => [
			self::item(),
			self::item(['id' => 'full', 'severity' => 'warning', 'op' => 'gte', 'value' => 2.5, 'source' => [
				'register' => 'reg',
				'schema' => 'thing',
				'filter' => ['status' => ['open', 'pending'], 'count[gte]' => 3, 'mine' => true],
			], 'action' => ['label' => 'Open', 'path' => '/a/b-c/d_e/']]),
		]]));

		self::assertSame('info', $items[0]['severity']);
		self::assertSame('gt', $items[0]['op']);
		self::assertSame(0, $items[0]['value']);
		self::assertSame([], $items[0]['source']['filter']);
		self::assertSame('/apps/things/things', $items[0]['action']['path']);
		self::assertSame('warning', $items[1]['severity']);
		self::assertSame('gte', $items[1]['op']);
		self::assertSame(2.5, $items[1]['value']);
		self::assertSame(['status' => ['open', 'pending'], 'count[gte]' => 3, 'mine' => true], $items[1]['source']['filter']);
		self::assertSame('/apps/things/a/b-c/d_e/', $items[1]['action']['path']);
	}

	public function testAnEmptyDeclarationIsValidAndOffersNothing(): void {
		$this->installApp('quiet', ['version' => 1, 'items' => []]);

		$result = $this->makeService()->collect($this->createMock(IUser::class));

		self::assertSame(['sources' => [], 'invalid' => []], $result);
	}

	public function testBrokenJsonThrowsFromTheValidator(): void {
		$this->expectException(InvalidArgumentException::class);
		(new AttentionDeclarationValidator())->check(appId: 'x', raw: '[');
	}
}
