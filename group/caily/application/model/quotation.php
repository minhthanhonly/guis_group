<?php

class Quotation extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'quotations';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'quotation_number' => array('notnull'),
            'issue_date' => array('notnull'),
            'sender_company' => array('notnull'),
            'sender_address' => array(),
            'sender_contact' => array(),
            'receiver_company' => array('notnull'),
            'receiver_address' => array(),
            'receiver_contact' => array(),
            'receiver_tel' => array(),
            'receiver_fax' => array(),
            'receiver_registration_number' => array(),
            'total_amount' => array('notnull'),
            'tax_rate' => array('notnull'),
            'total_with_tax' => array('notnull'),
            'delivery_location' => array(),
            'payment_method' => array(),
            'valid_until' => array(),
            'notes' => array(),
            'parent_project_id' => array('notnull'),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function getByParentProject($parent_project_id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE parent_project_id = ? ORDER BY created_at DESC";
        return $this->fetchAll($sql, array($parent_project_id));
    }

    function create($data) {
        // Validate required fields
        if (empty($data['quotation_number']) || empty($data['issue_date']) || 
            empty($data['sender_company']) || empty($data['receiver_company']) || 
            empty($data['parent_project_id'])) {
            return array('status' => 'error', 'message' => '必須項目が不足しています');
        }

        // Set default values
        $data['total_amount'] = $data['total_amount'] ?? 0;
        $data['tax_rate'] = $data['tax_rate'] ?? 10;
        $data['total_with_tax'] = $data['total_with_tax'] ?? 0;
        
        // Set default values for new receiver fields
        $data['receiver_tel'] = $data['receiver_tel'] ?? '';
        $data['receiver_fax'] = $data['receiver_fax'] ?? '';
        $data['receiver_registration_number'] = $data['receiver_registration_number'] ?? '';

        // Insert quotation
        $quotation_id = $this->insert($data);
        
        if ($quotation_id) {
            // Insert quotation items if provided
            if (!empty($data['items'])) {
                $items = json_decode($data['items'], true);
                if (is_array($items)) {
                    $this->insertQuotationItems($quotation_id, $items);
                }
            }
            
            return array('status' => 'success', 'id' => $quotation_id);
        }
        
        return array('status' => 'error', 'message' => '見積書の作成に失敗しました');
    }

    function insertQuotationItems($quotation_id, $items) {
        $item_table = DB_PREFIX . 'quotation_items';
        
        foreach ($items as $index => $item) {
            $item_data = array(
                'quotation_id' => $quotation_id,
                'title' => $item['title'] ?? '',
                'product_code' => $item['product_code'] ?? '',
                'product_name' => $item['product_name'] ?? '',
                'quantity' => $item['quantity'] ?? 0,
                'unit' => $item['unit'] ?? '',
                'unit_price' => $item['unit_price'] ?? 0,
                'amount' => $item['amount'] ?? 0,
                'notes' => $item['notes'] ?? '',
                'sort_order' => $index
            );
            
            $sql = "INSERT INTO " . $item_table . " 
                    (quotation_id, title, product_code, product_name, quantity, unit, unit_price, amount, notes, sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->execute($sql, array(
                $item_data['quotation_id'],
                $item_data['title'],
                $item_data['product_code'],
                $item_data['product_name'],
                $item_data['quantity'],
                $item_data['unit'],
                $item_data['unit_price'],
                $item_data['amount'],
                $item_data['notes'],
                $item_data['sort_order']
            ));
        }
    }

    function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE id = ?";
        $quotation = $this->fetch($sql, array($id));
        
        if ($quotation) {
            // Get quotation items
            $items_sql = "SELECT * FROM " . DB_PREFIX . "quotation_items WHERE quotation_id = ? ORDER BY sort_order ASC";
            $quotation['items'] = $this->fetchAll($items_sql, array($id));
        }
        
        return $quotation;
    }

    function delete($id) {
        // Delete quotation items first (due to foreign key constraint)
        $items_sql = "DELETE FROM " . DB_PREFIX . "quotation_items WHERE quotation_id = ?";
        $this->execute($items_sql, array($id));
        
        // Delete quotation
        $sql = "DELETE FROM " . $this->table . " WHERE id = ?";
        return $this->execute($sql, array($id));
    }
} 