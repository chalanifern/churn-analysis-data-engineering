<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\DashboardAnalytics;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardAnalytics $dashboard) {}

    public function index(): View
    {
        $overview = $this->dashboard->overview();
        $churnTrend = $this->dashboard->churnTrend(12);
        $reasons = $this->dashboard->churnByReason();
        $districts = $this->dashboard->churnByDistrict();
        $riskDist = $this->dashboard->riskDistribution();
        $topRisk = $this->dashboard->topRiskCustomers(10);

        return view('dashboard.index', compact(
            'overview',
            'churnTrend',
            'reasons',
            'districts',
            'riskDist',
            'topRisk',
        ));
    }
}
