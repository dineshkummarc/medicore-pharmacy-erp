<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use HandlesTableQueries;

    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $term = $request->string('q')->limit(80)->value();
        $action = $request->string('action')->limit(60)->value();

        $logs = ActivityLog::query()
            ->with('user')
            ->when($term !== '', fn ($q) => $q->where('description', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($action !== '', fn ($q) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('activity-log.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => ['auth', 'medicine', 'inventory', 'purchase', 'sale', 'invoice', 'customer', 'supplier', 'expense', 'staff', 'role', 'settings'],
            'filters' => $request->only(['q', 'action', 'user', 'from', 'to']),
        ]);
    }
}
