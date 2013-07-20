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
```

ids is overidden from \ORM\Model that lists all ids used in the class.

No registration is required of types. They are automatically registered with the ORM upon first usage.
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

Get many example:
```php
$classes = T::getMany(['message' => 'test']);
```

The above methods are designed for simplicty however if you need to get many of one type of model with complete custom sql then this method is more suitable:
```php
$classes = T::query('SELECT * from T where message=?', array('test'));
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

### Relationships
Releationships in RDBMS occurs in three forms, one to one, one to many and many to many.

Many to many requires a bridging table and this won't be directly supported.

One to many is supported by using \ORM\Model::getMany() where its paramater happens to be a an associative array consisting of properties to look for.
These are direct comparitive. No support for e.g. <> != type operators.

Lastly there is one to one, this can be done by just using get.
