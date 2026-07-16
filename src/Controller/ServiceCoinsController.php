<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\Dto\ServiceCoinsRequest;
use App\VendingMachine\Application\ServiceCoinsUseCase;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ServiceCoinsController
{
    public function __construct(private ServiceCoinsUseCase $serviceCoinsUseCase)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || !isset($payload['coins']) || !is_array($payload['coins'])) {
            throw new InvalidArgumentException('Field "coins" is required and must be an array.');
        }

        $response = ($this->serviceCoinsUseCase)(new ServiceCoinsRequest($payload['coins']));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $payload = $response->toArray();
        $jsonResponse->setData([
            'machine_coins' => $payload['machine_coins'],
        ]);

        return $jsonResponse;
    }
}
