<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'slug'      => $this->slug,
            'parent_id' => $this->parent_id,
            'children'  => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
