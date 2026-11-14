// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The calculated-field AST (REQ-CALC-001): seven node kinds, plain
 * JSON-serialisable objects, the same shape the PHP evaluator reads.
 *
 * @typedef {{type: 'literal', valueType: 'number'|'string'|'boolean'|'null', value: number|string|boolean|null}} LiteralNode
 * @typedef {{type: 'fieldRef', source: string, path: string}} FieldRefNode `source` is `self` or `widget:<id>`.
 * @typedef {{type: 'calculatedRef', name: string}} CalculatedRefNode
 * @typedef {{type: 'binary', op: string, left: AstNode, right: AstNode}} BinaryNode
 * @typedef {{type: 'unary', op: '-'|'not', operand: AstNode}} UnaryNode
 * @typedef {{type: 'if', cond: AstNode, then: AstNode, else: AstNode}} IfNode
 * @typedef {{type: 'call', name: string, args: AstNode[]}} CallNode
 * @typedef {LiteralNode|FieldRefNode|CalculatedRefNode|BinaryNode|UnaryNode|IfNode|CallNode} AstNode
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */

/**
 * The node kinds and the keys each one carries.
 *
 * @type {Readonly<Record<string, string[]>>}
 */
export const NODE_KEYS = Object.freeze({
	literal: ['valueType', 'value'],
	fieldRef: ['source', 'path'],
	calculatedRef: ['name'],
	binary: ['op', 'left', 'right'],
	unary: ['op', 'operand'],
	if: ['cond', 'then', 'else'],
	call: ['name', 'args'],
})

/**
 * The binary operators.
 */
export const BINARY_OPS = Object.freeze(['+', '-', '*', '/', '%', '<', '<=', '>', '>=', '==', '!=', 'and', 'or'])

/**
 * The literal value types.
 */
const LITERAL_TYPES = Object.freeze(['number', 'string', 'boolean', 'null'])

/**
 * The checks per node kind, beyond carrying exactly its keys.
 */
const NODE_CHECKS = {
	literal: (n) => LITERAL_TYPES.includes(n.valueType) && (n.valueType === 'null' ? n.value === null : typeof n.value === n.valueType),
	fieldRef: (n) => typeof n.source === 'string' && (n.source === 'self' || n.source.startsWith('widget:')) && typeof n.path === 'string' && n.path !== '',
	calculatedRef: (n) => typeof n.name === 'string' && n.name !== '',
	binary: (n) => BINARY_OPS.includes(n.op) && isAstNode(n.left) && isAstNode(n.right),
	unary: (n) => (n.op === '-' || n.op === 'not') && isAstNode(n.operand),
	if: (n) => isAstNode(n.cond) && isAstNode(n.then) && isAstNode(n.else),
	call: (n) => typeof n.name === 'string' && Array.isArray(n.args) && n.args.every(isAstNode),
}

/**
 * Whether a value is a well-formed AST node, recursively: one of the seven
 * kinds, carrying exactly that kind's keys, with valid operators and children.
 * A stored `compiledExpression` is checked with this before it is evaluated.
 *
 * @param {unknown} node The value.
 * @return {boolean} True for a well-formed node.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-001-formula-parser-produces-deterministic-ast
 */
export function isAstNode(node) {
	if (!node || typeof node !== 'object' || Array.isArray(node) || !Object.hasOwn(NODE_KEYS, node.type)) {
		return false
	}
	const keys = Object.keys(node).filter((key) => key !== 'type').sort()
	const expected = [...NODE_KEYS[node.type]].sort()
	if (keys.length !== expected.length || keys.some((key, i) => key !== expected[i])) {
		return false
	}
	return NODE_CHECKS[node.type](node)
}

/**
 * A short, readable name of a node for an error message: the field or
 * calculated-field name, a literal's value, or the called function.
 *
 * @param {AstNode} node The node.
 * @return {string} The name.
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-002-type-checking-at-save-time
 */
export function describeNode(node) {
	switch (node?.type) {
	case 'fieldRef':
		return node.source === 'self' ? node.path : `${node.source}.${node.path}`
	case 'calculatedRef':
		return node.name
	case 'literal':
		return node.valueType === 'string' ? JSON.stringify(node.value) : String(node.value)
	case 'call':
		return `${node.name}(…)`
	case 'binary':
		return `(… ${node.op} …)`
	case 'unary':
		return `${node.op} …`
	case 'if':
		return 'if(…)'
	default:
		return '?'
	}
}
