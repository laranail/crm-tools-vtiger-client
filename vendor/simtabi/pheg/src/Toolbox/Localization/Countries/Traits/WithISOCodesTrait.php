<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries\Traits;

use Sokil\IsoCodes\IsoCodesFactory;
use Sokil\IsoCodes\TranslationDriver\DummyDriver;
use Sokil\IsoCodes\Database\Countries\Country;

trait WithISOCodesTrait
{

    protected function getIsoCodesFactory(): IsoCodesFactory
    {
        return (new IsoCodesFactory(null, new DummyDriver()));
    }

    public function getCountryISOData($countryCode): ?Country
    {
        $alpha2 = $this->getIsoCodesFactory()->getCountries()->getByAlpha2($countryCode);
        $alpha3 = $this->getIsoCodesFactory()->getCountries()->getByAlpha3($countryCode);

        return $alpha2 ?? $alpha3;
    }

}