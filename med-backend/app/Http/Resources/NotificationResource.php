<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'category'   => $this->category,
            'level'      => $this->level,
            'title'      => $this->title,
            'body'       => $this->body,
            'link'       => $this->link,
            'is_read'    => $this->is_read,
            'read_at'    => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Etichetta relativa gia' pronta ("2 ore fa")
            'created_label' => $this->created_at?->diffForHumans(),
        ];
    }
}
