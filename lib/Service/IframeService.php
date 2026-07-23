<?php

/**
 * IframeService
 *
 * Server-side allow-list enforcement for the `iframe` dashboard widget
 * (REQ-IFRAME-002, REQ-IFRAME-003). Mirrors {@see LiveTileService}'s
 * `url`-mode allow-list shape: a JSON array of hostnames stored under
 * `iframe_allowed_hosts` (IAppConfig), enforced FAIL-CLOSED — an empty or
 * missing list permits NO host, never "allow all" (REQ-IFRAME-002 "Allow-
 * list default is empty and denies all").
 *
 * This is the single source of truth the CSP contribution
 * ({@see \OCA\LaunchPad\Listener\CspListener}) and the save-time /
 * render-time validation both read from, so a host removed from the list
 * is refused everywhere in one place (REQ-IFRAME-002 "Host removed after
 * configuration is refused at render").
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Allow-list validation + CSP-source-of-truth for the `iframe` widget.
 *
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */
class IframeService
{

    /**
     * IAppConfig key — JSON array of hostnames permitted as embed targets.
     * FAIL-CLOSED: empty or missing means NO host is permitted
     * (REQ-IFRAME-002).
     *
     * @var string
     */
    public const CONFIG_KEY_ALLOWED_HOSTS = 'iframe_allowed_hosts';

    /**
     * Sandbox token(s) an author can never grant — the frame must not be
     * able to navigate the host page (REQ-IFRAME-004 "the sandbox MUST
     * NEVER include allow-top-navigation"). Matched as a prefix so both
     * `allow-top-navigation` and `allow-top-navigation-by-user-activation`
     * are blocked.
     *
     * @var string
     */
    private const FORBIDDEN_SANDBOX_PREFIX = 'allow-top-navigation';

    /**
     * The sandbox tokens an author may toggle. Anything outside this set
     * (including any `allow-top-navigation*` variant) is stripped, never
     * merely flagged, so a malformed/tampered payload can't slip a
     * forbidden token through (REQ-IFRAME-004).
     *
     * @var array<int,string>
     */
    private const PERMITTED_SANDBOX_TOKENS = [
        'allow-scripts',
        'allow-same-origin',
        'allow-forms',
        'allow-popups',
        'allow-popups-to-escape-sandbox',
        'allow-presentation',
    ];

    /**
     * Constructor.
     *
     * @param IAppConfig $appConfig Admin config: allow-listed hosts.
     */
    public function __construct(
        private readonly IAppConfig $appConfig,
    ) {
    }//end __construct()

    /**
     * Validate a candidate iframe placement config at save time
     * (REQ-IFRAME-002 "rejected at save time"). FAIL-CLOSED: a URL whose
     * host is not on the (possibly empty) allow-list is always rejected.
     *
     * @param array<string,mixed> $config The candidate `{url, title, height, aspect, sandbox}` config.
     *
     * @return string[] Validation error codes; empty when the config is valid.
     *
     * @spec openspec/specs/iframe-embed-widget/spec.md
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        $url    = trim(string: (string) ($config['url'] ?? ''));

        if ($url === '') {
            $errors[] = 'url_required';
        } elseif ($this->hasValidScheme(url: $url) === false) {
            $errors[] = 'invalid_url';
        } elseif ($this->isHostAllowed(url: $url) === false) {
            // FAIL-CLOSED — never "allow all" on an empty/missing list.
            $errors[] = 'host_not_allowed';
        }

        if (trim(string: (string) ($config['title'] ?? '')) === '') {
            // REQ-IFRAME-004 "Accessible frame title" — an iframe with no
            // title cannot expose one to screen readers.
            $errors[] = 'title_required';
        }

        $sandbox = $config['sandbox'] ?? [];
        if (is_array(value: $sandbox) === true && $this->containsForbiddenSandboxToken(tokens: $sandbox) === true) {
            $errors[] = 'forbidden_sandbox_token';
        }

        return $errors;
    }//end validateConfig()

    /**
     * Whether a candidate URL's host is currently allow-listed
     * (REQ-IFRAME-002 / used at render time to refuse a placement whose
     * host was later removed from the list).
     *
     * @param string $url The URL to check.
     *
     * @return boolean True only when the host is explicitly allow-listed.
     *
     * @spec openspec/specs/iframe-embed-widget/spec.md
     */
    public function isHostAllowed(string $url): bool
    {
        $host = parse_url(url: $url, component: PHP_URL_HOST);
        if (is_string(value: $host) === false || $host === '') {
            return false;
        }

        $needle = strtolower(string: $host);
        foreach ($this->getAllowedHosts() as $allowed) {
            if (strtolower(string: $allowed) === $needle) {
                return true;
            }
        }

        return false;
    }//end isHostAllowed()

    /**
     * The full admin-configured allow-list, decoded and normalised. Empty
     * (never null) when the config key is missing/blank/malformed —
     * FAIL-CLOSED (REQ-IFRAME-002, REQ-IFRAME-003 "Non-allow-listed hosts
     * are never added").
     *
     * @return string[] Lower-case-safe hostnames (original casing preserved), deduplicated.
     *
     * @spec openspec/specs/iframe-embed-widget/spec.md
     */
    public function getAllowedHosts(): array
    {
        $raw = $this->appConfig->getValueString(
            app: Application::APP_ID,
            key: self::CONFIG_KEY_ALLOWED_HOSTS,
            default: ''
        );

        $decoded = json_decode(json: $raw, associative: true);
        if (is_array(value: $decoded) === false || $decoded === []) {
            return [];
        }

        $hosts = [];
        foreach ($decoded as $host) {
            if (is_string(value: $host) === true && trim(string: $host) !== '') {
                $hosts[] = trim(string: $host);
            }
        }

        return array_values(array: array_unique(array: $hosts));
    }//end getAllowedHosts()

    /**
     * Strip any forbidden token (defence-in-depth — the config form never
     * offers `allow-top-navigation*`, but a save request is not required
     * to have gone through the form) and any token outside the permitted
     * set from a candidate sandbox token list.
     *
     * @param mixed $tokens The candidate sandbox token list.
     *
     * @return string[] The sanitised token list.
     *
     * @spec openspec/specs/iframe-embed-widget/spec.md
     */
    public function sanitiseSandboxTokens(mixed $tokens): array
    {
        if (is_array(value: $tokens) === false) {
            return [];
        }

        $clean = [];
        foreach ($tokens as $token) {
            if (is_string(value: $token) === false) {
                continue;
            }

            if (str_starts_with(haystack: $token, needle: self::FORBIDDEN_SANDBOX_PREFIX) === true) {
                continue;
            }

            if (in_array(needle: $token, haystack: self::PERMITTED_SANDBOX_TOKENS, strict: true) === true) {
                $clean[] = $token;
            }
        }

        return array_values(array: array_unique(array: $clean));
    }//end sanitiseSandboxTokens()

    /**
     * Whether a candidate sandbox token list contains a forbidden token
     * (REQ-IFRAME-004).
     *
     * @param array<int,mixed> $tokens The candidate sandbox token list.
     *
     * @return boolean
     */
    private function containsForbiddenSandboxToken(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (is_string(value: $token) === true
                && str_starts_with(haystack: $token, needle: self::FORBIDDEN_SANDBOX_PREFIX) === true
            ) {
                return true;
            }
        }

        return false;
    }//end containsForbiddenSandboxToken()

    /**
     * Validate a URL's scheme is `http` or `https`.
     *
     * @param string $url The URL to check.
     *
     * @return boolean
     */
    private function hasValidScheme(string $url): bool
    {
        $scheme = strtolower(string: (string) parse_url(url: $url, component: PHP_URL_SCHEME));
        return in_array(needle: $scheme, haystack: ['http', 'https'], strict: true);
    }//end hasValidScheme()
}//end class
