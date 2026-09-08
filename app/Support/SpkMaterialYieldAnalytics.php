<?php

namespace App\Support;

use Carbon\CarbonInterface;

class SpkMaterialYieldAnalytics
{
    public function __construct(private readonly ?CarbonInterface $month = null) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(): array
    {
        return (new SpkDashboardAnalytics($this->month))->summarizeMaterialYield();
    }
}
