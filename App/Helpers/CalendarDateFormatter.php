<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Hekmatinasser\Verta\Verta;

final class CalendarDateFormatter
{
    /** Binds display preferences without changing the stored date or Verta's global locale. */
    public function __construct(
        private readonly string $calendarSystem,
        public readonly DateTimeZone $timezone
    ) {}

    /** Displays a local calendar date; Jalali always uses Persian month names and digits. */
    public function date(DateTimeInterface $instant, bool $longMonth = false): string
    {
        $date = DateTimeImmutable::createFromInterface($instant)->setTimezone($this->timezone);
        if ($this->calendarSystem !== 'jalali') {
            return $date->format($longMonth ? 'F j, Y' : 'M j, Y');
        }

        [$year, $month, $day] = Verta::getJalali(
            (int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j')
        );
        $monthName = Verta::getMessages('fa')['year_months'][$month];

        return Verta::enToFaNumbers("{$day} {$monthName} {$year}");
    }

    /** Displays the user's local clock, keeping Jalali digits and AM/PM labels Persian. */
    public function time(DateTimeInterface $instant): string
    {
        $date = DateTimeImmutable::createFromInterface($instant)->setTimezone($this->timezone);
        if ($this->calendarSystem !== 'jalali') {
            return $date->format('h:i A');
        }

        $period = $date->format('A') === 'AM' ? 'ق.ظ' : 'ب.ظ';

        return Verta::enToFaNumbers($date->format('h:i')) . ' ' . $period;
    }

    /** Combines the calendar date and local time while preserving the existing Gregorian wording. */
    public function dateTime(DateTimeInterface $instant): string
    {
        $separator = $this->calendarSystem === 'jalali' ? ' ساعت ' : ' at ';

        return $this->date($instant) . $separator . $this->time($instant);
    }
}
