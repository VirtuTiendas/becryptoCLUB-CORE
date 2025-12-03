# BeCryptoCLUB – Code X Hub

Esqueleto inicial para la plataforma modular basada en **Laravel 11**, **Livewire 3** y **Tailwind** con módulos de minería, mercados e IA.

## Estructura principal
- `app/Modules/Mining/*`: Aleo, IronFish, VerusHash, Kawpow, Scrypt, Sha3, Blake3 (controladores, Livewire, vistas, policies, servicios, rutas).
- `app/Modules/Market/*`: CoinGecko, CoinPaprika, Minerstat, WhatToMine.
- `app/Modules/AI/*`: Insights, Clustering, Alerts, MiningHealth, MarketSummary.
- `routes/api.php`: APIs públicas (`/markets`, `/mining`, `/miners`) y privadas (`/ai-insights`, `/rigs`).
- `database/migrations`: tablas base de usuarios/roles, minería y mercados/IA.
- `database/seeders`: roles + usuario admin inicial.
- `infra/`: configuración base de nginx y supervisor.

## Despliegue rápido (HestiaCP/Nginx)
1. Crear dominio y apuntarlo al servidor.
2. Configurar PHP-FPM 8.2 y Nginx usando `infra/nginx.conf` como plantilla.
3. Clonar repositorio y ejecutar:
   ```bash
   cp .env.example .env
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```
4. Configurar cron: `* * * * * php artisan schedule:run`.
5. Supervisar workers con `infra/supervisor.conf`.

Para el stack local, `docker-compose up -d` levanta nginx, PHP y MySQL de desarrollo.
