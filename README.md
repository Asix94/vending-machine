# vending-machine

Backend API con Symfony + Docker.

## Reglas de negocio clave

- La maquina es unica y global.
- El usuario inserta monedas en su wallet (sesion actual).
- En una compra exitosa:
  - el stock del producto baja en 1,
  - las monedas de la wallet pasan al inventario de la maquina,
  - si hay sobrante, la maquina devuelve cambio exacto,
  - el cambio tambien queda acreditado en la wallet.
- Si no hay cambio exacto disponible, la compra se rechaza con `409` y no cambia estado (rollback).
- Monedas permitidas: `0.05`, `0.10`, `0.25`, `1.00`.
- Productos soportados: `WATER` (`0.65`), `JUICE` (`1.00`), `SODA` (`1.50`).

## Requisitos

- Docker Desktop
- Docker Compose

## Levantar entorno

```bash
docker compose up -d --build
```

## Comandos utiles

```bash
# Ver estado de contenedores
docker compose ps

# Ver logs de un servicio
docker compose logs -f nginx
docker compose logs -f php

# Ejecutar comandos de Symfony
docker compose exec php php bin/console about

# Parar entorno
docker compose down

# Parar y borrar volumenes (reset completo)
docker compose down -v
```

## Validacion rapida

```bash
curl http://localhost:8080/health
```

Respuesta esperada:

```json
{"status":"ok"}
```

## API Contract (Paso 1 - Create Wallet)

### `POST /wallets`

Crea una wallet vacia para la sesion del usuario.

- Request body: vacio
- Response: `201 Created`

```json
{
  "wallet_id": "6b50cf5f-3d66-43dd-90f3-2bd03555c877",
  "inserted_balance": 0.0
}
```

Notas de contrato:

- `wallet_id`: UUID generado por backend.
- `inserted_balance`: saldo actual insertado (en API decimal, internamente en centimos).
- Error minimo esperado: `500 Internal Server Error` si falla persistencia.

Ejemplo de llamada:

```bash
curl -X POST http://localhost:8080/wallets
```

## API Contract (Paso 2 - Insert Money)

### `POST /wallets/{walletId}/insert-money`

Inserta una o varias monedas en la wallet del usuario y acumula el saldo.

- Request body: obligatorio
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

Notas de contrato:

- `walletId`: UUID de la wallet.
- `coins` debe ser un array no vacio.
- Monedas permitidas: `0.05`, `0.10`, `0.25`, `1.00`.
- El API trabaja con decimales y la logica/persistencia con centimos.

Errores esperados:

- `400 Bad Request`: payload invalido, `coins` vacio o moneda invalida.
- `404 Not Found`: wallet no existe.
- `500 Internal Server Error`: error inesperado de persistencia.

Ejemplo de llamada:

```bash
curl -X POST http://localhost:8080/wallets/<wallet_id>/insert-money \
  -H "Content-Type: application/json" \
  -d '{"coins":[0.25,1.0,0.1]}'
```

## API Contract (Paso 3 - Return Coin)

### `POST /wallets/{walletId}/return-coin`

Devuelve todas las monedas insertadas en la wallet y la deja en saldo cero.

- Request body: vacio
- Response: `200 OK`

```json
{
  "wallet_id": "6b50cf5f-3d66-43dd-90f3-2bd03555c877",
  "returned_coins": [1.0, 0.25, 0.1],
  "returned_total": 1.35,
  "wallet_balance_after": 0.0
}
```

Notas de contrato:

- La wallet no se elimina, solo se resetea su estado (`balance = 0`, sin monedas insertadas).
- Si la wallet no tenia monedas, devuelve `returned_coins: []` y `returned_total: 0.0`.

Errores esperados:

- `404 Not Found`: wallet no existe.
- `500 Internal Server Error`: error inesperado de persistencia.

Ejemplo de llamada:

```bash
curl -X POST http://localhost:8080/wallets/<wallet_id>/return-coin
```

## API Contract (Paso 4 - Service Vending Machine)

### `POST /vending-machine/service/products`

Suma stock a productos existentes de la maquina global.

- Request body: obligatorio
- Response: `200 OK`

```json
{
  "products": [
    {"selector": "WATER", "quantity_to_add": 2},
    {"selector": "JUICE", "quantity_to_add": 1}
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

Suma monedas al inventario de cambio de la maquina global.

- Request body: obligatorio
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

Notas de contrato:

- La maquina es unica y global para toda la aplicacion.
- `quantity_to_add` debe ser entero positivo (`> 0`).
- `selector` permitido: `WATER`, `JUICE`, `SODA`.
- `coin` permitido: `0.05`, `0.10`, `0.25`, `1.00`.

Errores esperados:

- `400 Bad Request`: payload invalido, selector/coin invalido o `quantity_to_add` invalido.
- `500 Internal Server Error`: error inesperado de persistencia.

Ejemplos de llamada:

```bash
curl -X POST http://localhost:8080/vending-machine/service/products \
  -H "Content-Type: application/json" \
  -d '{
    "products":[
      {"selector":"WATER","quantity_to_add":2},
      {"selector":"JUICE","quantity_to_add":1}
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

## API Contract (Paso 5 - Buy Product)

### `POST /wallets/{walletId}/buy/{selector}`

Compra un producto usando el dinero insertado en la wallet y devuelve cambio exacto cuando aplique.

- Request body: vacio
- Response: `200 OK`

```json
{
  "item": {
    "selector": "WATER",
    "price": 0.65
  },
  "change": [0.25, 0.1],
  "wallet_balance_after": 0.35
}
```

Notas de contrato:

- La compra es transaccional: si falla cualquier validacion, no se cambia estado.
- El dinero de la wallet se transfiere a la maquina cuando la compra es exitosa.
- El cambio se calcula con el inventario de monedas de la maquina y se acredita en la wallet.
- Si no hay cambio exacto, se rechaza la compra.

Errores esperados:

- `404 Not Found`: wallet no existe o producto no existe.
- `409 Conflict`:
  - `out_of_stock`
  - `insufficient_funds`
  - `cannot_make_exact_change`
- `400 Bad Request`: selector invalido o formato invalido.
- `500 Internal Server Error`: error inesperado de persistencia.

Ejemplo de llamada:

```bash
curl -X POST http://localhost:8080/wallets/<wallet_id>/buy/WATER
```

## Flujo end-to-end (manual)

```bash
# 1) Sumar stock a productos
curl -X POST http://localhost:8080/vending-machine/service/products \
  -H "Content-Type: application/json" \
  -d '{
    "products":[
      {"selector":"WATER","quantity_to_add":2},
      {"selector":"JUICE","quantity_to_add":1},
      {"selector":"SODA","quantity_to_add":1}
    ]
  }'

# 1.1) Sumar monedas para cambio
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

# 2) Crear wallet
curl -X POST http://localhost:8080/wallets

# 3) Insertar dinero (reemplaza <wallet_id>)
curl -X POST http://localhost:8080/wallets/<wallet_id>/insert-money \
  -H "Content-Type: application/json" \
  -d '{"coins":[1.0]}'

# 4) Comprar producto
curl -X POST http://localhost:8080/wallets/<wallet_id>/buy/WATER

# 5) Devolver monedas (si no compraste o insertaste mas dinero)
curl -X POST http://localhost:8080/wallets/<wallet_id>/return-coin
```

## Tests

```bash
# Crear y preparar base de datos de test
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Ejecutar suite completa
docker compose exec php php bin/phpunit
```
