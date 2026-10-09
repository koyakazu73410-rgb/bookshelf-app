<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'genres' => $this->genres->map(fn ($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
            ])->values(),
            'average_rating' => $this->reviews_avg_rating === null
                ? null
                : round((float) $this->reviews_avg_rating, 2),
            'reviews_count' => (int) $this->reviews_count,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
