<?php

namespace App\Http\Controllers;

use App\Support\SpkYearlyKpiAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class YearlyKpiDashboardController extends Controller
{
    /**
     * Display the yearly KPI analytics page.
     */
    public function index(Request $request): Response
    {
        $year = $this->resolveYear($request->string('year')->toString());
        $analytics = (new SpkYearlyKpiAnalytics($year))->summarize();

        $currentYear = (int) Carbon::parse(now()->toDateTimeString())->format('Y');

        return Inertia::render('analytics/yearly-kpi', [
            'analytics' => $analytics,
            'filters' => [
                'year' => (string) $year,
            ],
            'navigation' => [
                'previousYear' => (string) ($year - 1),
                'nextYear' => $year + 1 <= $currentYear ? (string) ($year + 1) : null,
                'currentYear' => (string) $currentYear,
                'isCurrentYear' => $year === $currentYear,
            ],
        ]);
    }

    private function resolveYear(string $year): int
    {
        $currentYear = (int) Carbon::parse(now()->toDateTimeString())->format('Y');

        if ($year !== '' && preg_match('/^\d{4}$/', $year) === 1) {
            $resolved = (int) $year;

            if ($resolved > $currentYear) {
                return $currentYear;
            }

            if ($resolved < 2000) {
                return $currentYear;
            }

            return $resolved;
        }

        return $currentYear;
    }
}
