<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security\AccessLog;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'ip',
        'method',
        'url',
        'user_agent',
        'request_data',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'request_data' => 'array',
        ];
    }
}
