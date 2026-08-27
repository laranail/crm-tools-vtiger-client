<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's Carbon macros: quarter/business-day/utility date
 * helpers plus a suite of locale-specific national holiday predicates.
 *
 * The holiday calendars are useful for i18n / scheduling applications. They are
 * faithful ports of the legacy national-date traits, with the legacy assignment
 * bugs (`=` where `===` was intended) fixed.
 *
 * Macros are pure closures, so cross-references between them (e.g. the various
 * `is*` predicates that delegate to `isEasterSunday()` / `isChristmasEve()` /
 * `isBoxingDay()` / `isLabourDay()`) resolve at call time — every macro in this
 * provider is registered in a single boot pass, so by the time any closure runs
 * the macro it depends on is already registered.
 */
final class CarbonMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerDateMacros();
        $this->registerMultiNationalDates();
        $this->registerBrazilianHolidays();
        $this->registerCanadianDates();
        $this->registerDutchHolidays();
        $this->registerFrenchHolidays();
        $this->registerGermanHolidays();
        $this->registerIndianHolidays();
        $this->registerIndonesianHolidays();
        $this->registerItalianHolidays();
        $this->registerKenyanHolidays();
        $this->registerSwedishHolidays();
        $this->registerUkrainianHolidays();
        $this->registerUsDates();
        $this->registerZambianHolidays();
    }

    /**
     * General-purpose quarter / business-day / formatting helpers.
     */
    private function registerDateMacros(): void
    {
        // Several legacy "date" macros merely shadowed methods Carbon 3 already
        // ships natively, so they are intentionally NOT re-registered:
        //   - startOfQuarter / endOfQuarter / isSameQuarter / isWeekday
        //   - nextWeekday / previousWeekday        (native mutating modifiers)
        //   - toDateTimeLocalString                (native, takes a $precision arg)
        // Only the genuinely-new helpers below are added.

        Carbon::macro('fromDateTimeLocalString', function (string $string): ?Carbon {
            try {
                return Carbon::createFromFormat('Y-m-d\TH:i', $string);
            } catch (InvalidFormatException) {
                return null;
            }
        });

        Carbon::macro('addBusinessDays', function (int $days): Carbon {
            /** @var Carbon $this */
            $date = $this->copy();
            $addedDays = 0;

            while ($addedDays < $days) {
                $date->addDay();

                if ($date->isWeekday()) {
                    $addedDays++;
                }
            }

            return $date;
        });

        Carbon::macro('subBusinessDays', function (int $days): Carbon {
            /** @var Carbon $this */
            $date = $this->copy();
            $subtractedDays = 0;

            while ($subtractedDays < $days) {
                $date->subDay();

                if ($date->isWeekday()) {
                    $subtractedDays++;
                }
            }

            return $date;
        });

        Carbon::macro('isLastDayOfMonth', function (): bool {
            /** @var Carbon $this */
            return $this->day === $this->daysInMonth;
        });

        Carbon::macro('isFirstDayOfMonth', function (): bool {
            /** @var Carbon $this */
            return $this->day === 1;
        });

        Carbon::macro('toHumanReadableString', function (): string {
            /** @var Carbon $this */
            $now = Carbon::now();

            if ($this->isToday()) {
                return 'Today at ' . $this->format('g:i A');
            }

            if ($this->isYesterday()) {
                return 'Yesterday at ' . $this->format('g:i A');
            }

            if ($this->isTomorrow()) {
                return 'Tomorrow at ' . $this->format('g:i A');
            }

            if ($this->year === $now->year) {
                return $this->format('M j \a\t g:i A');
            }

            return $this->format('M j, Y \a\t g:i A');
        });

        // getQuarter exposes the native `quarter` property as a method (the
        // method form is not native; isSameQuarter() already is, so it is dropped).
        Carbon::macro('getQuarter', function (): int {
            /** @var Carbon $this */
            return (int) ceil($this->month / 3);
        });
    }

    /**
     * Holidays observed across many nations (Christian feasts + new year).
     */
    private function registerMultiNationalDates(): void
    {
        Carbon::macro('isNewYearsDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 1 && $this->day === 1;
        });

        Carbon::macro('isEasterSunday', function (): bool {
            /** @var Carbon $this */
            return $this->copy()
                ->setMonth(3)
                ->setDay(21)
                ->eq($this->copy()->subDays(easter_days($this->year)));
        });

        Carbon::macro('isGoodFriday', function (): bool {
            /** @var Carbon $this */
            return $this->copy()->addDays(2)->isEasterSunday();
        });

        Carbon::macro('isAllSaintsDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 11 && $this->day === 1;
        });

        Carbon::macro('isChristmasDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 25;
        });

        Carbon::macro('isNewYearsEve', function (): bool {
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 31;
        });
    }

    private function registerBrazilianHolidays(): void
    {
        Carbon::macro('isTiradentesDay', function (): bool {
            // Tiradentes' Day, April 21st.
            /** @var Carbon $this */
            return $this->month === 4 && $this->day === 21;
        });

        Carbon::macro('isBrazilianLaborDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 1;
        });

        Carbon::macro('isBrazilianIndependenceDay', function (): bool {
            // Declared September 7th 1822.
            /** @var Carbon $this */
            return $this->month === 9 && $this->day === 7;
        });

        Carbon::macro('isTheDayOfOurLadyAparecida', function (): bool {
            // October 12th.
            /** @var Carbon $this */
            return $this->month === 10 && $this->day === 12;
        });

        Carbon::macro('isBrazilianDayOfTheDead', function (): bool {
            // November 2nd. (Legacy bug: `$this->month = 11` assignment fixed to `===`.)
            /** @var Carbon $this */
            return $this->month === 11 && $this->day === 2;
        });

        Carbon::macro('isBrazilianRepublicProclamationDay', function (): bool {
            // November 15th, 1889. (Legacy bug: `$this->month = 11` assignment fixed to `===`.)
            /** @var Carbon $this */
            return $this->month === 11 && $this->day === 15;
        });
    }

    private function registerCanadianDates(): void
    {
        Carbon::macro('isVictoriaDay', function (): bool {
            /** @var Carbon $this */
            if ($this->month !== 5) {
                return false;
            }

            if ($this->day >= 25) {
                return false;
            }

            return $this->copy()->setDay(25)->previous(Carbon::MONDAY)->day === $this->day;
        });

        Carbon::macro('isCanadaDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 7 && $this->day === 1;
        });

        Carbon::macro('isLabourDay', function (): bool {
            // First observed in Canada in 1894; first Monday in September.
            /** @var Carbon $this */
            if ($this->year < 1894) {
                return false;
            }

            return $this->month === 9
                && $this->copy()->firstOfMonth(Carbon::MONDAY)->day === $this->day;
        });

        Carbon::macro('isCanadianThanksgiving', function (): bool {
            // Second Monday in October.
            /** @var Carbon $this */
            if ($this->year < 1957) {
                return false;
            }

            return $this->month === 10
                && $this->copy()->firstOfMonth(Carbon::MONDAY)->addWeek()->day === $this->day;
        });

        Carbon::macro('isRemembranceDay', function (): bool {
            // First officially observed in Canada in 1931; November 11th.
            /** @var Carbon $this */
            if ($this->year < 1931) {
                return false;
            }

            return $this->month === 11 && $this->day === 11;
        });

        Carbon::macro('isBoxingDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 26;
        });

        Carbon::macro('isCivicHoliday', function (): bool {
            // First Monday in August.
            /** @var Carbon $this */
            if ($this->month !== 8) {
                return false;
            }

            return $this->copy()->firstOfMonth(Carbon::MONDAY)->day === $this->day;
        });

        Carbon::macro('isFamilyDay', function (): bool {
            // Third Monday in February; first observed (Alberta) in 1990.
            /** @var Carbon $this */
            if ($this->year < 1990) {
                return false;
            }

            if ($this->month !== 2) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 3, Carbon::MONDAY) === $this->day;
        });
    }

    private function registerDutchHolidays(): void
    {
        Carbon::macro('isDutchLiberationDay', function (): bool {
            // "Bevrijdingsdag"; May 5th, from 1945.
            /** @var Carbon $this */
            if ($this->year < 1945) {
                return false;
            }

            return $this->month === 5 && $this->day === 5;
        });

        Carbon::macro('isSaintNicholasEve', function (): bool {
            // "Sinterklaas"; December 5th.
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 5;
        });

        Carbon::macro('isDutchRemembranceDay', function (): bool {
            // May 4th, from 1945.
            /** @var Carbon $this */
            if ($this->year < 1945) {
                return false;
            }

            return $this->month === 5 && $this->day === 4;
        });

        Carbon::macro('isDutchNationalDay', function (): bool {
            // "Koningsdag"/"Koninginnedag". Since 2014: April 27th (moved a day
            // earlier when it would fall on a Sunday). Before 2014: April 30th.
            /** @var Carbon $this */
            if ($this->year < 1949) {
                return false;
            }

            if ($this->year < 2014) {
                return $this->month === 4 && $this->day === 30;
            }

            if ($this->dayOfWeek === Carbon::SUNDAY) {
                return false;
            }

            if ($this->month === 4 && $this->day === 26) {
                return $this->dayOfWeek === Carbon::SATURDAY;
            }

            return $this->month === 4 && $this->day === 27;
        });
    }

    private function registerFrenchHolidays(): void
    {
        Carbon::macro('isAscensionDay', function (): bool {
            // "Ascension"; 39 days after Easter Sunday.
            /** @var Carbon $this */
            return $this->copy()->subDays(39)->isEasterSunday();
        });

        Carbon::macro('isAssumptionDay', function (): bool {
            // "Assomption"; August 15th.
            /** @var Carbon $this */
            return $this->month === 8 && $this->day === 15;
        });

        Carbon::macro('isEasterMonday', function (): bool {
            // "Lundi de pâques"; the day after Easter Sunday.
            /** @var Carbon $this */
            return $this->copy()->subDays(1)->isEasterSunday();
        });

        Carbon::macro('isFirstWarArmisticeDay', function (): bool {
            // November 11th, from 1918.
            /** @var Carbon $this */
            if ($this->year < 1918) {
                return false;
            }

            return $this->month === 11 && $this->day === 11;
        });

        Carbon::macro('isFrenchNationalDay', function (): bool {
            // July 14th, from 1880. (Legacy `=== 07` octal literal cleaned to `=== 7`.)
            /** @var Carbon $this */
            if ($this->year < 1880) {
                return false;
            }

            return $this->month === 7 && $this->day === 14;
        });

        Carbon::macro('isPentecostDay', function (): bool {
            // "Pentecôte"; 49 days after Easter Sunday.
            /** @var Carbon $this */
            return $this->copy()->subDays(49)->isEasterSunday();
        });

        Carbon::macro('isSecondWarArmisticeDay', function (): bool {
            // May 8th, from 1945.
            /** @var Carbon $this */
            if ($this->year < 1945) {
                return false;
            }

            return $this->month === 5 && $this->day === 8;
        });
    }

    private function registerGermanHolidays(): void
    {
        Carbon::macro('isGermanLabourDay', function (): bool {
            // May 1st.
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 1;
        });

        Carbon::macro('isAscensionOfJesus', function (): bool {
            // 39 days after Easter Sunday (always a Thursday).
            /** @var Carbon $this */
            return $this->copy()->subDays(39)->isEasterSunday();
        });

        Carbon::macro('isWhitSunday', function (): bool {
            // Pentecost / Whitsunday; 49 days after Easter Sunday.
            /** @var Carbon $this */
            return $this->copy()->subDays(49)->isEasterSunday();
        });

        Carbon::macro('isWhitsun', function (): bool {
            /** @var Carbon $this */
            return $this->isWhitSunday();
        });

        Carbon::macro('isPentecost', function (): bool {
            /** @var Carbon $this */
            return $this->isWhitSunday();
        });

        Carbon::macro('isPentecostSunday', function (): bool {
            /** @var Carbon $this */
            return $this->isWhitSunday();
        });

        Carbon::macro('isWhitMonday', function (): bool {
            // The day after Pentecost.
            /** @var Carbon $this */
            return $this->copy()->subDays(1)->isWhitSunday();
        });

        Carbon::macro('isPentecostMonday', function (): bool {
            /** @var Carbon $this */
            return $this->isWhitMonday();
        });

        Carbon::macro('isCorpusChristi', function (): bool {
            // 60 days after Easter Sunday.
            /** @var Carbon $this */
            return $this->copy()->subDays(60)->isEasterSunday();
        });

        Carbon::macro('isGermanUnityDay', function (): bool {
            // October 3rd, from 1990.
            /** @var Carbon $this */
            if ($this->year < 1990) {
                return false;
            }

            return $this->month === 10 && $this->day === 3;
        });

        Carbon::macro('isHeiligerAbend', function (): bool {
            // Christmas Eve.
            /** @var Carbon $this */
            return $this->isChristmasEve();
        });

        Carbon::macro('isHeiligAbend', function (): bool {
            /** @var Carbon $this */
            return $this->isHeiligerAbend();
        });
    }

    private function registerIndianHolidays(): void
    {
        Carbon::macro('isIndianRepublicDay', function (): bool {
            // January 26th, from 1950.
            /** @var Carbon $this */
            if ($this->year < 1950) {
                return false;
            }

            return $this->month === 1 && $this->day === 26;
        });

        Carbon::macro('isIndianIndependenceDay', function (): bool {
            // August 15th, from 1947.
            /** @var Carbon $this */
            if ($this->year < 1947) {
                return false;
            }

            return $this->month === 8 && $this->day === 15;
        });

        Carbon::macro('isGandhiJayanti', function (): bool {
            // October 2nd, from 1869.
            /** @var Carbon $this */
            if ($this->year < 1869) {
                return false;
            }

            return $this->month === 10 && $this->day === 2;
        });
    }

    private function registerIndonesianHolidays(): void
    {
        Carbon::macro('isIndonesianIndependenceDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 8 && $this->day === 17;
        });

        Carbon::macro('isPancasilaDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 6 && $this->day === 1;
        });

        Carbon::macro('isIndonesianLaborDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 1;
        });

        Carbon::macro('isKartiniDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 4 && $this->day === 21;
        });

        Carbon::macro('isIndonesianEducationDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 2;
        });

        Carbon::macro('isIndonesiaCustomerDay', function (): bool {
            // September 4th, from 2003.
            /** @var Carbon $this */
            if ($this->year < 2003) {
                return false;
            }

            return $this->month === 9 && $this->day === 4;
        });

        Carbon::macro('isIndonesianHeroesDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 11 && $this->day === 10;
        });

        Carbon::macro('isIndonesianMothersDay', function (): bool {
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 22;
        });
    }

    private function registerItalianHolidays(): void
    {
        Carbon::macro('isLiberationDay', function (): bool {
            // April 25th, from 1946.
            /** @var Carbon $this */
            if ($this->year < 1946) {
                return false;
            }

            return $this->month === 4 && $this->day === 25;
        });

        Carbon::macro('isRepublicDay', function (): bool {
            // June 2nd, from 1946.
            /** @var Carbon $this */
            if ($this->year < 1946) {
                return false;
            }

            return $this->month === 6 && $this->day === 2;
        });

        Carbon::macro('isImmaculateConceptionFeast', function (): bool {
            // December 8th.
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 8;
        });

        Carbon::macro('isAssumptionOfMaryFeast', function (): bool {
            // August 15th.
            /** @var Carbon $this */
            return $this->month === 8 && $this->day === 15;
        });

        Carbon::macro('isEpiphany', function (): bool {
            // January 6th.
            /** @var Carbon $this */
            return $this->month === 1 && $this->day === 6;
        });

        Carbon::macro('isSaintStephenDay', function (): bool {
            // December 26th.
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 26;
        });

        Carbon::macro('isSaintSylvesterDay', function (): bool {
            // December 31st.
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 31;
        });

        Carbon::macro('isWorkersDay', function (): bool {
            // May 1st (April 21st under the 1924–1945 fascist regime); from 1890.
            /** @var Carbon $this */
            if ($this->year < 1890) {
                return false;
            }

            if (in_array($this->year, range(1924, 1945), true)) {
                return $this->month === 4 && $this->day === 21;
            }

            return $this->month === 5 && $this->day === 1;
        });
    }

    private function registerKenyanHolidays(): void
    {
        Carbon::macro('isKenyanIndependenceDay', function (): bool {
            // December 12th, from 1963 (Jamhuri Day).
            /** @var Carbon $this */
            if ($this->year < 1963) {
                return false;
            }

            return $this->month === 12 && $this->day === 12;
        });

        Carbon::macro('isKenyanJamhuriDay', function (): bool {
            /** @var Carbon $this */
            return $this->isKenyanIndependenceDay();
        });

        Carbon::macro('isKenyanLabourDay', function (): bool {
            // May 1st.
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 1;
        });

        Carbon::macro('isKenyanMadarakaDay', function (): bool {
            // June 1st, from 1920.
            /** @var Carbon $this */
            if ($this->year < 1920) {
                return false;
            }

            return $this->month === 6 && $this->day === 1;
        });

        Carbon::macro('isKenyanHudumaDay', function (): bool {
            // October 10th (formerly Moi Day).
            /** @var Carbon $this */
            return $this->month === 10 && $this->day === 10;
        });

        Carbon::macro('isKenyanMashujaaDay', function (): bool {
            // October 20th (Heroes' Day).
            /** @var Carbon $this */
            return $this->month === 10 && $this->day === 20;
        });

        Carbon::macro('isKenyanUtamaduniDay', function (): bool {
            // Boxing Day, December 26th.
            /** @var Carbon $this */
            return $this->isBoxingDay();
        });
    }

    private function registerSwedishHolidays(): void
    {
        Carbon::macro('isSwedishMidsummerDay', function (): bool {
            // The Saturday between June 20th and June 26th.
            /** @var Carbon $this */
            return $this->month === 6
                && $this->weekday() === Carbon::SATURDAY
                && ($this->day >= 20 && $this->day <= 26);
        });

        Carbon::macro('isChristmasEve', function (): bool {
            // December 24th.
            /** @var Carbon $this */
            return $this->month === 12 && $this->day === 24;
        });

        Carbon::macro('isSwedishNationalDay', function (): bool {
            // June 6th (previously Swedish Flag Day before 1983).
            /** @var Carbon $this */
            return $this->month === 6 && $this->day === 6;
        });
    }

    private function registerUkrainianHolidays(): void
    {
        Carbon::macro('isUkrainianIndependenceDay', function (): bool {
            // August 24th, from 1991.
            /** @var Carbon $this */
            if ($this->year < 1991) {
                return false;
            }

            return $this->month === 8 && $this->day === 24;
        });

        Carbon::macro('isUkraineDefenderDay', function (): bool {
            // October 14th, from 2015.
            /** @var Carbon $this */
            if ($this->year < 2015) {
                return false;
            }

            return $this->month === 10 && $this->day === 14;
        });

        Carbon::macro('isUkrainianConstitutionDay', function (): bool {
            // June 28th, from 1996.
            /** @var Carbon $this */
            if ($this->year < 1996) {
                return false;
            }

            return $this->month === 6 && $this->day === 28;
        });

        Carbon::macro('isUkrainianLabourDay', function (): bool {
            // May 1st.
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 1;
        });

        Carbon::macro('isKupalaNight', function (): bool {
            // July 6th–7th (traditional Julian calendar).
            /** @var Carbon $this */
            return $this->month === 7 && ($this->day === 6 || $this->day === 7);
        });

        Carbon::macro('isVictoryDayOverNazism', function (): bool {
            // May 9th, from 2015.
            /** @var Carbon $this */
            if ($this->year < 2015) {
                return false;
            }

            return $this->month === 5 && $this->day === 9;
        });
    }

    private function registerUsDates(): void
    {
        Carbon::macro('isMlkJrDay', function (): bool {
            // Third Monday in January, from 1986.
            /** @var Carbon $this */
            if ($this->year < 1986) {
                return false;
            }

            if ($this->month !== 1) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 3, Carbon::MONDAY) === $this->day;
        });

        Carbon::macro('isIndependenceDay', function (): bool {
            // July 4th, from 1781.
            /** @var Carbon $this */
            if ($this->year < 1781) {
                return false;
            }

            return $this->month === 7 && $this->day === 4;
        });

        Carbon::macro('isMemorialDay', function (): bool {
            // Last Monday in May, from 1874.
            /** @var Carbon $this */
            if ($this->year < 1874) {
                return false;
            }

            if ($this->month !== 5) {
                return false;
            }

            return $this->copy()->lastOfMonth(Carbon::MONDAY)->day === $this->day;
        });

        Carbon::macro('isLaborDay', function (): bool {
            // US spelling alias for the (Canadian) Labour Day predicate.
            /** @var Carbon $this */
            return $this->isLabourDay();
        });

        Carbon::macro('isVeteransDay', function (): bool {
            // November 11th, from 1954.
            /** @var Carbon $this */
            if ($this->year < 1954) {
                return false;
            }

            return $this->month === 11 && $this->day === 11;
        });

        Carbon::macro('isAmericanThanksgiving', function (): bool {
            // Fourth Thursday in November, from 1789.
            /** @var Carbon $this */
            if ($this->year < 1789) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 4, Carbon::THURSDAY) === $this->day;
        });

        Carbon::macro('isPresidentsDay', function (): bool {
            // Third Monday in February, from 1880.
            /** @var Carbon $this */
            if ($this->year < 1880) {
                return false;
            }

            if ($this->month !== 2) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 3, Carbon::MONDAY) === $this->day;
        });

        Carbon::macro('isColumbusDay', function (): bool {
            // Second Monday in October, from 1869.
            /** @var Carbon $this */
            if ($this->year < 1869) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 2, Carbon::MONDAY) === $this->day;
        });
    }

    private function registerZambianHolidays(): void
    {
        Carbon::macro('isZambianIndependenceDay', function (): bool {
            // October 24th, from 1964.
            /** @var Carbon $this */
            if ($this->year < 1964) {
                return false;
            }

            return $this->month === 10 && $this->day === 24;
        });

        Carbon::macro('isZambianLabourDay', function (): bool {
            // May 1st.
            /** @var Carbon $this */
            return $this->month === 5 && $this->day === 1;
        });

        Carbon::macro('isZambianYouthDay', function (): bool {
            // March 12th, from 1966.
            /** @var Carbon $this */
            if ($this->year < 1966) {
                return false;
            }

            return $this->month === 3 && $this->day === 12;
        });

        Carbon::macro('isZambianWomensDay', function (): bool {
            // March 8th (or the following Monday when it falls on a weekend), from 1977.
            /** @var Carbon $this */
            if ($this->year < 1977 || $this->month !== 3 || $this->isWeekend()) {
                return false;
            }

            return $this->day === 8 || ($this->isMonday() && in_array($this->day, [9, 10], true));
        });

        Carbon::macro('isZambianAfricanUnityDay', function (): bool {
            // May 25th, from 1963 (Africa Day).
            /** @var Carbon $this */
            if ($this->year < 1963) {
                return false;
            }

            return $this->month === 5 && $this->day === 25;
        });

        Carbon::macro('isZambianAfricaDay', function (): bool {
            /** @var Carbon $this */
            return $this->isZambianAfricanUnityDay();
        });

        Carbon::macro('isZambianHeroesDay', function (): bool {
            // First Monday in July, from 1964.
            /** @var Carbon $this */
            if ($this->year < 1964) {
                return false;
            }

            if ($this->month !== 7) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 1, Carbon::MONDAY) === $this->day;
        });

        Carbon::macro('isZambianUnityDay', function (): bool {
            // The day after Heroes' Day (first Monday in July + 1), from 1964.
            /** @var Carbon $this */
            if ($this->year < 1964) {
                return false;
            }

            if ($this->month !== 7) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 1, Carbon::MONDAY, 1) === $this->day;
        });

        Carbon::macro('isZambianFarmersDay', function (): bool {
            // First Monday in August, from 1964.
            /** @var Carbon $this */
            if ($this->year < 1964) {
                return false;
            }

            if ($this->month !== 8) {
                return false;
            }

            return CarbonMacros::nthWeekdayDay($this, 1, Carbon::MONDAY) === $this->day;
        });

        Carbon::macro('isZambianNationalPrayerDay', function (): bool {
            // October 18th, from 2015.
            /** @var Carbon $this */
            if ($this->year < 2015) {
                return false;
            }

            return $this->month === 10 && $this->day === 18;
        });
    }

    /**
     * Resolve the day-of-month for the Nth $weekday of $date's month, optionally
     * offset by $addDays. {@see Carbon::nthOfMonth()} returns `false` when the
     * Nth occurrence does not exist, so the result is narrowed here once instead
     * of at every holiday call site.
     *
     * Public because the holiday macros call it via the fully-qualified class
     * name: inside a registered macro the closure's `$this`/`self` are rebound to
     * the Carbon instance, so `self::` would not resolve to this provider.
     */
    public static function nthWeekdayDay(Carbon $date, int $nth, int $weekday, int $addDays = 0): ?int
    {
        $resolved = $date->copy()->nthOfMonth($nth, $weekday);

        if (!$resolved instanceof Carbon) {
            return null;
        }

        if ($addDays !== 0) {
            $resolved = $resolved->addDays($addDays);
        }

        return $resolved->day;
    }
}
