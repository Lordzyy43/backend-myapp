<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    // Pastikan role dasar ada sebelum test dimulai
    Role::firstOrCreate(['role_name' => 'user']);
    Role::firstOrCreate(['role_name' => 'admin']);
  }

  #[Test]
  public function user_can_register()
  {
    $userData = [
      'name' => 'John Doe',
      'email' => 'john@example.com',
      'password' => 'password123',
    ];

    $response = $this->postJson('/api/v1/auth/register', $userData);

    $response->assertStatus(200);
    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
  }

  #[Test]
  public function user_cannot_register_with_duplicate_email()
  {
    User::factory()->create(['email' => 'exists@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
      'name' => 'John Doe',
      'email' => 'exists@example.com',
      'password' => 'password123'
    ]);

    $response->assertStatus(422); // Unprocessable Entity
    $response->assertJsonValidationErrors(['email']);
  }

  #[Test]
  public function user_can_login()
  {
    // Harus verified agar lolos filter AuthController
    $user = User::factory()->create([
      'email' => 'test@example.com',
      'password' => Hash::make('password123'),
      'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
      'email' => 'test@example.com',
      'password' => 'password123',
      'device_name' => 'my-laptop' // Tambahkan ini!
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['success', 'message', 'data' => ['token', 'user']]);
  }

  #[Test]
  public function user_login_fails_if_not_verified()
  {
    // Password di db harus di-hash dengan benar
    $password = 'password123';
    User::factory()->create([
      'email' => 'unverified@example.com',
      'password' => Hash::make($password), // Hashed
      'email_verified_at' => null
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
      'email' => 'unverified@example.com',
      'password' => $password, // Password asli
      'device_name' => 'device'
    ]);

    $response->assertStatus(403);
  }

  #[Test]
  public function user_login_fails_with_wrong_password()
  {
    User::factory()->create(['email' => 'test@example.com', 'email_verified_at' => now()]);

    $response = $this->postJson('/api/v1/auth/login', [
      'email' => 'test@example.com',
      'password' => 'wrongpassword',
      'device_name' => 'device'
    ]);

    $response->assertStatus(401);
  }

  #[Test]
  public function authenticated_user_can_logout()
  {
    // 1. Buat user
    $user = User::factory()->create();

    // 2. Buat token ASLI (ini wajib agar currentAccessToken() ada isinya)
    $token = $user->createToken('test-device')->plainTextToken;

    // 3. Kirim header Authorization secara manual
    // Jangan gunakan actingAs, karena actingAs tidak menciptakan token di DB
    $response = $this->withHeader('Authorization', "Bearer $token")
      ->postJson('/api/v1/me/logout');

    $response->assertStatus(200);
  }

  #[Test]
  public function authenticated_user_can_access_profile()
  {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
      ->getJson('/api/v1/me');

    $response->assertStatus(200)
      ->assertJsonPath('data.user.email', $user->email);
  }

  #[Test]
  public function user_role_assignment_works()
  {
    $role = Role::where('role_name', 'user')->first();
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->assertEquals($role->id, $user->role_id);
  }
}
