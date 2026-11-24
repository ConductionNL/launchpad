/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Read and write the instance's database from a test, through PHP run on the
 * instance with Nextcloud bootstrapped.
 *
 * Some scenarios are about rows no request can write: a view or click bucket
 * on another day, a row older than the retention window, the table's own
 * shape. Every request lands in today's bucket, so those scenarios were
 * excluded as "only today's bucket can be written from outside". That is not
 * so: the suite already runs commands on the instance (`support/occ.ts`), and
 * a PHP script that requires `lib/base.php` gets the same `IDBConnection` the
 * app uses, on whatever database the instance runs. This file is that script
 * runner. It seeds and reads; what the product does with the rows is still
 * driven and judged through the product's own endpoints and jobs.
 *
 * The script is sent on standard input, so it needs no file on the instance.
 * Its arguments travel as base64-encoded JSON inside the script, and its
 * result comes back as one JSON line after a marker, so whatever PHP prints
 * while booting cannot be mistaken for it.
 *
 * The server root (the folder holding `lib/base.php`) is
 * `LAUNCHPAD_E2E_SERVER_ROOT` when set, else the directory commands run in on
 * the instance, which is the server root both in CI (occ runs from there) and
 * in the official container image (its working directory is /var/www/html).
 */

import { describeRun, onInstanceWithInput } from './occ.ts'

const MARKER = '@@LAUNCHPAD_E2E_PHP@@'

/**
 * Run a PHP body on the instance with Nextcloud booted and return what it put
 * in `$out`.
 *
 * Inside the body `$in` holds `args`, `$db` is the `OCP\IDBConnection` and
 * `$prefix` the table prefix (`dbtableprefix`, usually `oc_`).
 *
 * @param body PHP statements, without an opening tag.
 * @param args values the body reads from `$in`.
 * @return the decoded `$out`.
 */
export async function instancePhp<T>(
	body: string,
	args: Record<string, unknown> = {},
): Promise<T> {
	const payload = Buffer.from(
		JSON.stringify({
			root: (process.env.LAUNCHPAD_E2E_SERVER_ROOT ?? '').trim(),
			args,
		}),
	).toString('base64')

	const script = `<?php
$payload = json_decode(base64_decode('${payload}'), true);
$root = $payload['root'] !== '' ? $payload['root'] : getcwd();
if (!is_file($root . '/lib/base.php')) {
	fwrite(STDERR, 'no lib/base.php under ' . $root . '; set LAUNCHPAD_E2E_SERVER_ROOT' . PHP_EOL);
	exit(97);
}
require_once $root . '/lib/base.php';
$in = $payload['args'];
$db = \\OCP\\Server::get(\\OCP\\IDBConnection::class);
$prefix = \\OCP\\Server::get(\\OCP\\IConfig::class)->getSystemValueString('dbtableprefix', 'oc_');
$out = null;
${body}
echo PHP_EOL . '${MARKER}' . json_encode($out) . PHP_EOL;
`

	const result = await onInstanceWithInput(script, 'php')
	const line = result.stdout
		.split('\n')
		.find((l) => l.startsWith(MARKER))
	if (result.code !== 0 || line === undefined) {
		throw new Error(`PHP on the instance failed\n${describeRun(result)}`)
	}
	return JSON.parse(line.slice(MARKER.length)) as T
}

/** One column as the database reports it. */
export interface ColumnShape {
	/** The DBAL type name, e.g. `integer`, `bigint`, `string`, `date`. */
	type: string
	/** Whether the column refuses NULL. */
	notnull: boolean
	/** The default, as a string, or null for none. */
	default: string | null
	/** Whether the column is auto-incremented. */
	autoincrement: boolean
	/** The declared length, or null. */
	length: number | null
}

/** One index as the database reports it. */
export interface IndexShape {
	/** The columns, in index order. */
	columns: string[]
	/** Whether it refuses duplicates. */
	unique: boolean
	/** Whether it is the primary key. */
	primary: boolean
}

/** A table's columns and indexes, as introspected from the live database. */
export interface TableShape {
	/** The full table name, prefix included. */
	name: string
	/** Columns by lower-case name. */
	columns: Record<string, ColumnShape>
	/** Indexes by lower-case name. */
	indexes: Record<string, IndexShape>
	/** Foreign keys, as the local columns and the referenced table. */
	foreignKeys: Array<{ columns: string[]; foreignTable: string; foreignColumns: string[] }>
}

/**
 * Introspect a table on the instance's database, the schema the migrations
 * actually left there.
 *
 * @param table the table name without prefix, e.g. `launchpad_dashboard_views`.
 * @return its shape, or null when the table does not exist.
 */
export async function tableShape(table: string): Promise<TableShape | null> {
	return instancePhp<TableShape | null>(
		`
$schema = $db->createSchema();
$name = $prefix . $in['table'];
if ($schema->hasTable($name)) {
	$t = $schema->getTable($name);
	$typeName = function ($type) {
		try {
			return \\Doctrine\\DBAL\\Types\\Type::getTypeRegistry()->lookupName($type);
		} catch (\\Throwable $e) {
			return method_exists($type, 'getName') ? $type->getName() : get_class($type);
		}
	};
	$columns = [];
	foreach ($t->getColumns() as $c) {
		$columns[strtolower($c->getName())] = [
			'type' => $typeName($c->getType()),
			'notnull' => $c->getNotnull(),
			'default' => $c->getDefault() === null ? null : (string) $c->getDefault(),
			'autoincrement' => $c->getAutoincrement(),
			'length' => $c->getLength(),
		];
	}
	$indexes = [];
	foreach ($t->getIndexes() as $i) {
		$indexes[strtolower($i->getName())] = [
			'columns' => array_map('strtolower', $i->getColumns()),
			'unique' => $i->isUnique(),
			'primary' => $i->isPrimary(),
		];
	}
	$fks = [];
	foreach ($t->getForeignKeys() as $fk) {
		$fks[] = [
			'columns' => array_map('strtolower', $fk->getLocalColumns()),
			'foreignTable' => $fk->getForeignTableName(),
			'foreignColumns' => array_map('strtolower', $fk->getForeignColumns()),
		];
	}
	$out = ['name' => $name, 'columns' => $columns, 'indexes' => $indexes, 'foreignKeys' => $fks];
}
`,
		{ table },
	)
}

/**
 * Select rows from a table where every given column equals its value.
 *
 * @param table the table name without prefix.
 * @param where column => value.
 * @return the rows, as the database returns them.
 */
export async function selectRows(
	table: string,
	where: Record<string, string | number>,
): Promise<Array<Record<string, string | number | null>>> {
	return instancePhp(
		`
$qb = $db->getQueryBuilder();
$qb->select('*')->from($in['table']);
foreach ($in['where'] as $col => $val) {
	$qb->andWhere($qb->expr()->eq($col, $qb->createNamedParameter($val)));
}
$res = $qb->executeQuery();
$out = $res->fetchAll();
$res->closeCursor();
`,
		{ table, where },
	)
}

/**
 * Insert one row.
 *
 * @param table the table name without prefix.
 * @param values column => value. A DATE goes in as `YYYY-MM-DD`, the way
 *        the app's own mappers bind it.
 * @return null when it went in, else the database's refusal: the
 *         `OCP\DB\Exception` reason code, whether that reason is a unique
 *         constraint violation, and the message.
 */
export async function insertRow(
	table: string,
	values: Record<string, string | number>,
): Promise<{ reason: number | null; unique: boolean; message: string } | null> {
	return instancePhp(
		`
$qb = $db->getQueryBuilder();
$qb->insert($in['table']);
foreach ($in['values'] as $col => $val) {
	$qb->setValue($col, $qb->createNamedParameter($val));
}
try {
	$qb->executeStatement();
} catch (\\OCP\\DB\\Exception $e) {
	$out = [
		'reason' => $e->getReason(),
		'unique' => $e->getReason() === \\OCP\\DB\\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION,
		'message' => $e->getMessage(),
	];
}
`,
		{ table, values },
	)
}

/**
 * Update rows: set `set` where every `where` column equals its value.
 *
 * @param table the table name without prefix.
 * @param set column => new value.
 * @param where column => value.
 * @return how many rows changed.
 */
export async function updateRows(
	table: string,
	set: Record<string, string | number>,
	where: Record<string, string | number>,
): Promise<number> {
	return instancePhp<number>(
		`
$qb = $db->getQueryBuilder();
$qb->update($in['table']);
foreach ($in['set'] as $col => $val) {
	$qb->set($col, $qb->createNamedParameter($val));
}
foreach ($in['where'] as $col => $val) {
	$qb->andWhere($qb->expr()->eq($col, $qb->createNamedParameter($val)));
}
$out = $qb->executeStatement();
`,
		{ table, set, where },
	)
}

/**
 * Delete rows where every given column equals its value.
 *
 * @param table the table name without prefix.
 * @param where column => value; must not be empty.
 * @return how many rows went.
 */
export async function deleteRows(
	table: string,
	where: Record<string, string | number>,
): Promise<number> {
	if (Object.keys(where).length === 0) {
		throw new Error(`deleteRows(${table}) without a condition would empty the table`)
	}
	return instancePhp<number>(
		`
$qb = $db->getQueryBuilder();
$qb->delete($in['table']);
foreach ($in['where'] as $col => $val) {
	$qb->andWhere($qb->expr()->eq($col, $qb->createNamedParameter($val)));
}
$out = $qb->executeStatement();
`,
		{ table, where },
	)
}

/**
 * A DATE value read back from any database as `YYYY-MM-DD` (MySQL and
 * PostgreSQL return the date, SQLite may return it with a time part).
 *
 * @param value the raw column value.
 * @return the date.
 */
export function dateOf(value: string | number | null): string {
	return String(value ?? '').slice(0, 10)
}
