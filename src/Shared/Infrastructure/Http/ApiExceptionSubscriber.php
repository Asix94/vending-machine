<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Exception\InsufficientFundsException;
use App\VendingMachine\Domain\Exception\OutOfStockException;
use App\VendingMachine\Domain\Exception\ProductNotFoundException;
use App\Wallet\Domain\Exception\InvalidMoneyAmountException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        [$statusCode, $error, $message] = $this->mapException($exception);

        $event->setResponse(new JsonResponse([
            'error' => $error,
            'message' => $message,
        ], $statusCode));
    }

    /**
     * @return array{int, string, string}
     */
    private function mapException(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof WalletNotFoundException => [Response::HTTP_NOT_FOUND, 'wallet_not_found', 'Wallet not found.'],
            $exception instanceof ProductNotFoundException => [Response::HTTP_NOT_FOUND, 'product_not_found', 'Product not found.'],
            $exception instanceof OutOfStockException => [Response::HTTP_CONFLICT, 'out_of_stock', 'Selected product is out of stock.'],
            $exception instanceof InsufficientFundsException => [Response::HTTP_CONFLICT, 'insufficient_funds', 'Insufficient funds to buy selected product.'],
            $exception instanceof CannotMakeExactChangeException => [Response::HTTP_CONFLICT, 'cannot_make_exact_change', 'Cannot complete purchase because exact change is not available.'],
            $exception instanceof InvalidMoneyAmountException => [Response::HTTP_BAD_REQUEST, 'invalid_money_amount', $exception->getMessage()],
            $exception instanceof JsonException => [Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Invalid JSON payload.'],
            $exception instanceof InvalidArgumentException && $exception->getMessage() === 'Wallet ID must be a valid UUID.' => [Response::HTTP_BAD_REQUEST, 'invalid_wallet_id', 'Wallet ID must be a valid UUID.'],
            $exception instanceof InvalidArgumentException => [Response::HTTP_BAD_REQUEST, 'invalid_payload', $exception->getMessage()],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_server_error', 'Internal server error.'],
        };
    }
}
