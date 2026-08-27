<?php declare(strict_types=1);

// Directory Separator
if (!defined('DS')) {
    switch(PHP_OS){
        case "Linux"   : define("DS", DIRECTORY_SEPARATOR); break;
        case "Windows" : define("DS", DIRECTORY_SEPARATOR); break;
        case "WINNT"   : define("DS", DIRECTORY_SEPARATOR); break;
        default        : define("DS", '/'); break;
    }
}

$_SERVER['REQUEST_TIME_FLOAT'] = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
$_SERVER['REQUEST_TIME']       = $_SERVER['REQUEST_TIME']       ?? $_SERVER['REQUEST_TIME_FLOAT'];
