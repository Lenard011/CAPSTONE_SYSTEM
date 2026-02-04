<?php
// ZKBio Time to hrmo_paluan integration configuration

class ZKBioTimeIntegrator {
    private $db;
    private $zkbio_db;
    
    public function __construct() {
        // Connect to hrmo_paluan database
        $this->db = getDB();
        
        // Connect to ZKBio Time database (adjust credentials)
        try {
            $this->zkbio_db = new PDO(
                "mysql:host=localhost;dbname=zkbiobase;charset=utf8mb4",
                "zkbio_user",
                "zkbio_pass"
            );
            $this->zkbio_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Cannot connect to ZKBio Time database: " . $e->getMessage());
        }
    }
    
    // Sync attendance from ZKBio Time to hrmo_paluan
    public function syncAttendance($date_from, $date_to) {
        // Get records from ZKBio Time
        $sql = "SELECT 
                    user_id,
                    check_time,
                    check_type,
                    sensor_id as device_sn,
                    verify_type
                FROM iclock_attendrecord 
                WHERE DATE(check_time) BETWEEN ? AND ?
                ORDER BY check_time";
        
        $stmt = $this->zkbio_db->prepare($sql);
        $stmt->execute([$date_from, $date_to]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert into hrmo_paluan
        $inserted = 0;
        $skipped = 0;
        
        foreach ($records as $record) {
            // Check if record already exists
            $checkSql = "SELECT id FROM zk_attendance 
                        WHERE user_id = ? AND check_time = ? AND check_type = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([
                $record['user_id'],
                $record['check_time'],
                $record['check_type']
            ]);
            
            if ($checkStmt->rowCount() == 0) {
                // Get employee info
                $empSql = "SELECT full_name, department_id FROM employees WHERE emp_id = ?";
                $empStmt = $this->db->prepare($empSql);
                $empStmt->execute([$record['user_id']]);
                $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    // Insert record
                    $insertSql = "INSERT INTO zk_attendance 
                                 (user_id, employee_name, check_time, check_type, device_sn, verify_type, sync_status)
                                 VALUES (?, ?, ?, ?, ?, ?, 1)";
                    
                    $insertStmt = $this->db->prepare($insertSql);
                    $insertStmt->execute([
                        $record['user_id'],
                        $employee['full_name'],
                        $record['check_time'],
                        $record['check_type'],
                        $record['device_sn'],
                        $record['verify_type']
                    ]);
                    
                    $inserted++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'success' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => count($records)
        ];
    }
    
    // Sync employees from ZKBio Time
    public function syncEmployees() {
        $sql = "SELECT 
                    user_id,
                    name,
                    card_no,
                    department
                FROM personnel_userinfo 
                WHERE is_active = 1";
        
        $stmt = $this->zkbio_db->prepare($sql);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $added = 0;
        $updated = 0;
        
        foreach ($employees as $emp) {
            // Check if exists
            $checkSql = "SELECT emp_id FROM employees WHERE emp_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$emp['user_id']]);
            
            if ($checkStmt->rowCount() == 0) {
                // Insert new employee
                $insertSql = "INSERT INTO employees 
                             (emp_id, first_name, last_name, card_number, status)
                             VALUES (?, ?, ?, ?, 'Active')";
                
                // Parse name (assuming format "First Last")
                $name_parts = explode(' ', $emp['name'], 2);
                $first_name = $name_parts[0] ?? '';
                $last_name = $name_parts[1] ?? '';
                
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    $emp['user_id'],
                    $first_name,
                    $last_name,
                    $emp['card_no']
                ]);
                
                $added++;
            } else {
                // Update existing
                $updateSql = "UPDATE employees SET card_number = ? WHERE emp_id = ?";
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([$emp['card_no'], $emp['user_id']]);
                $updated++;
            }
        }
        
        return [
            'success' => true,
            'added' => $added,
            'updated' => $updated,
            'total' => count($employees)
        ];
    }
}

// Usage example
if (isset($_GET['sync'])) {
    $integrator = new ZKBioTimeIntegrator();
    
    if ($_GET['sync'] == 'attendance') {
        $result = $integrator->syncAttendance(date('Y-m-d'), date('Y-m-d'));
        echo json_encode($result);
    } elseif ($_GET['sync'] == 'employees') {
        $result = $integrator->syncEmployees();
        echo json_encode($result);
    }
}
?>