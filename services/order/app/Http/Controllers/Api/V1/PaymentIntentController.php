<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Customer\RequestPaymentIntent\RequestPaymentIntent;
use App\Application\Exceptions\OrderNotFound;
use App\Application\Exceptions\PaymentProviderUnavailable;
use App\Application\Ports\OrderRepository;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Enums\Role;
use App\Http\Auth\AuthUser;
use App\Http\Controllers\Controller;
use App\Http\Responses\ProblemResponse;
use App\Policies\OrderPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentIntentController extends Controller
{
    public function __construct(
        private RequestPaymentIntent $requestPaymentIntent,
        private OrderRepository $orders,
        private OrderPolicy $policy,
    ) {
    }

    public function store(Request $request, string $order): JsonResponse
    {
        $user = $this->authUser($request);

        if ($user->role !== Role::CUSTOMER) {
            return ProblemResponse::make(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                'Only customers can request a payment intent.',
            );
        }

        $found = $this->orders->findById($order);

        if ($found === null || ! $this->policy->pay($user, $found)) {
            return ProblemResponse::make(
                Response::HTTP_NOT_FOUND,
                'Not Found',
                'The order was not found.',
            );
        }

        try {
            $result = $this->requestPaymentIntent->execute($user, $order);
        } catch (OrderNotFound) {
            return ProblemResponse::make(
                Response::HTTP_NOT_FOUND,
                'Not Found',
                'The order was not found.',
            );
        } catch (InvalidOrderTransition $exception) {
            return ProblemResponse::make(
                Response::HTTP_CONFLICT,
                'Conflict',
                $exception->getMessage(),
            );
        } catch (PaymentProviderUnavailable) {
            return ProblemResponse::make(
                Response::HTTP_BAD_GATEWAY,
                'Bad Gateway',
                'The payment provider is unavailable.',
            );
        }

        return new JsonResponse([
            'client_secret' => $result->clientSecret,
            'status' => $result->status,
            'amount_total' => $result->amountTotal,
            'currency' => $result->currency,
        ], $result->created ? Response::HTTP_CREATED : Response::HTTP_OK);
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
