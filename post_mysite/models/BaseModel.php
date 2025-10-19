<?php
/**
 * Enhanced File Sharing System - Base Model Class
 * Provides common functionality for all models
 */

require_once __DIR__ . '/Database.php';

abstract class BaseModel {
    protected $table;
    protected $primary_key = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $timestamps = true;
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function find($id) {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE {$this->primary_key} = ?",
            [$id]
        );
    }

    public function findBy($field, $value) {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE {$field} = ?",
            [$value]
        );
    }

    public function all() {
        return $this->db->select("SELECT * FROM {$this->table}");
    }

    public function where($conditions, $params = []) {
        $where_clause = '';
        foreach ($conditions as $field => $value) {
            if ($where_clause) $where_clause .= ' AND ';
            $where_clause .= "{$field} = ?";
            $params[] = $value;
        }

        return $this->db->select(
            "SELECT * FROM {$this->table} WHERE {$where_clause}",
            $params
        );
    }

    public function create($data) {
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $id = $this->db->insert($this->table, $data);
        return $this->find($id);
    }

    public function update($id, $data) {
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $where = "{$this->primary_key} = ?";
        $this->db->update($this->table, $data, $where, [$id]);

        return $this->find($id);
    }

    public function delete($id) {
        $where = "{$this->primary_key} = ?";
        return $this->db->delete($this->table, $where, [$id]);
    }

    public function count($conditions = null) {
        if ($conditions) {
            $where_clause = '';
            $params = [];
            foreach ($conditions as $field => $value) {
                if ($where_clause) $where_clause .= ' AND ';
                $where_clause .= "{$field} = ?";
                $params[] = $value;
            }
            return $this->db->rowCount("SELECT COUNT(*) as count FROM {$this->table} WHERE {$where_clause}", $params);
        }

        return $this->db->rowCount("SELECT COUNT(*) as count FROM {$this->table}");
    }

    public function exists($conditions) {
        $where_clause = '';
        $params = [];
        foreach ($conditions as $field => $value) {
            if ($where_clause) $where_clause .= ' AND ';
            $where_clause .= "{$field} = ?";
            $params[] = $value;
        }

        return $this->db->exists($this->table, $where_clause, $params);
    }

    public function paginate($per_page = 15, $page = 1) {
        $offset = ($page - 1) * $per_page;

        $total = $this->count();
        $data = $this->db->select(
            "SELECT * FROM {$this->table} LIMIT ? OFFSET ?",
            [$per_page, $offset]
        );

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $per_page,
            'current_page' => $page,
            'last_page' => ceil($total / $per_page),
            'from' => $offset + 1,
            'to' => min($offset + $per_page, $total)
        ];
    }

    protected function sanitizeData($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable) || empty($this->fillable)) {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    protected function hideFields($data) {
        if (empty($this->hidden) || !is_array($data)) {
            return $data;
        }

        $result = $data;
        foreach ($this->hidden as $field) {
            if (isset($result[$field])) {
                unset($result[$field]);
            }
        }
        return $result;
    }
}
