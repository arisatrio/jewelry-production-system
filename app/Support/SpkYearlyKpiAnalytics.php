<?php

namespace App\Support;

class SpkYearlyKpiAnalytics
{
    public function __construct(private readonly int $year) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(): array
    {
        return (new SpkDashboardAnalytics)->summarizeYearlyKpi($this->year);
    }
}
