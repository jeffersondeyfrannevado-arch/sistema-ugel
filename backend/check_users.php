<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "=== USERS LIST ===\n";
try {
    $users = User::all();
    if ($users->isEmpty()) {
        echo "No users found in database.\n";
    } else {
        foreach ($users as $user) {
            echo "ID: {$user->id} | Name: {$user->name} | Email: {$user->email} | Role: {$user->role} | Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
