<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\Dto\ServiceProductsRequest;
use App\VendingMachine\Application\ServiceProductsUseCase;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ServiceProductsController
{
    public function __construct(private ServiceProductsUseCase $serviceProductsUseCase)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || !isset($payload['products']) || !is_array($payload['products'])) {
            throw new InvalidArgumentException('Field "products" is required and must be an array.');
        }

        $response = ($this->serviceProductsUseCase)(new ServiceProductsRequest($payload['products']));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $payload = $response->toArray();
        $jsonResponse->setData([
            'products' => $payload['products'],
        ]);

        return $jsonResponse;
    }
}
