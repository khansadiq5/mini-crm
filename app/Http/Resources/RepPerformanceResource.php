<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepPerformanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_leads' => (int) $this->total_leads,
            'status_counts' => [
                'new' => (int) $this->new_count,
                'contacted' => (int) $this->contacted_count,
                'qualified' => (int) $this->qualified_count,
                'won' => (int) $this->won_count,
                'lost' => (int) $this->lost_count,
            ],
            'total_expected_value' => number_format((float) $this->total_expected_value, 2, '.', ''),
            'won_expected_value' => number_format((float) $this->won_expected_value, 2, '.', ''),
            'total_activities' => (int) $this->total_activities,
        ];
    }
}
