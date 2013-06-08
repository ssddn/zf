<?php

namespace zf;

class ClosureSet
{
	private $_registered;
	private $_lookupPath;
	private $_context;
	public $delayed;

	public function __construct($context,$lookupPath)
	{
		$this->_context = $context;
		$this->_lookupPath = $lookupPath;
		$this->delayed = new Delayed($this);
	}

	public function __load($closureName)
	{
		$filename = $this->_lookupPath.DIRECTORY_SEPARATOR.$closureName.'.php';
		$closure = is_readable($filename) ? require $filename: null;

		if (!$closure)
		{
			throw new \Exception("closure \"$closureName\" not found under \"$this->_lookupPath\"");
		}
		elseif (1 === $closure)
		{
			throw new \Exception("invalid closure in \"$filename\", forgot to return the closure?");
		}
		return $closure;
	}

	public function __get($name)
	{
		if(isset($this->_registered[$name]))
		{
			$closure = $this->_registered[$name];
			$this->_registered[$name] = null; #  keep the key in $_registered array
			if(is_string($closure))
			{
				$closure = $this->__load($closure);
			}
		}
		else
		{
			$closure = $this->__load($name);
		}
		if (!$closure instanceof \Closure)
		{
			throw new \Exception("invalid closure \"$name\"");
		}
		is_null($this->_context) or $closure = $closure->bindTo($this->_context);
		return $this->{$name} = $closure;
	}

	public function __call($name, $args=null)
	{
		$closure = isset($this->{$name}) ? $this->{$name} : $this->__get($name);
		if($args)
		{
			$numArgs = count($args);
			return
				(1 == $numArgs ? $closure($args[0]) :
				(2 == $numArgs ? $closure($args[0], $args[1]) :
				(3 == $numArgs ? $closure($args[0], $args[1], $args[2]) : call_user_func_array($closure, $args))));
		}
		return $closure();
	}

	public function register($name, $closure=null)
	{
		if(is_array($name))
		{
			foreach($name as $name=>$closure)
			{
				is_int($name)
					? $this->_registered[$closure] = null
					: $this->_registered[$name] = $closure;
			}
		}
		else
		{
			$this->_registered[$name] = $closure;
		}
	}

	public function registered($name)
	{
		return $this->_registered && array_key_exists($name, $this->_registered);
	}

}

class Delayed
{
	private $closureSet;

	public function __construct($closureSet)
	{
		$this->closureSet = $closureSet;
	}

	public function __call($name, $args)
	{
		$closureSet = $this->closureSet;
		return function() use ($name, $args, $closureSet){ return $closureSet->__call($name, $args); };
	}
}
