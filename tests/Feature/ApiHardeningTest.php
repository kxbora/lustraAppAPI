<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_like_unauthenticated_api_request_returns_401(): void
    {
        $response = $this->get('/api/favorites/user/1');

        $response->assertStatus(401);
    }

    public function test_get_login_route_returns_helpful_message(): void
    {
        $response = $this->getJson('/api/login');

        $response->assertOk();
        $response->assertJsonPath('message', 'Use POST /api/login with email and password.');
    }

    public function test_guest_cannot_create_order(): void
    {
        $category = Category::create(['name' => 'Skincare']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cleanser',
            'price' => 10,
            'stock' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_cannot_create_order_for_another_user(): void
    {
        $category = Category::create(['name' => 'Skincare']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cleanser',
            'price' => 10,
            'stock' => 10,
        ]);

        $user = User::create([
            'name' => 'User A',
            'email' => 'a@example.com',
            'password' => bcrypt('password123'),
        ]);

        $otherUser = User::create([
            'name' => 'User B',
            'email' => 'b@example.com',
            'password' => bcrypt('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'user_id' => $otherUser->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_order_total_is_computed_and_stock_is_decremented(): void
    {
        $category = Category::create(['name' => 'Skincare']);
        $productOne = Product::create([
            'category_id' => $category->id,
            'name' => 'Cleanser',
            'price' => 12.5,
            'stock' => 10,
        ]);
        $productTwo = Product::create([
            'category_id' => $category->id,
            'name' => 'Serum',
            'price' => 20,
            'stock' => 6,
        ]);

        $user = User::create([
            'name' => 'User A',
            'email' => 'order@example.com',
            'password' => bcrypt('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'total' => 1,
            'items' => [
                [
                    'product_id' => $productOne->id,
                    'quantity' => 2,
                    'price' => 1,
                ],
                [
                    'product_id' => $productTwo->id,
                    'quantity' => 1,
                    'price' => 1,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('order.total', '45.00');

        $productOne->refresh();
        $productTwo->refresh();

        $this->assertSame(8, $productOne->stock);
        $this->assertSame(5, $productTwo->stock);
    }
}
