<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use DateTime;
use Simtabi\Pheg\Toolbox\Transfigures\Transfigure;

final class CopyrightText
{

    public const TYPE_COMBINED = 'combined';
    public const TYPE_START    = 'start';
    public const TYPE_END      = 'end';

    private $dateTime;
    private $copyrightSymbol   = "&copy;";
    private $registeredSign    = "&reg;";
    private $trademarkSign     = "&trade;";
    private $trademarked       = true;
    private $registered        = true;

    private $declarationText   = '';
    private $companyName       = '';
    private $startYear         = '';
    private $endYear           = '';
    private $longVersion       = true;
    private $longFormat        = true;
    private $type              = 'combined';
    private $build             = null;

    public function __construct()
    {
        $this->dateTime = new DateTime();
    }

    /**
     * @return string
     */
    public function getCopyrightSymbol(): string
    {
        return $this->copyrightSymbol;
    }

    /**
     * @param string $copyrightSymbol
     * @return $this
     */
    public function setCopyrightSymbol(string $copyrightSymbol): self
    {
        $this->copyrightSymbol = $copyrightSymbol;
        return $this;
    }



    /**
     * @return string
     */
    public function getRegisteredSign(): string
    {
        return $this->registeredSign;
    }

    /**
     * @param string $registeredSign
     * @return self
     */
    public function setRegisteredSign(string $registeredSign): self
    {
        $this->registeredSign = $registeredSign;
        return $this;
    }

    /**
     * @return string
     */
    public function getTrademarkSign(): string
    {
        return $this->trademarkSign;
    }

    /**
     * @param string $trademarkSign
     * @return self
     */
    public function setTrademarkSign(string $trademarkSign): self
    {
        $this->trademarkSign = $trademarkSign;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTrademarked(): bool
    {
        return $this->trademarked;
    }

    /**
     * @param bool $trademarked
     * @return self
     */
    public function setTrademarked(bool $trademarked): self
    {
        $this->trademarked = $trademarked;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * @param bool $registered
     * @return self
     */
    public function setRegistered(bool $registered): self
    {
        $this->registered = $registered;
        return $this;
    }


    /**
     * @return string
     */
    public function getDeclarationText(): string
    {
        return $this->declarationText;
    }

    /**
     * @param string $declarationText
     * @return self
     */
    public function setDeclarationText(string $declarationText): self
    {
        $this->declarationText = $declarationText;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /**
     * @param string $companyName
     * @return self
     */
    public function setCompanyName(string $companyName): self
    {
        $this->companyName = $companyName;
        return $this;
    }

    /**
     * @return string
     */
    public function getStartYear(): string
    {
        return $this->startYear;
    }

    /**
     * @param string $startYear
     * @return self
     */
    public function setStartYear(string $startYear): self
    {
        $this->startYear = $startYear;
        return $this;
    }

    /**
     * @return string
     */
    public function getEndYear(): string
    {
        return $this->endYear;
    }

    /**
     * @param string $endYear
     * @return self
     */
    public function setEndYear(string $endYear): self
    {
        $this->endYear = $endYear;
        return $this;
    }

    /**
     * @return bool
     */
    public function isLongVersion(): bool
    {
        return $this->longVersion;
    }

    /**
     * @param bool $longVersion
     * @return self
     */
    public function setLongVersion(bool $longVersion): self
    {
        $this->longVersion = $longVersion;
        return $this;
    }

    /**
     * @return bool
     */
    public function isLongFormat(): bool
    {
        return $this->longFormat;
    }

    /**
     * @param bool $longFormat
     * @return self
     */
    public function setLongFormat(bool $longFormat): self
    {
        $this->longFormat = $longFormat;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }


    private function generate()
    {

        $startYear   = $this->startYear;
        $endYear     = $this->endYear;
        $longFormat  = $this->longFormat;
        $longVersion = $this->longVersion;
        $type        = $this->type;

        // some variables
        $dateTime  = new DateTime();

        // get and process start year
        $startYear = empty($startYear) ? date('Y') . '-01-01' : $startYear . '-01-01';

        // get and process end year
        $endYear = empty($endYear) ? date('Y') . '-01-01' : $endYear . '-01-01';

        // start process
        if (!empty($startYear) && !empty($endYear)) {
            $dateTime->setTimestamp(strtotime($startYear));
            $dateTime->setTimestamp(strtotime($endYear));
            switch ($longFormat) {
                // lets filter format output
                case true :
                    // start year
                    $startYear = $dateTime->format('Y');

                    // end year
                    $endYear = $dateTime->format('Y');
                    break;
                case false :

                    // start year
                    $startYear = $dateTime->format('y');

                    // end year
                    $endYear = $dateTime->format('y');
                    break;
                default :
                    // start year
                    $startYear = $dateTime->format('y');

                    // end year
                    $endYear = $dateTime->format('y');
                    break;
            };

            // lets filter version request
            $this->build = match ($longVersion) {
                true => match ($type) {
                    "combined" => $startYear . " - " . $endYear,
                    "start"    => $startYear,
                    "end"      => $endYear,
                    default    => $endYear,
                },
                false => match ($type) {
                    "start" => $startYear,
                    "end"   => $endYear,
                    default => $endYear,
                },
                default => match ($type) {
                    "start" => $startYear,
                    "end"   => $endYear,
                    default => $endYear,
                },
            };

        } else {

            // set default date
            $dateTime->setTimestamp(strtotime(date('Y')));

            // lets filter output request
            $this->build = match ($longVersion) {
                true    => $dateTime->format('Y'),
                false   => $dateTime->format('y'),
                default => $dateTime->format('y'),
            };

        }

        return $this;
    }

    public function getYear()
    {
        return $this->generate()->build;
    }

    public function getText()
    {

        // get copyright year
        $companyName = $this->companyName;
        $declaration = ucfirst(strtolower(htmlentities($this->declarationText)));
        $symbol      = html_entity_decode($this->copyrightSymbol);
        $year        = $this->generate()->build;

        // construct
        $htmlText    = $symbol . '&nbsp;' . $year . '&nbsp;' . $companyName . '&nbsp;&centerdot;&nbsp;' . $declaration;
        return (new Transfigure())->toObject([
            'html' => html_entity_decode($htmlText),
            'text' => [
                'declaration' => $declaration,
                'company'     => $companyName,
                'symbol'      => $symbol,
                'year'        => $year,
            ],
        ]);

    }

    public function getCompanyNameText($companyName)
    {
        // get company name
        $companyName = ucwords(strtolower($companyName));

        // construct signs
        $trademark   = !$this->isTrademarked() ? '' : $this->getTrademarkSign();
        $registered  = !$this->isRegistered()  ? '' : " " . $this->getRegisteredSign();
        return $companyName . $trademark . $registered;
    }

}
