<?php

class Customer extends ApplicationModel {
    public $table_category;
    public $schema_category;
    function __construct() {
        $this->table = DB_PREFIX . 'customer';
        $this->schema = array(
            'id' => array('except' => array('search', 'update')),
            'company_name' => array(),
            'company_name_kana' => array(),
            'name' => array(),
            'name_kana' => array(),
            'branch' => array(),
            'department' => array(),
            'position' => array(),
            'tel' => array(),
            'fax' => array(),
            'phone' => array(),
            'email' => array(),
            'zip' => array(),
            'address1' => array(),
            'address2' => array(),
            'title' => array(),
            'category_id' => array(),
            'company_id' => array(),
            'guis_department' => array(),
            'created_at' => array('except' => array('search', 'update')),
            'updated_at' => array(),
            'updated_by' => array(),
            'created_by' => array('except' => array('search', 'update')),
            'status' => array(),
            'memo' => array(),
        );

        $this->table_category = DB_PREFIX . 'customer_category';
        $this->schema_category = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'name_kana' => array(),
            'memo' => array(),
        );

        $this->connect();
    }

    

    function list_category() {
        $query = sprintf(
            "SELECT c.*
            FROM " . DB_PREFIX . "customer_category c
            ORDER BY c.arrange ASC, c.id ASC"
        );
        $rows = $this->fetchAll($query);
        $this->attachCustomerCategoryAggregates($rows);
        return $rows;
    }

    private function attachCustomerCategoryAggregates(array &$rows) {
        if (empty($rows)) {
            return;
        }
        $categoryIds = array_values(array_filter(array_map('intval', array_column($rows, 'id')), function ($id) {
            return $id > 0;
        }));
        if (empty($categoryIds)) {
            return;
        }
        $idsList = implode(',', $categoryIds);
        $countMap = [];
        $countRows = $this->fetchAll(sprintf(
            "SELECT category_id, COUNT(*) as num_customers FROM %scustomer WHERE category_id IN (%s) GROUP BY category_id",
            DB_PREFIX,
            $idsList
        ));
        foreach ($countRows as $row) {
            $countMap[(int)$row['category_id']] = (int)$row['num_customers'];
        }
        foreach ($rows as &$row) {
            $row['num_customers'] = $countMap[(int)$row['id']] ?? 0;
        }
        unset($row);
    }

    function add_category() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $data = array(
                'name' => $_POST['name'],
                'name_kana' => $_POST['name_kana'],
                'memo' => $_POST['memo'],
            );
            $result = $this->query_insert($data, $this->table_category);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            } else {
                throw new Exception('カテゴリの追加に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    function edit_category() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $id = $_GET['id'];
            $data = array(
                'name' => $_POST['name'],
                'name_kana' => $_POST['name_kana'],
                'memo' => $_POST['memo'],
            );
            $result = $this->query_update($data, ['id' => $_GET['id']], $this->table_category);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            } else {
                throw new Exception('カテゴリの更新に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    function delete_category() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $id = $_GET['id'];
            // Check if customer is in use
            $query = sprintf(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "customer WHERE category_id = %d",
                intval($id)
            );
            $result = $this->fetchOne($query);
            
            if ($result['count'] > 0) {
                throw new Exception('使用中のため削除できません。');
            }
            $result = $this->query_delete(['id' => $id], $this->table_category);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            } else {
                throw new Exception('カテゴリの削除に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    /**
     * Page handler for customer/import.php (returns view hash).
     */
    function import() {
        return array();
    }

    /**
     * Find customer by name (LIKE) and branch, for given company (or default 大東建託株式会社).
     * Returns row with id or null.
     */
    function get_customer_by_name_contains_and_branch($name, $branch, $company_name = '') {
        $name = trim($name ?? '');
        $branch = trim($branch ?? '');
        if ($name === '') {
            return null;
        }
        $company = trim($company_name ?? '') !== '' ? trim($company_name) : '大東建託株式会社';
        $query = sprintf(
            "SELECT id FROM %s WHERE TRIM(COALESCE(company_name,'')) = '%s' AND name LIKE '%%%s%%' AND TRIM(COALESCE(branch,'')) = '%s' LIMIT 1",
            $this->table,
            $this->quote($company),
            $this->quote($name),
            $this->quote($branch)
        );
        return $this->fetchOne($query);
    }

    /**
     * Add customer via public API (no login).
     * If a customer exists with name containing $name and same branch_name (and company_name), return that id.
     * Otherwise insert with company_name (or default 大東建託株式会社) and return new id.
     * Params: name, branch_name (mapped to branch), company_name (optional, default 大東建託株式会社), category_id (optional, default 2).
     */
    function add_customer_public($params) {
        $hash = array(
            'status' => 'error',
            'message_code' => '',
            'id' => null,
        );
        $name = isset($params['name']) ? trim($params['name']) : '';
        $branch_name = isset($params['branch_name']) ? trim($params['branch_name'] ?? '') : trim($params['branch'] ?? '');
        $company_name = isset($params['company_name']) ? trim($params['company_name']) : '';
        $category_id = isset($params['category_id']) && $params['category_id'] !== '' && $params['category_id'] !== null
            ? intval($params['category_id']) : 2;
        if ($name === '') {
            $hash['message_code'] = 'name is required';
            return $hash;
        }
        if ($branch_name === '') {
            $hash['message_code'] = 'branch_name is required';
            return $hash;
        }
        $company = $company_name !== '' ? $company_name : '大東建託株式会社';
        $existing = $this->get_customer_by_name_contains_and_branch($name, $branch_name, $company);
        if ($existing && !empty($existing['id'])) {
            $hash['status'] = 'success';
            $hash['message_code'] = 'existing';
            $hash['id'] = (int) $existing['id'];
            return $hash;
        }
        $data = array(
            'name' => $name,
            'branch' => $branch_name,
            'company_name' => $company,
            'name_kana' => '',
            'department' => '',
            'position' => '',
            'tel' => '',
            'fax' => '',
            'email' => '',
            'zip' => '',
            'address1' => '',
            'address2' => '',
            'title' => '様',
            'company_name_kana' => '',
            'category_id' => $category_id,
            'guis_department' => '',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 0,
            'memo' => '',
        );
        $new_id = $this->query_insert($data);
        if ($new_id) {
            $hash['status'] = 'success';
            $hash['message_code'] = 'created';
            $hash['id'] = (int) $new_id;
        } else {
            $hash['message_code'] = 'insert failed';
        }
        return $hash;
    }

    /**
     * Get existing customer by company_name + branch + name (trùng = company_name, branch, name).
     * Returns row with id or null.
     */
    function get_customer_by_company_branch_name($company_name, $branch, $name) {
        $company_name = trim($company_name);
        $branch = trim($branch ?? '');
        $name = trim($name);
        if ($company_name === '' || $name === '') {
            return null;
        }
        $query = sprintf(
            "SELECT id FROM %s WHERE TRIM(company_name) = '%s' AND TRIM(COALESCE(branch,'')) = '%s' AND TRIM(name) = '%s' LIMIT 1",
            $this->table,
            $this->quote( $company_name ),
            $this->quote( $branch ),
            $this->quote( $name )
        );
        return $this->fetchOne($query);
    }

    /**
     * Get customer by id. Returns row with company_name, branch, name (or null).
     */
    function get_customer_by_id($id) {
        $id = intval($id);
        if ($id <= 0) {
            return null;
        }
        $query = sprintf(
            "SELECT id, company_name, branch, name FROM %s WHERE id = %d LIMIT 1",
            $this->table,
            $id
        );
        return $this->fetchOne($query);
    }

    /**
     * Import customers from JSON rows. Trùng (company_name + branch + name) thì update, chưa có thì insert.
     */
    function import_customers() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
            'inserted' => 0,
            'updated' => 0,
            'errors' => array(),
        );
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || !isset($input['rows'])) {
            $hash['message_code'] = 'Invalid input. Expected JSON with "rows" array.';
            return $hash;
        }
        $rows = $input['rows'];
        if (empty($rows)) {
            $hash['status'] = 'success';
            $hash['message_code'] = 'No rows to import.';
            return $hash;
        }
        $inserted = 0;
        $updated = 0;
        $errors = array();
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // 1-based + header row
            $category_id = isset($row['category_id']) ? intval($row['category_id']) : 0;
            $company_name = isset($row['company_name']) ? trim($row['company_name']) : '';
            $branch = isset($row['branch']) ? trim($row['branch']) : '';
            $name = isset($row['name']) ? trim($row['name']) : '';
            if ($company_name === '' || $name === '') {
                $errors[] = "Dòng $rowNum: Thiếu công ty hoặc tên liên hệ.";
                continue;
            }
            if ($category_id <= 0) {
                $errors[] = "Dòng $rowNum: category_id không hợp lệ.";
                continue;
            }
            $guis_department = '';
            if (isset($row['guis_department'])) {
                if (is_array($row['guis_department'])) {
                    $guis_department = implode(',', array_map('intval', $row['guis_department']));
                } else {
                    $guis_department = preg_replace('/[^0-9,]/', '', $row['guis_department']);
                }
            }
            $existing = $this->get_customer_by_company_branch_name($company_name, $branch, $name);
            if ($existing && !empty($existing['id'])) {
                $data = array(
                    'name' => $name,
                    'name_kana' => isset($row['name_kana']) ? $row['name_kana'] : '',
                    'department' => isset($row['department']) ? $row['department'] : '',
                    'branch' => $branch,
                    'position' => isset($row['position']) ? $row['position'] : '',
                    'tel' => isset($row['tel']) ? $row['tel'] : '',
                    'fax' => isset($row['fax']) ? $row['fax'] : '',
                    'email' => isset($row['email']) ? $row['email'] : '',
                    'zip' => isset($row['zip']) ? $row['zip'] : '',
                    'address1' => isset($row['address1']) ? $row['address1'] : '',
                    'address2' => isset($row['address2']) ? $row['address2'] : '',
                    'title' => isset($row['title']) ? $row['title'] : '様',
                    'company_name' => $company_name,
                    'company_name_kana' => isset($row['company_name_kana']) ? $row['company_name_kana'] : '',
                    'category_id' => $category_id,
                    'guis_department' => $guis_department,
                    'status' => isset($row['status']) ? intval($row['status']) : 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $_SESSION['userid'],
                    'memo' => isset($row['memo']) ? $row['memo'] : '',
                );
                try {
                    $result = $this->query_update($data, array('id' => $existing['id']));
                    if ($result) {
                        $updated++;
                    } else {
                        $errors[] = "Dòng $rowNum: Lỗi cập nhật DB.";
                    }
                } catch (Exception $e) {
                    $errors[] = "Dòng $rowNum: " . $e->getMessage();
                }
            } else {
                $data = array(
                    'name' => $name,
                    'name_kana' => isset($row['name_kana']) ? $row['name_kana'] : '',
                    'department' => isset($row['department']) ? $row['department'] : '',
                    'branch' => $branch,
                    'position' => isset($row['position']) ? $row['position'] : '',
                    'tel' => isset($row['tel']) ? $row['tel'] : '',
                    'fax' => isset($row['fax']) ? $row['fax'] : '',
                    'email' => isset($row['email']) ? $row['email'] : '',
                    'zip' => isset($row['zip']) ? $row['zip'] : '',
                    'address1' => isset($row['address1']) ? $row['address1'] : '',
                    'address2' => isset($row['address2']) ? $row['address2'] : '',
                    'title' => isset($row['title']) ? $row['title'] : '様',
                    'company_name' => $company_name,
                    'company_name_kana' => isset($row['company_name_kana']) ? $row['company_name_kana'] : '',
                    'category_id' => $category_id,
                    'guis_department' => $guis_department,
                    'status' => isset($row['status']) ? intval($row['status']) : 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $_SESSION['userid'],
                    'memo' => isset($row['memo']) ? $row['memo'] : '',
                );
                try {
                    $result = $this->query_insert($data);
                    if ($result) {
                        $inserted++;
                    } else {
                        $errors[] = "Dòng $rowNum: Lỗi ghi DB.";
                    }
                } catch (Exception $e) {
                    $errors[] = "Dòng $rowNum: " . $e->getMessage();
                }
            }
        }
        $hash['status'] = 'success';
        $hash['message_code'] = 'success';
        $hash['inserted'] = $inserted;
        $hash['updated'] = $updated;
        $hash['errors'] = $errors;
        return $hash;
    }

    function add_customer() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        $guis_department = '';
        if (isset($_POST['guis_department']) && is_array($_POST['guis_department'])) {
            $guis_department = implode(',', $_POST['guis_department']);
        } elseif (isset($_POST['guis_department']) && !empty($_POST['guis_department'])) {
            $guis_department = $_POST['guis_department'];
        }
        try {
            $data = array(
                'name' => $_POST['name'],
                'name_kana' => $_POST['name_kana'],
                'department' => $_POST['department'],
                'branch' => $_POST['branch'],
                'position' => $_POST['position'],
                'tel' => $_POST['tel'],
                'fax' => $_POST['fax'],
                'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
                'email' => $_POST['email'],
                'zip' => $_POST['zip'],
                'address1' => $_POST['address1'],
                'address2' => $_POST['address2'],
                'title' => $_POST['title'],
                'company_name' => $_POST['company_name'],
                'company_name_kana' => $_POST['company_name_kana'],
                'category_id' => $_POST['category_id'],
                'guis_department' => $guis_department,
                'status' => $_POST['status'],
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['userid'],
                'memo' => $_POST['memo']
            );
            $result = $this->query_insert($data);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    function edit_customer() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        $guis_department = '';
        if (isset($_POST['guis_department']) && is_array($_POST['guis_department'])) {
            $guis_department = implode(',', $_POST['guis_department']);
        } elseif (isset($_POST['guis_department']) && !empty($_POST['guis_department'])) {
            $guis_department = $_POST['guis_department'];
        }
        try {
            $id = $_GET['id'];
            $data = array(
                'name' => $_POST['name'],
                'name_kana' => $_POST['name_kana'],
                'department' => $_POST['department'],
                'branch' => $_POST['branch'],
                'position' => $_POST['position'],
                'tel' => $_POST['tel'],
                'fax' => $_POST['fax'],
                'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
                'email' => $_POST['email'],
                'zip' => $_POST['zip'],
                'address1' => $_POST['address1'],
                'address2' => $_POST['address2'],
                'title' => $_POST['title'],
                'category_id' => $_POST['category_id'],
                'company_name' => $_POST['company_name'],
                'company_name_kana' => $_POST['company_name_kana'],
                'guis_department' => $guis_department,
                'status' => $_POST['status'],
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['userid'],
                'memo' => $_POST['memo']
            );
            $result = $this->query_update($data, ['id' => $id]);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            } else {
                throw new Exception('顧客の更新に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    function delete_customer() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $id = $_GET['id'];
            // Check if customer is referenced by parent projects (order projects)
            $query = sprintf(
                "SELECT COUNT(*) as count 
                FROM " . DB_PREFIX . "parent_projects 
                WHERE customer_id = %d 
                AND (status IS NULL OR status != 'deleted')",
                intval($id)
            );
            $result = $this->fetchOne($query);
            
            if ($result['count'] > 0) {
                throw new Exception('この顧客は案件で使用中のため削除できません。');
            }
            $result = $this->query_delete(['id' => $id]);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            } else {
                throw new Exception('顧客の削除に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    function get() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $id = $_GET['id'];
            $query = sprintf(
                "SELECT c.*
                FROM {$this->table} c
                WHERE c.id = %d",
                intval($id)
            );
            $result = $this->fetchOne($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                throw new Exception('顧客の取得に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    function list_customer() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $category_id = $_GET['category_id'];
            $query = sprintf(
                "SELECT c.*
                FROM {$this->table} c
                WHERE c.category_id = %d
                ORDER BY c.id ASC",
                intval($category_id)
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                throw new Exception('担当者の取得に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get all customer categories
    function list_categories() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $query = "SELECT * FROM " . DB_PREFIX . "customer_category ORDER BY arrange ASC, id ASC";
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                throw new Exception('カテゴリの取得に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get unique company names
    function list_companies() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $where = '';
            if ($search) {
                $where = "WHERE company_name LIKE '%$search%' OR company_name_kana LIKE '%$search%'";
            }
            
            $query = "SELECT DISTINCT company_name FROM " . DB_PREFIX . "customer WHERE company_name IS NOT NULL AND company_name != '' $where ORDER BY company_name ASC";
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get all customer contacts
    function list_contacts() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $where = '';
            if ($search) {
                $where = "WHERE name LIKE '%$search%' OR name_kana LIKE '%$search%'";
            }
            
            $query = "SELECT id, name FROM " . DB_PREFIX . "customer WHERE name IS NOT NULL AND name != '' $where ORDER BY name ASC";
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get companies by category
    function list_companies_by_category() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $category_id = $_GET['category_id'];
            $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            $search = $_GET['search'];
            $where = '';
            if ($category_id) {
                $where .= " AND category_id = '$category_id'";
            }
            // if ($department_id) {
            //     $where .= " AND FIND_IN_SET('$department_id', guis_department) > 0";
            // }
            if ($search) {
                $where .= " AND (company_name LIKE '%$search%' OR company_name_kana LIKE '%$search%')";
            }
            $where = ltrim($where, ' AND');

            $query = sprintf(
                "SELECT DISTINCT company_name, MIN(id) as id
                FROM %scustomer 
                WHERE %s
                AND company_name IS NOT NULL 
                AND company_name != '' 
                GROUP BY company_name
                ORDER BY company_name ASC",
                DB_PREFIX,
                $where
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                throw new Exception('会社名の取得に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get contacts by company
    function list_contacts_by_company() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $company_name = $_GET['company_name'];
            $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            $search = $_GET['search'];
            $where = '';
            if ($company_name) {
                $where .= " AND company_name = '$company_name'";
            }
            // if ($department_id) {
            //     $where .= " AND FIND_IN_SET('$department_id', guis_department) > 0";
            // }
            if ($search) {
                $where .= " AND (name LIKE '%$search%' OR name_kana LIKE '%$search%')";
            }
            $where = ltrim($where, ' AND');
            $query = sprintf(
                "SELECT id, name 
                FROM %scustomer 
                WHERE %s
                ORDER BY id ASC",
                DB_PREFIX,
                $where
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                throw new Exception('担当者名の取得に失敗しました。');
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get companies by department
    function list_companies_by_department() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            if (!$department_id) {
                throw new Exception('部署IDが必要です。');
            }

            $where = "FIND_IN_SET('$department_id', guis_department) > 0";
            if ($search) {
                $where .= " AND (company_name LIKE '%$search%' OR company_name_kana LIKE '%$search%')";
            }

            $query = sprintf(
                "SELECT DISTINCT company_name, id
                FROM %scustomer 
                WHERE %s
                AND company_name IS NOT NULL 
                AND company_name != '' 
                ORDER BY company_name ASC",
                DB_PREFIX,
                $where
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get contacts by department
    function list_contacts_by_department() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            if (!$department_id) {
                throw new Exception('部署IDが必要です。');
            }

            $where = "FIND_IN_SET('$department_id', guis_department) > 0";
            if ($search) {
                $where .= " AND (name LIKE '%$search%' OR name_kana LIKE '%$search%')";
            }

            $query = sprintf(
                "SELECT id, name, company_name
                FROM %scustomer 
                WHERE %s
                AND name IS NOT NULL 
                AND name != '' 
                ORDER BY name ASC",
                DB_PREFIX,
                $where
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get branches by company name
    function list_branches_by_company() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $company_name = isset($_GET['company_name']) ? $_GET['company_name'] : '';
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            if (!$company_name) {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                return $hash;
            }

            $where = "company_name = '$company_name'";
            if ($search) {
                $where .= " AND (branch LIKE '%$search%' OR department LIKE '%$search%')";
            }

            $query = sprintf(
                "SELECT DISTINCT branch 
                FROM %scustomer 
                WHERE %s
                AND branch IS NOT NULL 
                AND branch != '' 
                ORDER BY branch ASC",
                DB_PREFIX,
                $where
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // Get contacts by company and branch
    function list_contacts_by_company_branch() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $company_name = isset($_GET['company_name']) ? $_GET['company_name'] : '';
            $branch_name = isset($_GET['branch_name']) ? $_GET['branch_name'] : '';
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            if (!$company_name) {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                return $hash;
            }

            $where = "company_name = '$company_name'";
            if ($branch_name) {
                $where .= " AND branch = '$branch_name'";
            }
            if ($search) {
                $where .= " AND (name LIKE '%$search%' OR name_kana LIKE '%$search%')";
            }

            $query = sprintf(
                "SELECT DISTINCT id, name 
                FROM %scustomer 
                WHERE %s
                AND name IS NOT NULL 
                AND name != '' 
                ORDER BY name ASC",
                DB_PREFIX,
                $where
            );
            $result = $this->fetchAll($query);
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
                $hash['data'] = $result;
            } else {
                $hash['data'] = [];
                $hash['status'] = 'success';
                $hash['message_code'] = 'success';
            }
        } catch (Exception $e) {
            $hash['data'] = [];
            $hash['message_code'] = $e->getMessage();
        }
        return $hash;
    }

    // 会社
    // function list_company() {
    //     $category_id = $_GET['category_id'];
    //     $query = sprintf(
    //         "SELECT c.*, 
    //         (SELECT COUNT(*) FROM " . DB_PREFIX . "customer WHERE company_id = c.id) as num_customers
    //         FROM " . DB_PREFIX . "customer_company c
    //         WHERE c.category_id = %d
    //         ORDER BY c.id ASC",
    //         intval($category_id)
    //     );
    //     return $this->fetchAll($query);
    // }

    // function add_company() {
    //     $hash = array(
    //         'status' => 'error',
    //         'message_code' => 'error',
    //     );
    //     try {
    //         $data = array(
    //             'name' => $_POST['name'],
    //             'name_kana' => $_POST['name_kana'],
    //             'tel' => $_POST['tel'],
    //             'fax' => $_POST['fax'],
    //             'email' => $_POST['email'],
    //             'zip' => $_POST['zip'],
    //             'address1' => $_POST['address1'],
    //             'address2' => $_POST['address2'],
    //             'memo' => $_POST['memo'],
    //             'category_id' => $_POST['category_id'],
    //         );
    //         $result = $this->query_insert($data, $this->table_company);
    //         if ($result) {
    //             $hash['status'] = 'success';
    //             $hash['message_code'] = 'success';
    //         } else {
    //             throw new Exception('会社の追加に失敗しました。');
    //         }
    //     } catch (Exception $e) {
    //         $hash['message_code'] = $e->getMessage();
    //     }
    //     return $hash;
    // }

    // function edit_company() {
    //     $hash = array(
    //         'status' => 'error',
    //         'message_code' => 'error',
    //     );
    //     try {
    //         $id = $_GET['id'];
    //         $data = array(
    //             'name' => $_POST['name'],
    //             'name_kana' => $_POST['name_kana'],
    //             'tel' => $_POST['tel'],
    //             'fax' => $_POST['fax'],
    //             'email' => $_POST['email'],
    //             'zip' => $_POST['zip'],
    //             'address1' => $_POST['address1'],
    //             'address2' => $_POST['address2'],
    //             'memo' => $_POST['memo'],
    //             'category_id' => $_POST['category_id'],
    //         );
    //         $result = $this->query_update($data, ['id' => $_GET['id']], $this->table_company);
    //         if ($result) {
    //             $hash['status'] = 'success';
    //             $hash['message_code'] = 'success';
    //         } else {
    //             throw new Exception('会社の更新に失敗しました。');
    //         }
    //     } catch (Exception $e) {
    //         $hash['message_code'] = $e->getMessage();
    //     }
    //     return $hash;
    // }

    // function delete_company() {
    //     $hash = array(
    //         'status' => 'error',
    //         'message_code' => 'error',
    //     );
    //     try {
    //         $id = $_GET['id'];
    //         // Check if customer is in use
    //         $query = sprintf(
    //             "SELECT COUNT(*) as count FROM " . DB_PREFIX . "customer WHERE company_id = %d",
    //             intval($id)
    //         );
    //         $result = $this->fetchOne($query);
            
    //         if ($result['count'] > 0) {
    //             throw new Exception('使用中のため削除できません。');
    //         }
    //         $result = $this->query_delete(['id' => $id], $this->table_company);
    //         if ($result) {
    //             $hash['status'] = 'success';
    //             $hash['message_code'] = 'success';
    //         } else {
    //             throw new Exception('会社の削除に失敗しました。');
    //         }
    //     } catch (Exception $e) {
    //         $hash['message_code'] = $e->getMessage();
    //     }
    //     return $hash;
    // }
} 