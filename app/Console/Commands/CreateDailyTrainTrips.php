<?php

namespace App\Console\Commands;

use App\Services\TrainTripService;
use Illuminate\Console\Command;

class CreateDailyTrainTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trips:create-daily {--date= : The date to create trips for (Y-m-d format)} {--days=7 : Number of days ahead to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create train trips for upcoming days';

    protected TrainTripService $tripService;

    public function __construct(TrainTripService $tripService)
    {
        parent::__construct();
        $this->tripService = $tripService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : today();
        $daysAhead = (int) $this->option('days');

        $this->info("Creating trips starting from {$startDate->format('Y-m-d')} for {$daysAhead} days...");

        $totalCreated = 0;

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $startDate->copy()->addDays($i);
            $this->line("Processing {$date->format('Y-m-d')}...");

            $count = $this->tripService->createTripsForDate($date->format('Y-m-d'));
            $totalCreated += $count;

            $this->info("  Created {$count} trips for {$date->format('Y-m-d')}");
        }

        $this->info("✓ Total trips created: {$totalCreated}");

        return Command::SUCCESS;
    }
}
