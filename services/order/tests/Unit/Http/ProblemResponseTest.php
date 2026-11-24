<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Responses\ProblemResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProblemResponseTest extends TestCase
{
    #[Test]
    public function it_builds_a_problem_json_response(): void
    {
        $response = ProblemResponse::make(422, 'Unprocessable Entity', 'The cursor is invalid.');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'The cursor is invalid.',
        ], $response->getData(true));
    }

    #[Test]
    public function it_merges_extra_fields_into_the_payload(): void
    {
        $response = ProblemResponse::make(409, 'Conflict', 'Already exists.', [
            'code' => 'slug_conflict',
            'errors' => ['slug' => ['Taken']],
        ]);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'Already exists.',
            'code' => 'slug_conflict',
            'errors' => ['slug' => ['Taken']],
        ], $response->getData(true));
    }
}
