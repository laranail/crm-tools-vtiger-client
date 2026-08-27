<?php

namespace Simtabi\Pheg\Toolbox\Intel\Info;

class Request
{

    public function __construct() {}

    /**
     * Check to see if the current page is being server over SSL or not
     * @return boolean
     */
    public function isHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'):
            return true;
        endif;
        return false;
    }

    /**
     * Determine if current page request type is ajax
     * @return boolean
     */
    public function isAjaxRequest(): bool
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'):
            return true;
        endif;
        return false;
    }

    /**
     * Gets all Server Headers
     *
     * @return array
     */
    public function getAllHttpHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}