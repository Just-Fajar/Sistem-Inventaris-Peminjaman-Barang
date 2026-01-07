<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Get all activity logs with filters
     */
    public function index(Request $request)
    {
        $query = Activity::with('causer', 'subject')
            ->latest();

        // Filter by subject type (model)
        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filter by event (created, updated, deleted)
        if ($request->has('event')) {
            $query->where('event', $request->event);
        }

        // Filter by causer (user who made the change)
        if ($request->has('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Get activity logs for a specific model
     */
    public function getForModel(Request $request, string $type, int $id)
    {
        $modelClass = $this->getModelClass($type);
        
        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid model type',
            ], 400);
        }

        $logs = Activity::where('subject_type', $modelClass)
            ->where('subject_id', $id)
            ->with('causer')
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Get recent activities for dashboard
     */
    public function recent(Request $request)
    {
        $logs = Activity::with('causer', 'subject')
            ->latest()
            ->limit($request->input('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get model class from type string
     */
    private function getModelClass(string $type): ?string
    {
        $models = [
            'item' => 'App\Models\Item',
            'borrowing' => 'App\Models\Borrowing',
            'category' => 'App\Models\Category',
            'user' => 'App\Models\User',
        ];

        return $models[$type] ?? null;
    }
}
