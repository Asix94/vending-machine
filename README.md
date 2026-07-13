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
docker compose logs -f mysql

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

## Variables Docker

Las variables de MySQL para Docker estan en `.env.docker`.
