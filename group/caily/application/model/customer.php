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
            'department' => array(),
            'position' => array(),
            'tel' => array(),
            'fax' => array(),
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
            "SELECT c.*, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "customer WHERE category_id = c.id) as num_customers
            FROM " . DB_PREFIX . "customer_category c
            ORDER BY c.id ASC"
        );
        return $this->fetchAll($query);
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

    function add_customer() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        $guis_department = implode(',', $_POST['guis_department']);
        try {
            $data = array(
                'name' => $_POST['name'],
                'name_kana' => $_POST['name_kana'],
                'department' => $_POST['department'],
                'position' => $_POST['position'],
                'tel' => $_POST['tel'],
                'fax' => $_POST['fax'],
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
                'created_by' => $_SESSION['user_id'],
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
        $guis_department = implode(',', $_POST['guis_department']);
        try {
            $id = $_GET['id'];
            $data = array(
                'name' => $_POST['name'],
                'name_kana' => $_POST['name_kana'],
                'department' => $_POST['department'],
                'position' => $_POST['position'],
                'tel' => $_POST['tel'],
                'fax' => $_POST['fax'],
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
                'updated_by' => $_SESSION['user_id'],
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
            // Check if customer is in use
            $query = sprintf(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "projects WHERE customer_id = %d",
                intval($id)
            );
            $result = $this->fetchOne($query);
            
            if ($result['count'] > 0) {
                throw new Exception('使用中のため削除できません。');
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
                "SELECT c.*, 
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
            $query = "SELECT * FROM " . DB_PREFIX . "customer_category ORDER BY id ASC";
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
            $query = "SELECT DISTINCT company_name FROM " . DB_PREFIX . "customer WHERE company_name IS NOT NULL AND company_name != '' ORDER BY company_name ASC";
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

    // Get all customer contacts
    function list_contacts() {
        $hash = array(
            'status' => 'error',
            'message_code' => 'error',
        );
        try {
            $query = "SELECT id, name FROM " . DB_PREFIX . "customer WHERE name IS NOT NULL AND name != '' ORDER BY name ASC";
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
            if ($department_id) {
                $where .= " AND FIND_IN_SET('$department_id', guis_department) > 0";
            }
            if ($search) {
                $where .= " AND (company_name LIKE '%$search%' OR company_name_kana LIKE '%$search%')";
            }
            $where = ltrim($where, ' AND');

            $query = sprintf(
                "SELECT DISTINCT company_name, id
                FROM %scustomer 
                WHERE %s
                AND company_name IS NOT NULL 
                AND company_name != '' 
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
            if ($department_id) {
                $where .= " AND FIND_IN_SET('$department_id', guis_department) > 0";
            }
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