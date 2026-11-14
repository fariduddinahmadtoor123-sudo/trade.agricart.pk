<?php

namespace App\Models;

use Database\Factories\PageInfoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['page_slug', 'title', 'user_guide_content', 'ai_prompt_content'])]
class PageInfo extends Model
{
    /** @use HasFactory<PageInfoFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'page_slug';
    }
}
