# Contributing to MyDash / Launchpad

Thank you for contributing. Please follow the conventions below to keep the codebase consistent and safe.

## Getting started

See [DEVELOPMENT.md](DEVELOPMENT.md) for environment setup, running tests, and the release process.

## Code style

- PHP: PSR-12 via `composer cs-fix` / `composer phpcs`
- Vue / JS: ESLint via `npm run lint`
- All tests must pass: `composer check:strict && npm test`

## Pull requests

1. Branch from `development` — never from `main`.
2. Target `development` — the pipeline rejects PRs targeting `main`.
3. One logical change per PR; link the related issue in the description.
4. Every new `lib/**/*.php` class needs a matching `tests/Unit/**/*Test.php`.

## Security

### SVG whitelist — security review required

The `SvgSanitiser` class in `lib/Service/SvgSanitiser.php` enforces two conservative
whitelists that define the safe SVG surface:

| Constant | Requirement | Count |
|---|---|---|
| `ALLOWED_ELEMENTS` | Permitted element local-names | 24 (REQ-RES-010) |
| `ALLOWED_ATTRIBUTES` | Permitted attribute names | 50 (REQ-RES-011) |

**Any PR that adds an entry to either whitelist MUST include a security review.**
SVG can carry executable payloads (`<script>`, `<foreignObject>`, `on*` event handlers,
`javascript:` URLs, CSS `expression()` constructs) that become stored XSS when rendered
in a logged-in user's browser. Adding an element or attribute expands the attack surface
and must be explicitly justified.

Security review checklist for whitelist changes:

- [ ] The added element/attribute cannot carry executable payloads in any browser.
- [ ] `on*` attributes are still stripped unconditionally (defence-in-depth rule in
  `cleanElement()` must not be weakened).
- [ ] `href` / `xlink:href` URL filtering still rejects `javascript:` and `data:` prefixes.
- [ ] `style` attribute filtering still rejects `expression(`, `javascript:`, and `url(data:`.
- [ ] A PHPUnit test covering the new element/attribute is included.
- [ ] The PR description explains why the addition is safe.

Reference: `openspec/changes/svg-sanitisation/specs/resource-uploads/spec.md`
(REQ-RES-010, REQ-RES-011).

### General security guidelines

- Validate and sanitise all user input server-side — client-side checks are UI only.
- Use `#[NoAdminRequired]` only when you have a per-object authorisation check in the body.
- Never return raw exception messages to clients (`$e->getMessage()` leaks internals).
- No credentials, tokens, or secrets in code or tests.
