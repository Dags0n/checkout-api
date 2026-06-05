<?php

declare(strict_types=1);

namespace Database\Seeders;

use Catalog\Domain\Product;
use Illuminate\Database\Seeder;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Camiseta Dry Fit',           'Camiseta esportiva dry fit masculina',     'CAM-DRY-001', 4990,  50],
            ['Camiseta Oversized',         'Camiseta oversized unissex',               'CAM-OVS-002', 5990,  40],
            ['Shorts Esportivo',           'Shorts de corrida com bolso lateral',      'SHT-RUN-003', 6990,  35],
            ['Legging Suplex',             'Legging feminina de alta compressão',      'LEG-SPL-004', 8990,  30],
            ['Top Esportivo',              'Top feminino com bojo removível',          'TOP-ESP-005', 5490,  45],
            ['Tênis de Corrida',           'Tênis leve com solado de EVA',             'TEN-COR-006', 19900, 15],
            ['Meia Cano Alto (pack 3)',    'Pack com 3 pares de meia cano alto',       'MEI-CNA-007', 2990,  100],
            ['Garrafa Térmica 1L',         'Garrafa térmica de inox 1 litro',          'GAR-TRM-008', 4490,  60],
            ['Whey Protein 900g',          'Whey protein concentrado sabor chocolate', 'WHY-CHO-009', 12900, 25],
            ['Creatina 300g',              'Creatina monohidratada 300g',              'CRE-MON-010', 7990,  40],
        ];

        foreach ($items as [$name, $description, $sku, $priceCents, $stock]) {
            Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'description' => $description,
                    'price_cents' => $priceCents,
                    'currency' => 'BRL',
                    'stock' => $stock,
                ],
            );
        }
    }
}
