<?php

class Specification extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'specifications';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'company_id' => array(),
            'text' => array(),
            'files' => array(),
            'created_at' => array()
        );
        $this->connect();
    }

    function handleUploadedFile($file) {
        $uploadDir = DIR_ASSETS . 'upload/specs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filePath = $uploadDir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return $filePath;
        } else {
            return null;
        }
    }

    function handleUploadedFiles($files) {
        $uploadDir = DIR_ASSETS . 'upload/specs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filePaths = [];
        foreach ($files['name'] as $index => $name) {
            if ($files['error'][$index] === UPLOAD_ERR_OK) {
                $filePath = $uploadDir . basename($name);
                if (move_uploaded_file($files['tmp_name'][$index], $filePath)) {
                    $filePaths[] = realpath($filePath);
                }
            }
        }
        return $filePaths;
    }

    function listSpecifications() {
        $query = "SELECT s.*, c.name AS company_name FROM {$this->table} s JOIN " . DB_PREFIX . "company c ON s.company_id = c.id ORDER BY s.created_at DESC";
        return $this->fetchAll($query);
    }

    function addSpecification($data, $files) {
        $filePaths = $this->handleUploadedFiles($files);
        $data['files'] = json_encode($filePaths);

        $query = sprintf(
            "INSERT INTO {$this->table} (name, company_id, text, files, created_at) VALUES ('%s', %d, '%s', '%s', NOW())",
            $this->quote($data['name']),
            intval($data['company_id']),
            $this->quote($data['text']),
            $this->quote($data['files'])
        );
        return $this->query($query);
    }

    function editSpecification($id, $data, $files) {
        $filePaths = $this->handleUploadedFiles($files);
        if (!empty($filePaths)) {
            $data['files'] = json_encode($filePaths);
        }

        $query = sprintf(
            "UPDATE {$this->table} SET name = '%s', company_id = %d, text = '%s', files = '%s' WHERE id = %d",
            $this->quote($data['name']),
            intval($data['company_id']),
            $this->quote($data['text']),
            $this->quote($data['files'] ?? ''),
            intval($id)
        );
        return $this->query($query);
    }

    function deleteSpecification($id) {
        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        return $this->query($query);
    }

    function getSpecification($id) {
        $query = sprintf("SELECT * FROM {$this->table} WHERE id = %d", intval($id));
        return $this->fetchOne($query);
    }
}

?>