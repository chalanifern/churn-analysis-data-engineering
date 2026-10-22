<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Support\ChurnVocabulary;
use Carbon\CarbonImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;

/**
 * Thin, well-documented wrapper around the native MongoDB aggregation API.
 *
 * The dashboard and the three analytics pages are all built on top of this class
 * so that every figure rendered in the interface is produced by a MongoDB
 * aggregation pipeline running on the server, never by PHP or JavaScript loops
 * over raw documents.
 *
 * The helper methods here are deliberately thin: they exist to give the
 * aggregation services a readable, intention-revealing vocabulary
 * (`lookupMany`, `groupCount`, `percentageOf`) without hiding the underlying
 * pipeline, which the assignment requires to remain visible in the codebase.
 */
final class MongoAggregator
{
    public function __construct(private readonly Database $database) {}

    /**
     * Run a pipeline against a collection and return the resulting documents.
     *
     * The `typeMap` option makes the driver return plain PHP arrays (rather than
     * `BSONDocument` objects) so the pipeline output can be handed straight to
     * Blade without further conversion.
     *
     * @param  list<array<string, mixed>>  $pipeline
     * @return list<array<string, mixed>>
     */
    public function aggregate(string $collection, array $pipeline): array
    {
        $documents = $this->collection($collection)
            ->aggregate($pipeline, [
                'allowDiskUse' => true,
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ])
            ->toArray();

        return array_values(array_filter(array_map(
            static fn (mixed $document): array => is_array($document) ? $document : [],
            $documents,
        )));
    }

    /**
     * Run a pipeline and return the first document, or an empty array.
     *
     * @param  list<array<string, mixed>>  $pipeline
     * @return array<string, mixed>
     */
    public function aggregateFirst(string $collection, array $pipeline): array
    {
        return $this->aggregate($collection, $pipeline)[0] ?? [];
    }

    /**
     * `$count` - the fastest way to total a whole collection or a `$match` subset.
     *
     * @param  array<string, mixed>  $match
     */
    public function count(string $collection, array $match = []): int
    {
        if ($match === []) {
            $result = $this->collection($collection)->countDocuments();

            return (int) $result;
        }

        $document = $this->aggregateFirst($collection, [
            ['$match' => $match],
            ['$count' => 'total'],
        ]);

        return (int) ($document['total'] ?? 0);
    }

    /**
     * Group and count, optionally sorted, using `$match` / `$group` / `$sort`.
     *
     * @param  array<string, mixed>  $match
     * @return list<array{_id: mixed, count: int}>
     */
    public function groupCount(string $collection, string $groupBy, array $match = [], int $limit = 0): array
    {
        $pipeline = $match === []
            ? []
            : [['$match' => $match]];

        $pipeline[] = ['$group' => ['_id' => '$'.$groupBy, 'count' => ['$sum' => 1]]];
        $pipeline[] = ['$sort' => ['count' => -1]];

        if ($limit > 0) {
            $pipeline[] = ['$limit' => $limit];
        }

        return $this->aggregate($collection, $pipeline);
    }

    /**
     * Group on the output of an `$addFields` expression rather than a plain field.
     * Used for derived dimensions such as age group and tenure band.
     *
     * @param  array<string, mixed>  $match
     * @return list<array{_id: mixed, count: int}>
     */
    public function groupCountByExpression(string $collection, array $match, array $switchBranches, int $limit = 0): array
    {
        $pipeline = $match === []
            ? []
            : [['$match' => $match]];

        $pipeline[] = ['$group' => [
            '_id' => ['$switch' => ['branches' => $switchBranches, 'default' => 'Unclassified']],
            'count' => ['$sum' => 1],
        ]];
        $pipeline[] = ['$sort' => ['count' => -1]];

        if ($limit > 0) {
            $pipeline[] = ['$limit' => $limit];
        }

        return $this->aggregate($collection, $pipeline);
    }

    /**
     * A `$lookup` stage definition joined on a shared string business key.
     *
     * The local collection stores the foreign key as a plain string (for example
     * `customer_id`), so the standard equality `$lookup` applies.
     *
     * @param  array<string, mixed>  $pipeline  optional sub-pipeline executed after the match
     * @return array<string, mixed>
     */
    public function lookup(string $from, string $localField, string $foreignField, string $as, array $pipeline = []): array
    {
        $stage = [
            'from' => $from,
            'localField' => $localField,
            'foreignField' => $foreignField,
            'as' => $as,
        ];

        if ($pipeline !== []) {
            $stage['pipeline'] = array_merge(
                [['$match' => ['$expr' => ['$eq' => ['$$'.$foreignField, '$$'.$localField]]]]],
                $pipeline,
            );
            unset($stage['localField'], $stage['foreignField']);
        }

        return ['$lookup' => $stage];
    }

    /**
     * Collapse a joined array into a single object. `{$first: "$path"}` is used
     * when the join is already ordered, otherwise `$arrayElemAt` picks the entry.
     *
     * @return array<string, mixed>
     */
    public function first(array $path): array
    {
        return ['$arrayElemAt' => [$path, 0]];
    }

    /**
     * Safe division that yields 0 instead of null when the denominator is zero.
     * Used for churn rates and complaint resolution percentages.
     *
     * @return array<string, mixed>
     */
    public function safeRatio(array $numerator, array $denominator, float $multiplier = 1.0): array
    {
        return [
            '$cond' => [
                ['$gt' => [$denominator, 0]],
                ['$multiply' => [
                    [$numerator, $denominator],
                    $multiplier,
                ]],
                0,
            ],
        ];
    }

    /**
     * Build the `$switch` branches for the age-group bucketing shown on the
     * Customer Analytics page.
     *
     * @return list<array<string, mixed>>
     */
    public static function ageGroupBranches(): array
    {
        $branches = [];

        foreach (ChurnVocabulary::AGE_GROUPS as $label => $band) {
            $branches[] = [
                'case' => [
                    '$and' => [
                        ['$gte' => ['$age', $band['min']]],
                        ['$lte' => ['$age', $band['max']]],
                    ],
                ],
                'then' => $label,
            ];
        }

        return $branches;
    }

    /**
     * Build the `$switch` branches for tenure bucketing. Used by both the churn
     * analysis tenure chart and the customer tenure distribution chart.
     *
     * @return list<array<string, mixed>>
     */
    public static function tenureGroupBranches(string $sourceField = 'tenure_months'): array
    {
        $branches = [];

        foreach (ChurnVocabulary::TENURE_GROUPS as $label => $band) {
            $branches[] = [
                'case' => [
                    '$and' => [
                        ['$gte' => ['$'.$sourceField, $band['min']]],
                        ['$lte' => ['$'.$sourceField, $band['max']]],
                    ],
                ],
                'then' => $label,
            ];
        }

        return $branches;
    }

    /**
     * "$dateDiff" in whole days between a stored `YYYY-MM-DD` string and the
     * reporting anchor. Stored dates are ISO strings, so they are converted with
     * `$toDate` before the difference is taken.
     *
     * The anchor is passed to the driver as a BSON UTCDateTime built from epoch
     * milliseconds - passing a PHP DateTimeInterface directly would encode it as
     * a sub-document, which `$dateDiff` rejects.
     *
     * @return array<string, mixed>
     */
    public static function daysSince(string $dateField, CarbonImmutable $anchor): array
    {
        return [
            '$dateDiff' => [
                'startDate' => ['$toDate' => '$'.$dateField],
                'endDate' => new UTCDateTime($anchor->getTimestamp() * 1000),
                'unit' => 'day',
            ],
        ];
    }

    public function collection(string $name): Collection
    {
        return $this->database->selectCollection($name);
    }

    /**
     * Column-oriented shape used by Chart.js: `['Colombo' => 120, ...]`.
     *
     * @param  list<array{_id: mixed, count: int}>  $grouped
     * @param  list<string>  $order  optional explicit ordering (e.g. a fixed legend)
     * @return array<string, int>
     */
    public static function toLabelMap(array $grouped, array $order = []): array
    {
        $map = [];

        foreach ($grouped as $row) {
            $map[(string) $row['_id']] = (int) $row['count'];
        }

        if ($order === []) {
            return $map;
        }

        // Emit the requested order first, then append anything unexpected so no
        // data silently disappears if a new value appears in the collection.
        $ordered = [];

        foreach ($order as $label) {
            $ordered[$label] = $map[$label] ?? 0;
        }

        return $ordered + array_diff_key($map, $ordered);
    }
}
