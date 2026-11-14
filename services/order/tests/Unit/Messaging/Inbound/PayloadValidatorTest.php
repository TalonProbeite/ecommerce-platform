<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Inbound;

use App\Infrastructure\Messaging\Inbound\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\Inbound\PayloadValidator;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PayloadValidatorTest extends TestCase
{
    #[Test]
    public function it_returns_the_validated_payload(): void
    {
        $payload = ['event_id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7', 'extra' => 'ignored'];

        $validated = PayloadValidator::validate($payload, [
            'event_id' => ['required', 'uuid'],
        ]);

        self::assertSame(['event_id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7'], $validated);
    }

    #[Test]
    public function it_throws_with_an_associative_error_map(): void
    {
        try {
            PayloadValidator::validate(['name' => ''], [
                'name' => ['required', 'string'],
                'quantity' => ['required', 'integer'],
            ]);
        } catch (InvalidMessageException $exception) {
            self::assertSame(['name', 'quantity'], array_keys($exception->errors));
            self::assertArrayHasKey('name', $exception->errors);
            self::assertArrayHasKey('quantity', $exception->errors);
            self::assertContainsOnly('string', $exception->errors['name']);

            return;
        }

        self::fail('Expected InvalidMessageException was not thrown.');
    }

    #[Test]
    public function it_runs_the_after_callback_and_collects_its_errors(): void
    {
        $afterWasCalled = false;

        try {
            PayloadValidator::validate(
                ['starts_at' => '2026-11-21T09:00:00+00:00'],
                ['starts_at' => ['required', 'string']],
                static function (Validator $validator) use (&$afterWasCalled): void {
                    $afterWasCalled = true;
                    $validator->errors()->add('starts_at', 'Custom window violation.');
                },
            );
        } catch (InvalidMessageException $exception) {
            self::assertTrue($afterWasCalled);
            self::assertArrayHasKey('starts_at', $exception->errors);
            self::assertContains('Custom window violation.', $exception->errors['starts_at']);

            return;
        }

        self::fail('Expected InvalidMessageException was not thrown.');
    }
}
