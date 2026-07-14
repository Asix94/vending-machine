<?php

declare(strict_types=1);

namespace App\Controller;

use App\Wallet\Application\CreateWalletUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class CreateWalletController
{
    public function __construct(private CreateWalletUseCase $createWalletUseCase)
    {
    }

    #[Route('/wallets', name: 'wallet_create', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $response = ($this->createWalletUseCase)();

        $jsonResponse = new JsonResponse(
            [
                'wallet_id' => $response->walletId,
                'inserted_balance' => $response->insertedBalance,
            ],
            Response::HTTP_CREATED,
            [
                'Location' => sprintf('/wallets/%s', $response->walletId),
            ],
        );

        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);

        return $jsonResponse;
    }
}
