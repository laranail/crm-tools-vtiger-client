# Carbon macros

`Macros\CarbonMacros` registers a set of date helpers and national-calendar
predicates on `Illuminate\Support\Carbon` (and therefore `CarbonImmutable`).
They are booted eagerly by the toolkit's `MacroServiceProvider` — no setup is
needed beyond installing the package.

Only helpers with **no native Carbon 3 equivalent** are registered. Native
methods (`startOfQuarter`, `isWeekday`, `nextWeekday`, `toDateTimeLocalString`,
etc.) are intentionally **not** shadowed — use Carbon's own.

> All holiday helpers are boolean predicates evaluated against the instance's
> date, e.g. `Carbon::parse('2026-07-14')->isFrenchNationalDay()` → `true`.
> Movable feasts (Easter-derived) are computed for the instance's year.

## Date utilities

| Macro | Signature | Effect |
|---|---|---|
| `fromDateTimeLocalString` | `(string $value): ?Carbon` | Parse an `<input type="datetime-local">` value (`Y-m-d\TH:i`), or `null` if invalid. |
| `addBusinessDays` | `(int $days): Carbon` | Add N weekdays (skips Sat/Sun). |
| `subBusinessDays` | `(int $days): Carbon` | Subtract N weekdays. |
| `isFirstDayOfMonth` | `(): bool` | Whether the date is the 1st. |
| `isLastDayOfMonth` | `(): bool` | Whether the date is the month's last day. |
| `getQuarter` | `(): int` | The quarter (1–4) as a method (Carbon exposes it as the `quarter` property). |
| `toHumanReadableString` | `(): string` | A friendly, human-readable rendering of the date. |

```php
use Illuminate\Support\Carbon;

Carbon::parse('2026-06-25')->addBusinessDays(3);          // skips the weekend
Carbon::parse('2026-06-30')->isLastDayOfMonth();          // true
Carbon::fromDateTimeLocalString('2026-06-25T14:30');      // Carbon|null
```

## Multinational feasts

Shared Christian feasts and new-year markers (movable feasts are year-aware):

`isNewYearsDay`, `isEasterSunday`, `isGoodFriday`, `isAllSaintsDay`,
`isChristmasDay`, `isNewYearsEve`.

## National calendars

Each helper is a `(): bool` predicate. Group by country:

### Brazil
`isTiradentesDay`, `isBrazilianLaborDay`, `isBrazilianIndependenceDay`,
`isTheDayOfOurLadyAparecida`, `isBrazilianDayOfTheDead`,
`isBrazilianRepublicProclamationDay`.

### Canada
`isVictoriaDay`, `isCanadaDay`, `isLabourDay`, `isCanadianThanksgiving`,
`isRemembranceDay`, `isBoxingDay`, `isCivicHoliday`, `isFamilyDay`.

### France
`isAscensionDay`, `isAssumptionDay`, `isEasterMonday`, `isFirstWarArmisticeDay`,
`isFrenchNationalDay`, `isPentecostDay`, `isSecondWarArmisticeDay`.

### Germany
`isGermanLabourDay`, `isAscensionOfJesus`, `isWhitSunday`, `isWhitsun`,
`isPentecost`, `isPentecostSunday`, `isWhitMonday`, `isPentecostMonday`,
`isCorpusChristi`, `isGermanUnityDay`, `isHeiligerAbend`, `isHeiligAbend`.

### India
`isIndianRepublicDay`, `isIndianIndependenceDay`, `isGandhiJayanti`.

### Indonesia
`isIndonesianIndependenceDay`, `isPancasilaDay`, `isIndonesianLaborDay`,
`isKartiniDay`, `isIndonesianEducationDay`, `isIndonesiaCustomerDay`,
`isIndonesianHeroesDay`, `isIndonesianMothersDay`.

### Italy
`isLiberationDay`, `isRepublicDay`, `isImmaculateConceptionFeast`,
`isAssumptionOfMaryFeast`, `isEpiphany`, `isSaintStephenDay`,
`isSaintSylvesterDay`, `isWorkersDay`.

### Kenya
`isKenyanIndependenceDay`, `isKenyanJamhuriDay`, `isKenyanLabourDay`,
`isKenyanMadarakaDay`, `isKenyanHudumaDay`, `isKenyanMashujaaDay`,
`isKenyanUtamaduniDay`.

### Netherlands
`isDutchLiberationDay`, `isSaintNicholasEve`, `isDutchRemembranceDay`,
`isDutchNationalDay`.

### Sweden
`isSwedishMidsummerDay`, `isChristmasEve`, `isSwedishNationalDay`.

### Ukraine
`isUkrainianIndependenceDay`, `isUkraineDefenderDay`,
`isUkrainianConstitutionDay`, `isUkrainianLabourDay`, `isKupalaNight`,
`isVictoryDayOverNazism`.

### United States
`isMlkJrDay`, `isIndependenceDay`, `isMemorialDay`, `isLaborDay`,
`isVeteransDay`, `isAmericanThanksgiving`, `isPresidentsDay`, `isColumbusDay`.

### Zambia
`isZambianIndependenceDay`, `isZambianLabourDay`, `isZambianYouthDay`,
`isZambianWomensDay`, `isZambianAfricanUnityDay`, `isZambianAfricaDay`,
`isZambianHeroesDay`, `isZambianUnityDay`, `isZambianFarmersDay`,
`isZambianNationalPrayerDay`.

```php
use Illuminate\Support\Carbon;

if (Carbon::now()->isCanadaDay()) {
    // July 1st
}

// Movable feasts are computed per the instance's year:
Carbon::parse('2026-04-06')->isEasterMonday();   // true (Easter 2026 = Apr 5)
```

> **Origin:** these calendars were folded into `CarbonMacros` from the legacy
> monolith's 14 national `Carbon` calendar traits during the migration (the
> legacy `=`/`===`/octal-literal bugs were fixed on the way).

[← Docs index](../README.md#documentation)
