<?php
namespace ORM;
/*
* Copyright (c) 2013 Richard Andrew Cattermole
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/

if (!defined('GlobalDBName'))
    define('GlobalDBName', 'pdo');

class Model {
    private $cameFromDb = false;
    private $updateStatement;
    private $insertStatement;
    private $insertIdsStatement;
    private $deleteStatement;
    private $db;
    
    protected static $ids = array();
    
    public function __construct($db = null) {
        if ($db == null && isset($GLOBALS[GlobalDBName])) {
            $db = $GLOBALS[GlobalDBName];
        }
        if ($db != null) {
            $this->db = $db;
            $names = '';
            $names2 = '';
            $updateSets = '';
            $where = '';
            
            foreach(class_property_names($this) as $prop) {
                if (!in_array($prop, static::$ids)) {
                    $names .= $prop . ', ';
                    $names2 .= ':' . $prop . ', ';
                    $updateSets .= $prop . '=:' .$prop . ',';
                } else {
					$namesIds .= $prop . ', ';
					$namesIds2 .= ':' . $prop . ', ';
				}
            }
            if (count_chars($names) > 0) {
                $names = substr($names, 0, -2);
                $names2 = substr($names2, 0, -2);
                $updateSets = substr($updateSets, 0, -1);
            }
            
            foreach(static::$ids as $id) {
                $where .= $id . '=:' . $id . ' AND ';
            }
            if (count_chars($where) > 0) {
                $where = substr($where, 0, -5);
            }
            
            $className = explode('\\', get_class($this));
            $className = array_pop($className);
            
            $this->updateStatement = $db->prepare('UPDATE `' . $className . '` SET ' . $updateSets . ' WHERE ' . $where);
            $this->insertStatement = $db->prepare('INSERT INTO `' . $className . '`(' . $names . ') VALUES(' . $names2 . ')');
			$this->insertIdsStatement = $db->prepare('INSERT INTO `' . $className . '`(' . $namesIds . $names . ') VALUES(' . $namesIds2 . $names2 . ')');
            $this->deleteStatement = $db->prepare('DELETE from `' . $className . '` where ' . $where);
        } else {
            throw new \Exception('Tried getting a database connection and failed - none existed');
        }
    }
    
    public function save() {
        $values = class_values($this);
        // save the data to the given database.
        $this->db->beginTransaction();
        try {
            if ($this->cameFromDb) {
                foreach($values as $key => $value) {
                    $this->updateStatement->bindValue(':' . $key, $value);
                }
                $this->updateStatement->execute();
                if ($this->updateStatement->errorCode() !== '00000')
                    throw new \Exception('Could not insert into database' . serialize($this->updateStatement->errorInfo()));
            } else {
				foreach($values as $key => $value) {
                    if (in_array($key, static::$ids)) {
                        if ($value !== null) {
							$insertIds = true;
						}
                    }
                }
				if ($insertIds) {
					foreach($values as $key => $value) {
						$this->insertIdsStatement->bindValue(':' . $key, $value);
					}
					$this->insertIdsStatement->execute();
					if ($this->insertIdsStatement->errorCode() !== '00000')
						throw new \Exception('Could not insert into database' . serialize($this->insertIdsStatement->errorInfo()));
					
					if($this->insertIdsStatement->rowCount() == 1) {
						foreach($values as $key => $value) {
							if (in_array($key, static::$ids)) {
								$value = $this->db->lastInsertId($key);
								$this->$key = $value;
							}
						}
					}
					$this->hasComeFromDB();
				} else {
					foreach($values as $key => $value) {
						if (!in_array($key, static::$ids)) {
							$this->insertStatement->bindValue(':' . $key, $value);
						}
					}
					$this->insertStatement->execute();
					if ($this->insertStatement->errorCode() !== '00000')
						throw new \Exception('Could not insert into database' . serialize($this->insertStatement->errorInfo()));
					
					if($this->insertStatement->rowCount() == 1) {
						foreach($values as $key => $value) {
							if (in_array($key, static::$ids)) {
								$value = $this->db->lastInsertId($key);
								$this->$key = $value;
							}
						}
					}
					$this->hasComeFromDB();
				}
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function delete() {
        // array of id values
        $this->db->beginTransaction();
        foreach(class_values($this) as $key => $value) {
            if (in_array($key, static::$ids)) {
                $this->deleteStatement->bindValue(':' . $key, $value);
            }
        }
        $this->deleteStatement->execute();
        if ($this->deleteStatement->errorCode() !== '00000') {
            $this->db->rollback();
            throw new \Exception('Could not delete from database' . serialize($this->deleteStatement->errorInfo()));
        } else {
            $this->db->commit();
        }
    }
    
    public function hasComeFromDB() {
        $this->cameFromDb = true;
    }
    
    public static function create($values) {
        return class_new(get_called_class(), $values, false);
    }
    
    public static function get($sqlOrIds, $sqlParams=array(), $db=null) {
        $ret = null;
        
        if ($db == null && isset($GLOBALS[GlobalDBName])) {
            $db = $GLOBALS[GlobalDBName];
        }
        if ($db != null) {
            $db->beginTransaction();
            if (gettype($sqlOrIds) == 'array') {
                $className = explode('\\', get_called_class());
                $className = array_pop($className);
                
                $where = '';
                foreach(static::$ids as $id) {
                    $where .= $id . '=:' . $id . ' AND ';
                }
                if (count_chars($where) > 0) {
                    $where = substr($where, 0, -5);
                }
                
                // array of id values
                $stmt = $db->prepare('SELECT * from ' . $className . ' where ' . $where);
                foreach ($sqlOrIds as $key => $value) {
                    if (gettype($key) == 'string') {
                        $stmt->bindValue(':' . $key, $value);
                    } else {
                        $stmt->bindValues($key, $value);
                    }
                }
                $stmt->execute();
                if ($stmt->errorCode() !== '00000') {
                    $db->rollback();
                    throw new \Exception('Could not insert into database' . serialize($stmt->errorInfo()));
                } else {
                    $db->commit();
                }
                
                $ret = $stmt->fetchObject(get_called_class());
            } else if (gettype($sqlOrIds) == 'string') {
                // string aka sql
                $stmt = $db->prepare($sqlOrIds);
                foreach ($sqlParams as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                if ($stmt->errorCode() !== '00000') {
                    $db->rollback();
                    throw new \Exception('Could not insert into database' . serialize($stmt->errorInfo()));
                } else {
                    $db->commit();
                }
                
                $ret = $stmt->fetchObject(get_called_class());
            }
        } else {
            throw new \Exception('Tried getting a database connection and failed - none existed');
        }
        
        if ($ret != null) {
            $ret->hasComeFromDB();
        }
        
        return $ret;
    }
    
    public static function getMany($condition=array(), $db = null) {
        $ret = array();
        
        if ($db == null && isset($GLOBALS[GlobalDBName])) {
            $db = $GLOBALS[GlobalDBName];
        }
        if ($db != null) {
            if (count($condition) > 0) {
                $className = explode('\\', get_called_class());
                $className = array_pop($className);

                $where = '';
                foreach($condition as $key => $value) {
                    $where .= $key . '==' . $key . ' AND ';
                }
                if (count_chars($where) > 0) {
                    $where = substr($where, 0, -5);
                }

                $stmt = $db->prepare('SELECT * from ' . $className . ' where ' . $where);
                foreach ($condition as $key => $value) {
                    $stmt->bindValue(':' . $key, $value);
                }
                $stmt->execute();
                if ($stmt->errorCode() !== '00000')
                    throw new \Exception('Could not get many from database' . serialize($stmt->errorInfo()));

                for($i = 0; $i < $stmt->rowCount(); $i++) {
                    $item = $stmt->fetchObject(get_called_class());
                    $item->hasComeFromDB();
                    $ret[] = $item;
                }
            }
        } else {
            throw new \Exception('Tried getting a database connection and failed - none existed');
        }
        return $ret;
    }
    
    public static function query($sql, $params=array(), $db = null) {
        $ret = array();
        
        if ($db == null && isset($GLOBALS[GlobalDBName])) {
            $db = $GLOBALS[GlobalDBName];
        }
        if ($db != null) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            for($i = 0; $i < $stmt->rowCount(); $i++) {
                $item = $stmt->fetchObject(get_called_class());
                $item->hasComeFromDB();
                $ret[] = $item;
            }
        } else {
            throw new \Exception('Tried getting a database connection and failed - none existed');
        }
        
        return $ret;
    }
}

$registered_classes = array();

function class_add($name) {
    global $registered_classes;
    $registered_classes[$name] = new \ReflectionClass($name);
}

function class_new($name, $values=null, $fromDb = false) {
    global $registered_classes;
    if (!array_key_exists($name, $registered_classes)) {
        class_add($name);
    }
    
    $reflect = $registered_classes[$name];
    $instance = $reflect->newInstance();
    
    if (gettype($values) == 'array') {
        foreach($values as $key => $value) {
            $reflect->getProperty($key)->setValue($instance, $value);
        }
    }
    
    if ($fromDb) {
        $instance->hasComeFromDB();
    }
    
    return $instance;
}

function class_values($obj) {
    global $registered_classes;
    $name = get_class($obj);
    if (!array_key_exists($name, $registered_classes)) {
        class_add($name);
    }
    $reflect = $registered_classes[$name];
    
    $ret = array();
    
    foreach($reflect->getProperties() as $prop) {
        if ($prop->isPublic() && !$prop->isStatic()) {
            $propName = $prop->getName();
            $ret[$propName] = $prop->getValue($obj);
        }
    }
    
    return $ret;
}

function class_property_names($obj) {
    global $registered_classes;
    if (gettype($obj) == 'object')
        $name = get_class($obj);
    else
        $name = $obj;
    if (!array_key_exists($name, $registered_classes)) {
        class_add($name);
    }
    $reflect = $registered_classes[$name];
    
    $ret = array();
    
    foreach($reflect->getProperties() as $prop) {
        if ($prop->isPublic() && !$prop->isStatic()) {
            $ret[] = $prop->getName();
        }
    }
    
    return $ret;
}
?>
