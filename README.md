# MySQL database abstraction layer for PHP5

## Install:

```PHP
include('db_mysql.php');
$db = new db_mysql('host', 'user', 'password', 'database', 3306, true);
```

## Examples:

### Get all matching rows

```PHP
$result = $db->select('a, b, c')->from('users')->where('age > 18')->get_all();
```

### Get first matching row

```PHP
$result = $db->select()
			->from('orders o')
			->join('users u', 'o.user_id = u.id')
			->where('o.status_id = 1')
			->orderby('o.id DESC')->get_one();
```

GROUP BY and HAVING method is also available.

### Update rows

```PHP
$no_updated_rows = $db->update('test_table', array('name' => 'Bart'), 'id = 53');
```

### Delete rows

```PHP
$no_deleted_rows = $db->delete()->from('test_table')->where('id = 53')->run();
```
