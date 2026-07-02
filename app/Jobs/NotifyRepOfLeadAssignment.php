<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyRepOfLeadAssignment implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $repId,
        public int $leadId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Rep {$this->repId} notified about lead {$this->leadId}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to notify rep of lead assignment', [
            'rep_id' => $this->repId,
            'lead_id' => $this->leadId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
