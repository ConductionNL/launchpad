<?php

/**
 * AdminSettingsService pinned communities test.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * An administrator pins up to 20 Talk conversations by token (engagement-communities D3).
 */
class AdminSettingsPinnedCommunitiesTest extends TestCase {

	private AdminSettingsService $service;

	private AdminSettingMapper&MockObject $settingMapper;

	protected function setUp(): void {
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->service = new AdminSettingsService(settingMapper: $this->settingMapper);
	}//end setUp()

	public function testSavedTokensAreTrimmedDeduplicatedAndStoredInOrder(): void {
		$this->settingMapper->expects($this->once())
			->method('setSetting')
			->with('pinned_communities', ['vraagict', 'duurzaam42']);

		$this->service->updateSettings(settings: ['pinnedCommunities' => [' vraagict ', '', 'duurzaam42', 'vraagict']]);
	}//end testSavedTokensAreTrimmedDeduplicatedAndStoredInOrder()

	public function testAnEmptyListUnpinsEverything(): void {
		$this->settingMapper->expects($this->once())->method('setSetting')->with('pinned_communities', []);

		$this->service->updateSettings(settings: ['pinnedCommunities' => []]);
	}//end testAnEmptyListUnpinsEverything()

	public function testAbsentPinnedCommunitiesAreLeftAsTheyAre(): void {
		$this->settingMapper->expects($this->never())->method('setSetting');

		$this->service->updateSettings(settings: []);
	}//end testAbsentPinnedCommunitiesAreLeftAsTheyAre()

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function notATokenProvider(): array {
		return [
			'uppercase' => ['VraagICT'],
			'too short' => ['abc'],
			'too long' => [str_repeat('a', 31)],
			'a url' => ['https://cloud.example/call/abcd1234'],
			'a number' => [12345],
		];
	}//end notATokenProvider()

	/**
	 * @dataProvider notATokenProvider
	 */
	public function testAnEntryThatIsNotATokenIsRefusedAndNothingIsStored(mixed $entry): void {
		$this->settingMapper->expects($this->never())->method('setSetting');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is not a Talk conversation token');

		$this->service->updateSettings(settings: ['pinnedCommunities' => ['abcd1234', $entry]]);
	}//end testAnEntryThatIsNotATokenIsRefusedAndNothingIsStored()

	public function testMoreThanTwentyIsRefused(): void {
		$tokens = array_map(static fn (int $n): string => 'room' . $n, range(1, 21));
		$this->settingMapper->expects($this->never())->method('setSetting');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('at most 20 conversations can be pinned');

		$this->service->updateSettings(settings: ['pinnedCommunities' => $tokens]);
	}//end testMoreThanTwentyIsRefused()

	public function testTwentyIsAllowed(): void {
		$tokens = array_map(static fn (int $n): string => 'room' . $n, range(1, 20));

		$this->assertSame($tokens, $this->service->normalisePinnedCommunities(tokens: $tokens));
	}//end testTwentyIsAllowed()

	public function testReadingKeepsOnlyTokensFromAStoredValue(): void {
		$this->settingMapper->method('getAllAsArray')->willReturn(
			['pinned_communities' => ['vraagict', 'NOT A TOKEN', 'vraagict', 42, 'duurzaam42']]
		);

		$this->assertSame(['vraagict', 'duurzaam42'], $this->service->getSettings()['pinnedCommunities']);
	}//end testReadingKeepsOnlyTokensFromAStoredValue()

	public function testAStoredValueThatIsNotAListReadsAsNothingPinned(): void {
		$this->settingMapper->method('getAllAsArray')->willReturn(['pinned_communities' => 'vraagict']);

		$this->assertSame([], $this->service->getSettings()['pinnedCommunities']);
	}//end testAStoredValueThatIsNotAListReadsAsNothingPinned()
}//end class
