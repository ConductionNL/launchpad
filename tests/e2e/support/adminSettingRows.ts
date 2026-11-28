/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The rows of `launchpad_admin_settings` as stored, for the scenarios whose
 * GIVEN is a row no endpoint can write or remove: a setting that was never
 * stored (a fresh install), a retired key an old version left behind, a value
 * that is not JSON. `PUT /api/admin/settings` treats null as "not supplied"
 * and ignores unknown keys, so these were excluded as "needs a DB edit". The
 * suite can make that edit (`support/instanceDb.ts`); what the product then
 * answers is still read through its own endpoints.
 *
 * Every test that uses this takes a snapshot first and puts it back exactly,
 * ids included, because the instance is shared with the rest of the suite.
 */

import { instancePhp } from './instanceDb.ts'

/** The table, without prefix. */
export const SETTINGS_TABLE = 'launchpad_admin_settings'

/** One stored setting row, as the database returns it. */
export type SettingRow = Record<string, string | number | null>

/**
 * The stored rows, all of them or those of one key.
 *
 * @param key the `setting_key`, or undefined for every row.
 * @return the rows.
 */
export async function settingRows(key?: string): Promise<SettingRow[]> {
	return instancePhp<SettingRow[]>(
		`
$qb = $db->getQueryBuilder();
$qb->select('*')->from($in['table']);
if ($in['key'] !== null) {
	$qb->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($in['key'])));
}
$qb->orderBy('id');
$res = $qb->executeQuery();
$out = $res->fetchAll();
$res->closeCursor();
`,
		{ table: SETTINGS_TABLE, key: key ?? null },
	)
}

/**
 * Remove the stored rows, all of them or those of one key, so the setting
 * reads as never written.
 *
 * @param key the `setting_key`, or undefined for every row.
 * @return how many rows went.
 */
export async function dropSettingRows(key?: string): Promise<number> {
	return instancePhp<number>(
		`
$qb = $db->getQueryBuilder();
$qb->delete($in['table']);
if ($in['key'] !== null) {
	$qb->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($in['key'])));
}
$out = $qb->executeStatement();
`,
		{ table: SETTINGS_TABLE, key: key ?? null },
	)
}

/**
 * Store a raw `setting_value` under a key, exactly as given and without the
 * JSON encoding the mapper applies, replacing whatever row the key had.
 *
 * @param key the `setting_key`.
 * @param raw the `setting_value` as it should sit in the column.
 * @return nothing.
 */
export async function storeRawSetting(key: string, raw: string): Promise<void> {
	await instancePhp<null>(
		`
$del = $db->getQueryBuilder();
$del->delete($in['table'])
	->where($del->expr()->eq('setting_key', $del->createNamedParameter($in['key'])));
$del->executeStatement();
$qb = $db->getQueryBuilder();
$qb->insert($in['table'])
	->setValue('setting_key', $qb->createNamedParameter($in['key']))
	->setValue('setting_value', $qb->createNamedParameter($in['raw']))
	->setValue('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));
$qb->executeStatement();
`,
		{ table: SETTINGS_TABLE, key, raw },
	)
}

/**
 * Put a snapshot back: the rows of the key (or every row) are replaced by
 * exactly the snapshot's rows, ids included, in one transaction.
 *
 * @param rows the snapshot from {@link settingRows}.
 * @param key the key the snapshot was taken for, or undefined for the table.
 * @return nothing.
 */
export async function putBackSettingRows(rows: SettingRow[], key?: string): Promise<void> {
	await instancePhp<number>(
		`
$db->beginTransaction();
try {
	$del = $db->getQueryBuilder();
	$del->delete($in['table']);
	if ($in['key'] !== null) {
		$del->where($del->expr()->eq('setting_key', $del->createNamedParameter($in['key'])));
	}
	$del->executeStatement();
	foreach ($in['rows'] as $row) {
		$qb = $db->getQueryBuilder();
		$qb->insert($in['table']);
		foreach ($row as $col => $val) {
			if ($val === null) {
				$qb->setValue($col, $qb->createNamedParameter(null, \\OCP\\DB\\QueryBuilder\\IQueryBuilder::PARAM_NULL));
			} else {
				$qb->setValue($col, $qb->createNamedParameter($val));
			}
		}
		$qb->executeStatement();
	}
	$db->commit();
} catch (\\Throwable $e) {
	$db->rollBack();
	throw $e;
}
$out = count($in['rows']);
`,
		{ table: SETTINGS_TABLE, key: key ?? null, rows },
	)
}
