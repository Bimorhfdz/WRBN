<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Seed the service catalogue. Idempotent: keyed by slug.
     */
    public function run(): void
    {
        $services = [
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'category' => 'package',
                'description' => 'Paket dasar untuk pengolahan data, perhitungan statistik, hasil analisis, kesimpulan, dan 1x revisi.',
                'price' => 199000,
                'price_label' => 'Rp199K mulai',
                'sort_order' => 1,
                'data_quota' => 1,
                'revision_quota' => 1,
                'deliverable_label' => 'Kesimpulan hasil',
            ],
            [
                'slug' => 'vip',
                'name' => 'VIP',
                'category' => 'package',
                'description' => 'Paket lengkap dengan data cleaning, EDA, visualisasi, interpretasi, insight, prediction/forecasting jika relevan, rekomendasi, laporan, dan 2x revisi.',
                'price' => 399000,
                'price_label' => 'Rp399K mulai',
                'sort_order' => 2,
                'data_quota' => 1,
                'revision_quota' => 2,
                'deliverable_label' => 'Rekomendasi & laporan lengkap',
            ],
            [
                'slug' => 'custom',
                'name' => 'Custom',
                'category' => 'package',
                'description' => 'Paket fleksibel sesuai metode, output, dan kompleksitas kebutuhan klien. Harga mulai Rp199K+.',
                'price' => 199000,
                'price_label' => 'Rp199K+',
                'sort_order' => 3,
                'data_quota' => 1,
                'revision_quota' => 3,
                'deliverable_label' => 'Sesuai output yang dipilih',
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service + ['active' => true]);
        }
    }
}
