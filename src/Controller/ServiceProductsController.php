<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\Dto\ServiceProductsRequest;
use App\VendingMachine\Application\ServiceProductsUseCase;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ServiceProductsController
{
    public function __construct(private ServiceProductsUseCase $serviceProductsUseCase)
    {
    }

    #[Route('/vending-machine/service/products', name: 'vending_machine_service_products', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponse('invalid_payload', 'Invalid JSON payload.', 400);
        }

        if (!is_array($payload) || !isset($payload['products']) || !is_array($payload['products'])) {
            return $this->errorResponse('invalid_payload', 'Field "products" is required and must be an array.', 400);
        }

        try {
            $response = ($this->serviceProductsUseCase)(new ServiceProductsRequest($payload['products']));
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse('invalid_payload', $exception->getMessage(), 400);
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
