<?php

namespace Modules\Trading\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Policies\PropAccountPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TradingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Trading';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'trading';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(PropAccount::class, PropAccountPolicy::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
