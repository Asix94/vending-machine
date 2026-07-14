# vending-machine

Backend API con Symfony + Docker.

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

### `POST /vending-machine/service`

Configura el estado global de la maquina: stock de productos y monedas disponibles para cambio.

- Request body: obligatorio
- Response: `200 OK`

```json
{
  "products": [
    {"selector": "WATER", "stock": 2},
    {"selector": "JUICE", "stock": 1},
    {"selector": "SODA", "stock": 1}
  ],
  "coins": {
    "0.05": 10,
    "0.10": 10,
    "0.25": 10,
    "1.00": 5
  }
}
```

```json
{
  "products": [
    {"selector": "WATER", "price": 0.65, "stock": 2},
    {"selector": "JUICE", "price": 1.00, "stock": 1},
    {"selector": "SODA", "price": 1.50, "stock": 1}
  ],
  "machine_coins": {
    "0.05": 10,
    "0.10": 10,
    "0.25": 10,
    "1.00": 5
  }
}
```

Notas de contrato:

- La maquina es unica y global para toda la aplicacion.
- `selector` permitido: `WATER`, `JUICE`, `SODA`.
- `stock` y `coin_count` deben ser enteros mayores o iguales a `0`.
- Los precios de producto se mantienen en la maquina (`WATER=0.65`, `JUICE=1.00`, `SODA=1.50`).

Errores esperados:

- `400 Bad Request`: payload invalido, selector invalido o cantidades negativas.
- `500 Internal Server Error`: error inesperado de persistencia.

Ejemplo de llamada:

```bash
curl -X POST http://localhost:8080/vending-machine/service \
  -H "Content-Type: application/json" \
  -d '{
    "products":[
      {"selector":"WATER","stock":2},
      {"selector":"JUICE","stock":1},
      {"selector":"SODA","stock":1}
    ],
    "coins":{"0.05":10,"0.10":10,"0.25":10,"1.00":5}
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
  "wallet_balance_after": 0.0
}
```

Notas de contrato:

- La compra es transaccional: si falla cualquier validacion, no se cambia estado.
- El dinero de la wallet se transfiere a la maquina cuando la compra es exitosa.
- El cambio se calcula con el inventario de monedas de la maquina.
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
