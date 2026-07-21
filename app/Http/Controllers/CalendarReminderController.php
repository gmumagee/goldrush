<?php

namespace App\Http\Controllers;

use App\Models\CalendarReminder;
use App\Services\CalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CalendarReminderController extends Controller
{
    public function __construct(protected CalendarService $calendarService)
    {
    }

    public function dismiss(Request $request, int $calendarReminder): RedirectResponse
    {
        $reminder = CalendarReminder::query()
            ->forAccount($this->currentAccountId($request))
            ->findOrFail($calendarReminder);
        $this->authorize('update', $reminder);

        $this->calendarService->dismissReminder($reminder, (int) $request->user()->id);

        return back()->with('status', 'Reminder dismissed.');
    }
}
