<?php

namespace App\Livewire;

use App\Models\PageInfo;
use App\Support\LocalizedPageInfo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PageInfoWindow extends Component
{
    #[Locked]
    public string $title = '';

    #[Locked]
    public ?string $userGuide = null;

    #[Locked]
    public ?string $aiPrompt = null;

    #[Locked]
    public ?string $standaloneUrl = null;

    public function mount(): void
    {
        $slug = $this->currentSlug();
        $pageInfo = $slug === null ? null : PageInfo::query()->where('page_slug', $slug)->first();
        $text = app(LocalizedPageInfo::class);

        $this->title = $text->title($pageInfo, $slug);
        $this->userGuide = $text->guide($pageInfo, $slug);
        $this->aiPrompt = $text->prompt($pageInfo, $slug);
        $this->standaloneUrl = $pageInfo === null ? null : route('page-info.show', $pageInfo);
    }

    public function render(): View
    {
        return view('livewire.page-info-window');
    }

    private function currentSlug(): ?string
    {
        $path = trim(request()->path(), '/');

        if (! str_starts_with($path, 'admin/trading/')) {
            return null;
        }

        $slug = basename($path);

        return $slug !== '' && $slug !== 'trading' ? $slug : null;
    }
}
