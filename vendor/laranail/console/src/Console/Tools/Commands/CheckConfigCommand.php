<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Console\Tools\Support\ConfigValidator;

/**
 * Validates the `console.*` configuration and reports any problems.
 *
 *   php artisan laranail::console.check
 *
 * Exits non-zero when the config is invalid, so it can gate CI/deploys.
 *
 * @api Stable command (SemVer-covered).
 */
final class CheckConfigCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::console.check';

    protected $description = 'Validate the laranail/console configuration';

    public function handle(): int
    {
        $errors = ConfigValidator::validate();
        $display = $this->services->display();

        if ($errors === []) {
            $display->success('console configuration is valid.');

            return self::SUCCESS;
        }

        $display->error(count($errors) . ' configuration problem(s) found:');
        foreach ($errors as $error) {
            $this->line('  • ' . $error);
        }

        return self::FAILURE;
    }
}
