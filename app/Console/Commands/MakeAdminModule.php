<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminModule extends Command
{
    protected $signature = 'becrypto:make-admin-module {name}';
    protected $description = 'Generate a modular Livewire admin module with controller, policy, views and routes';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $base = app_path("Modules/Admin/{$name}");

        $stubs = [
            'Controllers', 'Livewire', 'Views', 'Policies', 'Routes'
        ];

        foreach ($stubs as $stub) {
            $path = "$base/{$stub}";
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
                $this->info("Created: {$path}");
            }
        }

        $this->comment('Stub generation complete. Wire up routes and permissions to finish.');
        return self::SUCCESS;
    }
}
