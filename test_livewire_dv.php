<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
$rutDv = '0'; // Simulated from Livewire
$data = ['rut_dv' => $rutDv !== '' ? strtoupper($rutDv) : null];
echo "Data to update: "; print_r($data);
$user->update($data);
echo "\nSaved rut_dv: " . $user->fresh()->rut_dv . "\n";
