<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\RepPerformanceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get rep performance report.
     */
    public function repPerformance(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Subquery for lead statistics grouped by rep (assigned_to)
        $leadStatsSub = DB::table('leads')
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as total_leads')
            ->selectRaw("SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count")
            ->selectRaw("SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted_count")
            ->selectRaw("SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_count")
            ->selectRaw("SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_count")
            ->selectRaw("SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_count")
            ->selectRaw('SUM(expected_value) as total_expected_value')
            ->selectRaw("SUM(CASE WHEN status = 'won' THEN expected_value ELSE 0 END) as won_expected_value")
            ->groupBy('assigned_to');

        // Subquery for activities grouped by rep (via leads.assigned_to)
        $activityStatsSub = DB::table('activities')
            ->join('leads', 'activities.lead_id', '=', 'leads.id')
            ->select('leads.assigned_to')
            ->selectRaw('COUNT(activities.id) as total_activities')
            ->groupBy('leads.assigned_to');

        $query = User::query()
            ->select('users.id', 'users.name')
            ->selectRaw('COALESCE(lead_stats.total_leads, 0) as total_leads')
            ->selectRaw('COALESCE(lead_stats.new_count, 0) as new_count')
            ->selectRaw('COALESCE(lead_stats.contacted_count, 0) as contacted_count')
            ->selectRaw('COALESCE(lead_stats.qualified_count, 0) as qualified_count')
            ->selectRaw('COALESCE(lead_stats.won_count, 0) as won_count')
            ->selectRaw('COALESCE(lead_stats.lost_count, 0) as lost_count')
            ->selectRaw('COALESCE(lead_stats.total_expected_value, 0.00) as total_expected_value')
            ->selectRaw('COALESCE(lead_stats.won_expected_value, 0.00) as won_expected_value')
            ->selectRaw('COALESCE(activity_stats.total_activities, 0) as total_activities')
            ->leftJoinSub($leadStatsSub, 'lead_stats', 'lead_stats.assigned_to', '=', 'users.id')
            ->leftJoinSub($activityStatsSub, 'activity_stats', 'activity_stats.assigned_to', '=', 'users.id')
            ->where('users.role', UserRole::Rep)
            ->when($user->isRep(), fn ($q) => $q->where('users.id', $user->id));

        return RepPerformanceResource::collection($query->get());
    }
}
