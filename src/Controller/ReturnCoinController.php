<?php

declare(strict_types=1);

namespace App\Controller;

use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\ReturnCoinUseCase;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ReturnCoinController
{
    public function __construct(private ReturnCoinUseCase $returnCoinUseCase)
    {
    }

    public function __invoke(string $walletId): JsonResponse
    {
        try {
            $response = ($this->returnCoinUseCase)(new ReturnCoinRequest($walletId));
        } catch (WalletNotFoundException) {
            return $this->errorResponse('wallet_not_found', 'Wallet not found.', 404);
        } catch (InvalidArgumentException) {
            return $this->errorResponse('invalid_wallet_id', 'Wallet ID must be a valid UUID.', 400);
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
