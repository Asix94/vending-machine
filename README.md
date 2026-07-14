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
