<?php

declare(strict_types=1);

namespace App\Controller;

use App\Wallet\Application\AddMoneyUseCase;
use App\Wallet\Application\Dto\AddMoneyRequest;
use App\Wallet\Domain\Exception\InvalidMoneyAmountException;
use App\Wallet\Domain\ValueObject\Money;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class InsertMoneyController
{
    public function __construct(private AddMoneyUseCase $addMoneyUseCase)
    {
    }

    public function __invoke(string $walletId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || !array_key_exists('coins', $payload) || !is_array($payload['coins']) || $payload['coins'] === []) {
            throw new InvalidArgumentException('Field "coins" is required and must be a non-empty array.');
        }

        $coins = [];
        foreach ($payload['coins'] as $coin) {
            if (!is_string($coin)) {
                throw new InvalidArgumentException('Each coin value must be a canonical string.');
            }

            try {
                Money::toCentsFromCanonicalDecimal($coin);
            } catch (InvalidMoneyAmountException $exception) {
                throw $exception;
            }

            $coins[] = $coin;
        }

        $response = ($this->addMoneyUseCase)(new AddMoneyRequest($walletId, $coins));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $jsonResponse->setData($response->toArray());

        return $jsonResponse;
    }
}
