<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Train\TrainTrip;
use App\Services\DelayEstimationService;
use Carbon\Carbon;

class EstimateTripDelays extends Command
{
    protected $signature = 'trips:estimate-delays {--trip-id= : Specific trip ID to estimate}';

    protected $description = 'Estimate delays for active train trips based on passenger GPS and community reports';

    protected DelayEstimationService $delayEstimationService;

    public function __construct(DelayEstimationService $delayEstimationService)
    {
        parent::__construct();
        $this->delayEstimationService = $delayEstimationService;
    }

    public function handle(): int
    {
        $tripId = $this->option('trip-id');

        if ($tripId) {
            // Estimate specific trip
            $this->info("Estimating delay for trip {$tripId}...");
            $result = $this->delayEstimationService->estimateDelay($tripId);

            if (isset($result['error'])) {
                $this->error($result['error']);
                return 1;
            }

            $this->displayResult($result);
            return 0;
        }

        // Estimate all active trips
        $activeTrips = TrainTrip::whereDate('trip_date', Carbon::today())
            ->whereIn('status', ['active', 'departed', 'in_transit', 'delayed'])
            ->get();

        if ($activeTrips->isEmpty()) {
            $this->info('No active trips found.');
            return 0;
        }

        $this->info("Found {$activeTrips->count()} active trips. Estimating delays...");

        $processed = 0;
        $updated = 0;

        foreach ($activeTrips as $trip) {
            $result = $this->delayEstimationService->estimateDelay($trip->id);

            if (!isset($result['error'])) {
                $processed++;

                if ($result['estimated_delay_minutes'] !== $result['current_delay_minutes']) {
                    $updated++;
                    $this->line("Trip {$trip->id} (Train {$trip->train->number}): " .
                               "{$result['current_delay_minutes']} → {$result['estimated_delay_minutes']} min " .
                               "(confidence: {$result['confidence_level']})");
                }
            }
        }

        $this->info("\nProcessed: {$processed} trips");
        $this->info("Updated: {$updated} trips with delay changes");

        return 0;
    }

    protected function displayResult(array $result): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Trip ID', $result['trip_id']],
                ['Current Delay', $result['current_delay_minutes'] . ' minutes'],
                ['Estimated Delay', $result['estimated_delay_minutes'] . ' minutes'],
                ['Confidence Level', $result['confidence_level']],
                ['Passenger GPS Data', $result['data_sources']['passenger_gps_count']],
                ['Community Reports', $result['data_sources']['community_reports_count']],
            ]
        );

        if ($result['estimated_position']) {
            $this->info("\nEstimated Position:");
            $this->line("  Nearest Station: #{$result['estimated_position']['nearest_station_id']}");
            $this->line("  Distance: " . round($result['estimated_position']['distance_to_station_km'], 2) . " km");
            $this->line("  Average Speed: {$result['estimated_position']['average_speed_kmh']} km/h");
        }

        if ($result['expected_position']) {
            $this->info("\nExpected Position:");
            $this->line("  Station: #{$result['expected_position']['station_id']}");
            $this->line("  Stop Number: {$result['expected_position']['stop_number']}");
        }
    }
}
