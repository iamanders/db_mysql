<?php
	/**
	 * Database abstraction layer for MySQL
	 * @package db_mysql
	 * @author Anders <anders@iamanders.com>
	 */
	class db_mysql {

		private $host;
		private $user;
		private $password;
		private $database;
		private $port;
		private $charset;

		public $db_handle;
		public $debug;

		public $no_queries;

		public function __construct($host, $user, $password, $database, $port = 3306, $debug = false, $auto_connect = true, $charset = 'utf8') {
			$this->host = $host;
			$this->user = $user;
			$this->password = $password;
			$this->database = $database;
			$this->port = $port;
			$this->charset = $charset;

			$this->debug = $debug;

			$this->no_queries = 0;

			if($auto_connect) { $this->connect(); }
		}

		public function connect() {
			try {
				@$temp_db = new mysqli($this->host, $this->user, $this->password, $this->database, $this->port);
				if (mysqli_connect_error()) {
					throw new Exception(mysqli_connect_errno() . " - " . mysqli_connect_error());
				}
			} catch (Exception $e) {
				throw $e;
			}
			
			$this->db_handle = $temp_db;
			
			$this->db_handle->query("SET NAMES '" . $this->charset . "'");
			$this->db_handle->query("SET CHARACTER SET " . $this->charset);
			$this->db_handle->set_charset($this->charset);
		}

		public function disconnect() {
			$this->db_handle->close();
		}

		public function escape($string) {
			return $this->db_handle->real_escape_string($string);
		}


		public function select($what = null) {
			$this->no_queries++;
			return new db_mysql_select($this->db_handle, $what);
		}

		public function insert($table, $values) {
			$this->no_queries++;
			return new db_mysql_insert($this->db_handle, $table, $values);
		}

		public function update($table, $values, $where = null) {
			$this->no_queries++;
			return new db_mysql_update($this->db_handle, $table, $values, $where);
		}

		public function delete() {
			$this->no_queries++;
			return new db_mysql_delete($this->db_handle);
		}

		/**
		 * Execute misc sql query
		 * @param $sql string sql query
		 * @return bool
		 */
		public function sql_no_return($sql) {
			if($this->db_handle->query($sql)) {
				return true;
			} else {
				throw new Exception("Could not execute query: " . $this->db_handle->errno . ' - ' . $this->db_handle->error . ' - ' . $sql);
			}
			return false;
		}
	}


	/**
	 * Database abstraction layer for executing SELECT queries
	 * @package db_mysql
	 * @author Anders <anders@iamanders.com>
	 */
	class db_mysql_select {

		private $select;
		private $from;
		private $join;
		private $where;
		private $groupby;
		private $having;
		private $orderby;
		private $limit;

		private $db_handle;
		private $sql;

		public function __construct($db_handle, $what = null) {
			$this->db_handle = $db_handle;
			$this->select = $what;
		}

		public function from($what) {
			if(!$this->from) { $this->from = array(); }
			$this->from[] = $what;
			return $this;
		}

		public function join($what, $where, $type = '') {
			if(!$this->join) { $this->join = array(); }
			$this->join[] = array($what, $where, $type);
			return $this;
		}

		public function where($what) {
			if(!$this->where) { $this->where = array(); }
			if(is_array($what)) {
				foreach($what as $w) { $this->where[] = $w; }
			} else {
				$this->where[] = $what;
			}
			return $this;
		}

		public function groupby($what) {
			$this->groupby = $what;
			return $this;
		}

		public function having($what) {
			$this->having = $what;
			return $this;
		}

		public function orderby($what) {
			$this->orderby = $what;
			return $this;
		}

		public function limit($what) {
			$this->limit = $what;
			return $this;
		}

		public function sql() {
			$temp_sql = 'SELECT ';
			if($this->select) { $temp_sql .= $this->select . "\n"; } else { $temp_sql .= "*\n"; }
			if($this->from) {
				$temp_sql .= 'FROM ';
				foreach($this->from as $f) {$temp_sql .= $f . ",\n"; }
				$temp_sql = mb_substr($temp_sql, 0, -2) . "\n";
			}
			if($this->join) {
				foreach($this->join as $j) {
					if(mb_strlen($j[2]) > 0) { $j[2] .= ' '; }
					$temp_sql .= $j[2] . 'JOIN ' . $j[0] . ' ON ' . $j[1] . "\n";
				}
			}
			if($this->where) {
				$temp_sql .= 'WHERE ';
				foreach($this->where as $w) { $temp_sql .= $w . " AND\n"; }
				$temp_sql = mb_substr($temp_sql, 0, -5) . "\n";
			}
			if($this->groupby) {
				$temp_sql .= 'GROUP BY ' . $this->groupby . "\n";
			}
			if($this->having) {
				$temp_sql .= 'HAVING ' . $this->having . "\n";
			}
			if($this->orderby) {
				$temp_sql .= 'ORDER BY ' . $this->orderby . "\n";
			}
			if($this->limit) {
				$temp_sql .= 'LIMIT ' . $this->limit . "\n";
			}
			
			$this->sql = $temp_sql;
			return $this->sql;
		}

		/**
		 * Get all matching rows
		 * @return array with objects
		 */
		public function get_all() {
			$this->sql(); //Calculate sql

			$result = array();

			if($query = $this->db_handle->query($this->sql)) {
				while($row = $query->fetch_object()) {
					$result[] = $row;
				}
			} else {
				throw new Exception("Could not execute query: " . $this->db_handle->errno . ' - ' . $this->db_handle->error . ' - ' . $this->sql);
			}

			return $result;
		}
					

		/**
		 * Get one/first row
		 * @return object or null
		 */
		public function get_one() {
			$this->sql(); //Calculate sql

			$result = array();

			if($query = $this->db_handle->query($this->sql)) {
				$result = $query->fetch_object();
			} else {
				throw new Exception("Could not execute query: " . $this->db_handle->errno . ' - ' . $this->db_handle->error . ' - ' . $this->sql);
			}

			if($result) {
				return $result;
			} else { return null; }
		}

	}


	
	/**
	 * Database abstraction layer for executing INSERT queries
	 * @package db_mysql
	 * @author Anders <anders@iamanders.com>
	 */
	class db_mysql_insert {

		private $table;
		private $values;

		private $db_handle;
		private $sql;

		public function __construct($db_handle, $table, $values) {
			$this->db_handle = $db_handle;
			$this->table = $table;
			$this->values = $values;
		}

		private function escape($string) {
			return $this->db_handle->real_escape_string($string);
		}

		public function run() {
			$this->sql(); //Calculate sql

			if($this->db_handle->query($this->sql)) {
				return $this->db_handle->insert_id;
			} else {
				throw new Exception("Could not execute query: " . $this->db_handle->errno . ' - ' . $this->db_handle->error . ' - ' . $this->sql);
			}

			return null;
		}

		public function sql() {
			$temp_columns = '';
			$temp_values = '';
			foreach($this->values as $vk => $vv) {
				$temp_columns .= $vk . ', ';
				if(is_int($vv) || is_float($vv)) {
					$temp_values .= $vv . ', ';
				} else {
					$temp_values .= "'" . $this->escape($vv) . "', ";
				}
			}
			$temp_columns = mb_substr($temp_columns, 0, -2);
			$temp_values = mb_substr($temp_values, 0, -2);
			
			$temp_sql = 'INSERT INTO ' . $this->table . ' (' . $temp_columns . ') VALUES (' . $temp_values . ');';

			$this->sql = $temp_sql;
			return $this->sql;
		}

	}


	/**
	 * Database abstraction layer for executing UPDATE queries
	 * @package db_mysql
	 * @author Anders <anders@iamanders.com>
	 */
	class db_mysql_update {
		
		private $table;
		private $values;
		private $where;

		private $db_handle;
		private $sql;

		public function __construct($db_handle, $table, $values, $where) {
			$this->db_handle = $db_handle;
			$this->table = $table;
			$this->values = $values;
			if(is_array($where)) {
				$this->where = $where;
			} else {
				$this->where = array($where);
			}
		}
		
		private function escape($string) {
			return $this->db_handle->real_escape_string($string);
		}

		public function run() {
			$this->sql(); //Calculate sql

			if($this->db_handle->query($this->sql)) {
				return $this->db_handle->affected_rows;
			} else {
				throw new Exception("Could not execute query: " . $this->db_handle->errno . ' - ' . $this->db_handle->error . ' - ' . $this->sql);
			}

			return null;
		}

		public function sql() {
			$temp_where = '';
			$temp_values = '';

			foreach($this->values as $vk => $vv) {
				$temp_values .= $vk . " = ";
				if(is_int($vv)) {
					$temp_values .= $vv;
				} else {
					$temp_values .= "'" . $this->escape($vv) . "'";
				}
				$temp_values .= ', ';
			}

			if($this->where) {
				foreach($this->where as $w) {
					$temp_where .= $w . ' AND ';
				}
				if(mb_strlen($temp_where) > 0) { $temp_where = mb_substr($temp_where, 0, -5); }
			}

			if(mb_strlen($temp_values) > 0) { $temp_values = mb_substr($temp_values, 0, -2); }
			$temp_sql = 'UPDATE ' . $this->table . ' SET ' . $temp_values;
			if($temp_where && mb_strlen($temp_where) > 0) { $temp_sql .= ' WHERE ' . $temp_where; }

			$this->sql = $temp_sql;
			return $this->sql;
		}

	}


	/**
	 * Database abstraction layer for executing DELETE queries
	 * @package db_mysql
	 * @author Anders <anders@iamanders.com>
	 */
	class db_mysql_delete {
		
		private $table;
		private $where;

		private $db_handle;
		private $sql;

		public function __construct($db_handle) {
			$this->db_handle = $db_handle;
			$this->where = null;
		}

		private function escape($string) {
			return $this->db_handle->real_escape_string($string);
		}

		public function from($table) {
			$this->table = $table;
			return $this;
		}

		public function where($where) {
			if(is_array($where)) {
				$this->where = $where;
			} else {
				$this->where = array($where);
			}
			return $this;
		}

		public function run() {
			$this->sql(); //Calculate sql
			
			if($this->db_handle->query($this->sql)) {
				return $this->db_handle->affected_rows;
			} else {
				throw new Exception("Could not execute query: " . $this->db_handle->errno . ' - ' . $this->db_handle->error . ' - ' . $this->sql);
			}

			return null;
		}

		public function sql() {

			if(!$this->table) { throw new Exception('No table selected'); }

			$temp_sql = 'DELETE FROM ' . $this->table;
			if($this->where && count($this->where) > 0) {
				$temp_sql .= ' WHERE ';
				foreach($this->where as $w) {
					$temp_sql .= $w . ' AND ';
				}
				$temp_sql = mb_substr($temp_sql, 0, -5);
			}

			$this->sql = $temp_sql;
			return $this->sql;
		}
	}


	/**
	 * Database abstraction layer for altering database
	 * @package db_mysql
	 * @author Anders <anders@iamanders.com>
	 */
	 class db_mysql_alter {
	 	
	 	public function add_column() { throw new Exception('TODO'); }
	 	public function delete_column() { throw new Exception('TODO'); }

	 	public function add_table() { throw new Exception('TODO'); }
	 	public function delete_table() { throw new Exception('TODO'); }

	 }
