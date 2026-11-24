<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Middleware\VerifyRequestOrigin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class VerifyRequestOriginTest extends TestCase
{
    #[Test]
    public function it_rejects_a_cross_site_post(): void
    {
        $response = $this->handle($this->request('POST', '/api/v1/orders', fetchSite: 'cross-site'));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_a_same_origin_post(): void
    {
        $response = $this->handle($this->request('POST', '/api/v1/orders', fetchSite: 'same-origin'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_a_none_fetch_site_post(): void
    {
        $response = $this->handle($this->request('POST', '/api/v1/orders', fetchSite: 'none'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_a_same_site_post_when_the_origin_matches_the_host(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/api/v1/orders',
            origin: 'https://shop.example.com',
            fetchSite: 'same-site',
            host: 'shop.example.com',
        ));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_a_same_site_post_when_the_origin_does_not_match_the_host(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/api/v1/orders',
            origin: 'https://evil.example',
            fetchSite: 'same-site',
            host: 'shop.example.com',
        ));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function it_compares_origin_and_host_when_sec_fetch_site_is_missing(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/api/v1/orders',
            origin: 'https://shop.example.com',
            host: 'shop.example.com',
        ));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_a_foreign_origin_when_sec_fetch_site_is_missing(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/api/v1/orders',
            origin: 'https://evil.example',
            host: 'shop.example.com',
        ));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function it_ignores_the_port_when_comparing_origin_and_host(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/api/v1/orders',
            origin: 'http://localhost',
            host: 'localhost',
        ));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_an_unparseable_origin_when_sec_fetch_site_is_missing(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/api/v1/orders',
            origin: 'not a url',
            host: 'localhost',
        ));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_an_unsafe_request_without_origin_or_sec_fetch_site(): void
    {
        $response = $this->handle($this->request('POST', '/api/v1/orders'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_does_not_block_a_get_request_with_cross_site_metadata(): void
    {
        $response = $this->handle($this->request(
            'GET',
            '/api/v1/orders',
            origin: 'https://evil.example',
            fetchSite: 'cross-site',
        ));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function it_does_not_block_a_cross_site_stripe_webhook(): void
    {
        $response = $this->handle($this->request(
            'POST',
            '/webhooks/stripe',
            origin: 'https://evil.example',
            fetchSite: 'cross-site',
        ));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function request(
        string $method,
        string $uri,
        ?string $origin = null,
        ?string $fetchSite = null,
        string $host = 'shop.example.com',
    ): Request {
        $request = Request::create($uri, $method);
        $request->headers->set('Host', $host);

        if ($origin !== null) {
            $request->headers->set('Origin', $origin);
        }

        if ($fetchSite !== null) {
            $request->headers->set('Sec-Fetch-Site', $fetchSite);
        }

        return $request;
    }

    private function handle(Request $request): Response
    {
        return (new VerifyRequestOrigin())->handle(
            $request,
            static fn (): JsonResponse => new JsonResponse(['ok' => true]),
        );
    }
}
