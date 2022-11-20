<?php declare(strict_types=1);
/**
* Vtiger Web Services PHP Client Library
*
* The MIT License (MIT)
*
* Copyright (c) 2015, Zhmayev Yaroslav <salaros@salaros.com>
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
* @author    Zhmayev Yaroslav <salaros@salaros.com>
* @copyright 2015-2016 Zhmayev Yaroslav
* @license   The MIT License (MIT)
*/

namespace USIPCOM\VtWsClient\Services;

use USIPCOM\VtWsClient\VtwsClient;
use USIPCOM\VtWsClient\Exceptions\VtWsClientException;
use USIPCOM\VtWsClient\Services\Session;

/**
* Vtiger Web Services PHP Client Session class
*
* Class Modules
* @package USIPCOM\Vtiger\VtWsClient
*/
class Modules
{

    private object  $vtWsClient;
    private Session $session;

    /**
     * Class constructor
     * @param object $vtwsClient Parent VtWsClient instance
     */
    public function __construct(object $vtwsClient, Session $session)
    {
        /** @var VtwsClient $vtwsClient */
        $this->vtWsClient = $vtwsClient;

        /** @var Session $session */
        $this->session    = $session;
    }

    /**
     * Lists all the Vtiger entity types available through the API
     * @access public
     * @return array List of entity types
     * @throws VtWsClientException
     */
    public function getAll(): array
    {
        $result  = $this->vtWsClient->invokeOperation('listtypes', [], 'GET');

        $modules = $result['types'];
        $result  = [];

        foreach ($modules as $moduleName) {
            $result[ $moduleName] = ['name' => $moduleName];
        }

        return $result;
    }

    /**
     * List types is a command to provide you with all possible types the Vtiger CRM supports.
     * For each type, you can run to describe() command, to obtain the data structure.
     * Alias for getAll()
     *
     * @return array
     * @throws VtWsClientException
     */
    public function listTypes(): array
    {
        return $this->vtWsClient->modules->getAll();
    }

    /**
     * Get the type information about a given VTiger entity type.
     * @access public
     * @param string $moduleName Name of the module / entity type
     * @return array  Result object
     * @throws VtWsClientException
     */
    public function getOne(string $moduleName): array
    {
        return $this->vtWsClient->invokeOperation('describe', [
            'elementType' => $moduleName,
        ], 'GET');
    }

    /**
     * Gets the entity ID prepended with module / entity type ID
     * @access private
     * @param string $moduleName Name of the module / entity type
     * @param string $entityID Numeric entity ID
     * @return string Returns false if it is not possible to retrieve module / entity type ID
     * @throws VtWsClientException
     */
    public function getTypedID(string $moduleName, string $entityID): string
    {
        if (stripos((string) $entityID, 'x') !== false) {
            return $entityID;
        }

        if (empty($entityID) || intval($entityID) < 1) {
            throw new VtWsClientException('Entity ID must be a valid number');
        }

        $type = $this->getOne($moduleName);
        if (!is_array($type) || !array_key_exists('idPrefix', $type)) {
            throw new VtWsClientException(sprintf(
                "The following module is not installed: %s",
                $moduleName
            ));
        }

        return "{$type['idPrefix']}x{$entityID}";
    }
}
