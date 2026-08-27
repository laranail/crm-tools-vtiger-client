<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries\Traits;

trait WithCountriesTrait
{

    private string $countryCode;

    /**
     * @param string $countryCode
     * @return self
     */
    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = strtoupper(trim($countryCode));
        return $this;
    }

    /**
     * @return string
     */
    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    protected function getCountriesData($request = null)
    {
        $data = $this->getData('countries');
        return $data[$request] ?? $data;
    }


    public function getAllCountriesWithData(): array
    {
        $countries = $this->getCountriesData('countries');
        $iso2data  = $this->getCountriesData('countries2to3');
        $data      = [];
        foreach ($iso2data as $iso2 => $iso3){
            if (isset($countries[$iso2])) {
                $data[$iso2] = array_merge($countries[$iso2], [
                    'iso2' => $iso2,
                    'iso3' => $iso3,
                ]);
            }
        }
        return $data;
    }

    public function getAllCountries(): array
    {
        $countries = $this->getAllCountriesWithData();
        $data      = [];
        foreach ($countries as $code => $country){
            $data[strtoupper(strtolower($code))] = ucwords(strtolower($country['name']));
        }
        return $data;
    }

    public function getCountryInfo(): array|false
    {
        $data = $this->getAllCountriesWithData();
        // reverse if we are having a valid iso3 code
        $code = $this->isValidISO3CountryCode() ? $this->getCountryIsoReversed() : $this->countryCode;

        return $data[$code] ?? false;
    }

    public function getCountryName()
    {
        return $this->getCountryInfo()['name'] ?? false;
    }

    public function getCountryNativeName(){
        return $this->getCountryInfo()['native'] ?? false;
    }

    public function getCountryDialingCode()
    {
        return $this->getCountryInfo()['phone'] ?? false;
    }

    public function getCountryContinentCode()
    {
        return $this->getCountryInfo()['continent'] ?? false;
    }

    public function getCountryContinentName()
    {
        $continents  = $this->getAllContinents();
        $countryCode = $this->getCountryInfo()['continent'] ?? false;

        return isset($continents[$countryCode]) && !empty($countryCode) ? $continents[$countryCode] : false;
    }

    public function getCountryCapitalCity()
    {
        return $this->getCountryInfo()['capital'] ?? false;
    }

    public function getCountryCurrency()
    {
        return $this->getCountryInfo()['currency'] ?? false;
    }

    public function getCountryLanguages()
    {
        return $this->getCountryInfo()['languages'] ?? false;
    }

    public function getCountryIsoReversed()
    {
        $code = $this->countryCode;
        $iso2 = $this->getCountryIso3ToIso2();
        $iso3 = $this->getCountryIso2ToIso3();

        if ($this->isValidISO2CountryCode()) {
            foreach ($iso3 as $i => $item){
                if ($i === $code){
                    return $item;
                }
            }
        }elseif ($this->isValidISO3CountryCode()) {
            foreach ($iso2 as $k => $item){
                if ($k === $code){
                    return $item;
                }
            }
        }
        return false;
    }

    public function getCountryIso3ToIso2()
    {
        return $this->getCountriesData('countries3to2');
    }

    public function getCountryIso2ToIso3()
    {
        return $this->getCountriesData('countries2to3');
    }









    /**
     * Get Country Calling Code by Country Code
     *
     * @param  string $code
     * @return string
     */
    public function getCountryCode2DialingCode($code, $type = 'alpha2'): string|false
    {

    }


    /**
     * Get Country Name by Country Code
     *
     * @param  string $code
     * @return string
     */
    public function getCountryCode2CountryName($code): string|false
    {

    }


    public function getCallingCode2CountryName($countryCode)
    {
        return $this->getCountryISOData($countryCode)->getLocalName();
    }

    public function getCountryCode2CurrencyCode($countryCode)
    {
        $country = $this->getCountryISOData($countryCode);

        return $this->getIsoCodesFactory()->getCurrencies();
    }




    /**
     * Find a country name from the country code specified, throw an exception if the country could not
     * be found.
     *
     * @param string $countryCode The country code for the required country name.
     * @return string|bool The name of the country for the $countryCode paramete, or false if not found
     */
    public function countryCode2CountryName($countryCode)
    {
        $countryCode = strtoupper($countryCode);
        $countries   = $this->getAllCountries();
        if (array_key_exists($countryCode, $countries)) {
            return $countries[$countryCode];
        } else {
            return false;
        }
    }

    /**
     * Find a country code from the country name specified, throw an exception if the country could not
     * be found.
     *
     * @param string $countryName The country name for the required country code.
     * @return string|bool The code of the country for the $countryName parameter or false if not found
     */
    public function countryName2isoCode($countryName)
    {
        $countryName = ucwords($countryName);
        $countries   = $this->getAllCountries();
        if (in_array($countryName, $countries)) {
            return array_search($countryName, $countries);
        } else {
            return false;
        }
    }
}
