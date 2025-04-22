<?php

class Customer extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'company';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'type' => array(),
            'address' => array(),
            'phone' => array(),
            'created_at' => array()
        );
        $this->connect();
    }

    function index(){

    }

    function list() {
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $this->fetchAll($query);
    }

    function add() {
        // Check if company exists by name
        $companyQuery = sprintf("SELECT id FROM {$this->table} WHERE name = '%s'", $this->quote($_POST['name']));
        $company = $this->fetchOne($companyQuery);
        
        if ($company) {
            $hash['status'] = 'error';
            $hash['message_code'] = 7; // Company with this name already exists
            return $hash;
        }
        $query = sprintf(
            "INSERT INTO {$this->table} (name, type, address, phone, created_at) VALUES ('%s', '%s', '%s', '%s', NOW())",
            $this->quote($_POST['name']),
            $this->quote($_POST['type']),
            $this->quote($_POST['address']),
            $this->quote($_POST['phone'])
        );
        $result = $this->query($query);
        $hash['status'] = $result ? 'success' : 'error';
        $hash['message_code'] = $result ? 1 : 0;
        return $hash;
    }

    function edit($id = null) {
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        if (!$id) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }
        // Check if company exists by name
        $companyQuery = sprintf("SELECT id FROM {$this->table} WHERE name = '%s' AND id != %d", $this->quote($_POST['name']), intval($id));
        $company = $this->fetchOne($companyQuery);
        
        if ($company) {
            $hash['status'] = 'error';
            $hash['message_code'] = 7; // Company with this name already exists
            return $hash;
        }
        
        $query = sprintf(
            "UPDATE {$this->table} SET name = '%s', type = '%s', address = '%s', phone = '%s' WHERE id = %d",
            $this->quote($_POST['name']),
            $this->quote($_POST['type']),
            $this->quote($_POST['address']),
            $this->quote($_POST['phone']),
            intval($id)
        );
        $result = $this->query($query);
        $hash['status'] = $result ? 'success' : 'error';
        $hash['message_code'] = $result ? 2 : 0;
        return $hash;
    }

    function delete($id = null) {
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        if (!$id) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }
        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        $result = $this->query($query);
        $hash['status'] = $result ? 'success' : 'error';
        $hash['message_code'] = $result ? 3 : 0;
        return $hash;
    }

    function get($id = '') {
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        if (!$id) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }
        $query = sprintf("SELECT * FROM {$this->table} WHERE id = %d", intval($id));
        $hash['data'] = $this->fetchOne($query);
        return $hash;
    }

    function listRepresentatives($companyId = null) {
        if(isset($_GET['company_id'])){
            $companyId = $_GET['company_id'];
        }
        if (!$companyId) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }
        $query = sprintf("SELECT * FROM groupware_representatives WHERE company_id = %d ORDER BY created_at DESC", intval($companyId));
        $hash['list'] = $this->fetchAll($query);
        return $hash;
    }

    function addRepresentative() {
        // Check if representative exists by name
        $repQuery = sprintf("SELECT id FROM groupware_representatives WHERE name = '%s'", $this->quote($_POST['name']));
        $rep = $this->fetchOne($repQuery);
        
        if ($rep) {
            $hash['status'] = 'error';
            $hash['message_code'] = 8; // Representative with this name already exists
            return $hash;
        }

        $query = sprintf(
            "INSERT INTO groupware_representatives (company_id, name, email, phone, created_at) VALUES (%d, '%s', '%s', '%s', NOW())",
            intval($_POST['company_id']),
            $this->quote($_POST['name']),
            $this->quote($_POST['email']),
            $this->quote($_POST['phone'])
        );
        $result = $this->query($query);
        $hash['status'] = $result ? 'success' : 'error';
        $hash['message_code'] = $result ? 4 : 0;
        return $hash;
    }

    function deleteRepresentative($id = null) {
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        if (!$id) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }
        $query = sprintf("DELETE FROM groupware_representatives WHERE id = %d", intval($id));
        $result = $this->query($query);
        $hash['status'] = $result ? 'success' : 'error';
        $hash['message_code'] = $result ? 5 : 0;
        return $hash;
    }

    function editRepresentative($id = null) {
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        if (!$id) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }

        // Check if representative exists by name (excluding current record)
        $repQuery = sprintf("SELECT id FROM groupware_representatives WHERE name = '%s' AND id != %d", 
            $this->quote($_POST['name']), 
            intval($id)
        );
        $rep = $this->fetchOne($repQuery);
        
        if ($rep) {
            $hash['status'] = 'error';
            $hash['message_code'] = 8; // Representative with this name already exists
            return $hash;
        }

        $query = sprintf(
            "UPDATE groupware_representatives SET name = '%s', email = '%s', phone = '%s' WHERE id = %d",
            $this->quote($_POST['name']),
            $this->quote($_POST['email']),
            $this->quote($_POST['phone']),
            intval($id)
        );
        $result = $this->query($query);
        $hash['status'] = $result ? 'success' : 'error';
        $hash['message_code'] = $result ? 6 : 0;
        return $hash;
    }

    function getRepresentative($id = '') {
        if(isset($_GET['id'])){
            $id = $_GET['id'];
        }
        if (!$id) {
            $hash['status'] = 'error';
            $hash['message_code'] = 0;
            return $hash;
        }
        $query = sprintf("SELECT * FROM groupware_representatives WHERE id = %d", intval($id));
        $hash['data'] = $this->fetchOne($query);
        return $hash;
    }

    function listWithRepresentatives() {
        $query = "SELECT c.*, 
                         r.id AS representative_id, 
                         r.name AS representative_name, 
                         r.email AS representative_email, 
                         r.phone AS representative_phone 
                  FROM {$this->table} c 
                  LEFT JOIN groupware_representatives r ON c.id = r.company_id 
                  ORDER BY c.created_at DESC, r.created_at DESC";
        $result = $this->fetchAll($query);

        $companies = [];
        foreach ($result as $row) {
            $companyId = $row['id'];
            if (!isset($companies[$companyId])) {
                $companies[$companyId] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'address' => $row['address'],
                    'phone' => $row['phone'],
                    'created_at' => $row['created_at'],
                    'representatives' => []
                ];
            }

            if (!empty($row['representative_id'])) {
                $companies[$companyId]['representatives'][] = [
                    'id' => $row['representative_id'],
                    'name' => $row['representative_name'],
                    'email' => $row['representative_email'],
                    'phone' => $row['representative_phone']
                ];
            }
        }
        $hash['list'] = $companies;
        return $hash;
    }
}

?>