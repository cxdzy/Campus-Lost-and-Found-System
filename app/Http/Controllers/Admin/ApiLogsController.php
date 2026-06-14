<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Inertia\Inertia;

class ApiLogsController extends Controller
{
    public function index()
    {
        $logs = ApiLog::with('item:id,title_description')
            ->orderByDesc('logged_at')
            ->paginate(50)
            ->through(fn (ApiLog $log) => [
                'id'               => $log->id,
                'service'          => $log->service,
                'http_status_code' => $log->http_status_code,
                'item_id'          => $log->item_id,
                'item_title'       => $log->item?->title_description,
                'logged_at'        => $log->logged_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/ApiLogs', [
            'logs' => $logs,
        ]);
    }
}
