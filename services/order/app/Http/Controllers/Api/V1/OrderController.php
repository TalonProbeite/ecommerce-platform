<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Customer\CreateOrder\CreateOrderCommand;
use App\Application\Customer\CreateOrder\CreateOrderUseCase;
use App\Application\Exceptions\IdempotencyFingerprintMismatch;
use App\Application\Exceptions\IdempotencyLockConflict;
use App\Application\Exceptions\InvalidCursor;
use App\Application\Exceptions\ProductUnavailable;
use App\Application\Ports\OrderRepository;
use App\Application\Query\ListOrders\ListOrdersQuery;
use App\Application\Query\ListOrders\ListOrdersUseCase;
use App\Domain\Exceptions\InvalidRentalPeriod;
use App\Domain\Exceptions\MixedStoreOrder;
use App\Domain\Order\OrderStatus;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ProblemResponse;
use App\Policies\OrderPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class OrderController extends Controller
{
    public function __construct(
        private CreateOrderUseCase $createOrder,
        private ListOrdersUseCase $listOrders,
        private OrderRepository $orders,
        private OrderPolicy $policy,
    ) {
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = $this->authUser($request);

        if (! $this->policy->create($user)) {
            return ProblemResponse::make(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                'Only customers can create orders.',
            );
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || preg_match('/^[\x21-\x7E]{1,64}$/D', $idempotencyKey) !== 1) {
            return ProblemResponse::make(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                'Idempotency-Key must contain 1 to 64 visible ASCII characters.',
            );
        }

        try {
            $result = $this->createOrder->execute(CreateOrderCommand::fromValidated(
                $user->id,
                $idempotencyKey,
                $request->validated(),
            ));
        } catch (IdempotencyLockConflict $exception) {
            return ProblemResponse::make(
                Response::HTTP_CONFLICT,
                'Conflict',
                $exception->getMessage(),
            );
        } catch (IdempotencyFingerprintMismatch $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
            );
        } catch (ProductUnavailable $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
                ['product_id' => $exception->productId],
            );
        } catch (MixedStoreOrder $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
            );
        } catch (InvalidRentalPeriod $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
            );
        }

        $response = (new OrderResource($result->order))
            ->response()
            ->setStatusCode($result->created ? Response::HTTP_CREATED : Response::HTTP_OK);

        if ($result->created) {
            $response->header('Location', route('orders.show', ['order' => $result->order->id()]));
        }

        return $response;
    }

    public function index(ListOrdersRequest $request): JsonResponse
    {
        $user = $this->authUser($request);
        $validated = $request->validated();
        $requestedStoreId = isset($validated['store_id']) ? (string) $validated['store_id'] : null;

        if ($requestedStoreId !== null && ! in_array($user->role, [Role::ADMIN, Role::ANALYST], true)) {
            return ProblemResponse::make(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                'Only admin and analyst can filter orders by store_id.',
            );
        }

        $customerId = $user->role === Role::CUSTOMER ? $user->id : null;
        $storeId = match ($user->role) {
            Role::CUSTOMER => null,
            Role::STORE_MANAGER => $user->storeId,
            Role::ADMIN, Role::ANALYST => $requestedStoreId,
        };

        $status = isset($validated['status'])
            ? OrderStatus::from((string) $validated['status'])
            : null;

        $limit = isset($validated['limit'])
            ? (int) $validated['limit']
            : ListOrdersQuery::DEFAULT_LIMIT;

        try {
            $page = $this->listOrders->execute(new ListOrdersQuery(
                customerId: $customerId,
                storeId: $storeId,
                status: $status,
                cursor: isset($validated['cursor']) ? (string) $validated['cursor'] : null,
                limit: $limit,
            ));
        } catch (InvalidCursor $exception) {
            return ProblemResponse::make(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $exception->getMessage(),
            );
        }

        return OrderResource::collection($page->orders)
            ->additional([
                'meta' => [
                    'next_cursor' => $page->nextCursor,
                    'limit' => $limit,
                ],
            ])
            ->response();
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $user = $this->authUser($request);
        $found = $this->orders->findById($order);

        if ($found === null || ! $this->policy->view($user, $found)) {
            return ProblemResponse::make(
                Response::HTTP_NOT_FOUND,
                'Not Found',
                'The order was not found.',
            );
        }

        return (new OrderResource($found))->response();
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
