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
        // The demo college has public logins (password "password"). Never load it into a
        // real production database by accident.
        if (app()->isProduction() && ! config('app.demo')) {
            throw new \RuntimeException('Refusing to seed demo data in production. Set APP_DEMO=true only for a throw-away demo server.');
        }

        $this->call(AcademicSeeder::class);

        foreach ([AttendanceSeeder::class, AdmissionSeeder::class, CmsSeeder::class] as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        $this->call(ExtraClassSeeder::class);
        $this->call(ActivitySeeder::class);
    }
}
