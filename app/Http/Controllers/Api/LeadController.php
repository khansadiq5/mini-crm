<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLeadRequest;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LeadController extends Controller
{
    /**
     * List leads with filtering, searching, sorting, and pagination.
     *
     * Reps only see their assigned leads; managers see all.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lead::class);

        $user = $request->user();

        $query = Lead::query()
            ->when($user->isRep(), fn ($q) => $q->where('assigned_to', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->input('assigned_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->whereAny(['name', 'email', 'company'], 'LIKE', "%{$search}%");
            })
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );

        $perPage = min((int) $request->input('per_page', 15), 100);

        return LeadResource::collection($query->paginate($perPage));
    }

    /**
     * Create a new lead.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated());

        return (new LeadResource($lead))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single lead with activities and assigned rep eager-loaded.
     */
    public function show(Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);

        $lead->load(['activities.user', 'assignedRep']);

        return new LeadResource($lead);
    }

    /**
     * Update a lead. Enforces the won/lost rule.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $incomingStatus = $request->validated()['status'] ?? null;

        if ($incomingStatus !== null) {
            $statusEnum = LeadStatus::from($incomingStatus);

            if (in_array($statusEnum, [LeadStatus::Won, LeadStatus::Lost]) && $lead->activities()->count() === 0) {
                abort(422, 'A lead must have at least one logged activity before it can be marked as won or lost.');
            }
        }

        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    /**
     * Assign a lead to a specific rep.
     */
    public function assign(AssignLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update([
            'assigned_to' => $request->input('rep_id'),
        ]);

        return new LeadResource($lead->load('assignedRep'));
    }

    /**
     * Log a new activity against a lead.
     */
    public function logActivity(StoreActivityRequest $request, Lead $lead): JsonResponse
    {
        $activity = $lead->activities()->create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'body' => $request->input('body'),
            'occurred_at' => $request->input('occurred_at'),
        ]);

        return (new ActivityResource($activity->load('user')))
            ->response()
            ->setStatusCode(201);
    }
}
