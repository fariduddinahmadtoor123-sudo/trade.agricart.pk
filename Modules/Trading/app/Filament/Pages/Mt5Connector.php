<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use JsonException;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Mt5Connector extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'mt5-link';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string $translationKey = 'mt5_link';

    protected static ?string $navigationLabel = 'Link';

    protected static ?string $title = 'MT5 Link';

    protected static ?int $navigationSort = 8;

    protected string $view = 'trading::filament.pages.mt5-link';

    public string $connectorScript = '';

    public string $launcherScript = '';

    public bool $connectorReady = false;

    public function mount(): void
    {
        $this->connectorScript = $this->buildConnectorScript();
        $this->launcherScript = $this->buildLauncherScript();
        $this->connectorReady = $this->connectorScript !== ''
            && $this->launcherScript !== ''
            && $this->token('trading.mt5.bridge_token') !== ''
            && $this->token('trading.mt5.laravel_token') !== '';
    }

    public function downloadConnector(): StreamedResponse
    {
        abort_if($this->connectorScript === '', 404);

        return $this->downloadText('connect-mt5.py', $this->connectorScript);
    }

    public function downloadLauncher(): StreamedResponse
    {
        abort_if($this->launcherScript === '', 404);

        return $this->downloadText('start-mt5.bat', $this->launcherScript);
    }

    public function buildConnectorScript(): string
    {
        $path = base_path('Modules/Trading/bridge/mt5_bridge.py');

        if (! File::isFile($path)) {
            return '';
        }

        $source = File::get($path);
        $source = preg_replace('/\A"""[\s\S]*?"""\R+/', '', $source, 1) ?? $source;
        $source = preg_replace('/\Afrom __future__ import annotations\R+/', '', $source, 1) ?? $source;

        return $this->scriptHeader()."\n".ltrim($source);
    }

    public function buildLauncherScript(): string
    {
        $path = base_path('Modules/Trading/bridge/start-mt5.bat');

        if (! File::isFile($path)) {
            return '';
        }

        $source = str_replace(["\r\n", "\r"], "\n", File::get($path));

        return str_replace("\n", "\r\n", trim($source))."\r\n";
    }

    private function downloadText(string $name, string $contents): StreamedResponse
    {
        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $name);
    }

    private function scriptHeader(): string
    {
        return implode("\n", [
            'from __future__ import annotations',
            '',
            'import importlib',
            'import os',
            'import subprocess',
            'import sys',
            '',
            'os.environ["LARAVEL_API_URL"] = '.$this->pythonString($this->siteUrl()),
            'os.environ["LARAVEL_API_TOKEN"] = '.$this->pythonString($this->token('trading.mt5.laravel_token')),
            'os.environ["BRIDGE_TOKEN"] = '.$this->pythonString($this->token('trading.mt5.bridge_token')),
            'os.environ["BRIDGE_HOST"] = "127.0.0.1"',
            'os.environ["BRIDGE_PORT"] = "8765"',
            'os.environ["MT5_LOGIN"] = ""',
            'os.environ["MT5_PASSWORD"] = ""',
            'os.environ["MT5_SERVER"] = ""',
            'os.environ["MT5_TERMINAL_PATH"] = ""',
            '',
            'def _ensure_packages() -> None:',
            '    try:',
            '        importlib.import_module("numpy")',
            '        importlib.import_module("MetaTrader5")',
            '    except ImportError:',
            '        subprocess.check_call([',
            '            sys.executable,',
            '            "-m",',
            '            "pip",',
            '            "install",',
            '            "--disable-pip-version-check",',
            '            "numpy==2.2.6",',
            '            "MetaTrader5",',
            '        ])',
            '        os.execv(sys.executable, [sys.executable, *sys.argv])',
            '',
            '_ensure_packages()',
        ]);
    }

    private function siteUrl(): string
    {
        $configured = rtrim((string) config('app.url'), '/');

        if (app()->environment('production') && preg_match('#^https?://#', $configured) === 1) {
            return $configured;
        }

        return rtrim(request()->getSchemeAndHttpHost(), '/');
    }

    private function token(string $key): string
    {
        $value = config($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @throws JsonException
     */
    private function pythonString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
