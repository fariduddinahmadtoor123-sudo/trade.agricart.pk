<?php

namespace Modules\Trading\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MyfxbookOutlookEmbedController extends Controller
{
    private const WIDGET_URL = 'https://widgets.mfbcdn.net/widgets/outlook.html';

    private const ASSET_BASE = 'https://widgets.mfbcdn.net/';

    /**
     * Myfxbook Community Outlook IDs used by the Signals desk.
     *
     * @var list<int>
     */
    private const ALLOWED_SYMBOL_IDS = [1, 2, 3, 50, 51];

    public function __invoke(int $symbolId): Response
    {
        abort_unless(in_array($symbolId, self::ALLOWED_SYMBOL_IDS, true), 404);

        $html = Cache::remember(
            'trading:myfxbook-outlook-embed:v2:'.$symbolId,
            now()->addMinutes(5),
            fn (): string => $this->fetchPaddedWidget($symbolId),
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, max-age=120',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function fetchPaddedWidget(int $symbolId): string
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; AgriCartTradingDesk/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get(self::WIDGET_URL, [
                    'type' => 1,
                    'symbols' => $symbolId,
                ]);
        } catch (Throwable) {
            abort(502, 'Sentiment embed unavailable.');
        }

        abort_unless($response->successful(), 502, 'Sentiment embed unavailable.');

        return $this->padWidgetHtml($response->body());
    }

    private function padWidgetHtml(string $html): string
    {
        $html = preg_replace(
            '/type="[a-f0-9]+-text\/javascript"/i',
            'type="text/javascript"',
            $html,
        ) ?? $html;

        $inject = '<base href="'.e(self::ASSET_BASE).'">'
            .'<style id="agricart-sentiment-room">'
            .'html,body{margin:0!important;padding:0!important;background:#fff!important;overflow:hidden!important;}'
            .'body{box-sizing:border-box!important;}'
            .'.boxTitle,.boxTitle *{display:none!important;}'
            .'.box_cont,#outlookCont{margin:0!important;padding:0!important;}'
            .'#outlookSymbolsTable{margin:0!important;}'
            .'.tpd-tooltip,.tpd-content-wrapper{z-index:99999!important;}'
            .'</style>';

        if (preg_match('/<head([^>]*)>/i', $html) === 1) {
            return preg_replace('/<head([^>]*)>/i', '<head$1>'.$inject, $html, 1) ?? $html;
        }

        return $inject.$html;
    }
}
