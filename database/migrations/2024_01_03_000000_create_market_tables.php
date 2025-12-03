<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_sources', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('name');
            $table->string('source')->default('coingecko');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('price_usd', 18, 8)->nullable();
            $table->decimal('price_btc', 18, 8)->nullable();
            $table->decimal('market_cap', 26, 8)->nullable();
            $table->decimal('volume_24h', 26, 8)->nullable();
            $table->decimal('change_24h', 10, 4)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('predicted_for');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('market_snapshots');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('market_sources');
    }
};
