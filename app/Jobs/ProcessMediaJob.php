<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\Media\ProcessMediaFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class ProcessMediaJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public Media $media)
    {
        $this->onQueue('media');
    }

    /**
     * Execute the job.
     */
    public function handle(ProcessMediaFile $processor): void
    {
        $processor->handle($this->media);
    }
}
