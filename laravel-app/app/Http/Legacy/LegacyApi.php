<?php

namespace App\Http\Legacy;

use App\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Everything that makes the new app answer exactly like the old ASP.NET Web API 2 service did:
 * response helpers, value formatting, and the error format. Kept in one place so the compatibility
 * layer can be deleted cleanly once every client has moved to /api/v2.
 */
final class LegacyApi
{
    // PRESERVE_ZERO_FRACTION: money stays a decimal (958.0, not 958), like the .NET decimals it replaces.
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /** v1 = every /api route that isn't the new versioned API. */
    public static function handles(Request $request): bool
    {
        return $request->is('api/*') && ! $request->is('api/v2', 'api/v2/*');
    }

    public static function ok(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers, self::JSON_FLAGS);
    }

    public static function error(int $status, string $message): JsonResponse
    {
        return self::ok(['Message' => $message], $status);
    }

    /** @param array<string, string[]> $modelState */
    public static function invalid(array $modelState): JsonResponse
    {
        return self::ok(['Message' => 'The request is invalid.', 'ModelState' => $modelState], 400);
    }

    /** Local Bangkok time without an offset, e.g. 2024-03-01T09:30:00 (what the old API returned). */
    public static function date(?Carbon $value): ?string
    {
        return $value?->copy()->setTimezone(config('legacy.timezone'))->format('Y-m-d\TH:i:s');
    }

    public static function money(string|float|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    /** Turns any exception on a v1 route into the shape v1 clients already parse. */
    public static function renderException(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::invalid(self::modelState($e)),
            $e instanceof BusinessRuleException => self::error($e->status, $e->getMessage()),
            $e instanceof ModelNotFoundException => self::error(404, 'Not found'),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 404 => self::error(404, 'No HTTP resource was found that matches the request URI.'),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 405 => self::error(405, 'The requested resource does not support this http method.'),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 429 => self::error(429, 'Too many requests.'),
            default => self::error(500, 'An error has occurred.'),
        };
    }

    /**
     * Web API 2 reported one entry per top-level property. Nested Laravel keys like "Lines.0.Quantity"
     * are folded into "Lines", keeping the first message for each.
     *
     * @return array<string, string[]>
     */
    private static function modelState(ValidationException $e): array
    {
        $state = [];
        foreach ($e->errors() as $key => $messages) {
            $top = explode('.', $key)[0];
            $state[$top] ??= [$messages[0]];
        }

        return $state;
    }
}
