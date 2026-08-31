<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Database\Seeders\BankingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'bank-accounts',
        'masscollect-domains',
        'virtual-accounts',
        'statement-entries',
        'journal-entries',
        'journal-lines',
        'reconciliations',
        'discrepancies',
        'tenants',
        'bnp-operations',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    public function test_operator_can_open_every_panel_page(): void
    {
        $this->actingAs(User::factory()->create(['is_operator' => true]));

        foreach (self::PAGES as $page) {
            $this->get('/admin/'.$page)->assertSuccessful();
        }
    }

    public function test_account_without_operator_flag_is_refused(): void
    {
        // Panel pokazuje pełną księgę i pozwala zatwierdzać rozjazdy -
        // samo posiadanie konta nie może wystarczyć.
        $this->actingAs(User::factory()->create(['is_operator' => false]));

        $this->get('/admin/journal-entries')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/bank-accounts')->assertRedirect('/admin/login');
    }
}
