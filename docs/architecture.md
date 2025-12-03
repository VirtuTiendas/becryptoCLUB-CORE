# BeCryptoCLUB – Code X Hub

## Arquitectura Modular
```mermaid
graph TD
    A[Front Neon UI] -->|Blade/Tailwind| B(Laravel 11)
    B --> C[Livewire 3 Admin]
    B --> D[Public API]
    B --> E[Private API]
    C --> F[Mining Modules]
    C --> G[Market Modules]
    C --> H[AI Modules]
    F --> F1[Aleo/IronFish/VerusHash/...]
    G --> G1[CoinGecko/CoinPaprika/Minerstat/WhatToMine]
    H --> H1[Insights/Clustering/Alerts/Health/Summary]
    E -->|Sanctum Tokens| H
```

## Diagrama de Base de Datos
```mermaid
erDiagram
    users ||--o{ model_has_roles : assigns
    users ||--o{ miners : owns
    roles ||--o{ model_has_roles : ""
    permissions ||--o{ role_has_permissions : grants
    roles ||--o{ role_has_permissions : ""
    algorithms ||--o{ network_snapshots : tracks
    algorithms ||--o{ mining_pools : hosts
    algorithms ||--o{ miners : mines
    assets ||--o{ market_snapshots : prices
```

## Convenciones de módulos
- Cada módulo vive en `app/Modules/<Domain>/<Submodule>` con carpetas `Controllers`, `Livewire`, `Views`, `Policies`, `Services`, `Routes`.
- Las rutas públicas usan `routes/api.php` y las privadas utilizan middleware `auth:sanctum` + `throttle:private_api`.
- Se utilizará `spatie/laravel-permission` para roles/permisos y `Sanctum` para autenticación tokenizada.
