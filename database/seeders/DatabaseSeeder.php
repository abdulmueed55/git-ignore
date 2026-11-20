<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a complete, realistic college so every screen has data from day one.
     * Module seeders are optional so modules can be developed independently.
     */
    public function run(): void
    {
        $this->call(AcademicSeeder::class);

        foreach ([AttendanceSeeder::class, AdmissionSeeder::class, CmsSeeder::class] as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        $this->call(ActivitySeeder::class);
    }
}
