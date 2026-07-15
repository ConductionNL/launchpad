<?php

/**
 * Doctrine DBAL stubs for unit tests.
 *
 * The Nextcloud OCP signature stubs at
 * `vendor/nextcloud/ocp/OCP/IDBConnection.php` and
 * `vendor/nextcloud/ocp/OCP/DB/QueryBuilder/IQueryBuilder.php`
 * reference Doctrine DBAL classes in their type-hints. Doctrine DBAL
 * is provided at runtime by Nextcloud (not as a composer dependency
 * of this app), so when PHPUnit's automatic mock generator
 * introspects `IDBConnection` to build a test double, the autoloader
 * fails on the missing Doctrine classes.
 *
 * Loading this file before `createMock(IDBConnection::class)` defines
 * minimal placeholder classes so the type-hints resolve. Inside the
 * Nextcloud Docker container the real Doctrine classes are already
 * loaded, so the `class_exists` guards turn this into a no-op.
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Doctrine\DBAL {
    if (class_exists(__NAMESPACE__ . '\\ParameterType', false) === false) {
        class ParameterType
        {
            public const NULL         = 0;
            public const INTEGER      = 1;
            public const STRING       = 2;
            public const LARGE_OBJECT = 3;
            public const BOOLEAN      = 5;
            public const BINARY       = 16;
            public const ASCII        = 17;
        }
        class ArrayParameterType
        {
            public const INTEGER = 101;
            public const STRING  = 102;
            public const ASCII   = 103;
            public const BINARY  = 116;
        }
        class Connection
        {
        }
    }
}

namespace Doctrine\DBAL\Types {
    if (class_exists(__NAMESPACE__ . '\\Types', false) === false) {
        class Types
        {
            public const BOOLEAN              = 'boolean';
            public const DATETIME_MUTABLE     = 'datetime';
            public const DATETIME_IMMUTABLE   = 'datetime_immutable';
            public const DATETIMETZ_MUTABLE   = 'datetimetz';
            public const DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
            public const DATE_MUTABLE         = 'date';
            public const DATE_IMMUTABLE       = 'date_immutable';
            public const TIME_MUTABLE         = 'time';
            public const TIME_IMMUTABLE       = 'time_immutable';
        }
    }
}

namespace Doctrine\DBAL\Query\Expression {
    if (class_exists(__NAMESPACE__ . '\\ExpressionBuilder', false) === false) {
        class ExpressionBuilder
        {
            public const EQ  = '=';
            public const NEQ = '<>';
            public const LT  = '<';
            public const LTE = '<=';
            public const GT  = '>';
            public const GTE = '>=';
        }
    }
}

namespace Doctrine\DBAL\Schema {
    if (class_exists(__NAMESPACE__ . '\\Schema', false) === false) {
        class Schema
        {
        }
    }
}

namespace Doctrine\DBAL\Query\Expression {
    if (class_exists(__NAMESPACE__ . '\\ExpressionBuilder', false) === false) {
        class ExpressionBuilder
        {
            public const EQ   = '=';
            public const NEQ  = '<>';
            public const LT   = '<';
            public const LTE  = '<=';
            public const GT   = '>';
            public const GTE  = '>=';
        }
    }
}

namespace OC\DB\QueryBuilder\Sharded {
    if (class_exists(__NAMESPACE__ . '\\CrossShardMoveHelper', false) === false) {
        class CrossShardMoveHelper
        {
        }
        class ShardDefinition
        {
        }
    }
}

namespace OC\Hooks {
    if (interface_exists(__NAMESPACE__ . '\\Emitter', false) === false) {
        /**
         * Minimal stub for OC\Hooks\Emitter referenced by OCP\Files\IRootFolder.
         * Only needed outside the Nextcloud Docker container (where the full
         * runtime is not available). PHPUnit's mock generator requires the
         * interface to be resolvable before it can build a test double.
         */
        interface Emitter
        {
            /**
             * @param string   $scope
             * @param string   $method
             * @param callable $callback
             * @return void
             */
            public function listen(string $scope, string $method, callable $callback): void;

            /**
             * @param string        $scope
             * @param string        $method
             * @param callable|null $callback
             * @return void
             */
            public function removeListener(string $scope, string $method, ?callable $callback=null): void;

            /**
             * @param string $scope
             * @param string $method
             * @param array  $arguments
             * @return void
             */
            public function emit(string $scope, string $method, array $arguments=[]): void;
        }
    }
}

namespace Psr\Http\Client {
    if (interface_exists(__NAMESPACE__ . '\\ClientInterface', false) === false) {
        /**
         * Minimal stub for Psr\Http\Client\ClientInterface.
         *
         * OCP\Http\Client\IClient extends this interface. The real psr/http-client
         * package is a Nextcloud runtime dependency — not a composer dep of this
         * app — so this stub keeps PHPUnit's mock generator happy inside the
         * container where the NC autoloader has not yet registered PSR packages.
         */
        interface ClientInterface
        {
            /**
             * @param \Psr\Http\Message\RequestInterface $request
             * @return \Psr\Http\Message\ResponseInterface
             * @throws \Psr\Http\Client\ClientExceptionInterface
             */
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface;
        }
    }
}

namespace Psr\Http\Message {
    if (interface_exists(__NAMESPACE__ . '\\RequestInterface', false) === false) {
        /** Minimal PSR-7 RequestInterface stub. */
        interface MessageInterface
        {
        }
        interface RequestInterface extends MessageInterface
        {
        }
        interface ResponseInterface extends MessageInterface
        {
        }
        interface StreamInterface
        {
        }
        interface UriInterface
        {
        }
    }
}

namespace Psr\Http\Client {
    if (interface_exists(__NAMESPACE__ . '\\ClientExceptionInterface', false) === false) {
        /** Minimal PSR-18 exception stub. */
        interface ClientExceptionInterface extends \Throwable
        {
        }
    }
}
