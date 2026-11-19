# Embed troubleshooting

| What you see | Why | What to do |
| --- | --- | --- |
| The iframe stays empty; the browser console reports a `frame-ancestors` violation | The page's origin is not in the token's host origins | Add the exact origin (scheme, host and port) to a new token |
| `403 origin_mismatch` | A cross-site request came from a page whose origin the token does not list | As above |
| `403 scope_mismatch` | The URL names another widget or dashboard than the token | Use the subject the token was issued for |
| `401 missing_token` | No `?token=` and no `Authorization: Bearer` header | Pass the token |
| `401 token_expired` / `token_revoked` | The token's time is up, or an administrator withdrew it | Ask for a new token |
| `401 invalid_token` | The token is damaged or was not issued by this LaunchPad | Copy the token again in full |
| `403 read_only_token` | A write was attempted with a read token | Writes need a `read-with-interactions` token naming the action |
| `403 action_not_in_scope` | The action is not among the token's `allowedActions` | Issue a token that allows it |
| `429 rate_limit_exceeded` | The token's request budget is spent | Wait `Retry-After` seconds, or raise the token's rate limit |
| `render()` rejects after 10 seconds | The embed page did not load (network, CORS, blocked by your CSP) | Allow `frame-src` for the LaunchPad address in your own CSP |
