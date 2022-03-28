<?php

class MobbexTask extends \ObjectModel
{
    public $id;
    public $name;
    public $args;
    public $interval;
    public $period;
    public $limit;
    public $executions;
    public $start_date;
    public $last_execution;
    public $next_execution;

    public static $definition = [
        'table'     => 'mobbex_task',
        'primary'   => 'id',
        'multilang' => false,
        'fields' => [
            'id' => [
                'type'     => self::TYPE_INT,
                'required' => false
            ],
            'name' => [
                'type'     => self::TYPE_STRING,
                'required' => false
            ],
            'args' => [
                'type'     => self::TYPE_STRING,
                'required' => false
            ],
            'interval' => [
                'type'     => self::TYPE_INT,
                'required' => false
            ],
            'period' => [
                'type'     => self::TYPE_STRING,
                'required' => false
            ],
            'limit' => [
                'type'     => self::TYPE_INT,
                'required' => false
            ],
            'executions' => [
                'type'     => self::TYPE_INT,
                'required' => false
            ],
            'start_date' => [
                'type'     => self::TYPE_DATE,
                'required' => false
            ],
            'last_execution' => [
                'type'     => self::TYPE_DATE,
                'required' => false
            ],
            'next_execution' => [
                'type'     => self::TYPE_DATE,
                'required' => false
            ],
        ],
    ];

    /**
     * Instance a new task.
     * 
     * @param string $id For instance an existing task.
     * @param string $name Name of hook to call on execution.
     * @param int|string $interval Quantity of periods between which the task will run.
     * @param string $period "day" | "month" | "year"
     * @param int|string $limit Maximum number of executions.
     * @param mixed ...$args Arguments to pass in each execution.
     */
    public function __construct($id = null, $name = null, $interval = null, $period = null, $limit = null, ...$args)
    {
        $this->name     = $name;
        $this->args     = json_encode($args);
        $this->interval = $interval;
        $this->period   = $period;
        $this->limit    = $limit;

        parent::__construct($id);
    }

    /**
     * Add current task to db.
     *
     * @param bool $autoDate
     * @param bool $nullValues
     * 
     * @return bool Result of the addition.
     */
    public function add($autoDate = true, $nullValues = false)
    {
        $result = true;

        // Create hook if not exists
        if (!\Hook::getIdByName($this->name)) {
            $hook       = new \Hook();
            $hook->name = $this->name;

            if (!$hook->add())
                $result = false;
        }

        // Set first execution date
        $this->next_execution = date('Y-m-d H:i:s', strtotime("$this->interval $this->period"));

        return parent::add($autoDate, $nullValues) && $result;
    }

    /**
     * Execute the task using action hook.
     * 
     * @return bool Result of the execution.
     */
    public function execute()
    {
        $result = \MobbexHelper::executeHook($this->name, false, ...json_decode($this->args, true));

        if (!$result) {
            \MobbexHelper::log('Error Execution Task #' . $this->id, $this->name . ' ' . $this->args, true);
            return false;
        }

        $this->executions += 1;

        // Delete task if it reaches the execution limit
        if ($this->limit == $this->executions)
            return $this->delete();

        // Update execution dates
        $this->last_execution = date('Y-m-d H:i:s');
        $this->next_execution = date('Y-m-d H:i:s', strtotime("$this->last_execution + $this->interval $this->period"));

        if (!$this->start_date || strtotime($this->start_date) < 0)
            $this->start_date = $this->last_execution;

        $this->save();

        return $result;
    }

    /**
     * Get all tasks from a hook name.
     * 
     * @param string $name Hook name.
     * 
     * @return array
     */
    public static function getByName($name)
    {
        $tasks = new \PrestaShopCollection('MobbexTask');
        $tasks->where('name', '=', $name);

        return $tasks->getResults() ?: [];
    }

    /**
     * Get all current pending tasks from db.
     * 
     * @return \MobbexTask[]
     */
    public static function getPendingTasks()
    {
        $tasks = new \PrestaShopCollection('MobbexTask');
        $tasks->sqlWhere("DATE(`next_execution`) <= '" . date('Y-m-d') . "'");

        return $tasks->getResults() ?: [];
    }

    /**
     * Execute all current pending tasks.
     * 
     * @return bool Result of execution.
     */
    public static function executePendingTasks()
    {
        $tasks  = self::getPendingTasks();
        $result = true;

        foreach ($tasks as $task)
            $result = $task->execute() && $result;

        return $result;
    }
}