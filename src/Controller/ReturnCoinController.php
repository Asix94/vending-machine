<?php

declare(strict_types=1);

namespace App\Controller;

use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\ReturnCoinUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ReturnCoinController
{
    public function __construct(private ReturnCoinUseCase $returnCoinUseCase)
    {
    }

    public function __invoke(string $walletId): JsonResponse
    {
        $response = ($this->returnCoinUseCase)(new ReturnCoinRequest($walletId));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $jsonResponse->setData($response->toArray());

        return $jsonResponse;
    }
}
