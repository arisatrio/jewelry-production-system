<?php

namespace App\Http\Controllers;

use App\Support\SpkDashboardAnalytics;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the production analytics dashboard.
     */
    public function index(Request $request): Response
    {
        $month = $this->resolveMonth($request->string('month')->toString());
        $analytics = (new SpkDashboardAnalytics($month))->summarize();

        $previousMonth = $month->copy()->subMonthNoOverflow()->startOfMonth();
        $nextMonth = $month->copy()->addMonthNoOverflow()->startOfMonth();
        $currentMonth = Carbon::parse(now()->toDateTimeString())->startOfMonth();
        $canGoNext = $nextMonth->lte($currentMonth);

        return Inertia::render('welcome', [
            'analytics' => $analytics,
            'filters' => [
                'month' => $month->format('Y-m'),
            ],
            'navigation' => [
                'previousMonth' => $previousMonth->format('Y-m'),
                'nextMonth' => $canGoNext ? $nextMonth->format('Y-m') : null,
                'currentMonth' => $currentMonth->format('Y-m'),
                'isCurrentMonth' => $month->isSameMonth($currentMonth),
            ],
        ]);
    }

    private function resolveMonth(string $month): CarbonInterface
    {
        $current = Carbon::parse(now()->toDateTimeString())->startOfMonth();

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                $resolved = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

                if ($resolved->gt($current)) {
                    return $current;
                }

                return $resolved;
            } catch (\Throwable) {
                // Fall through to current month.
            }
        }

        return $current;
    }
}
