<?php
namespace Simtabi\Pheg\Toolbox\Localization;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NorthAmericanStates
{

    protected array  $statesByCountry = [
        "US" => [
            ["abbr" => 'AL', "name" => 'Alabama'],
            ["abbr" => 'AK', "name" => 'Alaska'],
            ["abbr" => 'AZ', "name" => 'Arizona'],
            ["abbr" => 'AR', "name" => 'Arkansas'],
            ["abbr" => 'CA', "name" => 'California'],
            ["abbr" => 'CO', "name" => 'Colorado'],
            ["abbr" => 'CT', "name" => 'Connecticut'],
            ["abbr" => 'DC', "name" => 'District Of Columbia'],
            ["abbr" => 'DE', "name" => 'Delaware'],
            ["abbr" => 'FL', "name" => 'Florida'],
            ["abbr" => 'GA', "name" => 'Georgia'],
            ["abbr" => 'HI', "name" => 'Hawaii'],
            ["abbr" => 'ID', "name" => 'Idaho'],
            ["abbr" => 'IL', "name" => 'Illinois'],
            ["abbr" => 'IN', "name" => 'Indiana'],
            ["abbr" => 'IA', "name" => 'Iowa'],
            ["abbr" => 'KS', "name" => 'Kansas'],
            ["abbr" => 'KY', "name" => 'Kentucky'],
            ["abbr" => 'LA', "name" => 'Louisiana'],
            ["abbr" => 'ME', "name" => 'Maine'],
            ["abbr" => 'MD', "name" => 'Maryland'],
            ["abbr" => 'MA', "name" => 'Massachusetts'],
            ["abbr" => 'MI', "name" => 'Michigan'],
            ["abbr" => 'MN', "name" => 'Minnesota'],
            ["abbr" => 'MS', "name" => 'Mississippi'],
            ["abbr" => 'MO', "name" => 'Missouri'],
            ["abbr" => 'MT', "name" => 'Montana'],
            ["abbr" => 'NE', "name" => 'Nebraska'],
            ["abbr" => 'NV', "name" => 'Nevada'],
            ["abbr" => 'NH', "name" => 'New Hampshire'],
            ["abbr" => 'NJ', "name" => 'New Jersey'],
            ["abbr" => 'NM', "name" => 'New Mexico'],
            ["abbr" => 'NY', "name" => 'New York'],
            ["abbr" => 'NC', "name" => 'North Carolina'],
            ["abbr" => 'ND', "name" => 'North Dakota'],
            ["abbr" => 'OH', "name" => 'Ohio'],
            ["abbr" => 'OK', "name" => 'Oklahoma'],
            ["abbr" => 'OR', "name" => 'Oregon'],
            ["abbr" => 'PA', "name" => 'Pennsylvania'],
            ["abbr" => 'RI', "name" => 'Rhode Island'],
            ["abbr" => 'SC', "name" => 'South Carolina'],
            ["abbr" => 'SD', "name" => 'South Dakota'],
            ["abbr" => 'TN', "name" => 'Tennessee'],
            ["abbr" => 'TX', "name" => 'Texas'],
            ["abbr" => 'UT', "name" => 'Utah'],
            ["abbr" => 'VT', "name" => 'Vermont'],
            ["abbr" => 'VA', "name" => 'Virginia'],
            ["abbr" => 'WA', "name" => 'Washington'],
            ["abbr" => 'WV', "name" => 'West Virginia'],
            ["abbr" => 'WI', "name" => 'Wisconsin'],
            ["abbr" => 'WY', "name" => 'Wyoming'],
            ["abbr" => 'AS', "name" => 'American Samoa'],
            ["abbr" => 'FM', "name" => 'Federated States Of Micronesia'],
            ["abbr" => 'GU', "name" => 'Guam'],
            ["abbr" => 'MH', "name" => 'Marshall Islands'],
            ["abbr" => 'MP', "name" => 'Northern Mariana Islands'],
            ["abbr" => 'PW', "name" => 'Pala'],
            ["abbr" => 'PR', "name" => 'Puerto Rico'],
            ["abbr" => 'VI', "name" => 'Virgin Islands']
        ],
        "CA" => [
            ["abbr" => 'AB', "name" => 'Alberta'],
            ["abbr" => 'BC', "name" => 'British Columbia'],
            ["abbr" => 'MB', "name" => 'Manitoba'],
            ["abbr" => 'NB', "name" => 'New Brunswick'],
            ["abbr" => 'NL', "name" => 'Newfoundland And Labrador'],
            ["abbr" => 'NS', "name" => 'Nova Scotia'],
            ["abbr" => 'NT', "name" => 'Northwest Territories'],
            ["abbr" => 'NU', "name" => 'Nunavut'],
            ["abbr" => 'ON', "name" => 'Ontario'],
            ["abbr" => 'PE', "name" => 'Prince Edward Island'],
            ["abbr" => 'QC', "name" => 'Quebec'],
            ["abbr" => 'SK', "name" => 'Saskatchewan'],
            ["abbr" => 'YT', "name" => 'Yukon'],
        ],
        "MX" => [
            ["abbr" => "AGU", "name" => "Aguascalientes"],
            ["abbr" => "BCN", "name" => "Baja California"],
            ["abbr" => "BCS", "name" => "Baja California Sur"],
            ["abbr" => "CAM", "name" => "Campeche"],
            ["abbr" => "CHP", "name" => "Chiapas"],
            ["abbr" => "CHH", "name" => "Chihuahua"],
            ["abbr" => "CMX", "name" => "Ciudad de México"],
            ["abbr" => "COA", "name" => "Coahuila de Zaragoza"],
            ["abbr" => "COL", "name" => "Colima"],
            ["abbr" => "DUR", "name" => "Durango"],
            ["abbr" => "GUA", "name" => "Guanajuato"],
            ["abbr" => "GRO", "name" => "Guerrero"],
            ["abbr" => "HID", "name" => "Hidalgo"],
            ["abbr" => "JAL", "name" => "Jalisco"],
            ["abbr" => "MIC", "name" => "Michoacán de Ocampo"],
            ["abbr" => "MOR", "name" => "Morelos"],
            ["abbr" => "MEX", "name" => "México"],
            ["abbr" => "NAY", "name" => "Nayarit"],
            ["abbr" => "NLE", "name" => "Nuevo León"],
            ["abbr" => "OAX", "name" => "Oaxaca"],
            ["abbr" => "PUE", "name" => "Puebla"],
            ["abbr" => "QUE", "name" => "Querétaro"],
            ["abbr" => "ROO", "name" => "Quintana Roo"],
            ["abbr" => "SLP", "name" => "San Luis Potosí"],
            ["abbr" => "SIN", "name" => "Sinaloa"],
            ["abbr" => "SON", "name" => "Sonora"],
            ["abbr" => "TAB", "name" => "Tabasco"],
            ["abbr" => "TAM", "name" => "Tamaulipas"],
            ["abbr" => "TLA", "name" => "Tlaxcala"],
            ["abbr" => "VER", "name" => "Veracruz de Ignacio de la Llave"],
            ["abbr" => "YUC", "name" => "Yucatán"],
            ["abbr" => "ZAC", "name" => "Zacatecas"],
        ],
    ];
    protected string $isoToCountryCode;
    protected string $subject;

    public function __construct(string $isoToCountryCode)
    {
        $this->isoToCountryCode = strtoupper($isoToCountryCode);
        $this->subject          = $this->getSubject();
    }

    public function getStatesByCountry(): array
    {
        return $this->statesByCountry;
    }

    public function getIsoToCountryCode(): ?string
    {
        return $this->isoToCountryCode;
    }

    public function getSubject(): string
    {
        return match ($this->isoToCountryCode) {
            "US", "MX" => "State",
            "CA"       => "Province",
            default    => "State or Province",
        };
    }

    public function isAbbreviatedName($value): bool
    {
        return in_array(Str::upper($value), $this->getStateAbbreviations());
    }

    public function isFullName($value): bool
    {
        return in_array(Str::title($value), $this->getStateNames());
    }

    public function getStateAbbreviations(): array
    {
        $x = [];
        foreach ($this->statesByCountry as $c => $states) {
            if (empty($this->isoToCountryCode) || $c === $this->isoToCountryCode) {
                foreach ($states as $state) {
                    $x[] = $state['abbr'];
                }
            }
        }
        return $x;
    }

    public function getStateNames(): array
    {
        $x = [];
        foreach ($this->statesByCountry as $c => $states) {
            if (empty($this->isoToCountryCode) || $c === $this->isoToCountryCode) {
                foreach ($states as $state) {
                    $x[] = $state['name'];
                }
            }
        }
        return $x;
    }

}