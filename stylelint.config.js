module.exports = {
	extends: '@nextcloud/stylelint-config',
	rules: {
		// Prettier owns whitespace and quote style here. `indentation` and
		// `string-quotes` both contradicted it, and running either fixer broke
		// the other check.
		//
		// They are not merely disabled: stylelint removed both in 16, and 17
		// errors on an unknown rule even when it is set to null. Prettier keeps
		// formatting; stylelint keeps judging what only it can judge, CSS
		// semantics.
	},
}
