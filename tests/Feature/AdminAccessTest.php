<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_panel()
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_non_admin_cannot_access_admin_panel()
    {
        Config::set('app.admin_emails', 'admin@example.com');
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_authenticated_non_admin_cannot_access_protected_admin_resource()
    {
        Config::set('app.admin_emails', 'admin@example.com');
        $user = User::factory()->create(['email' => 'user@example.com']);

        // Test a representative protected admin action route
        $response = $this->actingAs($user)->get('/admin/site-settings');
        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_access_admin_panel()
    {
        Config::set('app.admin_emails', 'admin@example.com');
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    public function test_empty_or_missing_admin_allowlist_fails_closed()
    {
        Config::set('app.admin_emails', '');
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_allowlist_normalization()
    {
        Config::set('app.admin_emails', ' Admin@example.com , other@example.com  ');
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    public function test_admin_creation_command_fails_if_email_exists()
    {
        User::factory()->create(['email' => 'test@example.com']);
        
        $this->artisan('app:create-admin', ['email' => 'test@example.com'])
            ->expectsOutput('A user with this email already exists!')
            ->assertExitCode(1);
    }

    public function test_admin_creation_command_creates_user()
    {
        $this->artisan('app:create-admin', ['email' => 'admin@new.com'])
            ->expectsQuestion('Name', 'Admin User')
            ->expectsQuestion('Password', 'password123')
            ->expectsQuestion('Confirm Password', 'password123')
            ->expectsOutput("Admin user 'Admin User' created successfully!")
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@new.com',
            'name' => 'Admin User',
        ]);
        
        $user = User::where('email', 'admin@new.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }
}
