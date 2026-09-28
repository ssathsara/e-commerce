<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Support\Money;
use Database\Seeders\Support\PlaceholderImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic demo catalogue. Products are created through ProductService so opening
 * stock is recorded as inventory movements, exactly like products created in the admin.
 */
class CatalogSeeder extends Seeder
{
    /** name => [hue, featured, children[name => hue]] */
    private const CATEGORIES = [
        'Electronics' => [215, true, ['Phones' => 205, 'Laptops' => 225, 'Audio' => 260, 'Accessories' => 190]],
        'Fashion' => [340, true, ['Men' => 20, 'Women' => 330, 'Shoes' => 15]],
        'Home & Kitchen' => [35, true, ['Kitchen' => 30, 'Furniture' => 25, 'Decor' => 45]],
        'Sports & Outdoors' => [140, true, ['Fitness' => 130, 'Camping' => 110]],
        'Beauty' => [300, false, ['Skincare' => 310, 'Fragrance' => 285]],
        'Books' => [50, true, []],
    ];

    private const BRANDS = [
        'Nimbus' => 'Consumer electronics designed for everyday life.',
        'Vertex' => 'Performance laptops and computing gear.',
        'Sonora' => 'Studio-grade audio for home and travel.',
        'Northwind Apparel' => 'Durable, timeless clothing.',
        'Lumen & Co' => 'Minimal home goods and lighting.',
        'Stride' => 'Footwear engineered for comfort.',
        'Evergreen Outdoors' => 'Gear for trails, peaks and campsites.',
        'Pulse Fitness' => 'Training equipment for every level.',
        'Aurelia' => 'Clean skincare and fragrance.',
        'Hearth' => 'Cookware built to last generations.',
        'Paperleaf Press' => 'Independent publisher of modern non-fiction.',
    ];

    /**
     * [name, category, brand, price, compare_at, stock, short description, variants?, featured?]
     * Variants: [name, sku suffix, options, price|null, stock]
     */
    private function products(): array
    {
        return [
            ['Nimbus X12 Smartphone', 'Phones', 'Nimbus', 799.00, 899.00, null, '6.5" OLED display, triple camera and all-day battery.', [
                ['128GB / Graphite', '128-GR', ['Storage' => '128GB', 'Color' => 'Graphite'], null, 18],
                ['256GB / Graphite', '256-GR', ['Storage' => '256GB', 'Color' => 'Graphite'], 899.00, 12],
                ['256GB / Glacier', '256-GL', ['Storage' => '256GB', 'Color' => 'Glacier'], 899.00, 7],
            ], true],
            ['Nimbus Lite 5G', 'Phones', 'Nimbus', 399.00, null, 40, 'Affordable 5G phone with a 90Hz display.', null, false],
            ['Nimbus Fold Pro', 'Phones', 'Nimbus', 1499.00, 1699.00, 4, 'Foldable 7.6" display that fits in your pocket.', null, true],
            ['Vertex Air 14 Laptop', 'Laptops', 'Vertex', 1099.00, 1249.00, null, 'Ultra-light 14" laptop with 18-hour battery life.', [
                ['16GB RAM / 512GB', '16-512', ['Memory' => '16GB', 'Storage' => '512GB'], null, 10],
                ['32GB RAM / 1TB', '32-1TB', ['Memory' => '32GB', 'Storage' => '1TB'], 1449.00, 5],
            ], true],
            ['Vertex Pro 16 Workstation', 'Laptops', 'Vertex', 2399.00, null, 6, 'Creator laptop with a dedicated GPU and 16" mini-LED screen.', null, false],
            ['Vertex Chromebook 11', 'Laptops', 'Vertex', 279.00, 329.00, 25, 'Rugged, fast-booting laptop for school and travel.', null, false],
            ['Sonora Studio Headphones', 'Audio', 'Sonora', 299.00, 349.00, 30, 'Adaptive noise cancelling with 40-hour battery.', null, true],
            ['Sonora Buds 2', 'Audio', 'Sonora', 129.00, null, 55, 'True wireless earbuds with spatial audio.', null, false],
            ['Sonora Home Speaker', 'Audio', 'Sonora', 199.00, 229.00, 3, 'Room-filling sound with a built-in voice assistant.', null, false],
            ['Sonora Portable Speaker', 'Audio', 'Sonora', 89.00, null, 0, 'Waterproof Bluetooth speaker for the outdoors.', null, false],
            ['Nimbus 65W USB-C Charger', 'Accessories', 'Nimbus', 39.00, 49.00, 120, 'Compact GaN charger for phones and laptops.', null, false],
            ['Vertex Wireless Mouse', 'Accessories', 'Vertex', 49.00, null, 80, 'Ergonomic silent-click mouse with multi-device pairing.', null, false],
            ['Nimbus Protective Case', 'Accessories', 'Nimbus', 29.00, null, null, 'Slim shock-absorbing case for the Nimbus X12.', [
                ['Black', 'BLK', ['Color' => 'Black'], null, 60],
                ['Sage', 'SAG', ['Color' => 'Sage'], null, 35],
                ['Clear', 'CLR', ['Color' => 'Clear'], null, 2],
            ], false],
            ['Classic Oxford Shirt', 'Men', 'Northwind Apparel', 59.00, 75.00, null, 'Breathable cotton oxford with a tailored fit.', [
                ['Small', 'S', ['Size' => 'S'], null, 20],
                ['Medium', 'M', ['Size' => 'M'], null, 25],
                ['Large', 'L', ['Size' => 'L'], null, 18],
                ['X-Large', 'XL', ['Size' => 'XL'], null, 6],
            ], true],
            ['Everyday Chino Pants', 'Men', 'Northwind Apparel', 69.00, null, null, 'Stretch chinos that work from office to weekend.', [
                ['30 / 32', '30-32', ['Waist' => '30', 'Length' => '32'], null, 12],
                ['32 / 32', '32-32', ['Waist' => '32', 'Length' => '32'], null, 15],
                ['34 / 32', '34-32', ['Waist' => '34', 'Length' => '32'], null, 10],
            ], false],
            ['Merino Crew Sweater', 'Men', 'Northwind Apparel', 110.00, 140.00, 22, 'Soft, temperature-regulating merino wool.', null, false],
            ['Linen Wrap Dress', 'Women', 'Northwind Apparel', 98.00, 120.00, null, 'Lightweight linen dress with an adjustable waist.', [
                ['XS', 'XS', ['Size' => 'XS'], null, 8],
                ['S', 'S', ['Size' => 'S'], null, 14],
                ['M', 'M', ['Size' => 'M'], null, 11],
                ['L', 'L', ['Size' => 'L'], null, 5],
            ], true],
            ['Tailored Wool Coat', 'Women', 'Northwind Apparel', 249.00, 299.00, 9, 'Double-breasted coat in Italian wool blend.', null, false],
            ['Everyday Tote Bag', 'Women', 'Northwind Apparel', 79.00, null, 40, 'Structured canvas tote with a laptop sleeve.', null, false],
            ['Stride Cloud Runner', 'Shoes', 'Stride', 139.00, 159.00, null, 'Responsive cushioning for daily miles.', [
                ['US 8', '08', ['Size' => '8'], null, 9],
                ['US 9', '09', ['Size' => '9'], null, 12],
                ['US 10', '10', ['Size' => '10'], null, 10],
                ['US 11', '11', ['Size' => '11'], null, 4],
            ], true],
            ['Stride Leather Sneaker', 'Shoes', 'Stride', 119.00, null, 28, 'Minimal full-grain leather sneaker.', null, false],
            ['Stride Trail Boot', 'Shoes', 'Stride', 179.00, 199.00, 14, 'Waterproof hiking boot with Vibram outsole.', null, false],
            ['Hearth Cast Iron Skillet', 'Kitchen', 'Hearth', 59.00, null, 45, 'Pre-seasoned 12" skillet for searing and baking.', null, true],
            ['Hearth Chef Knife 8"', 'Kitchen', 'Hearth', 89.00, 110.00, 30, 'High-carbon steel blade with a balanced handle.', null, false],
            ['Hearth Dutch Oven 5.5qt', 'Kitchen', 'Hearth', 149.00, 179.00, 12, 'Enamelled cast iron for braises, soups and bread.', null, false],
            ['Pour-Over Coffee Set', 'Kitchen', 'Lumen & Co', 49.00, null, 35, 'Glass dripper, carafe and reusable filter.', null, false],
            ['Oak Lounge Chair', 'Furniture', 'Lumen & Co', 549.00, 649.00, 5, 'Solid oak frame with a woven seat.', null, true],
            ['Floating Wall Shelf', 'Furniture', 'Lumen & Co', 69.00, null, 50, 'Hidden-bracket shelf in natural walnut.', null, false],
            ['Minimal Desk Lamp', 'Decor', 'Lumen & Co', 89.00, 99.00, 26, 'Dimmable LED lamp with a warm-to-cool range.', null, false],
            ['Ceramic Vase Set', 'Decor', 'Lumen & Co', 45.00, null, 33, 'Set of three matte stoneware vases.', null, false],
            ['Linen Throw Blanket', 'Decor', 'Lumen & Co', 79.00, 95.00, 0, 'Stonewashed linen throw, 130x170 cm.', null, false],
            ['Pulse Adjustable Dumbbells', 'Fitness', 'Pulse Fitness', 299.00, 349.00, 11, 'Replace 15 sets of weights, 2.5–24 kg.', null, true],
            ['Pulse Yoga Mat', 'Fitness', 'Pulse Fitness', 49.00, null, 70, 'Non-slip 5 mm natural rubber mat.', null, false],
            ['Pulse Resistance Band Set', 'Fitness', 'Pulse Fitness', 29.00, 39.00, 90, 'Five resistance levels with door anchor.', null, false],
            ['Evergreen 2-Person Tent', 'Camping', 'Evergreen Outdoors', 229.00, 269.00, 8, 'Freestanding 3-season tent under 2 kg.', null, true],
            ['Evergreen Down Sleeping Bag', 'Camping', 'Evergreen Outdoors', 189.00, null, 15, 'Rated to -5°C with water-resistant down.', null, false],
            ['Evergreen Trekking Poles', 'Camping', 'Evergreen Outdoors', 79.00, 89.00, 24, 'Carbon fibre, collapsible, cork grips.', null, false],
            ['Aurelia Daily Moisturiser', 'Skincare', 'Aurelia', 38.00, null, 60, 'Lightweight hydration with SPF 30.', null, false],
            ['Aurelia Vitamin C Serum', 'Skincare', 'Aurelia', 52.00, 64.00, 42, 'Brightening serum with 15% vitamin C.', null, true],
            ['Aurelia Cedar Eau de Parfum', 'Fragrance', 'Aurelia', 95.00, null, 18, 'Warm cedar, bergamot and amber.', null, false],
            ['The Focused Mind', 'Books', 'Paperleaf Press', 24.00, 28.00, 65, 'Practical strategies for deep work in a distracted world.', null, true],
            ['Designing Calm Spaces', 'Books', 'Paperleaf Press', 35.00, null, 40, 'An illustrated guide to minimal interiors.', null, false],
            ['Cooking Over Fire', 'Books', 'Paperleaf Press', 32.00, null, 3, 'Seasonal recipes for grills, skillets and campfires.', null, false],
        ];
    }

    public function run(ProductService $products): void
    {
        $actor = User::role(RoleName::SuperAdmin->value)->firstOrFail();

        $categories = $this->seedCategories();
        $brands = $this->seedBrands();

        foreach ($this->products() as $index => [$name, $categoryName, $brandName, $price, $compareAt, $stock, $short, $variants, $featured]) {
            $category = $categories[$categoryName];

            $product = $products->create([
                'category_id' => $category->id,
                'brand_id' => $brands[$brandName]->id,
                'name' => $name,
                'sku' => strtoupper(Str::substr(Str::slug($categoryName, ''), 0, 3)).'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'short_description' => $short,
                'description' => $this->description($name, $short),
                'price' => Money::toCents($price),
                'compare_at_price' => $compareAt ? Money::toCents($compareAt) : null,
                'stock_quantity' => $stock ?? 0,
                'low_stock_threshold' => 5,
                'status' => $index === 42 ? ProductStatus::Draft : ProductStatus::Active,
                'is_featured' => $featured,
                'weight' => round(random_int(100, 4000) / 1000, 3),
                'seo_title' => Str::limit("{$name} | ShopSphere", 70, ''),
                'seo_description' => Str::limit($short, 160, ''),
                'variants' => array_map(fn (array $v) => [
                    'name' => $v[0],
                    'sku' => strtoupper(Str::substr(Str::slug($categoryName, ''), 0, 3)).'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).'-'.$v[1],
                    'options' => $v[2],
                    'price' => $v[3] ? Money::toCents($v[3]) : null,
                    'compare_at_price' => null,
                    'stock_quantity' => $v[4],
                    'is_active' => true,
                ], $variants ?? []),
            ], $actor);

            $hue = $category->getAttribute('seed_hue');
            foreach (range(0, 1) as $n) {
                $product->images()->create([
                    'path' => PlaceholderImage::create("products/{$product->id}", $name, $hue, variant: $n),
                    'alt_text' => $name,
                    'sort_order' => $n,
                    'is_primary' => $n === 0,
                ]);
            }

            $product->forceFill(['created_at' => now()->subDays(random_int(0, 90))])->saveQuietly();
        }
    }

    /** @return array<string, Category> keyed by name, with a transient seed_hue attribute */
    private function seedCategories(): array
    {
        $result = [];
        $sort = 0;

        foreach (self::CATEGORIES as $name => [$hue, $featured, $children]) {
            $parent = Category::create([
                'name' => $name,
                'description' => "Explore our curated {$name} collection.",
                'is_active' => true,
                'is_featured' => $featured,
                'sort_order' => $sort++,
            ]);
            $parent->forceFill(['image_path' => PlaceholderImage::create('categories', $name, $hue, 600, 400)])->save();
            $parent->setAttribute('seed_hue', $hue);
            $result[$name] = $parent;

            foreach ($children as $childName => $childHue) {
                $child = Category::create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'slug' => Str::slug("{$name} {$childName}"),
                    'description' => "{$childName} in {$name}.",
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]);
                $child->forceFill(['image_path' => PlaceholderImage::create('categories', $childName, $childHue, 600, 400)])->save();
                $child->setAttribute('seed_hue', $childHue);
                $result[$childName] = $child;
            }
        }

        return $result;
    }

    /** @return array<string, Brand> */
    private function seedBrands(): array
    {
        $result = [];

        foreach (self::BRANDS as $name => $description) {
            $brand = Brand::create(['name' => $name, 'description' => $description, 'is_active' => true]);
            $brand->forceFill(['logo_path' => PlaceholderImage::create('brands', $name, crc32($name) % 360, 300, 300)])->save();
            $result[$name] = $brand;
        }

        return $result;
    }

    private function description(string $name, string $short): string
    {
        return "{$short}\n\n"
            ."The {$name} is designed with care and built from carefully selected materials. "
            ."Every detail has been considered to deliver reliable performance and a product you will enjoy using every day.\n\n"
            ."What's included:\n- {$name}\n- Quick start guide\n- 2-year limited warranty";
    }
}
