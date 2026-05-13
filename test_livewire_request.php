<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$estudiante = \App\Models\Estudiante::first();
echo "Before DB rut_dv: " . var_export($estudiante->rut_dv, true) . "\n";

$component = new class extends \Livewire\Component {
    public $rutDv = 0;
    
    public function rules() {
        return [
            'rutDv' => ['nullable', 'max:1', 'regex:/^[0-9Kk]$/']
        ];
    }
    
    public function save() {
        $this->validate();
        echo "Validation passed! rutDv = " . var_export($this->rutDv, true) . "\n";
    }
};

try {
    $component->save();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
