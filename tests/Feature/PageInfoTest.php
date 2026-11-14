<?php

namespace Tests\Feature;

use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages\CreatePageInfo;
use App\Models\PageInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PageInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
    }

    public function test_guests_cannot_open_page_info_management_or_the_standalone_view(): void
    {
        $this->get('/admin/settings/page-infos')->assertRedirect('/admin/login');
        $this->get('/page-info/signals')->assertRedirect('/admin/login');
    }

    public function test_regular_users_can_read_the_trading_guide_but_cannot_edit_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/trading/signals')
            ->assertOk()
            ->assertSee('Page information', false)
            ->assertSee('User Guide', false)
            ->assertSee('AI Master Prompt', false)
            ->assertSee('Buying Area / Oversold under 30', false);

        $this->actingAs($user)
            ->get('/admin/settings/page-infos')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/page-info/signals')
            ->assertOk()
            ->assertSee('AI Master Prompt', false)
            ->assertSee('AgriCart trading desk assistant', false);
    }

    public function test_authorized_admins_can_manage_page_info_from_settings(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::findOrCreate('ViewAny:PageInfo'),
            Permission::findOrCreate('Create:PageInfo'),
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/page-infos')
            ->assertOk()
            ->assertSee('Page Info', false)
            ->assertSee('signals', false);

        Livewire::actingAs($user)
            ->test(CreatePageInfo::class)
            ->fillForm([
                'page_slug' => 'open-trades',
                'title' => 'Open Trades',
                'user_guide_content' => 'Review the open positions.',
                'ai_prompt_content' => 'Summarize open risk only.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(PageInfo::class, [
            'page_slug' => 'open-trades',
            'title' => 'Open Trades',
        ]);
    }
}
