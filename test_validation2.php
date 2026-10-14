<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Validator;

require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$validator = Validator::make(
    ['rutDv' => '0'],
    ['rutDv' => ['nullable', 'string', 'max:1', 'regex:/^[0-9Kk]$/']]
);

if ($validator->fails()) {
    echo 'Fails: ';
    print_r($validator->errors()->all());
} else {
    echo 'Passes!';
}
