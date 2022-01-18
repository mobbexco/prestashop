<?php

namespace Mobbex;

abstract class Model extends \ObjectModel
{
    /** List of fillable attributes */
    public $fillable = [];

    /**
     * Instance the model and try to fill properties.
     * 
     * @param mixed ...$props
     */
    public function __construct(...$props)
    {
        parent::__construct(isset($props[0]) ? $props[0] : null);

        // The id is always fillable
        if (isset(self::$definition['primary']))
            array_unshift($this->fillable, self::$definition['primary']);

        if ($this->fillable)
            $this->fill(...$props);
    }

    /**
     * Fill properties to current model.
     * 
     * @param mixed ...$props
     */
    public function fill(...$props)
    {
        foreach ($props as $key => $value)
            if (isset($this->fillable[$key]))
                $this->{$this->fillable[$key]} = $value;
    }
}