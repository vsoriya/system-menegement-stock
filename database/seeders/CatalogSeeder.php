<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $suppliers = $this->seedSuppliers();

        // Products start at zero. StockMovementSeeder builds the quantities up
        // through real movements so the history always matches the balance.
        foreach ($this->products() as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'] ?? null,
                    'category_id' => $categories[$product['category']]->id,
                    'supplier_id' => $suppliers[$product['supplier']]->id,
                    'unit' => $product['unit'],
                    'cost_price' => $product['cost'],
                    'sale_price' => $product['price'],
                    'reorder_level' => $product['reorder_level'],
                    'is_active' => $product['is_active'] ?? true,
                ],
            );
        }
    }

    /**
     * @return array<string, Category>
     */
    protected function seedCategories(): array
    {
        $definitions = [
            'Laptops' => 'Portable computers and notebooks.',
            'Peripherals' => 'Keyboards, mice, webcams and docking stations.',
            'Displays' => 'Monitors and screens.',
            'Storage' => 'Drives, memory cards and USB sticks.',
            'Networking' => 'Routers, switches and cabling.',
            'Consumables' => 'Printer supplies and everyday office items.',
        ];

        $categories = [];

        foreach ($definitions as $name => $description) {
            $categories[$name] = Category::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true],
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Supplier>
     */
    protected function seedSuppliers(): array
    {
        $definitions = [
            [
                'name' => 'Mekong Tech Distribution',
                'contact_person' => 'Dara Sok',
                'email' => 'sales@mekongtech.example',
                'phone' => '+855 23 456 789',
                'address' => '128 Norodom Blvd, Phnom Penh',
                'notes' => 'Net 30 payment terms. Lead time around 5 working days.',
            ],
            [
                'name' => 'Angkor Office Supplies',
                'contact_person' => 'Sophea Chan',
                'email' => 'orders@angkoroffice.example',
                'phone' => '+855 12 998 221',
                'address' => '45 Street 271, Phnom Penh',
                'notes' => 'Good for consumables. Same day delivery within the city.',
            ],
            [
                'name' => 'Pacific Hardware Imports',
                'contact_person' => 'Linh Nguyen',
                'email' => 'contact@pacifichw.example',
                'phone' => '+855 96 774 310',
                'address' => 'Unit 7, Sen Sok Industrial Park',
                'notes' => 'Bulk pricing above 50 units. Lead time 2 to 3 weeks.',
            ],
            [
                'name' => 'Riverside Components',
                'contact_person' => 'Vichea Meas',
                'email' => 'hello@riversidecomp.example',
                'phone' => '+855 77 130 908',
                'address' => '9 Sisowath Quay, Phnom Penh',
                'notes' => null,
            ],
        ];

        $suppliers = [];

        foreach ($definitions as $definition) {
            $suppliers[$definition['name']] = Supplier::query()->updateOrCreate(
                ['name' => $definition['name']],
                $definition + ['is_active' => true],
            );
        }

        return $suppliers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function products(): array
    {
        return [
            [
                'sku' => 'LAP-1001',
                'name' => 'ProBook 14 laptop',
                'description' => '14 inch business laptop, 16GB RAM, 512GB SSD.',
                'category' => 'Laptops',
                'supplier' => 'Mekong Tech Distribution',
                'unit' => 'pcs',
                'cost' => 720.00,
                'price' => 949.00,
                'reorder_level' => 5,
            ],
            [
                'sku' => 'LAP-1002',
                'name' => 'UltraSlim 13 laptop',
                'description' => 'Lightweight 13 inch ultrabook for travel.',
                'category' => 'Laptops',
                'supplier' => 'Mekong Tech Distribution',
                'unit' => 'pcs',
                'cost' => 890.00,
                'price' => 1199.00,
                'reorder_level' => 4,
            ],
            [
                'sku' => 'MON-2001',
                'name' => '27 inch QHD monitor',
                'description' => '2560x1440 IPS panel with height adjustment.',
                'category' => 'Displays',
                'supplier' => 'Pacific Hardware Imports',
                'unit' => 'pcs',
                'cost' => 185.00,
                'price' => 259.00,
                'reorder_level' => 8,
            ],
            [
                'sku' => 'MON-2002',
                'name' => '24 inch full HD monitor',
                'description' => 'Entry level 1080p office display.',
                'category' => 'Displays',
                'supplier' => 'Pacific Hardware Imports',
                'unit' => 'pcs',
                'cost' => 98.00,
                'price' => 145.00,
                'reorder_level' => 10,
            ],
            [
                'sku' => 'KEY-3001',
                'name' => 'Mechanical keyboard',
                'description' => 'Tenkeyless mechanical keyboard, brown switches.',
                'category' => 'Peripherals',
                'supplier' => 'Riverside Components',
                'unit' => 'pcs',
                'cost' => 42.00,
                'price' => 69.00,
                'reorder_level' => 15,
            ],
            [
                'sku' => 'MSE-3002',
                'name' => 'Wireless mouse',
                'description' => 'Silent click wireless mouse, USB-C rechargeable.',
                'category' => 'Peripherals',
                'supplier' => 'Riverside Components',
                'unit' => 'pcs',
                'cost' => 11.50,
                'price' => 22.00,
                'reorder_level' => 25,
            ],
            [
                'sku' => 'DOC-3003',
                'name' => 'USB-C docking station',
                'description' => '11 port dock with dual HDMI and gigabit ethernet.',
                'category' => 'Peripherals',
                'supplier' => 'Mekong Tech Distribution',
                'unit' => 'pcs',
                'cost' => 96.00,
                'price' => 149.00,
                'reorder_level' => 6,
            ],
            [
                'sku' => 'CAM-3004',
                'name' => '1080p webcam',
                'description' => 'Full HD webcam with dual microphones.',
                'category' => 'Peripherals',
                'supplier' => 'Riverside Components',
                'unit' => 'pcs',
                'cost' => 28.00,
                'price' => 47.00,
                'reorder_level' => 12,
            ],
            [
                'sku' => 'SSD-4001',
                'name' => '1TB NVMe SSD',
                'description' => 'PCIe Gen4 internal solid state drive.',
                'category' => 'Storage',
                'supplier' => 'Pacific Hardware Imports',
                'unit' => 'pcs',
                'cost' => 68.00,
                'price' => 105.00,
                'reorder_level' => 10,
            ],
            [
                'sku' => 'USB-4002',
                'name' => '64GB USB flash drive',
                'description' => 'USB 3.2 flash drive, sold in packs of ten.',
                'category' => 'Storage',
                'supplier' => 'Angkor Office Supplies',
                'unit' => 'pack',
                'cost' => 39.00,
                'price' => 62.00,
                'reorder_level' => 8,
            ],
            [
                'sku' => 'HDD-4003',
                'name' => '4TB external hard drive',
                'description' => 'USB 3.0 desktop backup drive.',
                'category' => 'Storage',
                'supplier' => 'Pacific Hardware Imports',
                'unit' => 'pcs',
                'cost' => 88.00,
                'price' => 132.00,
                'reorder_level' => 6,
            ],
            [
                'sku' => 'NET-5001',
                'name' => 'Dual band wifi router',
                'description' => 'AX1800 wifi 6 router, 4 gigabit LAN ports.',
                'category' => 'Networking',
                'supplier' => 'Pacific Hardware Imports',
                'unit' => 'pcs',
                'cost' => 54.00,
                'price' => 89.00,
                'reorder_level' => 7,
            ],
            [
                'sku' => 'NET-5002',
                'name' => '8 port gigabit switch',
                'description' => 'Unmanaged desktop switch, metal case.',
                'category' => 'Networking',
                'supplier' => 'Pacific Hardware Imports',
                'unit' => 'pcs',
                'cost' => 23.00,
                'price' => 39.00,
                'reorder_level' => 10,
            ],
            [
                'sku' => 'NET-5003',
                'name' => 'Cat6 patch cable 2m',
                'description' => 'Shielded Cat6 ethernet cable, sold per box of 20.',
                'category' => 'Networking',
                'supplier' => 'Riverside Components',
                'unit' => 'box',
                'cost' => 31.00,
                'price' => 52.00,
                'reorder_level' => 12,
            ],
            [
                'sku' => 'TON-6001',
                'name' => 'Black toner cartridge',
                'description' => 'High yield toner for mono laser printers.',
                'category' => 'Consumables',
                'supplier' => 'Angkor Office Supplies',
                'unit' => 'pcs',
                'cost' => 47.00,
                'price' => 74.00,
                'reorder_level' => 20,
            ],
            [
                'sku' => 'PAP-6002',
                'name' => 'A4 copy paper',
                'description' => '80gsm white copy paper, 500 sheets per ream.',
                'category' => 'Consumables',
                'supplier' => 'Angkor Office Supplies',
                'unit' => 'box',
                'cost' => 21.00,
                'price' => 33.00,
                'reorder_level' => 30,
            ],
            [
                'sku' => 'BAT-6003',
                'name' => 'AA alkaline batteries',
                'description' => 'Pack of 24 AA batteries.',
                'category' => 'Consumables',
                'supplier' => 'Angkor Office Supplies',
                'unit' => 'pack',
                'cost' => 8.50,
                'price' => 15.00,
                'reorder_level' => 25,
            ],
            [
                'sku' => 'ADP-3005',
                'name' => '65W USB-C charger',
                'description' => 'GaN fast charger with foldable plug.',
                'category' => 'Peripherals',
                'supplier' => 'Mekong Tech Distribution',
                'unit' => 'pcs',
                'cost' => 19.00,
                'price' => 34.00,
                'reorder_level' => 18,
            ],
            [
                'sku' => 'STD-3006',
                'name' => 'Adjustable laptop stand',
                'description' => 'Aluminium stand, six height positions.',
                'category' => 'Peripherals',
                'supplier' => 'Riverside Components',
                'unit' => 'pcs',
                'cost' => 16.00,
                'price' => 29.00,
                'reorder_level' => 14,
            ],
            [
                'sku' => 'LAP-1003',
                'name' => 'Legacy Notebook 15 (discontinued)',
                'description' => 'No longer stocked. Kept for historical reporting.',
                'category' => 'Laptops',
                'supplier' => 'Mekong Tech Distribution',
                'unit' => 'pcs',
                'cost' => 540.00,
                'price' => 699.00,
                'reorder_level' => 0,
                'is_active' => false,
            ],
        ];
    }
}
