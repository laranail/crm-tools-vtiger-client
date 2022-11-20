<?php declare(strict_types=1);

namespace USIPCOM\VtWsClient\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Simtabi\Pheg\Toolbox\Arr\Query\ArrayQuery;
use Simtabi\Pheg\Toolbox\Arr\Query\QueryEngine;
use USIPCOM\VtWsClient\Exceptions\VtWsClientException;
use USIPCOM\VtWsClient\Helpers\Helpers;
use USIPCOM\VtWsClient\VtwsClient;

/**
 * Vtiger Web Services PHP Client Session class
 *
 * Class Entities
 * @package USIPCOM\Vtiger\VtWsClient
 */
class Entities
{

    private object  $vtWsClient;
    private Session $session;

    /**
     * Class constructor
     * @param object $vtwsClient  Parent VtWsClient instance
     */
    public function __construct(object $vtwsClient, Session $session)
    {
        /** @var VtwsClient $vtwsClient */
        $this->vtWsClient = $vtwsClient;

        /** @var Session $session */
        $this->session    = $session;
    }

    /**
     * Retrieves an entity by ID
     * @param string $moduleName The name of the module / entity type
     * @param string $entityID The ID of the entity to retrieve
     * @param array $select
     * @return array|null $select  The list of fields to select (defaults to SQL-like '*' - all the fields)
     * @throws VtWsClientException
     */
    public function findOneByID(string $moduleName, string $entityID, array $select = []): ?array
    {
        $entityID = $this->vtWsClient->modules->getTypedID($moduleName, $entityID);
        $record   = $this->vtWsClient->invokeOperation('retrieve', ['id' => $entityID], 'GET');

        if (!is_array($record)) {
            return null;
        }

        return (empty($select)) ? $record : array_intersect_key($record, array_flip($select));
    }

    /**
     * Retrieve the entity matching a list of constraints
     * @param string $moduleName The name of the module / entity type
     * @param array $params Data used to find a matching entry
     * @param array $select
     * @return array|null $select  The list of fields to select (defaults to SQL-like '*' - all the fields)
     * @throws VtWsClientException
     */
    public function findOne(string $moduleName, array $params, array $select = []): ?array
    {
        $entityID = $this->getID($moduleName, $params);

        return (empty($entityID)) ? null : $this->findOneByID($moduleName, $entityID, $select);
    }

    /**
     * Retrieves the ID of the entity matching a list of constraints + prepends '<module_id>x' string to it
     * @param string $moduleName The name of the module / entity type
     * @param array $params Data used to find a matching entry
     * @return string|null Type ID (a numeric ID + '<module_id>x')
     * @throws VtWsClientException
     */
    public function getID(string $moduleName, array $params): ?string
    {
        $query = self::getQueryString($moduleName, $params, ['id'], 1);
        $records = $this->vtWsClient->runQuery($query);
        if (false === $records || !is_array($records) || empty($records)) {
            return null;
        }

        $record = $records[0];
        return (!is_array($record) || !isset($record['id']) || empty($record['id']))
            ? null
            : $record['id'];
    }

    /**
     * Retrieve a numeric ID of the entity matching a list of constraints
     * @param string $moduleName The name of the module / entity type
     * @param array $params Data used to find a matching entry
     * @return integer  Numeric ID
     * @throws VtWsClientException
     */
    public function getNumericID(string $moduleName, array $params): int
    {
        $entityID      = $this->getID($moduleName, $params);
        $entityIDParts = explode('x', $entityID, 2);

        return (is_array($entityIDParts) && count($entityIDParts) === 2)
            ? intval($entityIDParts[1])
            : -1;
    }

    /**
     * Creates an entity for the giving module
     * @param string $moduleName Name of the module / entity type for which the entry has to be created
     * @param array $params Entity data
     * @return array  Entity creation results
     * @throws VtWsClientException
     */
    public function createOne(string $moduleName, array $params): array
    {
        if (!Helpers::isAssocArray($params)) {
            throw new VtWsClientException(
                "You have to specify at least one search parameter (prop => value)
                in order to be able to create an entity"
            );
        }

        // Assign record to logged-in user if not specified
        if (!isset($params['assigned_user_id'])) {
            $currentUser = $this->vtWsClient->getCurrentUser();
            $params['assigned_user_id'] = $currentUser['id'];
        }

        return $this->vtWsClient->invokeOperation('create', [
            'elementType' => $moduleName,
            'element'     => json_encode($params)
        ]);
    }

    /**
     * Updates an entity
     * @param string $moduleName The name of the module / entity type
     * @param array $params Entity data
     * @return array  Entity update result
     * @throws VtWsClientException
     */
    public function updateOne(string $moduleName, $entityID, array $params): array
    {
        if (!Helpers::isAssocArray($params)) {
            throw new VtWsClientException(
                "You have to specify at least one search parameter (prop => value)
                in order to be able to update the entity(ies)"
            );
        }

        // Fail if no ID was supplied
        if (empty($entityID)) {
            throw new VtWsClientException("The list of constraints must contain a valid ID");
        }

        // Prepend so-called moduleId if needed
        $entityID = $this->vtWsClient->modules->getTypedID($moduleName, $entityID);

        // Check if the entity exists + retrieve its data so it can be used below
        $entityData = $this->findOneByID($moduleName, $entityID);
        if ($entityData === false && !is_array($entityData)) {
            throw new VtWsClientException("Such entity doesn't exist, so it cannot be updated");
        }

        // The new data overrides the existing one needed to provide
        // mandatory field values to WS 'update' operation
        $params = array_merge(
            $entityData,
            $params
        );

        return $this->vtWsClient->invokeOperation('update', [
            'elementType' => $moduleName,
            'element'     => json_encode($params)
       ]);
    }

    /**
     * Provides entity removal functionality
     * @param string $moduleName The name of the module / entity type
     * @param string $entityID The ID of the entity to delete
     * @return array  Removal status object
     * @throws VtWsClientException
     */
    public function deleteOne(string $moduleName, string $entityID): array
    {
        // Prepend so-called moduleId if needed
        $entityID = $this->vtWsClient->modules->getTypedID($moduleName, $entityID);

        return $this->vtWsClient->invokeOperation('delete', ['id' => $entityID]);
    }

    /**
     * Retrieves multiple records using module name and a set of constraints
     * @param string $moduleName The name of the module / entity type
     * @param array $params Data used to find matching entries
     * @param array $select
     * @param int $limit
     * @param int $offset
     * @return array|null $select  The list of fields to select (defaults to SQL-like '*' - all the fields)
     * @throws VtWsClientException
     */
    public function findMany(string $moduleName, array $params, array $select = [], int $limit = 0, int $offset = 0): ?array
    {
        if (!is_array($params) || (!empty($params) && !Helpers::isAssocArray($params))) {
            throw new VtWsClientException(
                "You have to specify at least one search parameter (prop => value)
                in order to be able to retrieve entity(ies)"
            );
        }

        // Builds the query
        $query   = self::getQueryString($moduleName, $params, $select, $limit, $offset);

        // Run the query
        $records = $this->vtWsClient->runQuery($query);
        if (false === $records || !is_array($records) || empty($records)) {
            return null;
        }

        return $records;
    }

    /**
     * Sync will return a sync result object containing details of changes after modifiedTime
     * @param integer|null $modifiedTime [$modifiedTime = null]    The date of the first change
     * @param string|null $moduleName [$moduleName = null]   The name of the module / entity type
     * @param string|null $syncType [$syncType = null]   Sync type determines the scope of the query
     * @return array  Sync result object
     * @throws VtWsClientException
     */
    public function sync(?int $modifiedTime = null, string $moduleName = null, string $syncType = null): array
    {
        $modifiedTime = (empty($modifiedTime)) ? strtotime('today midnight') : intval($modifiedTime);

        $requestData = [
            'modifiedTime' => $modifiedTime
       ];

        if (!empty($moduleName)) {
            $requestData['elementType'] = $moduleName;
        }

        if ($syncType) {
            $requestData['syncType'] = $syncType;
        }

        return $this->vtWsClient->invokeOperation('sync', $requestData, 'GET');
    }

    /**
     * Builds the query using the supplied parameters
     * @access public
     * @static
     * @param string $moduleName The name of the module / entity type
     * @param array $params Data used to find matching entries
     * @param array $select The list of fields to select (defaults to SQL-like '*' - all the fields)
     * @param integer $limit Limit the list of entries to N records (acts like LIMIT in SQL)
     * @param integer $offset Integer values to specify the offset of the query
     * @return string   The query build out of the supplied parameters
     */
    public static function getQueryString(string $moduleName, array $params, array $select = [], $limit = 0, $offset = 0): string
    {
        $criteria = [];
        $select   = (empty($select)) ? '*' : implode(',', $select);
        $query    = sprintf("SELECT %s FROM $moduleName", $select);

        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $criteria[] = "{$param} LIKE '{$value}'";
            }

            $query .= sprintf(' WHERE %s', implode(" AND ", $criteria));
        }

        if (intval($limit) > 0) {
            $query .= (intval($offset) > 0)
                ? sprintf(" LIMIT %s, %s", intval($offset), intval($limit))
                : sprintf(" LIMIT %s", intval($limit));
        }

        return $query;
    }

}
