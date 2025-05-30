<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\User;
use App\Http\Resources\LeadResource;
use App\Http\Requests\LeadRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Notification;
use App\Notifications\LeadAssigned;


class LeadController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Lead::query();
        $user = Auth::user();

        if($user->isAgent('agent')){
            $query->where('assigned_to', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $sortBy = $request->get('sort_by', 'created_at'); 
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        $perPage = $request->get('per_page', 10);
        $leads = $query->paginate($perPage);
        return LeadResource::collection($leads);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadRequest $request)
    {
        $this->authorize('create', Lead::class);
        $validated = $request->validated();
        $lead = Lead::create($validated);

        $agent = User::find($lead->assigned_to);
        if ($agent) {
            Notification::send($agent, new LeadAssigned($lead));
        }
        return (new LeadResource($lead))
            ->response()
            ->setStatusCode(201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        return new LeadResource($lead);
    }

   
    /**
     * Update the specified resource in storage.
     */
    public function update(LeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validated = $request->validated();
        $oldAssignedTo = $lead->assigned_to;
        $lead->update($validated);
        if (
            isset($validated['assigned_to']) &&
            $validated['assigned_to'] != $oldAssignedTo
        ) {
            $newAgent = User::find($validated['assigned_to']);
            if ($newAgent) {
                Notification::send($newAgent, new LeadAssigned($lead));
            }
        }

        return new LeadResource($lead);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $lead->delete();
        return response()->noContent();
    }

}
