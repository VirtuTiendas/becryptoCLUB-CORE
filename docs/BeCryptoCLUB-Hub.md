# BeCryptoCLUB Hub – Diseño completo (Laravel 11)

Guía integral para implementar **BeCryptoCLUB Hub** en un servidor con **HestiaCP** usando **Nginx + PHP-FPM 8.2**. Incluye arquitectura, migraciones, rutas, servicios, vistas base y pasos de despliegue.

## 1. Estructura del proyecto
Proyecto estándar de Laravel 11. Se indica la ubicación típica en HestiaCP: `/home/carpediemdiaz/web/becrypto.club/public_html` (puedes optar por colocar el core de Laravel fuera de `public_html` y enlazar su `public`).

```
becryptohub/               # Raíz del proyecto
├─ app/
│  ├─ Console/
│  ├─ Exceptions/
│  ├─ Http/
│  │  ├─ Controllers/
│  │  ├─ Middleware/
│  ├─ Jobs/
│  ├─ Models/
│  ├─ Providers/
│  └─ Services/
├─ bootstrap/
├─ config/
├─ database/
│  ├─ factories/
│  ├─ migrations/
│  └─ seeders/
├─ public/
├─ resources/
│  ├─ css/
│  ├─ js/
│  └─ views/
├─ routes/
├─ storage/
├─ tests/
├─ composer.json
└─ package.json
```

Archivos personalizados clave en esta guía:
- `composer.json`: dependencias Laravel 11.
- `.env` de ejemplo adaptado a HestiaCP.
- Migraciones completas para módulos solicitados.
- Rutas y controladores base.
- Servicios (`MiningDataService`, `MarketDataService`, `AiAnalysisService`).
- Job `ProcessAiRequest`.
- Vistas Blade con layout neon y componentes.
- Configuración Tailwind + Vite.

## 2. composer.json y dependencias
Ejemplo de `composer.json` ajustado a PHP 8.2 y Laravel 11.

```json
{
  "name": "becryptoclub/hub",
  "description": "BeCryptoCLUB Hub - Crypto content, mining, markets and AI assistant",
  "type": "project",
  "require": {
    "php": "^8.2",
    "laravel/framework": "^11.0",
    "laravel/sanctum": "^4.0",
    "laravel/tinker": "^2.9"
  },
  "require-dev": {
    "laravel/pint": "^1.14",
    "laravel/sail": "^1.30",
    "phpunit/phpunit": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    },
    "files": []
  },
  "scripts": {
    "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
      "@php artisan package:discover --ansi"
    ],
    "post-root-package-install": [
      "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
    ],
    "post-create-project-cmd": [
      "@php artisan key:generate --ansi"
    ]
  },
  "extra": {
    "laravel": {
      "dont-discover": []
    }
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

Comandos:
- `composer install` (instala dependencias en el servidor).
- `composer update` (solo si necesitas actualizar, no es obligatorio en producción).

## 3. Archivo .env de ejemplo (HestiaCP)

```
APP_NAME="BeCryptoCLUB Hub"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://becrypto.club

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=carpediemdiaz_becrypto
DB_USERNAME=carpediemdiaz_bchub
DB_PASSWORD=TU_PASSWORD_DB

MINING_API_ENDPOINT=https://api-mining-placeholder.test
MARKET_API_ENDPOINT=https://api-market-placeholder.test
AI_API_ENDPOINT=https://api-ai-placeholder.test
AI_API_KEY=TU_API_KEY

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

Reemplaza `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `AI_API_KEY` y endpoints por valores reales.

## 4. Base de datos y migraciones
Coloca los siguientes archivos en `database/migrations`. Los timestamps son de ejemplo; Laravel generará los suyos (ajusta nombres según convenga).

### A) Autenticación y roles
`database/migrations/2024_01_01_000001_create_users_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'editor', 'user'])->default('user');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### B) Contenido (News / Knowledge Hub)
`database/migrations/2024_01_01_000010_create_posts_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->enum('status', ['draft', 'published', 'featured'])->default('draft');
            $table->enum('type', ['news', 'guide'])->default('news');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unique(['category_id', 'post_id']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unique(['tag_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('posts');
    }
};
```

### C) Mining Hub
`database/migrations/2024_01_01_000020_create_mining_tables.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('algorithms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('hash_type')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mining_coins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol', 20)->unique();
            $table->foreignId('algorithm_id')->constrained('algorithms')->cascadeOnDelete();
            $table->string('official_site')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mining_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('api_endpoint')->nullable();
            $table->timestamps();
        });

        Schema::create('mining_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_id')->constrained('mining_coins')->cascadeOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained('mining_pools')->nullOnDelete();
            $table->decimal('difficulty', 20, 8)->nullable();
            $table->decimal('reward_per_block', 16, 8)->nullable();
            $table->decimal('est_profit_usd', 16, 8)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mining_snapshots');
        Schema::dropIfExists('mining_pools');
        Schema::dropIfExists('mining_coins');
        Schema::dropIfExists('algorithms');
    }
};
```

### D) Markets & Watchlists
`database/migrations/2024_01_01_000030_create_markets_tables.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_coins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol', 15)->unique();
            $table->string('external_id')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_id')->constrained('market_coins')->cascadeOnDelete();
            $table->decimal('price_usd', 16, 8)->nullable();
            $table->decimal('change_24h', 8, 4)->nullable();
            $table->decimal('volume_24h', 20, 2)->nullable();
            $table->decimal('market_cap', 20, 2)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coin_id')->constrained('market_coins')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'coin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlists');
        Schema::dropIfExists('market_snapshots');
        Schema::dropIfExists('market_coins');
    }
};
```

### E) AI Logs
`database/migrations/2024_01_01_000040_create_ai_requests_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['analyze-coin', 'summarize-news', 'chat']);
            $table->longText('input_text');
            $table->longText('response_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
```

### Seeder
`database/seeders/DatabaseSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\{User};
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@becrypto.club'],
            [
                'name' => 'Admin BeCrypto',
                'password' => Hash::make('StrongPassword123!'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        $algoId = DB::table('algorithms')->insertGetId([
            'name' => 'KawPoW',
            'slug' => 'kawpow',
            'hash_type' => 'PoW',
            'description' => 'Algoritmo orientado a GPUs',
            'notes' => 'Demo seed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $miningCoinId = DB::table('mining_coins')->insertGetId([
            'name' => 'Ravencoin',
            'symbol' => 'RVN',
            'algorithm_id' => $algoId,
            'official_site' => 'https://ravencoin.org',
            'notes' => 'Seed de ejemplo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mining_pools')->insert([
            'name' => 'Demo Pool',
            'url' => 'https://pool.demo',
            'api_endpoint' => 'https://api.pool.demo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $marketCoinId = DB::table('market_coins')->insertGetId([
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'external_id' => 'bitcoin',
            'logo_url' => 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_snapshots')->insert([
            'coin_id' => $marketCoinId,
            'price_usd' => 50000,
            'change_24h' => 1.25,
            'volume_24h' => 1200000000,
            'market_cap' => 950000000000,
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('posts')->insert([
            'title' => 'Bienvenido a BeCryptoCLUB Hub',
            'slug' => 'bienvenido-becryptoclub',
            'content' => 'Primer post de demo',
            'status' => 'published',
            'type' => 'news',
            'author_id' => $admin->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

## 5. Rutas y controladores
Coloca las rutas en `routes/web.php`.

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    HomeController,
    PostController,
    MiningController,
    MarketController,
    WatchlistController,
    AiController
};
use App\Http\Controllers\Admin\DashboardController;

Route::get('/', [HomeController::class, 'index']);

Route::prefix('news')->group(function () {
    Route::get('/', [PostController::class, 'index'])->name('news.index');
    Route::get('/{slug}', [PostController::class, 'show'])->name('news.show');
});

Route::prefix('mining')->group(function () {
    Route::get('/', [MiningController::class, 'index'])->name('mining.index');
    Route::get('/{symbol}', [MiningController::class, 'show'])->name('mining.show');
});

Route::prefix('markets')->group(function () {
    Route::get('/', [MarketController::class, 'index'])->name('markets.index');
    Route::get('/{symbol}', [MarketController::class, 'show'])->name('markets.show');
    Route::middleware(['auth'])->group(function () {
        Route::post('/watchlist/{symbol}', [WatchlistController::class, 'store'])->name('watchlist.store');
        Route::delete('/watchlist/{symbol}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');
    });
});

Route::prefix('ai')->group(function () {
    Route::get('/', [AiController::class, 'index'])->name('ai.index');
    Route::post('/analyze-coin', [AiController::class, 'analyzeCoin']);
    Route::post('/summarize-news', [AiController::class, 'summarizeNews']);
    Route::post('/chat', [AiController::class, 'chat']);
});

Route::middleware(['auth', 'can:admin'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
});
```

Controladores esenciales (colócalos en `app/Http/Controllers/`).

`HomeController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\{Post, MarketSnapshot};

class HomeController extends Controller
{
    public function index()
    {
        $featured = Post::where('status', 'featured')->latest('published_at')->take(3)->get();
        $marketHighlights = MarketSnapshot::with('coin')->latest('captured_at')->take(5)->get();

        return view('home', compact('featured', 'marketHighlights'));
    }
}
```

`PostController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\{Post, Category, Tag};
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with(['author', 'tags', 'categories'])
            ->where('status', 'published')
            ->latest('published_at');

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $posts = $query->paginate(12);
        $categories = Category::all();
        $tags = Tag::all();

        return view('news.index', compact('posts', 'categories', 'tags'));
    }

    public function show(string $slug)
    {
        $post = Post::with(['author', 'tags', 'categories'])->where('slug', $slug)->firstOrFail();
        return view('news.show', compact('post'));
    }
}
```

`MiningController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\{MiningCoin, MiningSnapshot};
use App\Services\MiningDataService;

class MiningController extends Controller
{
    public function __construct(private MiningDataService $miningDataService) {}

    public function index()
    {
        $coins = MiningCoin::with('algorithm')->paginate(20);
        return view('mining.index', compact('coins'));
    }

    public function show(string $symbol)
    {
        $coin = MiningCoin::with(['algorithm'])->where('symbol', strtoupper($symbol))->firstOrFail();
        $snapshots = MiningSnapshot::where('coin_id', $coin->id)->latest('captured_at')->take(20)->get();

        return view('mining.show', compact('coin', 'snapshots'));
    }
}
```

`MarketController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\{MarketCoin, MarketSnapshot};
use App\Services\MarketDataService;

class MarketController extends Controller
{
    public function __construct(private MarketDataService $marketDataService) {}

    public function index()
    {
        $coins = MarketCoin::with(['snapshots' => fn($q) => $q->latest('captured_at')->limit(1)])
            ->paginate(25);
        return view('markets.index', compact('coins'));
    }

    public function show(string $symbol)
    {
        $coin = MarketCoin::where('symbol', strtoupper($symbol))->firstOrFail();
        $history = MarketSnapshot::where('coin_id', $coin->id)->latest('captured_at')->take(50)->get();
        return view('markets.show', compact('coin', 'history'));
    }
}
```

`WatchlistController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\{MarketCoin, Watchlist};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WatchlistController extends Controller
{
    public function store(string $symbol): RedirectResponse
    {
        $coin = MarketCoin::where('symbol', strtoupper($symbol))->firstOrFail();
        Watchlist::firstOrCreate([
            'user_id' => Auth::id(),
            'coin_id' => $coin->id,
        ]);

        return back()->with('status', 'Añadido a tu watchlist');
    }

    public function destroy(string $symbol): RedirectResponse
    {
        $coin = MarketCoin::where('symbol', strtoupper($symbol))->firstOrFail();
        Watchlist::where('user_id', Auth::id())->where('coin_id', $coin->id)->delete();

        return back()->with('status', 'Eliminado de tu watchlist');
    }
}
```

`Admin/DashboardController.php`
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Post, MarketCoin, MiningCoin};

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'posts' => Post::count(),
            'marketCoins' => MarketCoin::count(),
            'miningCoins' => MiningCoin::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
```

`AiController.php`
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AiAnalysisService;
use App\Jobs\ProcessAiRequest;

class AiController extends Controller
{
    public function __construct(private AiAnalysisService $aiAnalysisService) {}

    public function index()
    {
        return view('ai.index');
    }

    public function analyzeCoin(Request $request)
    {
        $data = $request->validate([
            'coin' => 'required|string',
            'context' => 'nullable|string'
        ]);

        ProcessAiRequest::dispatch('analyze-coin', $data, $request->user());
        return back()->with('status', 'Petición enviada a la cola de IA');
    }

    public function summarizeNews(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        ProcessAiRequest::dispatch('summarize-news', $data, $request->user());
        return back()->with('status', 'Resumen en proceso');
    }

    public function chat(Request $request)
    {
        $data = $request->validate(['prompt' => 'required|string']);
        $reply = $this->aiAnalysisService->chat($data['prompt']);
        return back()->with('chat_reply', $reply);
    }
}
```

## 6. Servicios y Job
Colocar en `app/Services/`.

`MiningDataService.php`
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\{MiningCoin, MiningSnapshot, MiningPool};

class MiningDataService
{
    public function fetchAndStore(MiningCoin $coin, ?MiningPool $pool = null): MiningSnapshot
    {
        $endpoint = config('services.mining.endpoint');

        $response = Http::get($endpoint.'/stats', [
            'symbol' => $coin->symbol,
            'pool' => $pool?->id,
        ]);

        $data = $response->json();
        // Ejemplo de estructura de datos recibida (placeholder)
        // $data = [
        //   'difficulty' => 123456.78,
        //   'reward_per_block' => 2.5,
        //   'est_profit_usd' => 0.12,
        // ];

        return MiningSnapshot::create([
            'coin_id' => $coin->id,
            'pool_id' => $pool?->id,
            'difficulty' => $data['difficulty'] ?? null,
            'reward_per_block' => $data['reward_per_block'] ?? null,
            'est_profit_usd' => $data['est_profit_usd'] ?? null,
            'captured_at' => now(),
        ]);
    }
}
```

`MarketDataService.php`
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\{MarketCoin, MarketSnapshot};

class MarketDataService
{
    public function fetchAndStore(MarketCoin $coin): MarketSnapshot
    {
        $endpoint = config('services.market.endpoint');

        $response = Http::get($endpoint.'/prices', [
            'symbol' => $coin->symbol,
        ]);

        $data = $response->json();
        // Placeholder de datos de mercado
        // $data = [
        //   'price_usd' => 50250.12,
        //   'change_24h' => 1.12,
        //   'volume_24h' => 1200000000,
        //   'market_cap' => 980000000000,
        // ];

        return MarketSnapshot::create([
            'coin_id' => $coin->id,
            'price_usd' => $data['price_usd'] ?? null,
            'change_24h' => $data['change_24h'] ?? null,
            'volume_24h' => $data['volume_24h'] ?? null,
            'market_cap' => $data['market_cap'] ?? null,
            'captured_at' => now(),
        ]);
    }
}
```

`AiAnalysisService.php`
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiAnalysisService
{
    public function analyzeCoin(string $coin, ?string $context = null): string
    {
        $endpoint = config('services.ai.endpoint');
        $response = Http::withToken(config('services.ai.key'))
            ->post($endpoint.'/analyze-coin', [
                'coin' => $coin,
                'context' => $context,
            ]);

        // Aquí se llamaría a la API real de IA
        return $response->json('summary', 'Análisis no disponible');
    }

    public function summarizeNews(string $text): string
    {
        $endpoint = config('services.ai.endpoint');
        $response = Http::withToken(config('services.ai.key'))
            ->post($endpoint.'/summarize', ['text' => $text]);

        return $response->json('summary', 'Resumen no disponible');
    }

    public function chat(string $prompt): string
    {
        $endpoint = config('services.ai.endpoint');
        $response = Http::withToken(config('services.ai.key'))
            ->post($endpoint.'/chat', ['prompt' => $prompt]);

        return $response->json('reply', 'Respuesta no disponible');
    }
}
```

Job en `app/Jobs/ProcessAiRequest.php`:
```php
<?php

namespace App\Jobs;

use App\Services\AiAnalysisService;
use App\Models\AiRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $type,
        public array $payload,
        public ?User $user
    ) {
    }

    public function handle(AiAnalysisService $aiAnalysisService): void
    {
        $response = match ($this->type) {
            'analyze-coin' => $aiAnalysisService->analyzeCoin($this->payload['coin'] ?? '', $this->payload['context'] ?? null),
            'summarize-news' => $aiAnalysisService->summarizeNews($this->payload['text'] ?? ''),
            default => $aiAnalysisService->chat($this->payload['prompt'] ?? ''),
        };

        AiRequest::create([
            'user_id' => $this->user?->id,
            'type' => $this->type,
            'input_text' => json_encode($this->payload),
            'response_text' => $response,
        ]);
    }
}
```

## 7. Modelos y relaciones (resumen)
Define modelos básicos con relaciones (mostrar ejemplo para MiningCoin). Coloca en `app/Models`.

`app/Models/MiningCoin.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningCoin extends Model
{
    protected $fillable = [
        'name', 'symbol', 'algorithm_id', 'official_site', 'notes'
    ];

    public function algorithm(): BelongsTo
    {
        return $this->belongsTo(Algorithm::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MiningSnapshot::class, 'coin_id');
    }
}
```

## 8. Vistas y layout neon
### Layout principal `resources/views/layouts/app.blade.php`
```blade
<!DOCTYPE html>
<html lang="es" class="bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'BeCryptoCLUB') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#050816] text-slate-100">
    <div class="flex">
        <aside class="w-64 bg-slate-900/70 border-r border-cyan-500/30 shadow-[0_0_15px_rgba(34,211,238,0.35)]">
            <div class="p-4 text-2xl font-bold text-cyan-300">BeCryptoCLUB</div>
            <nav class="space-y-2 px-4">
                <a href="/" class="flex items-center gap-2 p-3 rounded-lg hover:bg-cyan-500/10 text-slate-100">Home</a>
                <a href="/news" class="flex items-center gap-2 p-3 rounded-lg hover:bg-cyan-500/10">News</a>
                <a href="/mining" class="flex items-center gap-2 p-3 rounded-lg hover:bg-cyan-500/10">Mining Hub</a>
                <a href="/markets" class="flex items-center gap-2 p-3 rounded-lg hover:bg-cyan-500/10">Markets</a>
                <a href="/ai" class="flex items-center gap-2 p-3 rounded-lg hover:bg-cyan-500/10">AI Assistant</a>
            </nav>
        </aside>
        <main class="flex-1">
            <header class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-900/60 backdrop-blur">
                <div class="text-xl font-semibold text-cyan-200">Dashboard Neon</div>
                <div class="flex items-center gap-3">
                    <button class="px-3 py-1 rounded-full bg-slate-800 text-slate-200 border border-cyan-400/30 shadow-[0_0_10px_rgba(34,211,238,0.25)]">Dark</button>
                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-cyan-500 to-violet-500"></div>
                </div>
            </header>
            <section class="p-6 bg-gradient-to-b from-[#050816] to-[#0b1024] min-h-screen">
                @if(session('status'))
                    <div class="mb-4 p-3 rounded border border-cyan-400/40 text-cyan-200 bg-cyan-500/10">
                        {{ session('status') }}
                    </div>
                @endif
                @yield('content')
            </section>
        </main>
    </div>
</body>
</html>
```

### Componentes Blade simples
`resources/views/components/card.blade.php`
```blade
<div {{ $attributes->merge(['class' => 'bg-slate-900/60 border border-cyan-500/20 rounded-xl p-4 shadow-[0_0_20px_rgba(34,211,238,0.15)] hover:shadow-[0_0_25px_rgba(168,85,247,0.25)] transition']) }}>
    {{ $slot }}
</div>
```

`resources/views/components/badge.blade.php`
```blade
@props(['type' => 'default'])
@php
    $colors = [
        'success' => 'bg-emerald-500/20 text-emerald-200 border-emerald-400/40',
        'warning' => 'bg-amber-500/20 text-amber-200 border-amber-400/40',
        'danger' => 'bg-rose-500/20 text-rose-200 border-rose-400/40',
        'info' => 'bg-cyan-500/20 text-cyan-200 border-cyan-400/40',
        'default' => 'bg-slate-700/40 text-slate-200 border-slate-500/40',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'px-3 py-1 rounded-full text-xs font-semibold border '.$colors[$type] ?? $colors['default']]) }}>
    {{ $slot }}
</span>
```

### Vistas principales (resumen)
`resources/views/home.blade.php`
```blade
@extends('layouts.app')

@section('content')
<div class="grid gap-6 md:grid-cols-2">
    <x-card>
        <h2 class="text-xl font-semibold text-cyan-200 mb-3">Noticias destacadas</h2>
        <div class="space-y-2">
            @foreach($featured as $post)
                <a href="/news/{{ $post->slug }}" class="block hover:text-cyan-300">{{ $post->title }}</a>
            @endforeach
        </div>
    </x-card>
    <x-card>
        <h2 class="text-xl font-semibold text-cyan-200 mb-3">Mercados (últimos)</h2>
        <div class="space-y-2">
            @foreach($marketHighlights as $snap)
                <div class="flex justify-between text-sm">
                    <span>{{ $snap->coin->symbol }}</span>
                    <span class="text-emerald-300">$ {{ number_format($snap->price_usd, 2) }}</span>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
@endsection
```

`resources/views/news/index.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-cyan-200">Noticias y guías</h1>
    </div>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach($posts as $post)
            <div class="bg-slate-800/60 p-4 rounded-xl border border-violet-500/20">
                <div class="text-sm text-slate-400">{{ $post->published_at?->format('d M Y') }}</div>
                <a class="block text-lg font-semibold text-cyan-200 hover:text-cyan-300" href="/news/{{ $post->slug }}">{{ $post->title }}</a>
                <div class="mt-2 flex gap-2">
                    <x-badge type="info">{{ strtoupper($post->type) }}</x-badge>
                    <x-badge type="success">{{ $post->status }}</x-badge>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $posts->links() }}</div>
</x-card>
@endsection
```

`resources/views/news/show.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <h1 class="text-3xl font-bold text-cyan-200 mb-2">{{ $post->title }}</h1>
    <div class="flex gap-2 mb-4">
        <x-badge type="info">{{ strtoupper($post->type) }}</x-badge>
        <x-badge type="success">{{ $post->status }}</x-badge>
    </div>
    <article class="prose prose-invert max-w-none">{!! nl2br(e($post->content)) !!}</article>
</x-card>
@endsection
```

`resources/views/mining/index.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <h1 class="text-2xl font-bold text-cyan-200 mb-4">Mining Hub</h1>
    <div class="grid md:grid-cols-3 gap-4">
        @foreach($coins as $coin)
            <div class="p-4 rounded-lg bg-slate-800/60 border border-cyan-500/20">
                <div class="text-lg font-semibold text-cyan-200">{{ $coin->name }} ({{ $coin->symbol }})</div>
                <div class="text-sm text-slate-400">Algoritmo: {{ $coin->algorithm->name }}</div>
                <a href="/mining/{{ $coin->symbol }}" class="mt-3 inline-block text-violet-300 hover:text-cyan-300">Ver detalles</a>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $coins->links() }}</div>
</x-card>
@endsection
```

`resources/views/mining/show.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-cyan-200">{{ $coin->name }} ({{ $coin->symbol }})</h1>
            <p class="text-sm text-slate-400">Algoritmo: {{ $coin->algorithm->name }}</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-slate-300">
                <tr>
                    <th class="p-2 text-left">Fecha</th>
                    <th class="p-2 text-left">Dificultad</th>
                    <th class="p-2 text-left">Reward</th>
                    <th class="p-2 text-left">Est. Profit USD</th>
                </tr>
            </thead>
            <tbody>
                @foreach($snapshots as $snap)
                    <tr class="border-t border-slate-800">
                        <td class="p-2">{{ $snap->captured_at }}</td>
                        <td class="p-2">{{ $snap->difficulty }}</td>
                        <td class="p-2">{{ $snap->reward_per_block }}</td>
                        <td class="p-2 text-emerald-300">{{ $snap->est_profit_usd }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
@endsection
```

`resources/views/markets/index.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <h1 class="text-2xl font-bold text-cyan-200 mb-4">Markets</h1>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-slate-300">
                    <th class="p-2 text-left">Coin</th>
                    <th class="p-2 text-left">Precio</th>
                    <th class="p-2 text-left">24h</th>
                    <th class="p-2 text-left">Volumen 24h</th>
                </tr>
            </thead>
            <tbody>
                @foreach($coins as $coin)
                    @php $latest = $coin->snapshots->first(); @endphp
                    <tr class="border-t border-slate-800">
                        <td class="p-2 font-semibold">{{ $coin->name }} ({{ $coin->symbol }})</td>
                        <td class="p-2">$ {{ number_format($latest->price_usd ?? 0, 2) }}</td>
                        <td class="p-2">
                            <span class="{{ ($latest->change_24h ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">
                                {{ $latest->change_24h ?? 0 }}%
                            </span>
                        </td>
                        <td class="p-2">{{ number_format($latest->volume_24h ?? 0, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $coins->links() }}</div>
</x-card>
@endsection
```

`resources/views/markets/show.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <h1 class="text-2xl font-bold text-cyan-200 mb-4">{{ $coin->name }} ({{ $coin->symbol }})</h1>
    <div class="grid md:grid-cols-2 gap-4">
        <div class="space-y-2">
            @foreach($history as $snap)
                <div class="flex justify-between text-sm border-b border-slate-800 pb-2">
                    <span>{{ $snap->captured_at }}</span>
                    <span class="text-emerald-300">$ {{ number_format($snap->price_usd, 2) }}</span>
                </div>
            @endforeach
        </div>
        <div class="bg-slate-800/60 rounded-lg p-4 border border-violet-500/20">
            <h3 class="text-cyan-200 font-semibold mb-2">Watchlist</h3>
            @auth
                <form method="POST" action="/markets/watchlist/{{ $coin->symbol }}">
                    @csrf
                    <button class="px-4 py-2 rounded bg-cyan-500 text-slate-900 font-semibold">Añadir</button>
                </form>
                <form class="mt-2" method="POST" action="/markets/watchlist/{{ $coin->symbol }}">
                    @csrf
                    @method('DELETE')
                    <button class="px-4 py-2 rounded bg-rose-500 text-white font-semibold">Quitar</button>
                </form>
            @else
                <p class="text-sm text-slate-400">Inicia sesión para guardar en tu lista.</p>
            @endauth
        </div>
    </div>
</x-card>
@endsection
```

`resources/views/ai/index.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <h1 class="text-2xl font-bold text-cyan-200 mb-4">AI Assistant</h1>
    <form method="POST" action="/ai/chat" class="space-y-3">
        @csrf
        <textarea name="prompt" class="w-full rounded bg-slate-800 border border-cyan-500/30 p-3" placeholder="Pregunta sobre una crypto"></textarea>
        <button class="px-4 py-2 bg-violet-500 text-white rounded shadow-[0_0_15px_rgba(168,85,247,0.4)]">Enviar</button>
    </form>

    @if(session('chat_reply'))
        <div class="mt-4 p-3 rounded border border-cyan-400/30 bg-slate-800/60">{{ session('chat_reply') }}</div>
    @endif

    <div class="grid md:grid-cols-2 gap-4 mt-6">
        <form method="POST" action="/ai/analyze-coin" class="space-y-2">
            @csrf
            <input name="coin" class="w-full rounded bg-slate-800 border border-cyan-500/30 p-2" placeholder="Ej: BTC">
            <textarea name="context" class="w-full rounded bg-slate-800 border border-cyan-500/30 p-2" placeholder="Contexto"></textarea>
            <button class="px-4 py-2 bg-cyan-500 text-slate-900 font-semibold rounded">Analizar coin</button>
        </form>
        <form method="POST" action="/ai/summarize-news" class="space-y-2">
            @csrf
            <textarea name="text" class="w-full rounded bg-slate-800 border border-cyan-500/30 p-2" placeholder="Pega una noticia"></textarea>
            <button class="px-4 py-2 bg-emerald-500 text-slate-900 font-semibold rounded">Resumir</button>
        </form>
    </div>
</x-card>
@endsection
```

`resources/views/admin/dashboard.blade.php`
```blade
@extends('layouts.app')

@section('content')
<x-card>
    <h1 class="text-2xl font-bold text-cyan-200 mb-4">Panel Admin</h1>
    <div class="grid md:grid-cols-4 gap-4">
        <div class="p-4 bg-slate-800/60 rounded-xl border border-cyan-500/20">
            <div class="text-sm text-slate-400">Usuarios</div>
            <div class="text-2xl font-bold">{{ $stats['users'] }}</div>
        </div>
        <div class="p-4 bg-slate-800/60 rounded-xl border border-cyan-500/20">
            <div class="text-sm text-slate-400">Posts</div>
            <div class="text-2xl font-bold">{{ $stats['posts'] }}</div>
        </div>
        <div class="p-4 bg-slate-800/60 rounded-xl border border-cyan-500/20">
            <div class="text-sm text-slate-400">Market coins</div>
            <div class="text-2xl font-bold">{{ $stats['marketCoins'] }}</div>
        </div>
        <div class="p-4 bg-slate-800/60 rounded-xl border border-cyan-500/20">
            <div class="text-sm text-slate-400">Mining coins</div>
            <div class="text-2xl font-bold">{{ $stats['miningCoins'] }}</div>
        </div>
    </div>
</x-card>
@endsection
```

## 9. Assets, Tailwind y Vite
`tailwind.config.js`
```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                neon: {
                    background: '#050816',
                    primary: '#22d3ee',
                    secondary: '#a855f7',
                    success: '#22c55e',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                neon: '0 0 15px rgba(34,211,238,0.3)',
            },
        },
    },
    plugins: [forms],
}
```

`resources/css/app.css`
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

body {
  background: #050816;
  color: #e2e8f0;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

a:hover {
  color: #22d3ee;
}
```

`resources/js/app.js`
```js
import './bootstrap';
```

Comandos para compilar assets en HestiaCP (sin npm global, instala localmente):
- `npm install`
- `npm run build`

## 10. Configuración de servicios en `config/services.php`
Añade entradas:
```php
return [
    // ...
    'mining' => [
        'endpoint' => env('MINING_API_ENDPOINT'),
    ],
    'market' => [
        'endpoint' => env('MARKET_API_ENDPOINT'),
    ],
    'ai' => [
        'endpoint' => env('AI_API_ENDPOINT'),
        'key' => env('AI_API_KEY'),
    ],
];
```

## 11. Guía de implementación en HestiaCP y servidor (paso a paso)

### 11.1 Preparación en HestiaCP
1. Inicia sesión como admin en HestiaCP (https://tu-servidor:8083).
2. Asegúrate de tener el usuario `carpediemdiaz` creado. Si no, en **Users → Add User**.
3. Crea el dominio `becrypto.club` para `carpediemdiaz` en **Web → Add Web Domain**.
   - Document root: `/home/carpediemdiaz/web/becrypto.club/public_html` (Hestia lo crea).
   - Activa **SSL** y **Let's Encrypt** para `becrypto.club` y `www.becrypto.club`.
   - Configura redirección www → non-www (opción "Aliases" o usando regla 301 en plantillas).
4. En **Web → Edit** del dominio, selecciona plantilla Nginx+PHP-FPM y versión PHP 8.2.

### 11.2 Preparación de la base de datos
5. Desde Hestia → **DB** (usuario `carpediemdiaz`): crea BD `carpediemdiaz_becrypto` y usuario `carpediemdiaz_bchub` con contraseña segura.
6. Copia esos valores en `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

### 11.3 Subida del proyecto vía SSH
7. Conéctate por SSH como `carpediemdiaz`: `ssh carpediemdiaz@tu-servidor`.
8. Ubicación sugerida:
   - **Opción A (recomendada):** coloca el proyecto en `/home/carpediemdiaz/web/becrypto.club/becryptohub` y deja `public_html` apuntando al `public` del proyecto:
     ```
     mv becryptohub/public /home/carpediemdiaz/web/becrypto.club/public_html
     ln -s /home/carpediemdiaz/web/becrypto.club/becryptohub/storage /home/carpediemdiaz/web/becrypto.club/public_html/storage
     ```
   - **Opción B:** subir todo directamente a `public_html` (menos seguro porque expone core). Preferir opción A.
9. Comandos típicos para subir y ajustar permisos:
   ```bash
   cd /home/carpediemdiaz/web/becrypto.club/
   # si usas git
   git clone https://tu-repo.git becryptohub
   # o sube ZIP y descomprime
   unzip becryptohub.zip -d becryptohub
   chown -R carpediemdiaz:carpediemdiaz becryptohub
   ```

### 11.4 Instalación de dependencias y configuración Laravel
10. Dentro del proyecto:
    ```bash
    cd /home/carpediemdiaz/web/becrypto.club/becryptohub
    composer install --no-dev --optimize-autoloader
    cp .env.example .env
    nano .env  # ajusta DB, APP_URL, endpoints
    php artisan key:generate
    ```
11. Migraciones y seeders:
    ```bash
    php artisan migrate --force
    php artisan db:seed --force
    ```

### 11.5 Configuración Nginx para Laravel
12. Edita la plantilla de Nginx en Hestia (Web → Edit → Advanced → nginx template). Asegura que `root` apunte a `public_html` (que contiene `public`). Incluye:
    ```nginx
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    ```
    Si usas Opción A y `public` está dentro de `public_html`, no se requieren cambios adicionales.

### 11.6 Permisos, cache y optimización
13. Ajusta permisos seguros:
    ```bash
    cd /home/carpediemdiaz/web/becrypto.club/becryptohub
    chown -R carpediemdiaz:carpediemdiaz storage bootstrap/cache
    chmod -R ug+rw storage bootstrap/cache
    ```
14. Optimiza caches:
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan optimize
    ```

### 11.7 Supervisión de colas (IA)
15. Configura el `QUEUE_CONNECTION=database` y ejecuta worker (via `supervisord` o `systemd`). Ejemplo rápido:
    ```bash
    php artisan queue:table && php artisan migrate --force
    php artisan queue:work --daemon --tries=3
    ```
    Para producción, crea un servicio systemd que ejecute el worker como `carpediemdiaz`.

### 11.8 Build de assets
16. Si tienes Node en el servidor:
    ```bash
    npm install
    npm run build
    ```
    Si no, compila en local y sube `public/build`.

### 11.9 Backup y seguridad
17. Activa backups en Hestia para el usuario. Mantén `.env` fuera del control público y revisa que `.git` no sea accesible desde la web. Usa HTTPS obligatorio.

