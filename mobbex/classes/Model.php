<?php

namespace Mobbex;

abstract class Model extends \ObjectModel
{
    /** List of fillable attributes */
    public $fillable = [];

    /**
     * Instance the model and try to fill properties.
     * 
     * @param int|string|null $id
     * @param mixed $props
     */
    public function __construct($id, ...$props)
    {
        parent::__construct($id);

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