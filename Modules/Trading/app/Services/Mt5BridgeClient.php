<?php

namespace Modules\Trading\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Trading\Models\TerminalOrder;

class Mt5BridgeClient
{
    /**
     * Settings already restored during this object, so a request logs once.
     *
     * @var array<string, true>
     */
    private array $restored = [];

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->token() !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function health(): ?array
    {
        $base = $this->baseUrl();

        if ($base === '') {
            return null;
        }

        try {
            $request = Http::acceptJson()->connectTimeout(2)->timeout(max(1, (int) config('trading.mt5.timeout')));
            $token = $this->token();

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($base.'/health');
        } catch (ConnectionException) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotes(): array
    {
        $base = $this->baseUrl();

        if ($base === '') {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(0.3)
                ->timeout(0.5)
                ->get($base.'/quotes');
        } catch (ConnectionException) {
            return [];
        }

        $body = $response->json();
        $rows = is_array($body) ? ($body['quotes'] ?? null) : null;

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, is_array(...)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function signIn(string $login, string $password, string $server): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken($this->token())
                ->acceptJson()
                ->asJson()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('trading.mt5.timeout')))
                ->post($this->url('/session'), [
                    'login' => $login,
                    'password' => $password,
                    'server' => $server,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : ['ok' => false];
    }

    public function execute(TerminalOrder $order, ?float $riskPercent = null): Mt5Execution
    {
        $number = $order->account?->account_number ?? $order->liveAccount?->login;

        if ($number === null) {
            return Mt5Execution::rejected(__('trading.terminal.missing_order'));
        }

        $payload = [
            'client_order_id' => $order->id,
            'account_number' => $number,
            'symbol' => $order->symbol,
            'side' => $order->side->value,
            'lots' => (float) $order->lots,
            'stop_loss' => $this->level($order->stop_loss),
            'take_profit' => $this->level($order->take_profit),
        ];

        if ($riskPercent !== null && $riskPercent > 0) {
            $payload['risk_percent'] = $riskPercent;
        }

        return $this->send('/orders', $payload, true);
    }

    public function close(TerminalOrder $order): Mt5Execution
    {
        if ($order->mt5_ticket === null) {
            return Mt5Execution::rejected(__('trading.terminal.bridge_no_ticket'));
        }

        return $this->send('/orders/close', [
            'client_order_id' => $order->id,
            'ticket' => $order->mt5_ticket,
            'symbol' => $order->symbol,
            'side' => $order->side->value,
            'lots' => (float) $order->lots,
        ], false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload, bool $opening): Mt5Execution
    {
        if (! $this->isConfigured()) {
            return Mt5Execution::unreachable(__('trading.terminal.bridge_not_configured'));
        }

        try {
            $response = Http::withToken($this->token())
                ->acceptJson()
                ->asJson()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('trading.mt5.timeout')))
                ->post($this->url($path), $payload);
        } catch (ConnectionException $exception) {
            Log::warning('MT5 bridge could not be reached.', [
                'path' => $path,
                'client_order_id' => $payload['client_order_id'] ?? null,
            ]);

            return Mt5Execution::unreachable($exception->getMessage());
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $message = is_string($body['message'] ?? null) ? $body['message'] : '';

        if ($response->status() === 401 || $response->status() === 403) {
            return Mt5Execution::rejected(__('trading.terminal.bridge_token_rejected'));
        }

        if ($response->serverError() || $response->status() === 503) {
            return Mt5Execution::unreachable($message !== '' ? $message : 'MT5 terminal is not connected.');
        }

        if (! $response->successful() || ($body['ok'] ?? false) !== true) {
            return Mt5Execution::rejected($message !== '' ? $message : 'MT5 rejected the order.');
        }

        $price = is_numeric($body['price'] ?? null) ? (float) $body['price'] : null;
        $profit = is_numeric($body['profit'] ?? null) ? (float) $body['profit'] : null;

        if ($price === null) {
            return Mt5Execution::rejected('MT5 did not return a fill price.');
        }

        if (! $opening) {
            return Mt5Execution::closed($price, $profit ?? 0.0);
        }

        $ticket = is_numeric($body['ticket'] ?? null) ? (int) $body['ticket'] : 0;

        if ($ticket < 1) {
            return Mt5Execution::rejected('MT5 did not return a position ticket.');
        }

        $stop = is_numeric($body['stop_loss'] ?? null) ? (float) $body['stop_loss'] : null;
        $take = is_numeric($body['take_profit'] ?? null) ? (float) $body['take_profit'] : null;

        return Mt5Execution::filled($ticket, $price, $profit, $stop, $take);
    }

    private function url(string $path): string
    {
        return $this->baseUrl().$path;
    }

    private function baseUrl(): string
    {
        $url = $this->setting('trading.mt5.bridge_url', 'TRADING_MT5_BRIDGE_URL');

        if ($url === '' && ! app()->runningUnitTests()) {
            $url = 'http://127.0.0.1:8765';
            config(['trading.mt5.bridge_url' => $url]);
        }

        return rtrim($url, '/');
    }

    private function token(): string
    {
        return $this->setting('trading.mt5.bridge_token', 'TRADING_MT5_BRIDGE_TOKEN');
    }

    /**
     * Config is snapshotted when the process boots. A live order must still
     * see the token when that snapshot is empty, without reading .env during
     * PHPUnit (that would reach the real bridge).
     */
    private function setting(string $configKey, string $envKey): string
    {
        $configured = config($configKey);
        $value = is_scalar($configured) ? trim((string) $configured) : '';

        if ($value !== '' || app()->runningUnitTests()) {
            return $value;
        }

        foreach ([$_ENV[$envKey] ?? null, $_SERVER[$envKey] ?? null, getenv($envKey)] as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = $this->unwrap($candidate);

            if ($candidate === '') {
                continue;
            }

            config([$configKey => $candidate]);
            $this->logRestore($envKey);

            return $candidate;
        }

        $fromFile = $this->envFile($envKey);

        if ($fromFile !== '') {
            config([$configKey => $fromFile]);
            $this->logRestore($envKey);
        }

        return $fromFile;
    }

    private function envFile(string $key): string
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return '';
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return '';
        }

        $prefix = $key.'=';

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            }

            $line = trim($line);

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if ($line === '' || str_starts_with($line, '#') || ! str_starts_with($line, $prefix)) {
                continue;
            }

            return $this->unwrap(substr($line, strlen($prefix)));
        }

        return '';
    }

    private function unwrap(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2) {
            $quote = $value[0];
            $last = $value[strlen($value) - 1];

            if (($quote === '"' || $quote === "'") && $last === $quote) {
                $value = substr($value, 1, -1);
            }
        }

        return trim($value);
    }

    private function logRestore(string $envKey): void
    {
        if (isset($this->restored[$envKey])) {
            return;
        }

        $this->restored[$envKey] = true;

        Log::warning('MT5 bridge config was empty and was restored for this request.', [
            'setting' => $envKey,
        ]);
    }

    private function level(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
