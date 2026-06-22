<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CHECKING MYSQL CONNECTION ===\n";
try {
    $dbName = DB::connection('mysql')->getDatabaseName();
    echo "MySQL Database Name: $dbName\n";
    $tables = DB::connection('mysql')->select('SHOW TABLES');
    $tableKey = 'Tables_in_' . $dbName;
    foreach ($tables as $table) {
        $tableName = $table->$tableKey;
        $count = DB::connection('mysql')->table($tableName)->count();
        echo "Table: $tableName | Rows: $count\n";
        
        // Let's also search for 'mype' columns or data in this table
        $columns = Schema::connection('mysql')->getColumnListing($tableName);
        foreach ($columns as $column) {
            if (stripos($column, 'mype') !== false) {
                echo "  -> Column match: $column\n";
            }
        }
        
        // Sample some data to see if 'mype' is in content
        try {
            $sample = DB::connection('mysql')->table($tableName)->limit(100)->get();
            $matchCount = 0;
            foreach ($sample as $row) {
                $rowStr = json_encode($row);
                if (stripos($rowStr, 'mype') !== false) {
                    $matchCount++;
                }
            }
            if ($matchCount > 0) {
                echo "  -> Found $matchCount sample rows containing 'mype'\n";
            }
        } catch (\Exception $e) {}
    }
} catch (\Exception $e) {
    echo "MySQL Error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECKING SQLITE CONNECTION ===\n";
try {
    $sqlitePath = database_path('database.sqlite');
    echo "SQLite Path: $sqlitePath\n";
    if (file_exists($sqlitePath)) {
        config(['database.connections.sqlite.database' => $sqlitePath]);
        $sqliteTables = DB::connection('sqlite')->select("SELECT name FROM sqlite_master WHERE type='table'");
        foreach ($sqliteTables as $table) {
            $tableName = $table->name;
            $count = DB::connection('sqlite')->table($tableName)->count();
            echo "Table: $tableName | Rows: $count\n";
            
            $columns = Schema::connection('sqlite')->getColumnListing($tableName);
            foreach ($columns as $column) {
                if (stripos($column, 'mype') !== false) {
                    echo "  -> Column match: $column\n";
                }
            }
            
            // Sample some data to see if 'mype' is in content
            try {
                $sample = DB::connection('sqlite')->table($tableName)->limit(100)->get();
                $matchCount = 0;
                foreach ($sample as $row) {
                    $rowStr = json_encode($row);
                    if (stripos($rowStr, 'mype') !== false) {
                        $matchCount++;
                    }
                }
                if ($matchCount > 0) {
                    echo "  -> Found $matchCount sample rows containing 'mype'\n";
                }
            } catch (\Exception $e) {}
        }
    } else {
        echo "SQLite file does not exist.\n";
    }
} catch (\Exception $e) {
    echo "SQLite Error: " . $e->getMessage() . "\n";
}
