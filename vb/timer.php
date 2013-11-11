<?php

class vB_Timer
{
	private static $timers = array();

	public static function get($name)
	{
		if (isset(self::$timers[$name]))
		{
			return self::$timers[$name];
		}
		else 
		{
			return self::reset($name);
		}
	}

	public static function reset($name)
	{
		self::$timers[$name] = new vB_Timer();
		return self::$timers[$name];
	}
	
	protected function __construct() {}

	public function start()
	{
		$this->start = $this->timestamp();	
	}

	public function stop()
	{
		return $this->end();
	}

	public function end()
	{
		$this->finish = $this->timestamp();
		$time = $this->finish - $this->start;

		$this->total += $time;
		$this->max = max($this->max, $time);
		$this->checkpoint = max($this->checkpoint, $time);
		return $this->get_time();
	}

	public function reset_checkpoint()
	{
		$this->checkpoint = 0;
	}	

	public function get_checkpoint()
	{
		return round($this->checkpoint, 4);
	}

	public function get_max()
	{
		return round($this->max, 4);
	}

	public function get_time()
	{
		return round($this->finish - $this->start, 4);
	}

	public function get_total()
	{
		return round($this->total, 4);
	}

	private function timestamp()
	{
		if (function_exists('microtime'))
		{
			return microtime(true);
		}
		else 
		{
			return time();
		}
	}

	private $start = 0;
	private $finish = 0;
	private $max = 0;
	private $checkpoint = 0;
	private $total = 0;
}

?>
