# ADR-0005: Keep the v1 compatibility layer isolated and deletable

- **Status:** Accepted
- **Date:** 2026-10-02

## Context
Existing clients need the old contract exactly: PascalCase keys, local timestamps, numeric status codes, Web API 2 errors and the static API key. New clients deserve a clean API. Mixing both styles in one set of controllers would make every future change risky for old clients and keep legacy conventions alive forever.

## Decision
- **v1 lives in its own corner:** `app/Http/Controllers/LegacyV1`, `app/Http/Requests/LegacyV1`, `app/Http/Legacy` (presenter, formatting, errors), and the `legacy.key` middleware.
- **Same domain underneath:** v1 and v2 controllers both call `OrderService` and `CustomerService`. Only the edges differ.
- **Errors by route, not by controller:** `LegacyApi::handles($request)` decides in `bootstrap/app.php` whether an exception renders as `{"Message": …}` / `ModelState` (v1) or Laravel's standard JSON (v2).
- **v1 is frozen:** no new endpoints or fields. New capabilities ship only in **v2**:
  - snake_case JSON;
  - UTC ISO-8601 timestamps;
  - money as strings;
  - pagination;
  - personal Sanctum tokens instead of a shared key.

## Consequences
- ✅ Retiring v1 later means deleting four folders and one route group; the domain and v2 are untouched.
- ✅ Both versions are tested: v1 by the contract suite plus feature tests, v2 by feature tests.
- ⚠️ Two response formats to keep in mind while v1 lives. The presenter is the only place that knows the old shape.
