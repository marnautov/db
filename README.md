# PDOAdapter

The PDOAdapter class is a class that implements the DbInterface interface, which provides a set of methods for interacting with a database. The class uses the PDO library to connect to a database and execute queries. Here's an overview of the class and its methods:

## Class: PDOAdapter Properties: 

**$db**: An instance of the PDO class that represents the database connection. 

**$cache**: A cache object used for caching query results. (This cache object is PSR-16 compatible, and an example of such an object is Memcached)

## Methods: 

**__construct(PDO $db, $cache = false)** 
Constructor method that initializes the class properties. Takes a PDO object as its first parameter and an optional cache object (PSR-16 compatible) as its second parameter.

    $pdo = new PDO('mysql:dbname=test;host=127.0.0.1', 'username', 'password');
    $db = new PDOAdapter($pdo, $memcached);


**cache($timeout)** 
Sets the cache timeout value and returns the object instance.

    $db->cache(86400)->rows("SELECT * FROM articles ORDER BY date DESC LIMIT 10");

**query(string $sql, array $vars = array())** 
Executes the given SQL query with the given parameters and returns a statement object.

**row(string $sql, array $vars = array())** 
Executes the given SQL query with the given parameters and returns the first row of the result set as an associative array.

    $article = $db->row("SELECT * FROM articles WHERE id=?", [$id]);

**rows(string $sql, array $vars = array())** 
Executes the given SQL query with the given parameters and returns all rows of the result set as an array of associative arrays.

    $articles = $db->rows("SELECT * FROM articles WHERE status=?", ['OK']);

**col(string $sql, array $vars = array())** 
Executes the given SQL query with the given parameters and returns the first column of the first row of the result set.

    $maxId = $db->col("SELECT max(id) FROM articles");

**insert($table, $data)** 
Inserts a row into the given table with the given data and returns the last inserted ID.
  

     $insertId = $db->insert('articles', [
            'title' => 'All you need is love',
            'status' => 'OK',
            'date' => $db->now(),
            'created_at' => $db->func('NOW()'),
        ]);


**insertUpdate($table, $data, $updateData = null)**
The insertUpdate method allows inserting a new row into the specified table with the provided data. In case of unique key conflicts (e.g., duplication of values in a unique index), the method updates the existing row in the table based on the provided data. If $updateData is not provided and a key conflict occurs, the data will be updated from $data.

To determine whether data was updated or a new record was added, you can use $db->rowCount(): if it returns 1, a new record was added; if it returns 2, an existing record was updated. This behavior is consistent with PDO's default behavior.

    $insertId = $db->insertUpdate('articles', [
            'title' => 'All you need is love',
            'status' => 'OK',
            'date' => $db->now(),
            'created_at' => $db->func('NOW()'),
        ], ['updated_at' => $db->func('NOW()')]);

**insertIgnore($table, $data)**

Alias for $db->insert($table, $data, ['ignore' => true]);

**replace($table, $data)**

Alias for $db->insert($table, $data, ['replace' => true]);


**update($table, $data, $where, $vars)** 
Updates a row in the given table with the given data and variables and returns the number of rows affected.

    $db->update('articles', [
            'status' => 'BAD',
            'updated_at' => $db->now()
        ], 'id=?', [5]);


**func($mysqlFunction)** 
Returns an object that represents a MySQL function to be used in a query.

    $ins = ['created_at' => $db->func('NOW()')];
    $ins = ['created_at' => $db->func('NOW() + INTERVAL 1 DAY')];

**now()** 
Returns the current date and time in the format "Y-m-d H:i:s". It provides a simple way to replace the MySQL function `NOW()`, and an alternative variant is to use `$db->func('NOW()')`."

    $ins = ['created_at' => $db->now()];

**fetch()** 
Returns the last fetched row of the result set as an associative array.

    $db->query("SELECT * from articles WHERE status=?",['OK']);
    while($row = $db->fetch()){
    	$row['text'] = $db->row("SELECT text FROM articles_text WHERE id=?", $row['id']);
    	if ($row['id']>100) break;
    }

Overall, the PDOAdapter class provides a simple and flexible way to interact with a database using PDO. Its methods allow for easy querying of the database and manipulation of data, and its caching feature helps to improve performance by reducing the number of database queries.