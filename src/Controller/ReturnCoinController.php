<?php

declare(strict_types=1);

namespace App\Controller;

use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\ReturnCoinUseCase;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ReturnCoinController
{
    public function __construct(private ReturnCoinUseCase $returnCoinUseCase)
    {
    }

    #[Route('/wallets/{walletId}/return-coin', name: 'wallet_return_coin', methods: ['POST'])]
    public function __invoke(string $walletId): JsonResponse
    {
        try {
            $response = ($this->returnCoinUseCase)(new ReturnCoinRequest($walletId));
        } catch (WalletNotFoundException) {
            return $this->errorResponse('wallet_not_found', 'Wallet not found.', 404);
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
