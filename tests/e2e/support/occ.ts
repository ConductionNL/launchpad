/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Run occ, or any other command, on the Nextcloud instance under test and keep
 * what a console command is judged on: its exit code, its stdout and its
 * stderr, apart.
 *
 * `support/scheduledExports.ts` has an `occ()` that hands back stdout and
 * throws on a non-zero exit. That suits a seed step. The cli-commands spec is
 * about the exit codes and the stream an error lands on, so it needs a runner
 * that reports a failure instead of throwing on one.
 *
 * Where occ lives is resolved the way `shared-instance.ts::occPrefix()`
 * documents:
 *  1. `LAUNCHPAD_E2E_OCC`, a complete prefix (e.g. `docker exec -u www-data nc php occ`);
 *  2. `LAUNCHPAD_E2E_CONTAINER` / `NEXTCLOUD_CONTAINER`, a container name;
 *  3. `php occ` in the server root, which is the CI case. The server root is
 *     the working directory when it holds `occ`, else the directory two levels
 *     above this checkout (`server/apps-extra/launchpad`).
 *  4. With none of those, the container `nextcloud`, as scheduledExports.ts
 *     defaults to.
 *
 * Whatever comes before `php occ` in the prefix is how a plain command reaches
 * the instance too (`docker exec -u www-data <name>`, or nothing at all), which
 * is what {@link onInstance} uses to read and write files there.
 */

import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'
import * as path from 'node:path'
import { occPrefix } from '../shared-instance.ts'

/** What one command run left behind. */
export interface RunResult {
	/** The process exit code. */
	code: number
	/** Everything written to stdout. */
	stdout: string
	/** Everything written to stderr. */
	stderr: string
}

/** How commands reach the instance: an argv prefix and a working directory. */
interface Route {
	/** Prepended to every argv run on the instance; empty for a local server root. */
	exec: string[]
	/** The working directory for a local server root, else undefined. */
	cwd: string | undefined
}

/** This checkout's root: tests/e2e/support is three levels down. */
const CHECKOUT = path.resolve(__dirname, '..', '..', '..')

let route: Route | null = null

/**
 * Resolve, once, how commands reach the instance.
 *
 * @return the route.
 */
function resolveRoute(): Route {
	if (route !== null) {
		return route
	}

	const explicit = (process.env.LAUNCHPAD_E2E_OCC ?? '').trim()
	const container = (
		process.env.LAUNCHPAD_E2E_CONTAINER
		?? process.env.NEXTCLOUD_CONTAINER
		?? ''
	).trim()
	if (explicit !== '' || container !== '') {
		const prefix = occPrefix()
		if (prefix.slice(-2).join(' ') !== 'php occ') {
			throw new Error(
				`LAUNCHPAD_E2E_OCC="${explicit}" does not end in "php occ", so the cli-commands `
					+ 'spec cannot tell how to run a plain command on the instance. Give it the '
					+ 'full prefix, e.g. "docker exec -u www-data nextcloud php occ".',
			)
		}
		route = { exec: prefix.slice(0, -2), cwd: undefined }
		return route
	}

	for (const root of [process.cwd(), path.resolve(CHECKOUT, '..', '..')]) {
		if (existsSync(path.join(root, 'occ'))) {
			route = { exec: [], cwd: root }
			return route
		}
	}

	route = {
		exec: ['docker', 'exec', '-u', 'www-data', 'nextcloud'],
		cwd: undefined,
	}
	return route
}

/**
 * Run a command and collect its exit code and both streams. Never throws on a
 * non-zero exit; that is the result being measured.
 *
 * @param argv the command and its arguments.
 * @param input text for its standard input, if any.
 * @param timeoutMs how long it may take.
 * @return the result.
 */
async function run(
	argv: string[],
	input?: string,
	timeoutMs = 180_000,
): Promise<RunResult> {
	const { exec, cwd } = resolveRoute()
	let full = [...exec, ...argv]
	if (input !== undefined && exec[0] === 'docker' && exec[1] === 'exec') {
		full = ['docker', 'exec', '-i', ...exec.slice(2), ...argv]
	}

	return new Promise((resolve, reject) => {
		const child = spawn(full[0], full.slice(1), {
			cwd,
			stdio: ['pipe', 'pipe', 'pipe'],
		})
		let stdout = ''
		let stderr = ''
		const timer = setTimeout(() => {
			child.kill('SIGKILL')
			reject(new Error(`${full.join(' ')} took longer than ${timeoutMs} ms`))
		}, timeoutMs)
		child.stdout.on('data', (chunk) => {
			stdout += String(chunk)
		})
		child.stderr.on('data', (chunk) => {
			stderr += String(chunk)
		})
		child.on('error', (error) => {
			clearTimeout(timer)
			reject(new Error(`could not start ${full.join(' ')}: ${error.message}`))
		})
		child.on('close', (code) => {
			clearTimeout(timer)
			resolve({ code: code ?? -1, stdout, stderr })
		})
		child.stdin.end(input ?? '')
	})
}

/**
 * Run occ on the instance.
 *
 * `--no-ansi` keeps colour codes and `<error>` decoration out of the streams,
 * so assertions read the text an operator's script would.
 *
 * @param args the occ arguments.
 * @return the result.
 */
export async function runOcc(...args: string[]): Promise<RunResult> {
	return run(['php', 'occ', '--no-ansi', ...args])
}

/**
 * Run occ with text on its standard input (an answer to a prompt).
 *
 * @param input the text.
 * @param args the occ arguments.
 * @return the result.
 */
export async function runOccWithInput(
	input: string,
	...args: string[]
): Promise<RunResult> {
	return run(['php', 'occ', '--no-ansi', ...args], input)
}

/**
 * Run a plain command (cat, test, rm, ...) on the instance, as the web server
 * user where the instance is a container.
 *
 * @param argv the command and its arguments.
 * @return the result.
 */
export async function onInstance(...argv: string[]): Promise<RunResult> {
	return run(argv)
}

/**
 * The text of one result, for an assertion message.
 *
 * @param result the result.
 * @return exit code and both streams, labelled.
 */
export function describeRun(result: RunResult): string {
	return `exit ${result.code}\n--- stdout ---\n${result.stdout}\n--- stderr ---\n${result.stderr}`
}

/**
 * The launchpad app folder on the instance.
 *
 * @return its absolute path there.
 */
export async function appPathOnInstance(): Promise<string> {
	const result = await runOcc('app:getpath', 'launchpad')
	if (result.code !== 0 || result.stdout.trim() === '') {
		throw new Error(`occ app:getpath launchpad failed\n${describeRun(result)}`)
	}
	return result.stdout.trim()
}

/**
 * Whether a path exists on the instance.
 *
 * @param file the path there.
 * @return true when it exists.
 */
export async function existsOnInstance(file: string): Promise<boolean> {
	return (await onInstance('test', '-e', file)).code === 0
}

/**
 * The content of a file on the instance.
 *
 * @param file the path there.
 * @return the content.
 */
export async function readOnInstance(file: string): Promise<string> {
	const result = await onInstance('cat', file)
	if (result.code !== 0) {
		throw new Error(
			`could not read ${file} on the instance\n${describeRun(result)}`,
		)
	}
	return result.stdout
}

/**
 * Write a file on the instance.
 *
 * @param file the path there.
 * @param content the content.
 * @return nothing.
 */
export async function writeOnInstance(file: string, content: string): Promise<void> {
	const result = await run(['sh', '-c', 'cat > "$1"', 'sh', file], content)
	if (result.code !== 0) {
		throw new Error(
			`could not write ${file} on the instance\n${describeRun(result)}`,
		)
	}
}

/**
 * Remove a file on the instance, tolerating one that is already gone.
 *
 * @param file the path there.
 * @return nothing.
 */
export async function removeOnInstance(file: string): Promise<void> {
	await onInstance('rm', '-f', file)
}

/**
 * The path of Nextcloud's log file on the instance, from `occ log:file`.
 *
 * @return the path.
 */
export async function logFileOnInstance(): Promise<string> {
	const result = await runOcc('log:file')
	const match = /Log file:\s*(\S+)/.exec(result.stdout)
	if (result.code !== 0 || match === null) {
		throw new Error(
			`occ log:file did not name the log file\n${describeRun(result)}`,
		)
	}
	return match[1]
}

/**
 * The messages of the log lines written after `since` bytes, decoded from the
 * JSON lines Nextcloud's file log writes.
 *
 * @param file the log file on the instance.
 * @param since the byte offset to read from.
 * @return each new line's message.
 */
export async function logMessagesSince(
	file: string,
	since: number,
): Promise<string[]> {
	const result = await onInstance('tail', '-c', `+${since + 1}`, file)
	if (result.code !== 0) {
		throw new Error(
			`could not read ${file} on the instance\n${describeRun(result)}`,
		)
	}
	return result.stdout
		.split('\n')
		.filter((line) => line.trim() !== '')
		.map((line) => {
			try {
				return String(JSON.parse(line).message ?? line)
			} catch {
				return line
			}
		})
}

/**
 * The size in bytes of a file on the instance (0 when it does not exist yet).
 *
 * @param file the path there.
 * @return the size.
 */
export async function sizeOnInstance(file: string): Promise<number> {
	const result = await onInstance('wc', '-c', file)
	if (result.code !== 0) {
		return 0
	}
	return Number.parseInt(result.stdout.trim().split(/\s+/)[0] ?? '0', 10) || 0
}
