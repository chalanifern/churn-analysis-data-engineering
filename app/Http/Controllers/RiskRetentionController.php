<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\ChurnRiskService;
use App\Support\ChurnVocabulary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class RiskRetentionController extends Controller
{
    public function __construct(private readonly ChurnRiskService $risk) {}

    public function index(Request $request): View
    {
        $segment = $request->query('segment');

        $filters = [
            'risk_level' => $this->validated($request->query('level'), ChurnVocabulary::RISK_LEVELS),
            'customer_segment' => $this->validated($segment, ChurnVocabulary::CUSTOMER_SEGMENTS),
            'connection_type' => $this->validated($request->query('connection'), ChurnVocabulary::CONNECTION_TYPES),
            'district' => $request->query('district'),
            'gender' => $this->validated($request->query('gender'), ChurnVocabulary::GENDERS),
            'search' => $request->query('search'),
            'sort' => $request->query('sort'),
        ];

        $result = $this->risk->paginatedRiskTable(
            filters: $filters,
            perPage: (int) ($request->query('per_page') ?: 15),
            page: (int) ($request->query('page') ?: 1),
        );

        $factors = $this->risk->factorDefinitions();

        return view('risk.index', compact('result', 'factors', 'filters'));
    }

    /**
     * Discard a query value that is not part of the controlled vocabulary, so a
     * hand-edited URL cannot inject an arbitrary match into the pipeline.
     *
     * @param  list<string>  $allowed
     */
    private function validated(?string $value, array $allowed): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }
}
