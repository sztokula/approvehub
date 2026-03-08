<?php

namespace Database\Seeders;

use App\Models\DocumentPermission;
use Illuminate\Database\Seeder;

class DocumentPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DocumentPermission::factory()->count(10)->create();
    }
}
