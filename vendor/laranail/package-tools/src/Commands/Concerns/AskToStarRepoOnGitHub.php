<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands\Concerns;

use Symfony\Component\Process\Process;

trait AskToStarRepoOnGitHub
{
    protected ?string $starRepo = null;

    protected bool $defaultStarAnswer = true;

    public function askToStarRepoOnGitHub(string $vendorSlashRepoName, bool $defaultAnswer = false): self
    {
        $this->starRepo = $vendorSlashRepoName;
        $this->defaultStarAnswer = $defaultAnswer;

        return $this;
    }

    protected function processStarRepo(): self
    {
        if ($this->starRepo && $this->confirm('Would you like to star our repo on GitHub?', $this->defaultStarAnswer)) {
            // The slug is interpolated into a URL that gets handed to the OS
            // shell "open" helper below. Refuse anything that is not a bare
            // `vendor/repo` token so shell metacharacters can never escape
            // the argument and turn the open into arbitrary command exec.
            if (preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $this->starRepo) !== 1) {
                return $this;
            }

            $repoUrl = "https://github.com/{$this->starRepo}";

            // Pass the URL as a discrete process argument (no shell): even a
            // crafted slug stays a single literal argv entry.
            $opener = match (PHP_OS_FAMILY) {
                'Darwin' => ['open', $repoUrl],
                'Windows' => ['cmd', '/c', 'start', '', $repoUrl],
                'Linux' => ['xdg-open', $repoUrl],
                default => null,
            };

            if ($opener !== null) {
                (new Process($opener))->run();
            }
        }

        return $this;
    }
}
