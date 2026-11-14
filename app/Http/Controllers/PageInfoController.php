<?php

namespace App\Http\Controllers;

use App\Models\PageInfo;
use App\Support\LocalizedPageInfo;
use Illuminate\Contracts\View\View;

class PageInfoController extends Controller
{
    public function show(PageInfo $pageInfo): View
    {
        $text = app(LocalizedPageInfo::class);

        return view('page-info.show', [
            'pageInfo' => $pageInfo,
            'title' => $text->title($pageInfo, $pageInfo->page_slug),
            'userGuide' => $text->guide($pageInfo, $pageInfo->page_slug),
            'aiPrompt' => $text->prompt($pageInfo, $pageInfo->page_slug),
        ]);
    }
}
