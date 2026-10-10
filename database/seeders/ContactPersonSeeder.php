<?php

namespace Database\Seeders;

use App\Models\ContactPerson;
use Illuminate\Database\Seeder;

class ContactPersonSeeder extends Seeder
{
    /**
     * The general contact person shown on the landing page and assigned to a
     * project when no client liaison has been picked yet.
     */
    public function run(): void
    {
        $name = (string) config('wrbn.cp_general_name');
        $whatsapp = (string) config('wrbn.cp_general_whatsapp');

        if ($name === '' || $whatsapp === '') {
            return;
        }

        ContactPerson::query()->updateOrCreate(
            ['whatsapp' => $whatsapp],
            ['name' => $name, 'is_general' => true, 'active' => true],
        );
    }
}
