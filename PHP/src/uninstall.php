<?php
require_once __DIR__."/install.lib.php";

echo "Uninstalling sink wrappers from Wordpress... This might take several minutes...\n";
flush();
SinkInstaller::$installMode=false;
$changed=SinkInstaller::process($wpdir);

if (count($changed))
{
    // file_put_contents($wpdir."/wp-sql-sink.php", file_get_contents(__DIR__."/wp-sql-sink.php"));
    if (file_exists($wpdir."/wp-sql-sink.php"))
    	unlink($wpdir."/wp-sql-sink.php");
    else
    	echo "Warning: sink wrappers were not properly installed!".PHP_EOL;
    $indexCode=file_get_contents($wpdir."/wp-config.php");
    $indexNewCode=str_replace("require_once __DIR__.'/wp-sql-sink.php';\n", "", $indexCode);
    file_put_contents($wpdir."/wp-config.php", $indexNewCode);

    foreach ($changed as $c)
        echo "'{$c}' was overwritten.".PHP_EOL;
    echo "Wrapper successfully uninstalled from sinks.".PHP_EOL;
}
else
{
    echo "Wrappers are not yet installed on sinks, nothing happened.".PHP_EOL;
}