<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Services;

use Simtabi\Laranail\CrmTools\VtigerClient\Exceptions\VtWsClientException;
use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;

/**
 * Vtiger Web Services PHP Client Session class
 *
 * Class Modules
 */
class Modules
{
    private VtWsClient $vtwsClient;

    private Session $session;

    /**
     * Class constructor
     *
     * @param  VtWsClient  $vtwsClient  Parent VtWsClient instance
     */
    public function __construct(VtWsClient $vtwsClient, Session $session)
    {
        $this->vtwsClient = $vtwsClient;
        $this->session = $session;
    }

    /**
     * Lists all the Vtiger entity types available through the API
     *
     * @return array List of entity types
     *
     * @throws VtWsClientException
     */
    public function getAll(): array
    {
        $result = $this->vtwsClient->invokeOperation('listtypes', [], 'GET');

        $modules = $result['types'];
        $result = [];

        foreach ($modules as $moduleName) {
            $result[$moduleName] = ['name' => $moduleName];
        }

        return $result;
    }

    /**
     * List types is a command to provide you with all possible types the Vtiger CRM supports.
     * For each type, you can run to describe() command, to obtain the data structure.
     * Alias for getAll()
     *
     * @throws VtWsClientException
     */
    public function listTypes(): array
    {
        return $this->vtwsClient->modules->getAll();
    }

    /**
     * Get the type information about a given VTiger entity type.
     *
     * @param  string  $moduleName  Name of the module / entity type
     * @return array Result object
     *
     * @throws VtWsClientException
     */
    public function getOne(string $moduleName): array
    {
        return $this->vtwsClient->invokeOperation('describe', [
            'elementType' => $moduleName,
        ], 'GET');
    }

    /**
     * Gets the entity ID prepended with module / entity type ID
     *
     * @param  string  $moduleName  Name of the module / entity type
     * @param  string  $entityID  Numeric entity ID
     * @return string Returns false if it is not possible to retrieve module / entity type ID
     *
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
        if (! is_array($type) || ! array_key_exists('idPrefix', $type)) {
            throw new VtWsClientException(sprintf(
                'The following module is not installed: %s',
                $moduleName
            ));
        }

        return "{$type['idPrefix']}x{$entityID}";
    }
}
