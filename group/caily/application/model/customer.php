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

    function add($data) {
        $query = sprintf(
            "INSERT INTO {$this->table} (name, type, address, phone, created_at) VALUES ('%s', '%s', '%s', '%s', NOW())",
            $this->quote($data['name']),
            $this->quote($data['type']),
            $this->quote($data['address']),
            $this->quote($data['phone'])
        );
        return $this->query($query);
    }

    function edit($id, $data) {
        $query = sprintf(
            "UPDATE {$this->table} SET name = '%s', type = '%s', address = '%s', phone = '%s' WHERE id = %d",
            $this->quote($data['name']),
            $this->quote($data['type']),
            $this->quote($data['address']),
            $this->quote($data['phone']),
            intval($id)
        );
        return $this->query($query);
    }

    function delete($id) {
        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        return $this->query($query);
    }

    function get($id) {
        $query = sprintf("SELECT * FROM {$this->table} WHERE id = %d", intval($id));
        return $this->fetchOne($query);
    }

    function listRepresentatives($companyId) {
        $query = sprintf("SELECT * FROM company_representatives WHERE company_id = %d ORDER BY created_at DESC", intval($companyId));
        return $this->fetchAll($query);
    }

    function addRepresentative($data) {
        $query = sprintf(
            "INSERT INTO company_representatives (company_id, name, email, phone, created_at) VALUES (%d, '%s', '%s', '%s', NOW())",
            intval($data['company_id']),
            $this->quote($data['name']),
            $this->quote($data['email']),
            $this->quote($data['phone'])
        );
        return $this->query($query);
    }

    function deleteRepresentative($id) {
        $query = sprintf("DELETE FROM company_representatives WHERE id = %d", intval($id));
        return $this->query($query);
    }

    function editRepresentative($id, $data) {
        $query = sprintf(
            "UPDATE company_representatives SET name = '%s', email = '%s', phone = '%s' WHERE id = %d",
            $this->quote($data['name']),
            $this->quote($data['email']),
            $this->quote($data['phone']),
            intval($id)
        );
        return $this->query($query);
    }

    function getRepresentative($id) {
        $query = sprintf("SELECT * FROM company_representatives WHERE id = %d", intval($id));
        return $this->fetchOne($query);
    }

    function listWithRepresentatives() {
        $query = "SELECT c.*, 
                         r.id AS representative_id, 
                         r.name AS representative_name, 
                         r.email AS representative_email, 
                         r.phone AS representative_phone 
                  FROM {$this->table} c 
                  LEFT JOIN company_representatives r ON c.id = r.company_id 
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

        return array_values($companies);
    }
}

?>