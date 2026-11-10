<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureStoreAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureStoreAccessTest extends TestCase
{
    public function test_admin_passes_without_store_id(): void
    {
        $request = $this->request(['role' => 'admin'], 'store-1');

        $response = $this->middleware()->handle($request, $this->next());

        self::assertSame('ok', $response->getContent());
    }

    public function test_store_manager_passes_for_own_store(): void
    {
        $request = $this->request(['role' => 'store_manager', 'store_id' => 'store-1'], 'store-1');

        $response = $this->middleware()->handle($request, $this->next());

        self::assertSame('ok', $response->getContent());
    }

    public function test_store_manager_is_forbidden_for_another_store(): void
    {
        $request = $this->request(['role' => 'store_manager', 'store_id' => 'store-1'], 'store-2');

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next())
        );
    }

    public function test_store_manager_without_store_id_is_forbidden(): void
    {
        $request = $this->request(['role' => 'store_manager'], 'store-1');

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next())
        );
    }

    public function test_store_manager_with_empty_store_id_is_forbidden(): void
    {
        $request = $this->request(['role' => 'store_manager', 'store_id' => ''], 'store-1');

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next())
        );
    }

    public function test_store_manager_with_non_string_store_id_is_forbidden(): void
    {
        $request = $this->request(['role' => 'store_manager', 'store_id' => 123], 'store-1');

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next())
        );
    }

    public function test_unknown_role_is_forbidden(): void
    {
        $request = $this->request(['role' => 'customer', 'store_id' => 'store-1'], 'store-1');

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next())
        );
    }

    public function test_missing_store_id_route_parameter_is_forbidden(): void
    {
        $request = $this->request(['role' => 'admin'], null);

        $this->assertForbidden(
            fn () => $this->middleware()->handle($request, $this->next())
        );
    }

    private function middleware(): EnsureStoreAccess
    {
        return new EnsureStoreAccess();
    }

    private function next(): Closure
    {
        return static fn (Request $request): Response => new Response('ok');
    }

    /**
     * @param  array<string, mixed>  $jwtPayload
     */
    private function request(array $jwtPayload, ?string $requestedStoreId): Request
    {
        $uri = $requestedStoreId === null ? '/stores' : '/stores/' . $requestedStoreId;
        $request = Request::create($uri, 'GET');
        $request->attributes->set('jwt_data', $jwtPayload);

        $route = new Route(['GET'], $requestedStoreId === null ? '/stores' : '/stores/{storeId}', []);
        $route->bind($request);

        $request->setRouteResolver(static fn (): Route => $route);

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
