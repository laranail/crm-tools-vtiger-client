<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Wrappers;

use Simtabi\Pheg\Toolbox\Wrappers\Filesystem\Filesystem;
use Simtabi\Pheg\Toolbox\Wrappers\Filesystem\FilesystemPath;
use Simtabi\Pheg\Toolbox\Wrappers\Guzzle\GuzzleApiCallBuilder;

final class Wrappers
{
    public function __construct() {}

    public function guzzle(string $url, string $uri, string $method = GuzzleApiCallBuilder::HTTP_POST): GuzzleApiCallBuilder
    {
        return new GuzzleApiCallBuilder($url, $uri, $method);
    }

    public function filesystem(): Filesystem
    {
        return new Filesystem();
    }

    public function filesystemPath(): FilesystemPath
    {
        return new FilesystemPath();
    }

}