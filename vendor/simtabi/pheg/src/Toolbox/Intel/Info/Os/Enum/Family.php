<?php declare(strict_types=1);

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Simtabi\Pheg\Toolbox\Intel\Info\Os\Enum;

final class Family extends Enum
{
    public const BSD = 0x0003;
    public const DARWIN = 0x0004;
    public const LINUX = 0x0005;
    public const UNIX_ON_WINDOWS = 0x0006;
    public const UNKNOWN = 0x0001;
    public const WINDOWS = 0x0002;
}
