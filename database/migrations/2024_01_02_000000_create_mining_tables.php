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
            $table->string('ticker')->unique();
            $table->string('hash_function');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('network_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('algorithm_id')->constrained('algorithms');
            $table->decimal('hashrate', 20, 6)->nullable();
            $table->decimal('difficulty', 20, 6)->nullable();
            $table->decimal('reward', 20, 8)->nullable();
            $table->decimal('price_usd', 20, 8)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        Schema::create('mining_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('algorithm_id')->constrained('algorithms');
            $table->string('name');
            $table->string('endpoint');
            $table->json('regions')->nullable();
            $table->timestamps();
        });

        Schema::create('miners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('algorithm_id')->constrained('algorithms');
            $table->string('address');
            $table->decimal('hashrate', 20, 6)->default(0);
            $table->decimal('profitability', 16, 8)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->morphs('hookable');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('miners');
        Schema::dropIfExists('mining_pools');
        Schema::dropIfExists('network_snapshots');
        Schema::dropIfExists('algorithms');
    }
};
