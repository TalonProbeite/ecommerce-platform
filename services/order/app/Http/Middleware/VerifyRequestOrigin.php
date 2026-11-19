<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ProblemResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyRequestOrigin
{
    private const array UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::UNSAFE_METHODS, true)) {
            return $next($request);
        }

        // Stripe authenticates webhooks with its signature, not browser cookies.
        if ($request->is('webhooks/stripe')) {
            return $next($request);
        }

        $fetchSiteHeader = $request->headers->get('Sec-Fetch-Site');
        $fetchSite = is_string($fetchSiteHeader) ? strtolower(trim($fetchSiteHeader)) : '';

        if ($fetchSite !== '') {
            if (in_array($fetchSite, ['same-origin', 'none'], true)) {
                return $next($request);
            }

            if ($fetchSite === 'same-site') {
                return $this->originMatchesHost($request) ? $next($request) : $this->forbidden();
            }

            return $this->forbidden();
        }

        $origin = $request->headers->get('Origin');

        if (is_string($origin) && $origin !== '') {
            return $this->originMatchesHost($request) ? $next($request) : $this->forbidden();
        }

        // No browser metadata: curl and service-to-service calls rely on Authenticate.
        return $next($request);
    }

    private function originMatchesHost(Request $request): bool
    {
        $originHost = $this->originHostname($request->headers->get('Origin'));
        $requestHost = $this->hostname($request->headers->get('Host'));

        return $originHost !== null && $requestHost !== null && $originHost === $requestHost;
    }

    private function originHostname(?string $origin): ?string
    {
        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $host = parse_url(trim($origin), PHP_URL_HOST);

        return is_string($host) ? $this->normalizeHost($host) : null;
    }

    private function hostname(?string $host): ?string
    {
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        // Host may include a port; scheme and port are intentionally ignored.
        $parsedHost = parse_url('http://' . trim($host), PHP_URL_HOST);

        return is_string($parsedHost) ? $this->normalizeHost($parsedHost) : null;
    }

    private function normalizeHost(?string $host): ?string
    {
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return rtrim(strtolower(trim($host)), '.');
    }

    private function forbidden(): Response
    {
        return ProblemResponse::make(
            Response::HTTP_FORBIDDEN,
            'Forbidden',
            'Cross-site state-changing requests are not allowed.',
        );
    }
}
