<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_system_notification(): void
    {
        User::create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'tester@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('notifications', [
            'title' => 'Log in Successful',
            'type' => 'system',
            'is_read' => false,
        ]);
    }

    public function test_user_can_get_own_notifications(): void
    {
        $user = User::create([
            'name' => 'User A',
            'email' => 'notify@example.com',
            'password' => bcrypt('password123'),
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Payment Successful',
            'message' => 'Payment done.',
            'type' => 'payment',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/notifications/user/{$user->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'title' => 'Payment Successful',
            'type' => 'payment',
        ]);
    }

    public function test_order_creation_creates_order_notification(): void
    {
        $category = Category::create(['name' => 'Skincare']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cleanser',
            'price' => 10,
            'stock' => 10,
        ]);

        $user = User::create([
            'name' => 'Order User',
            'email' => 'order-notify@example.com',
            'password' => bcrypt('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Order Placed',
            'type' => 'order',
            'is_read' => false,
        ]);
    }
}
