<?php

namespace Modules\Trading\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Http\Requests\ExecuteMt5OrderRequest;
use Modules\Trading\Http\Requests\SyncMt5SessionRequest;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Services\Mt5SessionSync;

class Mt5BridgeController extends Controller
{
    public function sync(SyncMt5SessionRequest $request, Mt5SessionSync $sync): JsonResponse
    {
        $sync->apply($request->validated());

        return response()->json(['data' => ['synced' => true]]);
    }

    public function pending(Request $request): JsonResponse
    {
        $orders = TerminalOrder::query()
            ->with(['account', 'liveAccount'])
            ->where(function ($query): void {
                $query->where('status', TerminalOrderStatus::Pending)
                    ->orWhere(function ($query): void {
                        $query->where('status', TerminalOrderStatus::Sending)
                            ->where('updated_at', '<=', now()->subSeconds(15));
                    });
            })
            ->when(
                is_string($request->query('account_number')) && $request->query('account_number') !== '',
                fn ($query) => $query->where(function ($query) use ($request): void {
                    $number = $request->query('account_number');
                    $query->whereHas(
                        'account',
                        fn ($account) => $account->where('account_number', $number),
                    )->orWhereHas(
                        'liveAccount',
                        fn ($account) => $account->where('login', $number),
                    );
                }),
            )
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(function (TerminalOrder $order): array {
                $row = [
                    'client_order_id' => $order->id,
                    'account_number' => $order->account?->account_number ?? $order->liveAccount?->login,
                    'symbol' => $order->symbol,
                    'side' => $order->side->value,
                    'lots' => (float) $order->lots,
                    'stop_loss' => is_numeric($order->stop_loss) ? (float) $order->stop_loss : null,
                    'take_profit' => is_numeric($order->take_profit) ? (float) $order->take_profit : null,
                ];

                if ($order->prop_account_id === null && $order->mt5_account_id !== null && $row['stop_loss'] === null && $row['take_profit'] === null) {
                    $row['risk_percent'] = 1.0;
                }

                return $row;
            })
            ->all();

        return response()->json(['data' => $orders]);
    }

    public function execution(ExecuteMt5OrderRequest $request, TerminalOrder $order, Mt5SessionSync $sync): JsonResponse
    {
        $order = $sync->applyExecution($order, $request->validated());

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status->value,
                'mt5_ticket' => $order->mt5_ticket,
            ],
        ]);
    }
}
