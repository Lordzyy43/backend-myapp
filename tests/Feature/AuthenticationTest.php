<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function user_can_register()
  {
    $userData = [
      'name' => 'John Doe',
      'email' => 'john@example.com',
      'password' => 'password123',
      'password_confirmation' => 'password123'
    ];

    $response = $this->postJson('/api/v1/register', $userData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
  }

  #[Test]
  public function user_cannot_register_with_duplicate_email()
  {
    User::factory()->create(['email' => 'exists@example.com']);

    $userData = [
      'name' => 'John Doe',
      'email' => 'exists@example.com',
      'password' => 'password123',
      'password_confirmation' => 'password123'
    ];

    $response = $this->postJson('/api/v1/register', $userData);

    $response->assertUnprocessable();
  }

  #[Test]
  public function user_can_login()
  {
    $user = User::factory()->create(['email' => 'test@example.com', 'password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/login', [
      'email' => 'test@example.com',
      'password' => 'password123'
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['success', 'message', 'data' => ['token', 'user' => ['id', 'name', 'email']]]);
  }

  #[Test]
  public function user_login_fails_with_wrong_password()
  {
    User::factory()->create(['email' => 'test@example.com', 'password' => bcrypt('password123')]);

    $response = $this->postJson('/api/v1/login', [
      'email' => 'test@example.com',
      'password' => 'wrongpassword'
    ]);

    $response->assertStatus(401);
  }

  #[Test]
  public function user_login_fails_with_non_existent_email()
  {
    $response = $this->postJson('/api/v1/login', [
      'email' => 'nonexistent@example.com',
      'password' => 'password123'
    ]);

    $response->assertStatus(401);
  }

  #[Test]
  public function authenticated_user_can_logout()
  {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer $token")
      ->postJson('/api/v1/logout');

    $response->assertStatus(200);
  }

  #[Test]
  public function unauthenticated_user_cannot_access_protected_route()
  {
    $response = $this->getJson('/api/v1/user/profile');

    $response->assertStatus(401);
  }

  #[Test]
  public function authenticated_user_can_access_profile()
  {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
      ->getJson('/api/v1/user/profile');

    $response->assertStatus(200);
  }

  #[Test]
  public function user_role_assignment_works()
  {
    $userRole = Role::firstOrCreate(['role_name' => 'user']);
    $user = User::factory()->create(['role_id' => $userRole->id]);

    $this->assertEquals($userRole->id, $user->role_id);
  }

  #[Test]
  public function admin_role_can_be_assigned()
  {
    $adminRole = Role::firstOrCreate(['role_name' => 'admin']);
    $user = User::factory()->create(['role_id' => $adminRole->id]);

    $this->assertEquals('admin', $user->role->role_name);
  }
}
