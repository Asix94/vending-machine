<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\Dto\ServiceCoinsRequest;
use App\VendingMachine\Application\ServiceCoinsUseCase;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class ServiceCoinsController
{
    public function __construct(private ServiceCoinsUseCase $serviceCoinsUseCase)
    {
    }

    #[Route('/vending-machine/service/coins', name: 'vending_machine_service_coins', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponse('invalid_payload', 'Invalid JSON payload.', 400);
        }

        if (!is_array($payload) || !isset($payload['coins']) || !is_array($payload['coins'])) {
            return $this->errorResponse('invalid_payload', 'Field "coins" is required and must be an array.', 400);
        }

        try {
            $response = ($this->serviceCoinsUseCase)(new ServiceCoinsRequest($payload['coins']));
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse('invalid_payload', $exception->getMessage(), 400);
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setEncodingOptions($jsonResponse->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);
        $payload = $response->toArray();
        $jsonResponse->setData([
            'machine_coins' => $payload['machine_coins'],
        ]);

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
