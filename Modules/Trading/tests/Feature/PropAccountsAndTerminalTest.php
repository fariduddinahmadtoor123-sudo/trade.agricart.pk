<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Trading\Enums\MaxDrawdownBasis;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\PropFundingModel;
use Modules\Trading\Enums\PropPayoutStyle;
use Modules\Trading\Enums\PropRiskCategory;
use Modules\Trading\Enums\PropRiskVerdict;
use Modules\Trading\Enums\PropRuleSource;
use Modules\Trading\Enums\PropStopLossMode;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\CreatePropAccount;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\PropAccountResource;
use Modules\Trading\Filament\Pages\Mt5Terminal;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Services\PropAccountMeters;
use Modules\Trading\Services\PropAccountSession;
use Modules\Trading\Services\PropRiskGuard;
use Tests\TestCase;

class PropAccountsAndTerminalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
    }

    public function test_guests_are_redirected_from_prop_accounts(): void
    {
        $this->get('/admin/trading/prop-accounts')->assertRedirect();
    }

    public function test_authenticated_users_can_open_the_prop_account_desk(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/trading/prop-accounts')
            ->assertOk()
            ->assertDontSee('fi-header-heading', false)
            ->assertDontSee('New prop account', false)
            ->assertSee('No results', false)
            ->assertSee('Moderate', false);

        $propItems = collect(Filament::getPanel('admin')->getClusteredComponents(TradingCluster::class))
            ->filter(fn (string $component): bool => $component === PropAccountResource::class);

        $this->assertCount(1, $propItems);
    }

    public function test_creating_an_account_stores_the_manual_rule_matrix(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreatePropAccount::class)
            ->fillForm([
                'firm_name' => 'FundedHive',
                'account_number' => 'FH-1001',
                'platform' => 'mt5',
                'status' => 'challenge',
                'model_type' => 'one_step',
                'phase' => 2,
                'risk_category' => 'low',
                'initial_balance' => '100000',
                'rule' => [
                    'profit_target_percent' => '99',
                    'daily_drawdown_percent' => '1',
                    'daily_drawdown_type' => 'eod',
                    'max_total_drawdown_percent' => '4',
                    'max_loss_per_trade_percent' => '1',
                    'minimum_trading_days' => 9,
                    'leverage' => '1:50',
                    'stop_loss_mode' => 'optional',
                    'stop_loss_notes' => 'Stop before entry.',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $account = PropAccount::query()->where('account_number', 'FH-1001')->first();

        $this->assertNotNull($account);
        $this->assertSame('FundedHive', $account->firm_name);
        $this->assertNotNull($account->rule);
        $this->assertSame(PropFundingModel::OneStep, $account->model_type);
        $this->assertSame(1, $account->activePhase());
        $this->assertSame(PropRuleSource::Manual, $account->rule->source);
        $this->assertSame('10.00', $account->rule->profit_target_percent);
        $this->assertNull($account->rule->phase_two_profit_target_percent);
        $this->assertSame('5.00', $account->rule->daily_drawdown_percent);
        $this->assertSame('10.00', $account->rule->max_total_drawdown_percent);
        $this->assertSame(MaxDrawdownBasis::Balanced, $account->rule->max_drawdown_basis);
        $this->assertSame('3.00', $account->rule->max_loss_per_trade_percent);
        $this->assertSame(3, $account->rule->minimum_trading_days);
        $this->assertSame('1:200', $account->rule->leverage);
        $this->assertSame(PropStopLossMode::RequiredOnOpenAndClose, $account->rule->stop_loss_mode);
        $this->assertSame(PropPayoutStyle::ProfitSplit, $account->rule->payout_style);
        $this->assertSame('100000.00', $account->initial_balance);
    }

    public function test_duplicate_firm_account_number_is_rejected(): void
    {
        $user = User::factory()->create();
        PropAccount::factory()->create([
            'firm_name' => 'FundedHive',
            'account_number' => 'FH-1001',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePropAccount::class)
            ->fillForm([
                'firm_name' => 'FundedHive',
                'account_number' => 'FH-1001',
                'platform' => 'mt5',
                'status' => 'challenge',
                'model_type' => 'one_step',
                'phase' => 1,
                'risk_category' => 'low',
                'initial_balance' => '50000',
                'rule' => [
                    'profit_target_percent' => '10',
                    'daily_drawdown_percent' => '4',
                    'daily_drawdown_type' => 'balance',
                    'max_total_drawdown_percent' => '8',
                    'max_loss_per_trade_percent' => '0.5',
                    'minimum_trading_days' => 3,
                    'leverage' => '1:200',
                    'stop_loss_mode' => 'optional',
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['account_number']);

        $this->assertSame(1, PropAccount::query()->where('account_number', 'FH-1001')->count());
    }

    public function test_terminal_session_shows_the_selected_account_rules(): void
    {
        $user = User::factory()->create();
        $account = PropAccount::factory()->withRule()->create([
            'firm_name' => 'FundedHive',
            'account_number' => 'FH-88',
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->assertSee('Open a prop account session', false)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->assertSee('FundedHive', false)
            ->assertSee('FH-88', false)
            ->assertSee('prop-desk__meters', false)
            ->assertSee('8.00%', false)
            ->assertSee('5.00%', false)
            ->assertSee('1:100', false)
            ->assertSee('EOD', false)
            ->call('logout')
            ->assertDontSee('prop-desk__meters', false);

        $this->assertNull(app(PropAccountSession::class)->id());
    }

    public function test_terminal_login_ignores_an_unknown_account(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', '999999')
            ->call('login')
            ->assertDontSee('prop-desk__meters', false);

        $this->assertNull(session(PropAccountSession::KEY));
    }

    public function test_terminal_blocks_a_risk_override_outside_the_safe_band(): void
    {
        $user = User::factory()->create();
        $account = PropAccount::factory()->withRule()->create();

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->set('riskPercent', '2')
            ->set('stopPlaced', true)
            ->call('checkRisk')
            ->assertSee('Per-trade risk must stay between 0.50% and 1.00%.', false)
            ->set('riskPercent', '0.75')
            ->call('checkRisk')
            ->assertSee('Within the account rules.', false);

        $this->assertSame(0.75, app(PropAccountSession::class)->riskPercent());
    }

    public function test_risk_guard_blocks_an_oversized_trade_and_alerts_near_the_daily_limit(): void
    {
        $account = PropAccount::factory()->withRule()->create([
            'initial_balance' => 100000,
            'current_balance' => 100000,
            'day_start_balance' => 100000,
            'today_pnl' => -3500,
            'snapshot_at' => now(),
        ]);

        $guard = app(PropRiskGuard::class);
        $blocked = $guard->assess($account, 3, true);

        $this->assertSame(PropRiskVerdict::Block, $blocked->verdict);

        $alert = $guard->assess($account, 0.6, true);

        $this->assertSame(PropRiskVerdict::Alert, $alert->verdict);
    }

    public function test_two_step_phase_two_uses_the_six_percent_target_and_static_loss(): void
    {
        $account = PropAccount::factory()->withRule()->create([
            'model_type' => PropFundingModel::TwoStep,
            'phase' => 2,
            'status' => PropAccountStatus::Challenge,
            'initial_balance' => 100000,
            'equity' => 104000,
            'snapshot_at' => now(),
        ]);

        $this->assertSame('8.00', $account->rule->profit_target_percent);
        $this->assertSame('6.00', $account->rule->phase_two_profit_target_percent);
        $this->assertSame(MaxDrawdownBasis::Static, $account->rule->max_drawdown_basis);
        $this->assertSame(6.0, $account->activeProfitTarget());
    }

    public function test_challenge_loss_cap_warns_near_three_percent_and_funded_low_risk_requires_a_stop(): void
    {
        $challenge = PropAccount::factory()->withRule()->create([
            'model_type' => PropFundingModel::OneStep,
            'status' => PropAccountStatus::Challenge,
            'risk_category' => 'low',
            'initial_balance' => 100000,
        ]);

        $guard = app(PropRiskGuard::class);

        $this->assertSame(PropRiskVerdict::Alert, $guard->assess($challenge, 2.5, false)->verdict);
        $this->assertSame(PropRiskVerdict::Block, $guard->assess($challenge, 3.2, true)->verdict);

        $funded = PropAccount::factory()->withRule()->create([
            'status' => PropAccountStatus::Funded,
            'risk_category' => 'low',
            'initial_balance' => 100000,
        ]);

        $decision = $guard->assess($funded, 0.5, false);

        $this->assertSame(PropRiskVerdict::Block, $decision->verdict);
        $this->assertSame(1.0, $funded->activeLossCap());
        $this->assertSame('1:30', PropRiskCategory::Medium->leverage());
        $this->assertSame(2.5, PropRiskCategory::Medium->maxRiskPercent());
        $this->assertSame('1:20/1:30', PropRiskCategory::High->leverage());
        $this->assertSame(3.0, PropRiskCategory::High->maxRiskPercent());
        $this->assertSame(PropPayoutStyle::FeeUpfront, PropRiskCategory::High->payout());
    }

    public function test_daily_drawdown_meter_warns_when_the_eod_limit_is_breached(): void
    {
        $account = PropAccount::factory()->withRule()->create([
            'initial_balance' => 100000,
            'day_start_balance' => 100000,
            'today_pnl' => -5000,
            'snapshot_at' => now(),
        ]);

        $meters = app(PropAccountMeters::class)->forAccount($account, 0.5);
        $daily = collect($meters)->firstWhere('key', 'today_pnl');

        $this->assertSame('danger', $daily['tone']);
        $this->assertSame('Breach warning', $daily['breach']);
    }
}
