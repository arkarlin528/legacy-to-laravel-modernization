# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_KEY}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

v2: get a token from <code>POST /api/v2/tokens</code> and send it as <code>Authorization: Bearer {token}</code>. v1: send the legacy <code>X-Api-Key</code> header instead.
