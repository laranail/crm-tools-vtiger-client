<?php

namespace USIPCOM\VtWsClient\Services;

use Illuminate\Database\Eloquent\Collection as EC;
use Illuminate\Support\Collection;
use Laranail;
use Simtabi\Pheg\Toolbox\Arr\Query\QueryEngine;
use USIPCOM\VtWsClient\Helpers\Helpers;
use USIPCOM\VtWsClient\VtWsClient;

class Fetchers
{

    private int|bool   $cacheTtl = 86400; // 24hrs = 86400

    private VtwsClient $vtWsClient;

    private Session    $session;

    /**
     * Class constructor
     */
    public function __construct(VtwsClient $vtWsClient, Session $session)
    {
        $this->vtWsClient = $vtWsClient;
        $this->cacheTtl   = Helpers::getCacheTtl();
        $this->session    = $session;
    }


    private function fetchFromCache(QueryEngine|Collection|EC|array $resource, string $cacheName): Collection
    {
        if (((!$resource instanceof Collection) || (!$resource instanceof EC)) && is_array($resource)) {
            $resource = collect($resource);
        }elseif ($resource instanceof QueryEngine) {
            $resource = $resource->toArray();
        }

        $resource = Laranail::cache(Helpers::getCacheName($cacheName), function () use ($resource) {
            return $resource;
        }, $this->cacheTtl);

        if (((!$resource instanceof Collection) || (!$resource instanceof EC)) && is_array($resource)) {
            return collect($resource);
        }

        return $resource;
    }

    public function fetchAccounts(bool $usable = false, bool $active = true): Collection
    {
        $data   = $this->vtWsClient->operations->fetchDeepWithPagination('Accounts');
        $filter = function ($data)
        {
            return $data->filter(function($item){
                if (!empty($item['accountstatus']) && (strcasecmp($item['accountstatus'], 'active') == 0)) {
                    return $item;
                }
            });
        };

        if ($usable) {
            $data = $data->where('email1', '!=', '');

            if ($active) {
                $key  = 'active_usable_accounts';
                $data = $filter($data);
            }else{
                $key  = 'usable_accounts';
            }

        }else{
            if ($active) {
                $key  = 'active_accounts';
                $data = $filter($data);
            }else{
                $key  = 'accounts';
            }
        }

        return $this->fetchFromCache($data, $key);
    }

    public function fetchActiveAccountsEmail(): Collection
    {
        return $this->fetchFromCache(Helpers::filterWhereNotEmpty($this->fetchAccounts(true, true), 'email1')->toArray(), 'active_accounts_emails');
    }

    public function fetchContacts($usable = true): Collection
    {
        $key  = "Contacts";
        $data = $this->vtWsClient->operations->fetchDeepWithPagination($key);
        if ($usable) {
            return $this->fetchFromCache($data->where('email', '!=', ''), 'usable_contacts');
        }

        return $this->fetchFromCache($data, $key);
    }

    public function fetchAssets(): Collection
    {
        $key = 'assets';
        return $this->fetchFromCache($this->vtWsClient->operations->fetchDeepWithPagination($key), $key);
    }

    public function fetchCases(): Collection
    {
        $key = 'cases';
        return $this->fetchFromCache($this->vtWsClient->operations->fetchDeepWithPagination($key), $key);
    }

    public function fetchProducts(): Collection
    {
        $key = 'products';
        return $this->fetchFromCache($this->vtWsClient->operations->fetchDeepWithPagination($key), $key);
    }

    public function fetchProjects(): Collection
    {
        $key = 'projects';
        return $this->fetchFromCache($this->vtWsClient->operations->fetchDeepWithPagination($key), $key);
    }

    public function fetchProductCategories(): Collection
    {
        $categories = $this->fetchProducts()->map(function ($category) {
            return $category['productcategory'];
        });

        $categories = $categories->filter()->unique()->mapWithKeys(function ($category, $index) {
            return [strtolower($category) => $category];
        });

        return $this->fetchFromCache($categories, 'product_categories');
    }

    public function fetchRelatedAccountEntities(string $emailOrId, array $types = []): Collection
    {
        $key = "related_account_entities_$emailOrId";

        return $this->fetchFromCache($this->vtWsClient->operations->getAllRelatedAccountEntities($emailOrId, $types), $key);
    }

    public function fetchEntityInfoBy(string $key, string $value, string $module, string $operand = '=')
    {
        $data = match (pheg()->str()->fromCamelCase($module)) {
            'accounts'   => $this->fetchAccounts(),
            'assets'     => $this->fetchAssets(),
            'cases'      => $this->fetchCases(),
            'contacts'   => $this->fetchContacts(),
            'products'   => $this->fetchProducts(),
            'projects'   => $this->fetchProjects(),
            default      => false,
        };

        if ($data) {
            $query = $data->where($key, $operand, $value)->first();
            if (!empty($query)) {
                return $query->toArray();
            }
        }

        return null;
    }

}
