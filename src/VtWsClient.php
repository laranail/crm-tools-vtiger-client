<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient;

use Simtabi\Laranail\CrmTools\VtigerClient\Support\Vtql;
use Simtabi\Laranail\CrmTools\VtigerClient\Helpers\Helpers;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Modules;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Session;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Entities;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Fetchers;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Operations;
use Simtabi\Laranail\CrmTools\VtigerClient\Exceptions\VtWsClientException;

class VtWsClient
{
    public ?Operations $operations = null;

    public ?Entities $entities = null;

    public ?Modules $modules = null;

    public ?Fetchers $fetchers = null;

    private ?Session $session = null;

    /**
     * Class constructor
     *
     * @throws VtWsClientException
     */
    public function __construct()
    {
        $this->session = new Session;
        $this->operations = new Operations($this, $this->session);
        $this->entities = new Entities($this, $this->session);
        $this->modules = new Modules($this, $this->session);
        $this->fetchers = new Fetchers($this, $this->session);

        $this->session->login();
    }

    /**
     * Gets all runtime errors that could be thrown but were suppressed
     */
    public function getErrors(): ?array
    {
        return $this->session->getErrors();
    }

    /**
     * Invokes custom operation (defined in vtiger_ws_operation table)
     *
     * @param string $operation Name of the webservice to invoke
     * @param array|null $params [$params = null] Parameter values to operation
     * @param string $method [$method = 'POST'] HTTP request method (GET, POST etc)
     *
     * @return array Result object
     *
     * @throws VtWsClientException
     */
    public function invokeOperation(string $operation, ?array $params = null, string $method = 'POST'): array
    {
        return $this->session->sessionHandler(function () use ($operation, $params, $method) {
            if (is_array($params) && ! empty($params) && ! Helpers::isAssocArray($params)) {
                throw new VtWsClientException(
                    VtWsClientException::getVtWsExceptionError(18)->getMessage(),
                    18,
                );
            }

            return $this->session->sendHttpRequest(array_merge([
                'operation' => $operation,
            ], $params), $method);
        });
    }

    /**
     * VTiger provides a simple query language for fetching data.
     * This language is quite similar to select queries in SQL.
     * There are limitations, the queries work on a single Module,
     * embedded queries are not supported, and does not support joins.
     * But this is still a powerful way of getting data from Vtiger.
     * Query always limits its output to 100 records,
     * Client application can use limit operator to get different records.
     *
     * @param string $query SQL-like expression
     *
     * @return array Query results
     *
     * @throws VtWsClientException
     */
    public function runQuery(string $query): array
    {
        // Make sure the query ends with ;
        $query = (strripos($query, ';') != strlen($query) - 1) ? trim($query .= ';') : trim($query);

        return $this->invokeOperation('query', ['query' => $query], 'GET');
    }

    /**
     * Query the VTiger API with the given query string
     * Alias for runQuery
     *
     *
     * @throws VtWsClientException
     */
    public function query(string $query): array
    {
        return $this->runQuery($query);
    }

    /**
     * Gets an array containing the basic information about current API user
     *
     * @return array Basic information about current API user
     */
    public function getCurrentUser(): array
    {
        return $this->session->getUserInfo();
    }

    /**
     * Gets an array containing the basic information about the connected vTiger instance
     *
     * @return array Basic information about the connected vTiger instance
     */
    public function getVtigerInfo(): array
    {
        return [
            'vtiger' => $this->session->getVtigerVersion(),
            'api'    => $this->session->getVtigerApiVersion(),
        ];
    }

    /**
     * Check if a record exists in a module
     *
     * @throws VtWsClientException
     */
    public function existsInModule(string $module, string $column, $value, string $operand = '='): bool
    {
        $module = Helpers::makeModuleName($module);
        $query = $this->query(sprintf('SELECT * FROM %s WHERE %s %s %s;', Vtql::identifier($module), Vtql::identifier($column), Vtql::operator($operand), Vtql::value($operand, $value)));

        return ! empty($query) || (is_array($query) && (count($query) >= 1));
    }
}
