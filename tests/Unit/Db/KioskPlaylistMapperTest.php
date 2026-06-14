<?php

/**
 * KioskPlaylistMapperTest
 *
 * Unit tests for KioskPlaylistMapper covering URL hydration, active-only
 * token lookup (revoked_at IS NULL filter), and soft-revoke UPDATE issuance.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KioskPlaylistMapper.
 *
 * Database execution is not exercised (no NC container); the query-builder is
 * mocked to assert the shape of the queries the mapper composes.
 */
class KioskPlaylistMapperTest extends TestCase
{

    /** @var IDBConnection&MockObject */
    private $db;

    /** @var IURLGenerator&MockObject */
    private $urlGenerator;

    private KioskPlaylistMapper $mapper;

    protected function setUp(): void
    {
        $this->db           = $this->createMock(IDBConnection::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);

        $this->mapper = new KioskPlaylistMapper(
            db: $this->db,
            urlGenerator: $this->urlGenerator,
        );
    }

    /**
     * Build a fully-chained IQueryBuilder mock returning itself for every
     * fluent call, with a mocked expression builder.
     *
     * @return IQueryBuilder&MockObject
     */
    private function makeQueryBuilder(): IQueryBuilder
    {
        $expr = $this->createMock(IExpressionBuilder::class);
        $expr->method('eq')->willReturn('eq-expr');
        $expr->method('isNull')->willReturn('isnull-expr');

        $qb = $this->createMock(IQueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('createNamedParameter')->willReturn('?');
        $qb->method('executeStatement')->willReturn(1);

        return $qb;
    }

    public function testMapperInstantiates(): void
    {
        $this->assertInstanceOf(KioskPlaylistMapper::class, $this->mapper);
    }

    public function testHydrateUrlPopulatesUrlFromRouteGenerator(): void
    {
        // findByToken hydrates the computed URL via linkToRouteAbsolute.
        $playlist = new KioskPlaylist();
        $playlist->setToken('tok-xyz');

        $this->urlGenerator
            ->expects($this->once())
            ->method('linkToRouteAbsolute')
            ->with(
                routeName: 'mydash.kiosk.render',
                arguments: ['token' => 'tok-xyz']
            )
            ->willReturn('https://example.com/apps/mydash/kiosk/tok-xyz');

        $qb = $this->makeQueryBuilder();
        $this->db->method('getQueryBuilder')->willReturn($qb);

        // Stub findEntity (protected QBMapper helper) so no DB row fetch occurs.
        $mapper = $this->getMockBuilder(KioskPlaylistMapper::class)
            ->setConstructorArgs([$this->db, $this->urlGenerator])
            ->onlyMethods(['findEntity'])
            ->getMock();
        $mapper->method('findEntity')->willReturn($playlist);

        $result = $mapper->findByToken(token: 'tok-xyz');

        $this->assertSame(
            'https://example.com/apps/mydash/kiosk/tok-xyz',
            $result->getUrl()
        );
    }

    public function testFindByTokenFiltersRevokedAtIsNull(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setToken('tok');

        $expr = $this->createMock(IExpressionBuilder::class);
        $expr->expects($this->once())->method('eq')->willReturn('eq-expr');
        // The active-only filter — revoked_at IS NULL — must be applied.
        $expr->expects($this->once())
            ->method('isNull')
            ->with(x: 'revoked_at')
            ->willReturn('isnull-expr');

        $qb = $this->createMock(IQueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('isnull-expr')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('createNamedParameter')->willReturn('?');

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $mapper = $this->getMockBuilder(KioskPlaylistMapper::class)
            ->setConstructorArgs([$this->db, $this->urlGenerator])
            ->onlyMethods(['findEntity'])
            ->getMock();
        $mapper->method('findEntity')->willReturn($playlist);

        $mapper->findByToken(token: 'tok');
    }

    public function testSoftRevokeIssuesUpdate(): void
    {
        $expr = $this->createMock(IExpressionBuilder::class);
        $expr->method('eq')->willReturn('eq-expr');
        $expr->method('isNull')->willReturn('isnull-expr');

        $qb = $this->createMock(IQueryBuilder::class);
        $qb->expects($this->once())->method('update')->with(update: 'mydash_kiosk_playlists')->willReturnSelf();
        $qb->expects($this->once())->method('set')->with(key: 'revoked_at')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('createNamedParameter')->willReturn('?');
        $qb->expects($this->once())->method('executeStatement')->willReturn(1);

        $this->db->method('getQueryBuilder')->willReturn($qb);

        $this->mapper->softRevoke(id: 42);
    }
}//end class
