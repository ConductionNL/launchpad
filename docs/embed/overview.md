# Embedded analytics

LaunchPad widgets and dashboards can be shown on other websites: a municipality's public site,
an intranet, a partner portal. Each embed is opened with an **embed token**, a signed token
(JWT) an administrator issues for one widget or one dashboard.

## Use cases

- A public WOO overview on the municipality's website.
- A team dashboard on the intranet, filtered to one department.
- A usage figure on a partner portal, refreshed live.

## Security model

- **One subject per token.** A token opens exactly the widget or dashboard it was issued for;
  any other subject is refused (`403 scope_mismatch`) without saying whether it exists.
- **Signed and checked on every request.** The signature, the issuer, the stored token hash,
  revocation and expiry are checked before anything renders. A revoked token stops working
  within 60 seconds on every server.
- **Only the listed websites.** The page answers with
  `Content-Security-Policy: frame-ancestors <host origins>`, so browsers show it only inside
  pages of those origins; a cross-site request from another origin is refused
  (`403 origin_mismatch`). Wildcards are not accepted.
- **Read-only unless allowed.** A `read` token cannot change anything: every request other than
  GET or HEAD made with it is refused at the API, before any code runs, whatever the page's
  JavaScript does. A `read-with-interactions` token may run only the actions it names
  (`interact`, `drillDown`, `export`).
- **Pinned filters.** Filters set on the token (for instance `gemeente: Zeist`) always apply;
  other filters only on the dimensions the token allows.
- **Rate limited.** Each token has a budget (default 600 requests per minute, bursts of 60);
  beyond it the answer is `429` with `Retry-After`.
- **Usage without personal data.** Views and interactions are counted per token with the
  host origin, browser family, a hash of the user agent and a viewport bucket. No IP address,
  no full user agent and no visitor identifier is stored. Telemetry can be switched off.
