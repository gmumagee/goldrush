<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->string('date'))
            : now();

        $weekStart = $selectedDate->copy()
            ->startOfWeek(Carbon::SUNDAY)
            ->startOfDay();

        $weekEnd = $selectedDate->copy()
            ->endOfWeek(Carbon::SATURDAY)
            ->endOfDay();

        $weekDays = collect();

        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $weekDays->push($date->copy());
        }

        $events = CalendarEvent::query()
            ->forAccount($accountId)
            ->with('assignedUser')
            ->where('status', CalendarEvent::STATUS_SCHEDULED)
            ->whereBetween('start_at', [$weekStart, $weekEnd])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        $eventsByDate = $events->groupBy(fn (CalendarEvent $event) => $event->start_at?->toDateString());

        return view('dashboard', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekDays' => $weekDays,
            'eventsByDate' => $eventsByDate,
        ]);
    }
}
