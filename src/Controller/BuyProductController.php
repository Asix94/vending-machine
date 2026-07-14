<?php

declare(strict_types=1);

namespace App\Controller;

use App\VendingMachine\Application\BuyProductUseCase;
use App\VendingMachine\Application\Dto\BuyProductRequest;
use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Exception\InsufficientFundsException;
use App\VendingMachine\Domain\Exception\OutOfStockException;
use App\VendingMachine\Domain\Exception\ProductNotFoundException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_PRESERVE_ZERO_FRACTION;

final readonly class BuyProductController
{
    public function __construct(private BuyProductUseCase $buyProductUseCase)
    {
    }

    #[Route('/wallets/{walletId}/buy/{selector}', name: 'wallet_buy_product', methods: ['POST'])]
    public function __invoke(string $walletId, string $selector): JsonResponse
    {
        try {
            $response = ($this->buyProductUseCase)(new BuyProductRequest($walletId, strtoupper($selector)));
        } catch (WalletNotFoundException|ProductNotFoundException) {
            return $this->errorResponse('not_found', 'Wallet or product not found.', 404);
        } catch (OutOfStockException) {
            return $this->errorResponse('out_of_stock', 'Selected product is out of stock.', 409);
        } catch (InsufficientFundsException) {
            return $this->errorResponse('insufficient_funds', 'Insufficient funds to buy selected product.', 409);
        } catch (CannotMakeExactChangeException) {
            return $this->errorResponse('cannot_make_exact_change', 'Cannot complete purchase because exact change is not available.', 409);
        } catch (\InvalidArgumentException) {
            return $this->errorResponse('invalid_selector', 'Invalid selector. Allowed values are WATER, JUICE, SODA.', 400);
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
