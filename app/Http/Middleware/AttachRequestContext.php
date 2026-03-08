<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles AttachRequestContext responsibilities for the ApproveHub domain.
 */
class AttachRequestContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId((string) $request->headers->get('X-Request-Id', ''));

        Log::withContext([
            'request_id' => $requestId,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(string $requestId): string
    {
        $requestId = trim($requestId);

        if ($requestId !== '' && preg_match('/^[A-Za-z0-9_-]{8,100}$/', $requestId) === 1) {
            return $requestId;
        }

        return Str::uuid()->toString();
    }
}
