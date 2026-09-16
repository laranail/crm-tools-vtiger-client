<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\Toolkit\Facades\Laranail;
use Simtabi\Pheg\Toolbox\Arr\Query\ArrayQuery;
use Simtabi\Pheg\Toolbox\Arr\Query\QueryEngine;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;
use Simtabi\Laranail\CrmTools\VtigerClient\Helpers\Helpers;
use Simtabi\Laranail\CrmTools\VtigerClient\Exceptions\VtWsClientException;

class Operations
{
    private VtWsClient $vtWsClient;

    private Session $session;

    /**
     * Class constructor
     *
     * @param VtWsClient $vtWsClient Parent VtWsClient instance
     */
    public function __construct(VtWsClient $vtWsClient, Session $session)
    {
        $this->vtWsClient = $vtWsClient;
        $this->session = $session;
    }

    /**
     * This function is a sql query builder wrapped around the query function.
     * Accepts instance of Laravels' QueryBuilder.
     *
     * @throws VtWsClientException
     */
    public function search(QueryBuilder $query, bool $quote = true): mixed
    {

        $queryString = $query->toSQL();
        $bindings = $query->getBindings();

        foreach ($bindings as $binding) {
            if ($quote) {
                $queryString = preg_replace('/\?/', DB::connection()->getPdo()->quote($binding), $queryString, 1);
            } else {
                $queryString = preg_replace('/\?/', $binding, $queryString, 1);
            }
        }

        // In the event there is an offset, append it to the front of the limit
        // Vtiger does not support the offset keyword
        $matchOffset = [];
        $matchLimit = [];
        if (preg_match('/(\s[o][f][f][s][e][t]) (\d*)/', $queryString, $matchOffset) && preg_match('/(\s[l][i][m][i][t]) (\d*)/', $queryString, $matchLimit)) {
            $queryString = preg_replace('/(\s[o][f][f][s][e][t]) (\d*)/', '', $queryString);
            $queryString = preg_replace('/(\s[l][i][m][i][t]) (\d*)/', '', $queryString);
            $queryString = $queryString . ' limit ' . $matchOffset[2] . ',' . $matchLimit[2];
        }

        // Remove the backticks and add semicolon
        $queryString = str_replace('`', '', $queryString) . ';';

        return $this->vtWsClient->query($queryString);
    }

    /**
     * This function uses the Vtiger Lookup API endpoint to search for a single piece of information within
     * multiple columns of a Vtiger module. This function is often multitudes faster than the search function.
     *
     * @throws VtWsClientException
     */
    public function lookup($dataType, $value, $module, array $columns): array
    {

        // Update columns into the proper format
        $columnsText = '';
        foreach ($columns as $column) {
            $columnsText .= '"' . $column . '",';
        }

        // Trim the last comma from the string
        $columnsText = substr($columnsText, 0, (strlen($columnsText) - 1));

        return $this->vtWsClient->invokeOperation('lookup', [
            'type'     => $dataType,
            'value'    => $value,
            'searchIn' => '{"' . $module . '":[' . $columnsText . ']}',
        ], 'GET');
    }

    /**
     * Retrieve a record from the VTiger API
     * Format of id must be {module_code}x{item_id}, e.g 4x12
     *
     * @throws VtWsClientException
     */
    public function retrieve(string $moduleName, string $entityID, array $select = []): ?array
    {
        return $this->vtWsClient->entities->findOneByID($moduleName, $entityID, $select);
    }

    /**
     * Retrieve all relationships of a given module
     *
     * @throws VtWsClientException
     */
    public function relatedTypes(string $moduleName): array
    {
        return $this->vtWsClient->invokeOperation('relatedtypes', [
            'elementType' => $moduleName,
        ], 'GET');
    }

    /**
     * Retrieve related records
     *
     * @throws VtWsClientException
     */
    public function retrieveRelated(string $id, string $targetLabel, string $targetModule): array
    {
        return $this->vtWsClient->invokeOperation('retrieve_related', [
            'relatedLabel' => $targetLabel,
            'relatedType'  => $targetModule,
            'id'           => $id,
        ], 'GET');
    }

    /**
     * Create a new entry in the VTiger API
     *
     * Make sure to fill all mandatory fields.
     *
     * @throws VtWsClientException
     */
    public function create(string $moduleName, array $params): array
    {
        return $this->vtWsClient->entities->createOne($moduleName, $params);
    }

    /**
     * Update an entry in the database from the given object
     *
     * The object should be an object retrieved from the database and then altered
     *
     * @throws VtWsClientException
     */
    public function update(string $moduleName, string $entityID, array $params): array
    {
        return $this->vtWsClient->entities->updateOne($moduleName, $entityID, $params);
    }

    /**
     * Delete from the database using the given id
     * Format of id must be {module_code}x{item_id}, e.g 4x12
     *
     * @throws VtWsClientException
     */
    public function delete(string $moduleName, string $entityID): array
    {
        return $this->vtWsClient->entities->deleteOne($moduleName, $entityID);
    }

    /**
     * To obtain the data structure of a module in Vtiger, run the describe method with the module name.
     * Module names can be obtained using listTypes()
     *
     * @throws VtWsClientException
     */
    public function describe(string $moduleName): array
    {
        return $this->vtWsClient->modules->getOne($moduleName);
    }

    /**
     * Generates a potential summary of all queryable modules and types
     */
    public function getAllInSummary(): array
    {
        try {
            return [
                'un_queryable' => [
                    'PriceBooks',
                    'ServiceContracts',
                    'PBXManager',
                    'Services',
                    'ProjectMilestone',
                    'Approvals',
                    'InternalTickets',
                    'PhoneCalls',
                    'WorkOrders',
                    'Forecast',
                    'SMSNotifier',
                    'Tax',
                    'ProductTaxes',
                ],
                'queryable' => [
                    'Calendar',
                    'Leads',
                    'Accounts',
                    'Contacts',
                    'Potentials',
                    'Products',
                    'Documents',
                    'Emails',
                    'Faq',
                    'Vendors',
                    'Quotes',
                    'PurchaseOrder',
                    'SalesOrder',
                    'Invoice',
                    'Campaigns',
                    'Events',
                    'Assets',
                    'ModComments',
                    'ProjectTask',
                    'Project',
                    'EmailCampaigns',
                    'SLA',
                    'Cases',
                    'Olark',
                    'PrintTemplates',
                    'EventForms',
                    'Timelog',
                    'Esign',
                    'Employees',
                    'Reactions',
                    'Groups',
                    'Currency',
                    'DocumentFolders',
                    'CompanyDetails',
                    'LineItem',
                ],
                'types' => Laranail::cache()->remember(__METHOD__, function () {
                    return $this->vtWsClient->modules->listTypes();
                }), $this->session->getCacheTtl(),
            ];
        } catch (VtWsClientException $exception) {
            return [
                $exception->getMessage(),
            ];
        }
    }

    /**
     * Builds and retrieves multiple records using module name and a set of supplied constraints
     *
     * @param string $moduleName The name of the module / entity type
     * @param array $conditions Data used to find matching entries
     * @param array $select The list of fields to select (defaults to SQL-like '*' - all the fields)
     * @param int $limit Limit the list of entries to N records (acts like LIMIT in SQL)
     * @param int $offset Integer values to specify the offset of the query
     *
     * @throws VtWsClientException
     */
    public function fetchDeepWithPagination(string $moduleName, array $conditions = [], array $select = [], bool $paginateQuery = true, int $limit = 200, int $offset = 0): bool|QueryEngine|ArrayQuery
    {
        $moduleName = Helpers::makeModuleName($moduleName);
        $prepareQueryData = function ($data): array {
            $ungrouped = [];

            for ($i = 0; $i < count($data); $i++) {
                if (is_array($ungrouped) && (count($ungrouped) >= 1)) {
                    $ungrouped = array_merge($ungrouped, $data[$i]);
                } else {
                    $ungrouped = $data[$i];
                }
            }

            return $ungrouped;
        };

        $makeQueryString = function ($moduleName, array $conditions = [], array $select = [], $limit = 0, $offset = 0) {
            $criteria = [];
            $select = (empty($select)) ? '*' : implode(',', $select);
            $query = sprintf("SELECT %s FROM {$moduleName}", $select);

            if (! empty($conditions)) {
                foreach ($conditions as $param => $value) {
                    $criteria[] = "{$param} LIKE '{$value}'";
                }

                $query .= sprintf(' WHERE %s', implode(' AND ', $criteria));
            }

            if (intval($limit) > 0) {
                $query .= (intval($offset) > 0)
                    ? sprintf(' LIMIT %s, %s', intval($offset), intval($limit))
                    : sprintf(' LIMIT %s', intval($limit));
            }

            return $query;
        };

        try {

            $data = Laranail::cache()->remember(__METHOD__ . $moduleName, function () use ($moduleName, $conditions, $select, $limit, $offset, $makeQueryString, $paginateQuery) {
                $counter = 0;
                $data = [];

                if ($paginateQuery) {
                    do {
                        // Run the query
                        $query = $this->vtWsClient->query($makeQueryString($moduleName, $conditions, $select, $limit, $offset));

                        // If not empty, collect into a new array
                        if (! Helpers::isEmptyData($query)) {
                            $data[$counter] = $query;
                        }

                        // Automatically calculate offset
                        $offset = $offset + $limit;
                        $counter++;

                    } while (! Helpers::isEmptyData($query));
                } else {
                    // Run the query
                    $data = $this->vtWsClient->query($makeQueryString($moduleName, $conditions, $select, $limit, $offset));
                }

                return $data;
            }, $this->session->getCacheTtl());

            return pheg()->arr()->query($prepareQueryData($data));

        } catch (VtWsClientException $exception) {
            throw new VtWsClientException($exception->getMessage(), $exception->getCode());
        }

    }

    /**
     * Fetched account information from the supplied email or id
     *
     * @return array|mixed
     *
     * @throws VtWsClientException
     */
    public function getAccountInfo(string $emailOrId): mixed
    {
        $column = Helpers::isValidEmail($emailOrId) ? 'email1' : 'id';

        return Laranail::cache()->remember(__METHOD__ . $emailOrId, function () use ($column, $emailOrId) {
            return $this->vtWsClient->query("SELECT * FROM Accounts WHERE {$column} = '{$emailOrId}'")[0] ?? [];
        }, $this->session->getCacheTtl());
    }

    /**
     * Fetched account email or id based on the supplied value
     *
     * @throws VtWsClientException
     */
    public function getAccountId2emailOrViceVersa(string $emailOrId): object
    {
        $query = $this->getAccountInfo($emailOrId);
        $value = Helpers::isValidEmail($emailOrId) ? $query['id'] : $query['email1'];
        $key = Helpers::isValidEmail($emailOrId) ? 'id' : 'email1';

        return (object) [
            'key'      => $key,
            'value'    => $value,
            'is_email' => Helpers::isValidEmail($value),
        ];
    }

    /**
     * Get all accounts related to a given account based on given account email or id
     *
     * @throws VtWsClientException
     */
    public function getAllAccountsRelatedToAccountId(string $emailOrId): mixed
    {
        $status = $this->getAccountId2emailOrViceVersa($emailOrId);

        if ($status->is_email) {
            $query = $this->getAccountInfo($status->value);
            $id = $query['id'];
        } else {
            $id = $status->value;
        }

        return Laranail::cache()->remember(__METHOD__ . $emailOrId, function () use ($id) {
            return $this->vtWsClient->query("SELECT * FROM Accounts WHERE account_id = '{$id}';");
        }, $this->session->getCacheTtl());
    }

    /**
     * Fetches all account contacts
     *
     * @param bool $usable optional to filter if we want only accounts with a not empty email column
     *
     * @throws VtWsClientException
     */
    public function getAllAccountContacts(string $emailOrId, bool $usable = true): Collection
    {
        $data = $this->getAllRelatedAccountEntities($emailOrId, ['Contacts']);
        $data = ($data['modules']['contacts'] ?? []);
        $data = collect($data);

        if ($usable) {
            $data = $data->where('email', '!=', '');
        }

        return $data;
    }

    /**
     * Fetches all related account entities
     *
     * @throws VtWsClientException
     */
    public function getAllRelatedAccountEntities(string $emailOrId, array $types = []): array
    {

        return Laranail::cache()->remember(__METHOD__ . $emailOrId, function () use ($emailOrId, $types) {

            // fetch account data
            $columnType = $this->getAccountId2emailOrViceVersa($emailOrId);
            $account = Laranail::cache()->remember(__METHOD__ . $emailOrId, function () use ($columnType) {
                return $this->vtWsClient->query("SELECT * FROM Accounts WHERE {$columnType->key} = '{$columnType->value}'")[0] ?? [];
            }, $this->session->getCacheTtl());

            // fetch all modules related to a given account
            if (count($types) < 1) {
                $types = Laranail::cache()->remember(__METHOD__ . 'relatedTypes', function () {
                    return $this->relatedTypes('Accounts');
                }, $this->session->getCacheTtl());
                $types = $types['types'];
            }

            // fetch account related modules summary
            $modules = [];
            $failed = [];

            if (! empty($account) && ! empty($types)) {
                // loop through each module and fetch it's data
                foreach ($types as $moduleName) {
                    $moduleName = ucfirst(strtolower($moduleName));
                    $arrayKey = strtolower(Helpers::fromCamelCase($moduleName));
                    if (! empty($arrayKey)) {
                        try {
                            $modules[$arrayKey] = $this->retrieveRelated($account['id'], $moduleName, $moduleName);

                            continue;
                        } catch (VtWsClientException $exception) {
                            $failed[$arrayKey] = $exception->getMessage();
                        }
                    }
                }
            }

            return [
                'account' => [
                    'parent'   => $account,
                    'children' => $this->getAllAccountsRelatedToAccountId($account['id']),
                ],
                'modules' => ($modules),
                'failed'  => array_unique($failed),
            ];

        }, $this->session->getCacheTtl());

    }
}
