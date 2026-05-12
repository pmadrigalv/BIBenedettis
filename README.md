# Angular + Laravel + MySQL en Docker

Este workspace levanta una aplicacion full stack con Angular 21, Laravel 13 y MySQL usando Docker Compose.

## Modos de uso

- `docker-compose.yml`: desarrollo (Angular con `ng serve` + proxy)
- `docker-compose.prod.yml`: produccion (Angular compilado en Nginx)

## Puertos de produccion (host)

- `frontend`: `http://localhost:8004`
- `backend`: `http://localhost:8005`
- `mysql`: `localhost:3310`

## Despliegue de produccion

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

Comandos utiles:

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f
docker compose -f docker-compose.prod.yml down
```

## Variables principales

Las variables de Docker Compose estan en `.env`. Si necesitas otra configuracion, copia `.env.example` y ajusta puertos o credenciales.

## Verificacion

- Frontend: `http://localhost:8004`
- API de salud: `http://localhost:8005/api/health`

## Estructura

- `frontend/`: aplicacion Angular
- `backend/`: API Laravel
- `docker-compose.yml`: orquestacion de desarrollo
- `docker-compose.prod.yml`: orquestacion de produccion