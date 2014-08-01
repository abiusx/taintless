#!/usr/bin/php
<?php
require_once __DIR__."/vendor/autoload.php";
function getAllPhpFiles($Path,$exclude=array())
{
	$Directory = new RecursiveDirectoryIterator($Path);
	$Iterator = new RecursiveIteratorIterator($Directory);
	$list=[];
	foreach ($Iterator as $it)
		if (pathinfo($it->getPathName(), PATHINFO_EXTENSION)=="php")
			if (!in_array(basename($it->getPathName()),$exclude)) //don't list your own script file!
				$list[]=$it->getPathName();
	return $list;
}
function plugin_name($file)
{
	global $wpdir;
	$plugindir=realpath($wpdir."/wp-content/plugins");
	if (substr($file,0,strlen($plugindir))==$plugindir)
	{
		$rest=substr($file,strlen($plugindir)+1);
		$t=explode("/",$rest);
		return $t[0];
	}
	return null;
}
function is_plugin($file)
{
	return plugin_name($file)!==null;
}
$options="o:p:i:s:q";
$longopts=array("extract","analyse","construct");
$usage="Usage: ".basename($argv[0])."
--extract -p path -o output-file.txt
--analyse -p path -o output-file.txt
--construct -i fragments.txt -s string [-q]
";
$options=getopt($options,$longopts);
if (!(isset($options['extract']) or isset($options['analyse']) or isset($options['construct'])))
	die($usage);

if ( !isset($options['construct']) && (!isset($options['o']) or !isset($options['p']) or count($options)<2) )
	die($usage);
elseif (isset($options['construct']) && (!isset($options['i']) or !isset($options['s']) )) 
	die($usage);

if (function_exists("xdebug_is_enabled"))
	if (!isset($options['q'])) echo "WARNING: Xdebug detected. For this script to work properly, you need to add 'xdebug.max_nesting_level = 5000' to your php.ini.\n";
if (isset($options['construct']))
{
	$fragments=realpath($options['i']);
	if (!$fragments)
		die("Unable to find fragments file.\n");
	require_once "src/construct.php";
	exit(0);
}

$path=realpath($options['p']);
if (!$path)
	die("Invalid app path.\n");
if (isset($options["extract"]))
	require_once "src/extract.php";
elseif (isset($options["analyse"]))
	require_once "src/analyse.php";
else
	die($usage);

