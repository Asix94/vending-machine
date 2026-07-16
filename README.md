# vending-machine

Backend API built with Symfony + Docker.

## Key Business Rules

- The vending machine is unique and global.
- The user inserts coins into their wallet (current session).
- On a successful purchase:
  - product stock is reduced by 1,
  - wallet coins are transferred to the machine inventory,
  - if there is a remaining amount, the machine returns exact change,
  - the wallet is reset to `0.0` after purchase (the machine gives the change).
- If exact change is not available, the purchase is rejected with `409` and no state is changed (rollback).
- Allowed coins: `0.05`, `0.10`, `0.25`, `1.00`.
- Supported products: `WATER` (`0.65`), `JUICE` (`1.00`), `SODA` (`1.50`).

## Requirements

- Docker Desktop
- Docker Compose

## Start Environment

```bash
docker compose up -d --build
```

## Useful Commands

```bash
# Check container status
docker compose ps

# Follow service logs
docker compose logs -f nginx
docker compose logs -f php

# Run Symfony commands
docker compose exec php php bin/console about

# Stop environment
docker compose down

# Stop and remove volumes (full reset)
docker compose down -v
```

## Quick Validation

```bash
curl http://localhost:8080/health
```

Expected response:

```json
{"status":"ok"}
```

## API Contract (Step 1 - Create Wallet)

### `POST /wallets`

Creates an empty wallet for the user session.

- Request body: empty
- Response: `201 Created`

```json
{
  "wallet_id": "6b50cf5f-3d66-43dd-90f3-2bd03555c877",
  "inserted_balance": 0.0
}
```

Contract notes:

- `wallet_id`: backend-generated UUID.
- `inserted_balance`: current inserted balance (decimal in API, cents internally).
- Minimum expected error: `500 Internal Server Error` if persistence fails.

Example call:

```bash
curl -X POST http://localhost:8080/wallets
```

## API Contract (Step 2 - Insert Money)

### `POST /wallets/{walletId}/insert-money`

Inserts one or more coins into the user wallet and accumulates balance.

- Request body: required
- Response: `200 OK`

```json
{
  "coins": [0.25, 1.0, 0.1]
}
```

```json
{
  "wallet_id": "6b50cf5f-3d66-43dd-90f3-2bd03555c877",
  "inserted_balance": 1.35,
  "inserted_coins": {
    "0.05": 0,
    "0.10": 1,
    "0.25": 1,
    "1.00": 1
  }
}
```

Contract notes:

- `walletId`: wallet UUID.
- `coins` must be a non-empty array.
- Allowed coins: `0.05`, `0.10`, `0.25`, `1.00`.
- The API uses decimals; business logic/persistence uses cents.

Expected errors:

- `400 Bad Request`: invalid payload, empty `coins`, or invalid coin.
- `404 Not Found`: wallet does not exist.
- `500 Internal Server Error`: unexpected persistence error.

Example call:

```bash
curl -X POST http://localhost:8080/wallets/<wallet_id>/insert-money \
  -H "Content-Type: application/json" \
  -d '{"coins":[0.25,1.0,0.1]}'
```

## API Contract (Step 3 - Return Coin)

### `POST /wallets/{walletId}/return-coin`

Returns all inserted coins in the wallet and resets it to zero balance.

- Request body: empty
- Response: `200 OK`

```json
{
  "wallet_id": "6b50cf5f-3d66-43dd-90f3-2bd03555c877",
  "returned_coins": [1.0, 0.25, 0.1],
  "returned_total": 1.35,
  "wallet_balance_after": 0.0
}
```

Contract notes:

- The wallet is not deleted, only reset (`balance = 0`, no inserted coins).
- If the wallet has no coins, response is `returned_coins: []` and `returned_total: 0.0`.

Expected errors:

- `404 Not Found`: wallet does not exist.
- `500 Internal Server Error`: unexpected persistence error.

Example call:

```bash
curl -X POST http://localhost:8080/wallets/<wallet_id>/return-coin
```

## API Contract (Step 4 - Service Vending Machine)

### `POST /vending-machine/service/products`

Adds stock to existing products in the global machine.

- Request body: required
- Response: `200 OK`

```json
{
  "products": [
    {"product": "WATER", "quantity_to_add": 2},
    {"product": "JUICE", "quantity_to_add": 1}
  ]
}
```

```json
{
  "products": [
    {"selector": "WATER", "price": 0.65, "stock": 2},
    {"selector": "JUICE", "price": 1.00, "stock": 1},
    {"selector": "SODA", "price": 1.50, "stock": 0}
  ]
}
```

### `POST /vending-machine/service/coins`

Adds coins to the global machine change inventory.

- Request body: required
- Response: `200 OK`

```json
{
  "coins": [
    {"coin": "0.25", "quantity_to_add": 4},
    {"coin": "1.00", "quantity_to_add": 1}
  ]
}
```

```json
{
  "machine_coins": {
    "0.05": 0,
    "0.10": 0,
    "0.25": 4,
    "1.00": 1
  }
}
```

Contract notes:

- The machine is unique and global for the whole application.
- `quantity_to_add` must be a positive integer (`> 0`).
- Allowed `product`: `WATER`, `JUICE`, `SODA`.
- Allowed `coin`: `0.05`, `0.10`, `0.25`, `1.00`.

Expected errors:

- `400 Bad Request`: invalid payload, invalid selector/coin, or invalid `quantity_to_add`.
- `500 Internal Server Error`: unexpected persistence error.

Example calls:

```bash
curl -X POST http://localhost:8080/vending-machine/service/products \
  -H "Content-Type: application/json" \
  -d '{
    "products":[
      {"product":"WATER","quantity_to_add":2},
      {"product":"JUICE","quantity_to_add":1}
    ]
  }'

curl -X POST http://localhost:8080/vending-machine/service/coins \
  -H "Content-Type: application/json" \
  -d '{
    "coins":[
      {"coin":"0.25","quantity_to_add":4},
      {"coin":"1.00","quantity_to_add":1}
    ]
  }'
```

## API Contract (Step 5 - Buy Product)

### `POST /vending-machine/buy`

Buys a product using the money inserted in the wallet and returns exact change when applicable.

- Request body: required
- Response: `200 OK`

```json
{
  "wallet_id": "6b50cf5f-3d66-43dd-90f3-2bd03555c877",
  "product": "water"
}
```

```json
{
  "item": {
    "selector": "WATER",
    "price": 0.65
  },
  "change": [0.25, 0.1],
  "wallet_balance_after": 0.0
}
```

Contract notes:

- Purchase is transactional: if any validation fails, no state is changed.
- Wallet money is transferred to the machine on successful purchase.
- Change is calculated from machine coin inventory and returned in `change`.
- Wallet is reset to `0.0` after successful purchase (change is delivered by the machine).
- If exact change is not available, purchase is rejected.

Expected errors:

- `404 Not Found`:
  - `wallet_not_found`
  - `product_not_found`
- `409 Conflict`:
  - `out_of_stock`
  - `insufficient_funds`
  - `cannot_make_exact_change`
- `400 Bad Request`:
  - `invalid_payload` (invalid JSON, missing `wallet_id`, missing `product`)
  - `invalid_selector` (invalid product)
- `500 Internal Server Error`: unexpected persistence error.

Example call:

```bash
curl -X POST http://localhost:8080/vending-machine/buy \
  -H "Content-Type: application/json" \
  -d '{"wallet_id":"<wallet_id>","product":"water"}'
```

## End-to-End Flow (manual)

```bash
# 1) Add stock to products
curl -X POST http://localhost:8080/vending-machine/service/products \
  -H "Content-Type: application/json" \
  -d '{
    "products":[
      {"product":"WATER","quantity_to_add":2},
      {"product":"JUICE","quantity_to_add":1},
      {"product":"SODA","quantity_to_add":1}
    ]
  }'

# 1.1) Add machine coins for change
curl -X POST http://localhost:8080/vending-machine/service/coins \
  -H "Content-Type: application/json" \
  -d '{
    "coins":[
      {"coin":"0.05","quantity_to_add":10},
      {"coin":"0.10","quantity_to_add":10},
      {"coin":"0.25","quantity_to_add":10},
      {"coin":"1.00","quantity_to_add":5}
    ]
  }'

# 2) Create wallet
curl -X POST http://localhost:8080/wallets

# 3) Insert money (replace <wallet_id>)
curl -X POST http://localhost:8080/wallets/<wallet_id>/insert-money \
  -H "Content-Type: application/json" \
  -d '{"coins":[1.0]}'

# 4) Buy product
curl -X POST http://localhost:8080/vending-machine/buy \
  -H "Content-Type: application/json" \
  -d '{"wallet_id":"<wallet_id>","product":"water"}'

# 5) Return coins (if you did not buy or inserted extra money)
curl -X POST http://localhost:8080/wallets/<wallet_id>/return-coin
```

## Tests

```bash
# Create and prepare test database
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Run full test suite
docker compose exec php php bin/phpunit
```
