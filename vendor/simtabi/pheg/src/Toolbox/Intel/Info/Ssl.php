<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Intel\Info;

use Spatie\SslCertificate\SslCertificate;

/**
 * Utilizes https://github.com/spatie/ssl-certificate
 */

final class Ssl
{

    public function createForHostName(string $url, int $timeout = 30, bool $verifyCertificate = true): SslCertificate
    {
        return SslCertificate::createForHostName($url, $timeout, $verifyCertificate);
    }

    public function createFromFile(string $pathToCertificate): SslCertificate
    {
        return SslCertificate::createFromFile($pathToCertificate);
    }

    public function createFromString(string $certificatePem): SslCertificate
    {
        return SslCertificate::createFromString($certificatePem);
    }

    public function createFromArray(array $properties): SslCertificate
    {
        return SslCertificate::createFromArray($properties);
    }

    public function der2pem($der_data): string
    {
        return SslCertificate::der2pem($der_data);
    }
}