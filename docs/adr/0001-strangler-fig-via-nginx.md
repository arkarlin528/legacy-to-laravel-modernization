# ADR-0001: Strangler fig through an nginx facade, one phase file per step

- **Status:** Accepted
- **Date:** 2026-10-02

## Context
Clients call the legacy API directly and can't all be changed at once. We need to move traffic gradually, per endpoint, with instant rollback and no client changes.

## Options considered
1. **Big-bang rewrite, cut over one weekend.** One huge risk, no gradual learning, and rollback means restoring the old database.
2. **Ask clients to switch base URLs per endpoint.** Months of coordination with external teams.
3. **A routing facade in front of both systems** (strangler fig): clients keep one URL, and the facade decides who answers.

Options for the facade: an API gateway product, a YARP reverse proxy in .NET, or nginx.

## Decision
**nginx**, with routing as a `map "$request_method:$uri" $backend`, in one file per phase (`facade/phases/*.conf`):
- the map matches on **method + path**, so `GET` and `POST` of the same URL can move separately;
- `mirror` sends a copy of GETs to the new system in the shadow phase (writes are never mirrored);
- a `$frozen` flag returns `503 + Retry-After` for writes during the cut-over window;
- every response carries `X-Served-By: legacy|modern`, and the access log records the backend.

Switching phase is a file copy plus `nginx -s reload`: zero downtime, reviewable in a pull request, and trivially reversible.

## Consequences
- ✅ Each move is small, observable and reversible. The full contract suite passes **through** the facade in mixed routing (phase 2).
- ✅ nginx is boring, well-understood infrastructure; no new product to operate.
- ⚠️ `mirror` keeps the subrequest open, so nginx's logged `$request_time` includes the shadow call. Measured client latency was unaffected.
- ⚠️ Routing is by path, not by tenant or customer. A canary by customer would need a header or cookie in the map key.
