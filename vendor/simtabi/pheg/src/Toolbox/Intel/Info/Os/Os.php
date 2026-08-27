<?php declare(strict_types=1);

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Intel\Info\Os;

use Exception;
use Simtabi\Pheg\Toolbox\Intel\Info\Os\Enum\Family;
use Simtabi\Pheg\Toolbox\Intel\Info\Os\Enum\FamilyName;
use Simtabi\Pheg\Toolbox\Intel\Info\Os\Enum\Os as OsEnum;
use Simtabi\Pheg\Toolbox\Intel\Info\Os\Enum\OsName;
use Simtabi\Pheg\Toolbox\Intel\Info\Os\Contracts\OsInfoInterface;

final class Os implements OsInfoInterface
{
    public function arch(): string
    {
        return php_uname('m');
    }

    public function family(): string
    {
        return sprintf('%s', FamilyName::value(Family::key(self::detectFamily())));
    }

    public function hostname(): string
    {
        return php_uname('n');
    }

    public function isApple(): bool
    {
        return self::isFamily(Family::DARWIN);
    }

    public function isBSD(): bool
    {
        return self::isFamily(Family::BSD);
    }

    public function isFamily($family): bool
    {
        $detectedFamily = self::detectFamily();

        if (true === is_string($family)) {
            $family = self::normalizeConst($family);

            if (false === Family::has($family)) {
                return false;
            }

            $family = Family::value($family);
        }

        return $detectedFamily === $family;
    }

    public function isOs($os): bool
    {
        $detectedOs = self::detectOs();

        if (true === is_string($os)) {
            $os = self::normalizeConst($os);

            if (false === OsEnum::has($os)) {
                return false;
            }

            $os = OsEnum::value($os);
        }

        return $detectedOs === $os;
    }

    public function isUnix(): bool
    {
        return self::isFamily(Family::LINUX);
    }

    public function isWindows(): bool
    {
        return self::isFamily(Family::WINDOWS);
    }

    public function os(): string
    {
        return sprintf('%s', OsName::value(OsEnum::key(self::detectOs())));
    }

    public function register(): void
    {
        $family = self::family();
        $os = self::os();

        if (false === defined('PHP_OS_FAMILY')) {
            define('PHP_OS_FAMILY', $family);
        }

        if (false === defined('PHP_OS')) {
            define('PHP_OS', $os);
        }

        if (false === defined('PHPOSINFO_OS_FAMILY')) {
            define('PHPOSINFO_OS_FAMILY', $family);
        }

        if (false === defined('PHPOSINFO_OS')) {
            define('PHPOSINFO_OS', $os);
        }
    }

    public function release(): string
    {
        return php_uname('r');
    }

    public function uuid(): ?string
    {
        $uuidGenerator = 'shell_exec';
        $uuidCommand = null;

        switch (self::family()) {
            case FamilyName::LINUX:
                // phpcs:disable
                $uuidCommand = '( cat /var/lib/dbus/machine-id /etc/machine-id 2> /dev/null || hostname ) | head -n 1 || :';
                // phpcs:enable

                break;
            case FamilyName::DARWIN:
                $uuidCommand = 'ioreg -rd1 -c IOPlatformExpertDevice | grep IOPlatformUUID';
                $uuidGenerator = static function (string $command) use ($uuidGenerator): ?string {
                    $output = $uuidGenerator($command);
                    $uuid = null;

                    if (null !== $output) {
                        $parts = explode('=', str_replace('"', '', $output));
                        $uuid = strtolower(trim($parts[1]));
                    }

                    return $uuid;
                };

                break;
            case FamilyName::WINDOWS:
                // phpcs:disable
                $uuidCommand = '%windir%\\System32\\reg query "HKEY_LOCAL_MACHINE\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid';
                // phpcs:enable

                break;
            case FamilyName::BSD:
                $uuidCommand = 'kenv -q smbios.system.uuid';

                break;

            default:
                $uuidGenerator = static function (?string $command): ?string {
                    return $command;
                };
        }

        return null !== $uuidCommand ? $uuidGenerator($uuidCommand) : null;
    }

    public function version(): string
    {
        return php_uname('v');
    }

    /**
     * @throws Exception
     */
    private static function detectFamily(?int $os = null): int
    {
        $os = $os ?? self::detectOs();

        // Get the last 4 bits.
        $family = $os - (($os >> 16) << 16);

        if (true === Family::isValid($family)) {
            return $family;
        }

        if (true === defined(PHP_OS_FAMILY)) {
            $phpOsFamily = self::normalizeConst(PHP_OS_FAMILY);

            if (true === Family::has($phpOsFamily)) {
                return (int) Family::value($phpOsFamily);
            }
        }

        throw self::errorMessage();
    }

    /**
     * @throws Exception
     */
    private static function detectOs(): int
    {
        foreach ([php_uname('s'), PHP_OS] as $os) {
            $os = self::normalizeConst($os);

            if (true === OsEnum::has($os)) {
                return (int) OsEnum::value($os);
            }
        }

        throw self::errorMessage();
    }

    /**
     * @throws Exception
     */
    private static function errorMessage(): Exception
    {
        $uname = php_uname();
        $os = php_uname('s');

        $message = <<<EOF
            Unable to find a proper information for this operating system.

            Please open an issue on https://github.com/loophp/phposinfo and attach the
            following information so I can update the library:

            ---8<---
            php_uname(): {$uname}
            php_uname('s'): {$os}
            --->8---

            Thanks.

            EOF;

        throw new Exception($message);
    }

    private static function normalizeConst(string $name): string
    {
        return strtoupper(
            str_replace('-.', '', (string) preg_replace('/[^a-zA-Z0-9]/', '', $name))
        );
    }
}
