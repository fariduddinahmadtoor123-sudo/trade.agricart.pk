<?php

namespace App\Support;

use App\Models\PageInfo;
use Illuminate\Support\Facades\Lang;

class LocalizedPageInfo
{
    public function title(?PageInfo $pageInfo, ?string $slug): string
    {
        $navKey = $slug === null ? null : 'trading.nav.'.str_replace('-', '_', $slug);

        if ($navKey !== null && $this->usesTranslation($navKey)) {
            return __($navKey);
        }

        return $pageInfo->title ?? __('trading.page_info.title');
    }

    public function guide(?PageInfo $pageInfo, ?string $slug): ?string
    {
        return $this->body($pageInfo?->user_guide_content, $slug, 'user');
    }

    public function prompt(?PageInfo $pageInfo, ?string $slug): ?string
    {
        return $this->body($pageInfo?->ai_prompt_content, $slug, 'prompt');
    }

    private function body(?string $fallback, ?string $slug, string $part): ?string
    {
        $key = $slug === null ? null : "trading.guides.{$slug}.{$part}";

        if ($key !== null && $this->usesTranslation($key)) {
            return __($key);
        }

        return $fallback;
    }

    private function usesTranslation(string $key): bool
    {
        return app()->getLocale() !== config('app.fallback_locale') && Lang::has($key);
    }
}
