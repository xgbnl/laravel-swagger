<?php

namespace Xgbnl\LaravelSwagger\Command;

use Illuminate\Console\Command;

class SwaggerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install swagger api document tools';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('vendor:publish', [
            "--provider" => "Xgbnl\LaravelSwagger\SwaggerServiceProvider"
        ]);
    }
}

