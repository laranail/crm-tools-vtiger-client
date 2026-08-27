<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands\Concerns;

trait AskToRunMigrations
{
    protected bool $askToRunMigrations = false;

    public function askToRunMigrations(): self
    {
        $this->askToRunMigrations = true;

        return $this;
    }

    protected function processAskToRunMigrations(): self
    {
        if ($this->askToRunMigrations && $this->confirm('Would you like to run the migrations now?')) {
            $this->comment('Running migrations...');
            $this->call('migrate');
        }

        return $this;
    }
}
