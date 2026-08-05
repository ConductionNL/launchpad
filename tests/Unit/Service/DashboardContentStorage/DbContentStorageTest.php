<?php

/**
 * DbContentStorageTest
 *
 * Covers the database-backed DashboardContentStorage implementation
 * (REQ-GFSB-002). Verifies read/write/delete/exists behaviour using
 * mocked DashboardMapper and LoggerInterface dependencies.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\DashboardContentStorage
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\DashboardContentStorage;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\DashboardContentStorage\DashboardNotFoundException;
use OCA\LaunchPad\Service\DashboardContentStorage\DbContentStorage;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for {@see DbContentStorage}.
 */
class DbContentStorageTest extends TestCase
{

    /**
     * Dashboard mapper mock.
     *
     * @var DashboardMapper&MockObject
     */
    private $dashboardMapper;

    /**
     * PSR-3 logger mock.
     *
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * Service under test.
     *
     * @var DbContentStorage
     */
    private DbContentStorage $storage;

    /**
     * Set up fresh mocks and the service under test for every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
        $this->logger          = $this->createMock(originalClassName: LoggerInterface::class);

        $this->storage = new DbContentStorage(
            dashboardMapper: $this->dashboardMapper,
            logger: $this->logger
        );
    }//end setUp()

    /**
     * Build a Dashboard mock that supports the magic __call getter/setter for `content`.
     *
     * @param string|null $contentValue The value returned by getContent().
     *
     * @return Dashboard&MockObject
     */
    private function makeDashboardMock(?string $contentValue=null): Dashboard
    {
        $mock = $this->getMockBuilder(Dashboard::class)
            ->addMethods(['getContent', 'setContent'])
            ->getMock();

        $mock->method('getContent')->willReturn($contentValue);

        return $mock;
    }//end makeDashboardMock()

    /**
     * read() MUST return the JSON-decoded content array when the dashboard
     * exists and its content column holds a valid JSON object.
     *
     * @return void
     */
    public function testReadReturnsDecodedContent(): void
    {
        $dashboard = $this->makeDashboardMock(
            contentValue: (string) json_encode(['widgets' => []])
        );

        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $result = $this->storage->read(uuid: 'test-uuid');

        $this->assertSame(expected: ['widgets' => []], actual: $result);
    }//end testReadReturnsDecodedContent()

    /**
     * read() MUST throw DashboardNotFoundException when the mapper raises
     * DoesNotExistException for the given UUID.
     *
     * @return void
     */
    public function testReadThrowsDashboardNotFoundExceptionWhenUuidMissing(): void
    {
        $this->dashboardMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->expectException(exception: DashboardNotFoundException::class);

        $this->storage->read(uuid: 'missing-uuid');
    }//end testReadThrowsDashboardNotFoundExceptionWhenUuidMissing()

    /**
     * read() MUST return an empty array when the dashboard row exists but
     * its content column is null.
     *
     * @return void
     */
    public function testReadReturnsEmptyArrayWhenContentIsNull(): void
    {
        $dashboard = $this->makeDashboardMock(contentValue: null);

        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $result = $this->storage->read(uuid: 'test-uuid');

        $this->assertSame(expected: [], actual: $result);
    }//end testReadReturnsEmptyArrayWhenContentIsNull()

    /**
     * write() MUST call mapper->update() exactly once after encoding and
     * setting the content on the retrieved dashboard entity.
     *
     * @return void
     */
    public function testWriteCallsMapperUpdate(): void
    {
        $dashboard = $this->getMockBuilder(Dashboard::class)
            ->addMethods(['getContent', 'setContent'])
            ->getMock();

        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $this->dashboardMapper
            ->expects($this->once())
            ->method('update');

        $this->storage->write(uuid: 'test-uuid', content: ['widgets' => []]);
    }//end testWriteCallsMapperUpdate()

    /**
     * delete() MUST call mapper->update() once when the dashboard exists.
     *
     * @return void
     */
    public function testDeleteCallsUpdate(): void
    {
        $dashboard = $this->getMockBuilder(Dashboard::class)
            ->addMethods(['getContent', 'setContent'])
            ->getMock();

        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $this->dashboardMapper
            ->expects($this->once())
            ->method('update');

        $this->storage->delete(uuid: 'test-uuid');
    }//end testDeleteCallsUpdate()

    /**
     * delete() MUST be a no-op (no exception, no update) when the UUID
     * does not exist — soft-delete semantics per interface contract.
     *
     * @return void
     */
    public function testDeleteNoOpsWhenUuidNotFound(): void
    {
        $this->dashboardMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->dashboardMapper
            ->expects($this->never())
            ->method('update');

        // No exception expected.
        $this->storage->delete(uuid: 'missing-uuid');

        $this->assertTrue(condition: true);
    }//end testDeleteNoOpsWhenUuidNotFound()

    /**
     * exists() MUST return true when the dashboard has a non-empty content
     * column value.
     *
     * @return void
     */
    public function testExistsReturnsTrueWhenContentNotEmpty(): void
    {
        $dashboard = $this->makeDashboardMock(contentValue: '{}');

        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $this->assertTrue(condition: $this->storage->exists(uuid: 'test-uuid'));
    }//end testExistsReturnsTrueWhenContentNotEmpty()

    /**
     * exists() MUST return false (without throwing) when the mapper raises
     * DoesNotExistException for the given UUID.
     *
     * @return void
     */
    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $this->dashboardMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->assertFalse(condition: $this->storage->exists(uuid: 'missing-uuid'));
    }//end testExistsReturnsFalseWhenNotFound()
}//end class
