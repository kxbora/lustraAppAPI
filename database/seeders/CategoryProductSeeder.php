<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = [
            'Cleanser' => [
                'Gentle Foam Cleanser',
                'Hydrating Gel Cleanser',
                'Cream Cleanser',
                'Micellar Cleansing Water',
                'Deep Pore Cleanser',
                'Brightening Facial Wash',
                'Oil Control Cleanser',
            ],
            'Toner' => [
                'Hydrating Toner',
                'Rose Water Toner',
                'Brightening Toner',
                'Pore Minimizing Toner',
                'Soothing Toner',
                'Acne Control Toner',
                'Refreshing Mist Toner',
            ],
            'Serum' => [
                'Vitamin C Serum',
                'Niacinamide Serum',
                'Retinol Serum',
                'Hyaluronic Acid Serum',
                'Anti-Aging Repair Serum',
                'Whitening Booster Serum',
                'Acne Repair Serum',
                'Collagen Boost Serum',
            ],
            'Moisturizer' => [
                'Daily Hydration Cream',
                'Oil Control Gel',
                'Night Repair Cream',
                'Aloe Moisturizing Cream',
                'Brightening Day Cream',
                'Collagen Firming Cream',
                'Sensitive Skin Cream',
            ],
            'Sunscreen' => [
                'SPF 30 Sun Cream',
                'SPF 50+ UV Protection',
                'Matte Finish Sunscreen',
                'Waterproof Sunblock',
                'Sensitive Skin Sunscreen',
                'Tone-Up Sunscreen',
                'Gel Sunscreen',
            ],
            'Face Mask' => [
                'Clay Purifying Mask',
                'Charcoal Detox Mask',
                'Hydrating Sheet Mask',
                'Brightening Peel-Off Mask',
                'Overnight Sleeping Mask',
                'Aloe Soothing Mask',
                'Gold Repair Mask',
            ],
            'Eye Care' => [
                'Dark Circle Eye Cream',
                'Firming Eye Gel',
                'Anti-Wrinkle Eye Cream',
                'Hydrating Eye Serum',
                'Cooling Eye Roll-On',
            ],
            'Lip Care' => [
                'Moisturizing Lip Balm',
                'Lip Sleeping Mask',
                'Tinted Lip Balm',
                'Lip Scrub',
                'Repair Lip Cream',
            ],
            'Exfoliator / Scrub' => [
                'Gentle Face Scrub',
                'Exfoliating Gel',
                'Sugar Scrub',
                'AHA Exfoliating Toner',
                'BHA Pore Treatment',
            ],
            'Body Care' => [
                'Whitening Body Lotion',
                'Hydrating Body Cream',
                'Body Scrub',
                'Body Sunscreen',
                'Repair Body Serum',
            ],
            'Makeup' => [
                'Liquid Foundation',
                'Matte Lipstick',
                'Cushion Foundation',
                'Compact Powder',
                'Blush Palette',
                'Mascara',
                'Eyeliner',
            ],
            'Hair Care' => [
                'Repair Shampoo',
                'Smooth Conditioner',
                'Hair Growth Serum',
                'Hair Mask Treatment',
                'Anti-Dandruff Shampoo',
            ],
        ];

        $categoryIndex = 0;
        foreach ($catalog as $categoryName => $products) {
            $categorySlug = Str::slug($categoryName);
            $category = Category::query()->updateOrCreate(
                ['name' => $categoryName],
                ['image' => sprintf('https://picsum.photos/seed/lustra-category-%s/1200/800', $categorySlug)]
            );

            foreach ($products as $productIndex => $productName) {
                $price = 8 + (($categoryIndex * 7 + $productIndex * 3) % 35);
                $price = (float) number_format($price + 0.99, 2, '.', '');
                $oldPrice = (float) number_format($price * 1.2, 2, '.', '');
                $productSlug = Str::slug($categoryName . '-' . $productName);

                Product::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $productName,
                    ],
                    [
                        'description' => $productName . ' for daily care and visible skin improvement.',
                        'price' => $price,
                        'old_price' => $oldPrice,
                        'image' => sprintf('https://picsum.photos/seed/lustra-%s/800/800', $productSlug),
                        'stock' => 40 + (($productIndex * 9 + $categoryIndex * 5) % 110),
                        'rating' => (float) number_format(4.0 + (($productIndex % 10) / 10), 1, '.', ''),
                        'reviews_count' => 20 + (($categoryIndex + 1) * 11) + ($productIndex * 7),
                    ]
                );
            }

            $categoryIndex++;
        }
    }
}
