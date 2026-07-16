<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\BuyProductUseCase;
use App\VendingMachine\Application\Dto\BuyProductRequest;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class BuyProductController
{
    public function __construct(private BuyProductUseCase $buyProductUseCase)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $walletId = $payload['wallet_id'] ?? null;
        if (!is_string($walletId) || $walletId === '') {
            throw new InvalidArgumentException('Field "wallet_id" is required and must be a non-empty string.');
        }

        $product = $payload['product'] ?? null;
        if (!is_string($product) || $product === '') {
            throw new InvalidArgumentException('Field "product" is required and must be a non-empty string.');
        }

        $response = ($this->buyProductUseCase)(new BuyProductRequest($walletId, $product));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $jsonResponse->setData($response->toArray());

        return $jsonResponse;
    }
}
