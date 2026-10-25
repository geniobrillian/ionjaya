<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('UD ION JAYA MOTOR');
        $response->assertSee('Sistem Persediaan Sparepart Motor');
    }

    public function test_super_admin_can_login_and_redirect_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        // Akses halaman dashboard
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Super Admin');
    }

    public function test_admin_kasir_can_login_and_redirect_to_sales(): void
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/penjualan');
        $this->assertAuthenticated();

        // Akses halaman penjualan
        $salesResponse = $this->get('/penjualan');
        $salesResponse->assertStatus(200);
        $salesResponse->assertSee('Admin Kasir');
    }

    public function test_admin_kasir_cannot_access_super_admin_routes(): void
    {
        $admin = User::where('role', 'admin')->first();

        // Admin kasir mencoba mengakses halaman khusus Super Admin
        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(403);

        $responseAnalysis = $this->actingAs($admin)->get('/analisis-fsn-rop');
        $responseAnalysis->assertStatus(403);

        $responseItems = $this->actingAs($admin)->get('/barang');
        $responseItems->assertStatus(403);

        $responseSuppliers = $this->actingAs($admin)->get('/pemasok');
        $responseSuppliers->assertStatus(403);
    }

    public function test_super_admin_can_access_super_admin_routes(): void
    {
        $superAdmin = User::where('role', 'super_admin')->first();

        $response = $this->actingAs($superAdmin)->get('/dashboard');
        $response->assertStatus(200);

        $responseAnalysis = $this->actingAs($superAdmin)->get('/analisis-fsn-rop');
        $responseAnalysis->assertStatus(200);
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'password_salah',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_redirects_to_login(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
