<?php

namespace Tests\Feature;

use App\Models\User;
use App\Filament\Pages\Auth\EditProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_side_protection_against_profile_email_changes()
    {
        // 1. Arrange: Create an admin user.
        Config::set('app.admin_emails', 'admin@example.com');
        $user = User::factory()->create([
            'name' => 'Original Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 2. Act: Attempt to manipulate the email through the Livewire component.
        // Even if a malicious user alters the frontend payload to include a different email.
        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => 'Hacked Name',
                'email' => 'hacker@example.com',
                'password' => '',
                'passwordConfirmation' => '',
            ])
            ->call('save');

        // 3. Assert: The name changed, but the email remains unaffected.
        $user->refresh();
        
        $this->assertEquals('Hacked Name', $user->name);
        $this->assertEquals('admin@example.com', $user->email);
        
        // Ensure unauthorized users cannot reach the protected action
        $nonAdmin = User::factory()->create(['email' => 'hacker@example.com']);
        $response = $this->actingAs($nonAdmin)->get('/admin/profile');
        $response->assertStatus(403);
    }
}
