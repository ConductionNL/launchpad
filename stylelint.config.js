module.exports = {
	extends: '@nextcloud/stylelint-config',
	rules: {
		// Prettier owns whitespace here, and these two rules contradict it.
		// `npm run format` indents a wrapped selector list; stylelint's
		// `indentation` demanded 0 tabs on the same six lines, so running
		// either fixer broke the other check — an unwinnable loop.
		//
		// Both rules are also DEPRECATED in stylelint 15 (it prints a
		// deprecation warning for each on every run) precisely because
		// formatters like Prettier do this better. Turning them off resolves
		// the conflict in favour of the tool that owns formatting, and leaves
		// stylelint judging what only it can judge: CSS semantics.
		indentation: null,
		'string-quotes': null,
	},
}
