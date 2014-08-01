<?php
/**
 * this script holds the sink handlers. 
 * 
 */
function sink_handler($query)
{
	//put your sink handling logic here
}
class PDO_ extends PDO
{

	function exec($query=null)
	{
		$args=func_get_args();
		sink_handler($query);
 		$reflector = new ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('exec');
        return $method->invokeArgs($this, $args);
	}
	function query($query=null)
	{
		$args=func_get_args();
		sink_handler($query);
 		$reflector = new ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('query');
        return $method->invokeArgs($this, $args);
    }
}
class mysqli_ extends mysqli
{
	function query($query=null)
	{
		$args=func_get_args();
		sink_handler($query);
 		$reflector = new ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('query');
        return $method->invokeArgs($this, $args);
	}
	function real_query($query=null)
	{
		$args=func_get_args();
		sink_handler($query);
 		$reflector = new ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('real_query');
        return $method->invokeArgs($this, $args);
	}
	function multi_query($query=null)
	{
		$args=func_get_args();
		sink_handler($query);
 		$reflector = new ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('multi_query');
        return $method->invokeArgs($this, $args);
	}
}

function mysql_query_($query=null)
{
	$args=func_get_args();
	sink_handler($query);
	return call_user_func_array("mysql_query", $args);
}
function mysqli_query_($link=null,$query=null)
{
	$args=func_get_args();
	sink_handler($query);
	return call_user_func_array("mysqli_query", $args);
}
function mysqli_real_query_($link=null,$query=null)
{
	$args=func_get_args();
	sink_handler($query);
	return call_user_func_array("mysqli_real_query", $args);
}
function mysqli_multi_query_($link=null,$query=null)
{
	$args=func_get_args();
	sink_handler($query);
	return call_user_func_array("mysqli_multi_query", $args);
}
function mysql_db_query_($dbname=null,$query=null)
{
	$args=func_get_args();
	sink_handler($query);
	return call_user_func_array("mysql_db_query", $args);
}
