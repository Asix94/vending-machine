<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\BuyProductUseCase;
use App\VendingMachine\Application\Dto\BuyProductRequest;
use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Exception\InsufficientFundsException;
use App\VendingMachine\Domain\Exception\OutOfStockException;
use App\VendingMachine\Domain\Exception\ProductNotFoundException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use InvalidArgumentException;
use JsonException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class BuyProductController
{
    public function __construct(private BuyProductUseCase $buyProductUseCase)
    {
    }

    public function __invoke(string $machineId, Request $request): JsonResponse
    {
        if (!Uuid::isValid($machineId)) {
            return $this->errorResponse('invalid_machine_id', 'Machine ID must be a valid UUID.', 400);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponse('invalid_payload', 'Invalid JSON payload.', 400);
        }

        if (!is_array($payload)) {
            return $this->errorResponse('invalid_payload', 'Invalid JSON payload.', 400);
        }

        $walletId = $payload['wallet_id'] ?? null;
        if (!is_string($walletId) || $walletId === '') {
            return $this->errorResponse('invalid_payload', 'Field "wallet_id" is required and must be a non-empty string.', 400);
        }

        $product = $payload['product'] ?? null;
        if (!is_string($product) || $product === '') {
            return $this->errorResponse('invalid_payload', 'Field "product" is required and must be a non-empty string.', 400);
        }

        try {
            $response = ($this->buyProductUseCase)(new BuyProductRequest($machineId, $walletId, $product));
        } catch (WalletNotFoundException) {
            return $this->errorResponse('wallet_not_found', 'Wallet not found.', 404);
        } catch (ProductNotFoundException) {
            return $this->errorResponse('product_not_found', 'Product not found.', 404);
        } catch (OutOfStockException) {
            return $this->errorResponse('out_of_stock', 'Selected product is out of stock.', 409);
        } catch (InsufficientFundsException) {
            return $this->errorResponse('insufficient_funds', 'Insufficient funds to buy selected product.', 409);
        } catch (CannotMakeExactChangeException) {
            return $this->errorResponse('cannot_make_exact_change', 'Cannot complete purchase because exact change is not available.', 409);
        } catch (InvalidArgumentException) {
            return $this->errorResponse('invalid_selector', 'Invalid selector. Allowed values are WATER, JUICE, SODA.', 400);
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $jsonResponse->setData($response->toArray());

        return $jsonResponse;
    }

    private function errorResponse(string $error, string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'message' => $message,
        ], $statusCode);
    }
}
