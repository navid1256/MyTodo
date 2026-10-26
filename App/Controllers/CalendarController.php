<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CalendarHolidayService;

final class CalendarController
{
    /** Receives the shared holiday service without adding any database dependencies. */
    public function __construct(private readonly CalendarHolidayService $holidays) {}

    /** Accepts a bounded Jalali year and returns Gregorian date keys without occasion titles. */
    public function holidays(Request $request): Response
    {
        $year = $request->queryString('year');
        if (preg_match('/^\d{4}$/D', $year) !== 1
            || (int) $year < CalendarHolidayService::MINIMUM_YEAR || (int) $year > CalendarHolidayService::MAXIMUM_YEAR) {
            return Response::json(['success' => false, 'message' => 'Invalid calendar year.'], 422);
        }
        return Response::json($this->holidays->getHolidays((int) $year), 200, ['Cache-Control' => 'no-store']);
    }
}
