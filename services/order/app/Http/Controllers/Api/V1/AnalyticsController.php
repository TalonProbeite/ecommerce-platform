<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Exceptions\ForbiddenException;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Query\OverdueRentals\ListOverdueRentalsUseCase;
use App\Http\Auth\AuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverdueRentalsRequest;
use App\Http\Resources\OverdueRentalResource;
use App\Http\Responses\ProblemResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AnalyticsController extends Controller
{
    public function __construct(private ListOverdueRentalsUseCase $listOverdueRentals)
    {
    }

    public function overdueRentals(OverdueRentalsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $storeId = isset($validated['store_id']) ? (string) $validated['store_id'] : null;
        $cursor = isset($validated['cursor']) ? (string) $validated['cursor'] : null;
        $limit = isset($validated['limit'])
            ? (int) $validated['limit']
            : ListOverdueRentalsUseCase::DEFAULT_LIMIT;

        try {
            $page = $this->listOverdueRentals->execute(
                $this->authUser($request),
                $storeId,
                $cursor,
                $limit,
            );
        } catch (ForbiddenException $exception) {
            return ProblemResponse::make(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                $exception->getMessage(),
            );
        } catch (InvalidCursor $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
            );
        }

        return OverdueRentalResource::collection($page->items)
            ->additional([
                'meta' => [
                    'next_cursor' => $page->nextCursor,
                    'count' => count($page->items),
                ],
            ])
            ->response();
    }

    private function authUser(Request $request): AuthUser
    {
        $user = $request->attributes->get(AuthUser::ATTRIBUTE);

        if (! $user instanceof AuthUser) {
            throw new \LogicException('The Authenticate middleware did not set an AuthUser.');
        }

        return $user;
    }
}
