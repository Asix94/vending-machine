<?php

declare(strict_types=1);

namespace App\Controller;

use App\Wallet\Application\AddMoneyUseCase;
use App\Wallet\Application\Dto\AddMoneyRequest;
use App\Wallet\Domain\Exception\InvalidMoneyAmountException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class InsertMoneyController
{
    public function __construct(private AddMoneyUseCase $addMoneyUseCase)
    {
    }

    #[Route('/wallets/{walletId}/insert-money', name: 'wallet_insert_money', methods: ['POST'])]
    public function __invoke(string $walletId, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponse('Invalid JSON payload.', 400);
        }

        if (!is_array($payload) || !array_key_exists('coins', $payload) || !is_array($payload['coins']) || $payload['coins'] === []) {
            return $this->errorResponse('Field "coins" is required and must be a non-empty array.', 400);
        }

        $coins = [];
        foreach ($payload['coins'] as $coin) {
            if (!is_int($coin) && !is_float($coin)) {
                return $this->errorResponse('Each coin value must be numeric.', 400);
            }

            $coins[] = (float) $coin;
        }

        try {
            $response = ($this->addMoneyUseCase)(new AddMoneyRequest($walletId, $coins));
        } catch (WalletNotFoundException) {
            return $this->errorResponse('Wallet not found.', 404);
        } catch (InvalidMoneyAmountException|InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $jsonResponse->setData($response->toArray());

        return $jsonResponse;
    }

    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
        ], $statusCode);
    }
}
