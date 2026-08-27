<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries\Traits;

trait WithLanguagesTrait
{

    protected function getLanguagesData($request = null)
    {
        $data = $this->getCountriesData('languages');
        return isset($data[$request]) && is_array($data) ? $data[$request] : $data;
    }

    public function getAllLanguages($request = null, bool $native = true)
    {
        $data = [];
        foreach($this->getLanguagesData() as $key => $item) {
            $data[strtoupper(trim($key))] = $native ? $item['native'] : $item['name'];
        }
        return !empty($request) && isset($data[$request]) ? $data[$request] :$data;
    }

}
