<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureRoleTest extends TestCase
{
    public function test_allowed_role_passes_request(): void
    {
        $request = $this->requestWithRole('store_manager');

        $response = $this->middleware()->handle($request, $this->next(), 'admin', 'store_manager');

        self::assertSame('ok', $response->getContent());
    }

    public function test_role_outside_allowed_list_is_forbidden(): void
    {
        $request = $this->requestWithRole('customer');

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next(), 'admin', 'store_manager')
        );
    }

    public function test_missing_role_is_forbidden(): void
    {
        $request = $this->requestWithRole(null);

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next(), 'admin')
        );
    }

    private function middleware(): EnsureRole
    {
        return new EnsureRole();
    }

    private function next(): Closure
    {
        return static fn (Request $request): Response => new Response('ok');
    }

    private function requestWithRole(?string $role): Request
    {
        $request = Request::create('/test', 'GET');

        $request->attributes->set(
            'jwt_data',
            $role === null ? [] : ['role' => $role],
        );

        return $request;
    }

    private function assertForbidden(Closure $action): void
    {
        $this->assertThrows(
            $action,
            static fn (HttpException $exception): bool => $exception->getStatusCode() === Response::HTTP_FORBIDDEN,
        );
    }
}
