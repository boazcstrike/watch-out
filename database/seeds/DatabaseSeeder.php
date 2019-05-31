<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        factory(App\User::class, 1)->create([
            'name' => 'admin',
            'email' => 'admin@l.l',
            'email_verified_at' => now()
        ]);

        factory(App\User::class, 1)->create([
            'name' => 'Boaz Sze',
            'email' => 'boaz.sze@gmail.com',
            'email_verified_at' => now()
        ]);
    }
}
