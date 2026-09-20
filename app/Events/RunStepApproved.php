<?php

namespace App\Events;

use App\Models\SopRun;
use App\Models\SopRunStep;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunStepApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SopRun $run, public SopRunStep $step)
    {
    }

    public function getEventName(): string
    {
        return 'run.step.approved';
    }
}
