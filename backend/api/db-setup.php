<?php
// Database Setup and Sample Data Insertion via PHP
require_once '../conn.php';

// Disable CORS headers for this setup script
ob_start();

try {
    // Read and execute complete setup SQL
    $setupSql = file_get_contents('../sql/complete_setup.sql');
    if ($setupSql === false) {
        throw new Exception('Could not read complete_setup.sql');
    }

    // Execute multi-query for setup
    if ($conn->multi_query($setupSql)) {
        echo "✅ Database schema setup completed successfully!\n\n";
        
        // Clear all results from multi_query
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
    } else {
        throw new Exception('Setup SQL failed: ' . $conn->error);
    }

    // Read and execute sample data SQL
    $sampleSql = file_get_contents('../sql/sample_data.sql');
    if ($sampleSql === false) {
        throw new Exception('Could not read sample_data.sql');
    }

    // Execute multi-query for sample data
    if ($conn->multi_query($sampleSql)) {
        echo "✅ Sample data insertion completed successfully!\n\n";
        
        // Clear all results from multi_query
        do {
            if ($result = $conn->store_result()) {
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        foreach ($row as $key => $value) {
                            echo "$key: $value\n";
                        }
                    }
                }
                $result->free();
            }
        } while ($conn->next_result());
        
    } else {
        throw new Exception('Sample data SQL failed: ' . $conn->error);
    }

    // Verify data counts
    echo "\n📊 Data Verification:\n";
    
    $tables_to_check = [
        'packages' => 'Travel packages',
        'accounts' => 'User accounts', 
        'guides' => 'Tour guides',
        'booking' => 'Bookings',
        'reviews' => 'Reviews',
        'notices' => 'Notices'
    ];
    
    foreach ($tables_to_check as $table => $description) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "- $description: {$row['count']} records\n";
        }
    }

    echo "\n🎉 Database setup and data population completed successfully!\n";
    echo "All HTML files in user directory can now load data from the database.\n\n";
    
    // Test a sample API call
    echo "🔧 Testing API connectivity...\n";
    $test_query = "SELECT packageId, packageName, packagePrice, rating FROM packages LIMIT 3";
    $result = $conn->query($test_query);
    
    if ($result && $result->num_rows > 0) {
        echo "✅ API test successful! Sample packages:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['packageName']}: ₱" . number_format($row['packagePrice']) . " (Rating: {$row['rating']})\n";
        }
    } else {
        echo "⚠️ API test failed or no data found\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if ($conn->error) {
        echo "MySQL Error: " . $conn->error . "\n";
    }
}

$output = ob_get_clean();

// Return as plain text for better readability
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo $output;
?>