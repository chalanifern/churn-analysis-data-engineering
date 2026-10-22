<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\ChurnAnalytics;
use Illuminate\Contracts\View\View;

final class ChurnAnalysisController extends Controller
{
    public function __construct(private readonly ChurnAnalytics $analytics) {}

    public function index(): View
    {
        $summary = $this->analytics->summary();
        $bySegment = $this->analytics->bySegment();
        $byPlan = $this->analytics->byPlan();
        $byDistrict = $this->analytics->byDistrict();
        $reasons = $this->analytics->reasons();

        return view('churn.index', compact('summary', 'bySegment', 'byPlan', 'byDistrict', 'reasons'));
    }
}
