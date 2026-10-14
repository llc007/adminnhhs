<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::first();
Auth::login($user);

// Get the actual component class name from the file
$componentClass = require __DIR__.'/resources/views/pages/usuarios/funcionarios/⚡ficha.blade.php';

$component = Livewire\Livewire::test($componentClass, ['id' => $user->id]);
$component->set('rutDv', '0')
    ->call('guardar');

echo 'After save rut_dv: '.var_export(User::find($user->id)->rut_dv, true)."\n";
