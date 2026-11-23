<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Support\Notice;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Platform Super Admin (credentials come from .env, never hard-coded)
        $super = User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@imagefreeway.test')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'ImageFreeway Super Admin'),
                'password' => env('SUPER_ADMIN_PASSWORD') ?: bin2hex(random_bytes(8)),
                'role' => 'super_admin',
                'status' => 'active',
            ],
        );
        if ($super->wasRecentlyCreated) {
            Notice::send($super, 'Welcome to ImageFreeway 2.0', 'You are the platform Super Admin. Start by creating a client.', route('clients.create', [], false), 'party-popper', 'success');
        }

        // Demo client (F-DEMO-01): used for previews and distributor demos
        $demo = Client::firstOrCreate(['slug' => 'demo'], [
            'name' => 'ImageFreeway Demo Company',
            'status' => 'active',
            'modules' => array_fill_keys(array_keys(Client::MODULES), false),
        ]);
        $demoPassword = env('DEMO_PASSWORD') ?: bin2hex(random_bytes(8));
        User::firstOrCreate(['email' => 'admin@demo.test'], [
            'client_id' => $demo->id, 'name' => 'Demo Admin', 'password' => $demoPassword, 'role' => 'client_admin',
        ]);
        User::firstOrCreate(['email' => 'user@demo.test'], [
            'client_id' => $demo->id, 'name' => 'Demo User', 'password' => $demoPassword, 'role' => 'user',
        ]);

        // The WDG flowcharts as ready-made workflows, forms and document types
        \App\Support\WdgTemplates::install($demo, $demoPassword);

        $this->call(HelpSeeder::class);
    }
}
