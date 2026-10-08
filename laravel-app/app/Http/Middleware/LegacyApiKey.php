<?php

namespace App\Http\Middleware;

use App\Http\Legacy\LegacyApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The legacy clients' static X-Api-Key, checked exactly like the old service did (but in constant time). */
class LegacyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('legacy.api_key');
        $provided = (string) $request->header('X-Api-Key', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return LegacyApi::error(401, 'Authorization has been denied for this request.');
        }

        return $next($request);
    }
}
