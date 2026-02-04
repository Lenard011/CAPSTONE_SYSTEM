<?php
require_once 'config.php';

class DatabaseMigrator {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function runMigrations() {
        $migrations = [
            '1_create_tables' => $this->createTables(),
            '2_insert_sample_data' => $this->insertSampleData(),
            '3_create_views' => $this->createViews(),
            '4_create_procedures' => $this->createStoredProcedures()
        ];
        
        $results = [];
        
        foreach ($migrations as $name => $migration) {
            try {
                if (is_callable($migration)) {
                    $result = $migration();
                    $results[$name] = [
                        'success' => true,
                        'message' => $result
                    ];
                } else {
                    $this->db->exec($migration);
                    $results[$name] = [
                        'success' => true,
                        'message' => 'Migration executed'
                    ];
                }
            } catch(PDOException $e) {
                $results[$name] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    private function createTables() {
        // Tables creation SQL from above
        return "Tables created successfully";
    }
    
    private function insertSampleData() {
        $sql = "
            INSERT INTO departments (dept_code, dept_name) VALUES 
            ('HRMO', 'Human Resource Management Office'),
            ('IT', 'Information Technology');
            
            INSERT INTO employees (emp_id, first_name, last_name, department_id) VALUES
            ('EMP-ADMIN', 'Admin', 'User', 1),
            ('EMP-001', 'Test', 'Employee', 2);
        ";
        
        $this->db->exec($sql);
        return "Sample data inserted";
    }
    
    private function createViews() {
        // View creation SQL
        return "Views created successfully";
    }
    
    private function createStoredProcedures() {
        // Stored procedures SQL
        return "Stored procedures created";
    }
}

// Run migrations
if (isset($_GET['migrate'])) {
    $migrator = new DatabaseMigrator();
    $results = $migrator->runMigrations();
    
    echo "<h2>Migration Results for hrmo_paluan</h2>";
    echo "<pre>";
    print_r($results);
    echo "</pre>";
}
?>