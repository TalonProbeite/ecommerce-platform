<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Exceptions\ForbiddenException;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Query\ListOrderEvents\ListOrderEventsUseCase;
use App\Http\Auth\AuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderEventsRequest;
use App\Http\Resources\OrderEventResource;
use App\Http\Responses\ProblemResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class OrderEventController extends Controller
{
    public function __construct(private ListOrderEventsUseCase $listOrderEvents)
    {
    }

    public function index(OrderEventsRequest $request, string $order): JsonResponse
    {
        $validated = $request->validated();
        $cursor = isset($validated['cursor']) ? (string) $validated['cursor'] : null;
        $limit = isset($validated['limit'])
            ? (int) $validated['limit']
            : ListOrderEventsUseCase::DEFAULT_LIMIT;

        try {
            $page = $this->listOrderEvents->execute(
                $this->authUser($request),
                $order,
                $cursor,
                $limit,
            );
        } catch (ForbiddenException $exception) {
            return ProblemResponse::make(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                $exception->getMessage(),
            );
        } catch (OrderNotFound) {
            return ProblemResponse::make(
                Response::HTTP_NOT_FOUND,
                'Not Found',
                'The order was not found.',
            );
        } catch (InvalidCursor|InvalidArgumentException $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
            );
        }

        return OrderEventResource::collection($page->events)
            ->additional([
                'meta' => [
                    'next_cursor' => $page->nextCursor,
                    'limit' => $limit,
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
