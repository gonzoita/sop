<?php

namespace App\Events;

use App\Models\SopRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SopRun $run)
    {
    }

    public function getEventName(): string
    {
        return 'run.completed';
    }
}
