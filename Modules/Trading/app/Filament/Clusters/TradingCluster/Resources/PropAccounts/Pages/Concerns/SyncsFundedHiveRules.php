<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns;

use Modules\Trading\Enums\PropFundingModel;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Services\PropRulePreset;

trait SyncsFundedHiveRules
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeFundingPhase($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeFundingPhase($data);
    }

    protected function afterCreate(): void
    {
        $this->syncFundedHiveRules();
    }

    protected function afterSave(): void
    {
        $this->syncFundedHiveRules();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFundingPhase(array $data): array
    {
        $model = $data['model_type'] ?? null;
        $model = $model instanceof PropFundingModel
            ? $model
            : PropFundingModel::tryFrom((string) $model);
        $phase = (int) ($data['phase'] ?? 1);

        $data['phase'] = $model === PropFundingModel::TwoStep && $phase === 2 ? 2 : 1;

        return $data;
    }

    private function syncFundedHiveRules(): void
    {
        $record = $this->getRecord();

        if ($record instanceof PropAccount) {
            app(PropRulePreset::class)->sync($record);
        }
    }
}
