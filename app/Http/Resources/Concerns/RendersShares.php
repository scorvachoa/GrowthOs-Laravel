<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

trait RendersShares
{
    private function withShares(Request $request, array $data): array
    {
        if (! $this->relationLoaded('shares')) {
            return $data;
        }

        $data['shared_user_ids'] = $this->shares->pluck('shared_with_user_id')->toArray();
        $currentUserId = $request->user()?->id;
        $myShare = $this->shares->firstWhere('shared_with_user_id', $currentUserId);
        if ($myShare) {
            $data['shared_by_user_name'] = $myShare->sharedByUser?->name;
        }
        $data['shared_with_users'] = $this->shares->map(fn ($s) => [
            'id' => $s->shared_with_user_id,
            'name' => $s->sharedWithUser?->name,
            'accepted' => $s->isAccepted(),
            'role' => $s->role,
        ])->values()->all();

        return $data;
    }
}
