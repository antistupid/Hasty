<?php

namespace Hasty\DB;

class Model
{

    private $alias;

    public function getTableName()
    {
        return static::$__table_name__ . ($this->alias ? '_' . $this->alias: '');
    }

    public function getTableNameWithAs()
    {
        return static::$__table_name__ .
        ($this->alias ?
            ' AS ' . static::$__table_name__ . '_' . $this->alias : '');
    }

    public function getFields()
    {
        $props = (new \ReflectionClass($this))->getProperties();
        $fields = [];
        foreach ($props as $v) {
            $doc = $v->getDocComment();
            preg_match_all('#.*@field\s(.*?)#s', $doc, $annotations);
            foreach ($annotations[1] as $a) {
                $fields[] = static::$__table_name__ .
                    ($this->alias ? '._' . $this->alias : '') . '.' . $v->name;
                break;
            }
        }
        return $fields;
    }

    final public function __construct($alias = null)
    {
        $this->alias = $alias;
        $r = new \ReflectionClass(\get_called_class());
        foreach ($r->getProperties() as $m) {
            $doc = $m->getDocComment();
            $match = preg_match_all('#@(hidden)?field\s(.*?)(\s|\n)#s', $doc, $annotations);
            if (!$match)
                continue;
            $a = $annotations[2][0];
            $fieldClassName = '\\Hasty\\DB\\Field\\' . ucfirst($a) . 'Field';
            $mClass = $m->class;
            $mName = $m->name;
            $this->{$mName} = new $fieldClassName(
                $this->getTableName(), $mName);
        }
    }
}
