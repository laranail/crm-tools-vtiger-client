<?php

namespace Simtabi\Pheg\Toolbox\Intel\Info;

final class Network
{

    public function __construct() {}

    public function isConnected($host = 'www.google.com'): bool
    {
        return (bool) @fsockopen($host, 80, $iErrno, $sErrStr, 5);
    }

}