<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Analytics\MongoAggregator;
use App\Services\Analytics\PaymentDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Eloquent\Model as MongoModel;

class AppServiceProvider extends ServiceProvider
{
    private ?string $anchor = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The analytics layer works with the native MongoDB database handle so it
        // can run aggregation pipelines directly. Binding it here keeps the
        // services free of container lookups.
        $this->app->singleton(MongoAggregator::class, function (): MongoAggregator {
            return new MongoAggregator(DB::connection('mongodb')->getDatabase());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly in development if a relationship is accessed without an
        // explicit $with / load() - helpful while working with MongoDB.
        Model::preventLazyLoading($this->app->environment('local', 'testing'));

        // Every document model in this project lives on the MongoDB connection, so
        // the global defaults follow suit.
        MongoModel::setConnectionResolver($this->app['db']);

        $this->sharePrototypeMetadata();
    }

    /**
     * Prototype wording and vocabulary lists are needed by almost every view, so
     * they are shared once rather than re-read in each controller.
     */
    private function sharePrototypeMetadata(): void
    {
        View::composer('*', function ($view): void {
            $view->with('prototypeLabel', config('churn-analytics.prototype.label'));
            $view->with('prototypeDisclaimer', config('churn-analytics.prototype.disclaimer'));
            $view->with('prototypeAnchor', $this->reportingAnchor());
        });
    }

    /**
     * Resolved once per request: the view composer runs for every view, and the
     * anchor query must not be repeated for each partial.
     */
    private function reportingAnchor(): string
    {
        return $this->anchor ??= PaymentDates::latestMonth($this->app->make(MongoAggregator::class))
            ->format('F Y');
    }
}
