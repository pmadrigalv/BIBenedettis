# Angular + Laravel + MySQL en Docker

Este workspace levanta una aplicacion full stack con Angular 21, Laravel 13 y MySQL usando Docker Compose.

## Servicios

- `frontend`: Angular dev server en `http://localhost:4200`
- `backend`: Laravel en `http://localhost:8000`
- `mysql`: MySQL en `localhost:3306`

## Arranque

```bash
docker compose up --build
```

## Variables principales

Las variables de Docker Compose estan en `.env`. Si necesitas otra configuracion, copia `.env.example` y ajusta puertos o credenciales.

## Verificacion

- Frontend: abre `http://localhost:4200`
- API de salud: `http://localhost:8000/api/health`

## Estructura

- `frontend/`: aplicacion Angular
- `backend/`: API Laravel
- `docker-compose.yml`: orquestacion local