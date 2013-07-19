BasicORM
========

A basic ORM implemented in PHP

Example usage
----------

### Model

NOTE all public properties must reflect how it is named in database.

```php
class T extends \ORM\Model {
    protected static $ids = array("id");
    
    public $id;
    public $message;
}

class_add('T');
```

ids is overidden from \ORM\Model that lists all ids used in the class.

### Get data from database

```php
$clasz = T::get(array('id' => 30));
```
The array provided is an associative array which emcompasses the ids and the values.
Notice this is static on the class itself.

get method also supports plain sql and paramaters towards it in the form:
```php
$clasz = T::get('SELECT * FROM T WHERE id=?', array(30));
```

### Create new

```php
$clasz = T::create(['message' => 'test']);
```
Creates a new T instance with message property having the value test.

### Save and delete

There is no update method. That is performed by save itself.

```php
$clasz->save();
$clasz->delete();
```
Very simple usage on an instance.

### Required to work
A PDO instance is expected in global scape.
Its name can be changed by setting the constant GlobalDBName.
```php
define('GlobalDBName', 'dbCon');
```
This should happen _before_ inclusion of the ORM file.
