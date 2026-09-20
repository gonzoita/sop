<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs (Read-only for admin).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-audit-logs');

        $query = Activity::query()
            ->with(['causer'])
            ->latest();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('user_id')) {
            $query->where('causer_type', User::class)
                ->where('causer_id', $request->user_id);
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        $logs = $query->paginate(15)->withQueryString();

        $teamId = currentTeamId();

        $users = User::query()
            ->when($teamId, function ($q) use ($teamId) {
                $q->whereHas('teams', fn ($tq) => $tq->where('teams.id', $teamId))
                    ->orWhere('id', auth()->id());
            })
            ->get(['id', 'name', 'email']);

        $logNames = Activity::query()
            ->distinct()
            ->whereNotNull('log_name')
            ->pluck('log_name');

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['date_from', 'date_to', 'user_id', 'log_name']),
            'users' => $users,
            'logNames' => $logNames,
        ]);
    }
}