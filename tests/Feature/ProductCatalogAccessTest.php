<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategoryProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_is_public_and_global_for_all_accounts(): void
    {
        $this->seed(CategoryProductSeeder::class);

        $guestResponse = $this->getJson('/api/products?per_page=100');
        $guestResponse->assertOk();

        $guestProducts = $guestResponse->json('data');
        $this->assertIsArray($guestProducts);
        $this->assertNotEmpty($guestProducts);

        $userOne = User::create([
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'password' => bcrypt('password123'),
        ]);

        $userTwo = User::create([
            'name' => 'Buyer Two',
            'email' => 'buyer2@example.com',
            'password' => bcrypt('password123'),
        ]);

        Sanctum::actingAs($userOne);
        $userOneResponse = $this->getJson('/api/products?per_page=100');
        $userOneResponse->assertOk();

        Sanctum::actingAs($userTwo);
        $userTwoResponse = $this->getJson('/api/products?per_page=100');
        $userTwoResponse->assertOk();

        $guestIds = collect($guestProducts)->pluck('id')->values()->all();
        $userOneIds = collect($userOneResponse->json('data'))->pluck('id')->values()->all();
        $userTwoIds = collect($userTwoResponse->json('data'))->pluck('id')->values()->all();

        $this->assertSame($guestIds, $userOneIds);
        $this->assertSame($guestIds, $userTwoIds);
    }
}
