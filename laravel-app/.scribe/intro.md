# Introduction

Order API for a freight forwarder: the legacy-compatible v1 contract and the new v2 API, both served by the Laravel rewrite.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

Two API generations run side by side while clients migrate:

- **Legacy v1 (compatible)**: the original `/api/customers` and `/api/orders` endpoints, byte-for-byte the
  contract of the old ASP.NET Web API 2 service (PascalCase JSON, local Bangkok timestamps, `{"Message": ...}`
  errors). Authenticate with the static `X-Api-Key` header existing clients already send.
- **v2**: the new API under `/api/v2`. snake_case JSON, UTC ISO-8601 timestamps, pagination, money as strings,
  and a personal Sanctum token per user (`POST /api/v2/tokens`).

<aside>v1 is frozen: no new features land there. It will be retired once every client has moved to v2.</aside>

