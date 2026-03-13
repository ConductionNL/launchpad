# Multi-Domain Support

## What Open Forms Does

### Multi-Domain Model
- Single Open Forms instance can serve multiple domains
- Each domain can have its own theme/branding
- Domain-specific configuration (logo, organization name, main website)
- Forms inherit domain-level styling when accessed via that domain

### Template Tag
- `multidomain` templatetag for domain-aware URL generation
- Ensures links in emails/PDFs use the correct domain

### Cross-Origin
- CORS and CSP headers managed per domain
- SDK embedding on external websites

## Already in Procest

- Single Nextcloud instance per deployment
- No multi-domain support

## Not Yet in Procest

- **Multi-domain serving** -- No single instance serving multiple domains
- **Domain-specific branding** -- No per-domain theme configuration
- **Domain-aware URL generation** -- No domain context in generated links
