<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Customer\CancelOrder\CancelOrder;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\PaymentInProgress;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Exceptions\RefundExceedsPayment;
use App\Application\Ports\OrderRepository;
use App\Application\Staff\IssueItems\IssueItems;
use App\Application\Staff\ReturnItems\ReturnItems;
use App\Domain\Exceptions\InvalidItemTransition;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Domain\Exceptions\ItemNotInOrder;
use App\Domain\Exceptions\ReturnBeforeRentalStart;
use App\Domain\Order\CancelReason;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIdsRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ProblemResponse;
use App\Policies\OrderPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OrderActionController extends Controller
{
    public function __construct(
        private CancelOrder $cancelOrder,
        private IssueItems $issueItems,
        private ReturnItems $returnItems,
        private OrderRepository $orders,
        private OrderPolicy $policy,
    ) {
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $user = $this->authUser($request);
        $found = $this->orders->findById($order);

        if ($found === null || ! $this->policy->cancel($user, $found)) {
            return $this->notFound();
        }

        try {
            $result = $this->cancelOrder->execute(
                $user,
                $order,
                $user->role === Role::CUSTOMER ? CancelReason::CUSTOMER_REQUEST : CancelReason::ADMIN_REQUEST,
            );
        } catch (OrderNotFound) {
            return $this->notFound();
        } catch (InvalidOrderTransition|PaymentInProgress $exception) {
            return ProblemResponse::make(
                Response::HTTP_CONFLICT,
                'Conflict',
                $exception->getMessage(),
            );
        } catch (PaymentProviderUnavailable) {
            return $this->badGateway();
        }

        return (new OrderResource($result->order))->response();
    }

    public function issue(ItemIdsRequest $request, string $order): JsonResponse
    {
        $user = $this->authUser($request);
        $found = $this->orders->findById($order);

        if ($found === null || ! $this->policy->issue($user, $found)) {
            return $this->notFound();
        }

        try {
            $result = $this->issueItems->execute($user, $order, $this->itemIds($request));
        } catch (OrderNotFound) {
            return $this->notFound();
        } catch (InvalidOrderTransition|InvalidItemTransition $exception) {
            return ProblemResponse::make(
                Response::HTTP_CONFLICT,
                'Conflict',
                $exception->getMessage(),
            );
        } catch (ItemNotInOrder $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
                ['item_id' => $exception->itemId],
            );
        }

        return (new OrderResource($result->order))
            ->additional(['skipped_item_ids' => $result->skippedItemIds])
            ->response();
    }

    public function return(ItemIdsRequest $request, string $order): JsonResponse
    {
        $user = $this->authUser($request);
        $found = $this->orders->findById($order);

        if ($found === null || ! $this->policy->return($user, $found)) {
            return $this->notFound();
        }

        try {
            $result = $this->returnItems->execute($user, $order, $this->itemIds($request));
        } catch (OrderNotFound) {
            return $this->notFound();
        } catch (InvalidOrderTransition|InvalidItemTransition|ReturnBeforeRentalStart $exception) {
            return ProblemResponse::make(
                Response::HTTP_CONFLICT,
                'Conflict',
                $exception->getMessage(),
            );
        } catch (ItemNotInOrder $exception) {
            return ProblemResponse::make(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Unprocessable Entity',
                $exception->getMessage(),
                ['item_id' => $exception->itemId],
            );
        } catch (RefundExceedsPayment $exception) {
            return ProblemResponse::make(
                Response::HTTP_CONFLICT,
                'Conflict',
                $exception->getMessage(),
            );
        } catch (PaymentProviderUnavailable) {
            return $this->badGateway();
        }

        return (new OrderResource($result->order))
            ->additional(['skipped_item_ids' => $result->skippedItemIds])
            ->response();
    }

    /**
     * @return list<string>
     */
    private function itemIds(ItemIdsRequest $request): array
    {
        /** @var list<string> $itemIds */
        $itemIds = $request->validated('item_ids');

        return $itemIds;
    }

    private function notFound(): JsonResponse
    {
        return ProblemResponse::make(
            Response::HTTP_NOT_FOUND,
            'Not Found',
            'The order was not found.',
        );
    }

    private function badGateway(): JsonResponse
    {
        return ProblemResponse::make(
            Response::HTTP_BAD_GATEWAY,
            'Bad Gateway',
            'The payment provider is unavailable.',
        );
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
