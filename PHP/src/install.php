<?php
require_once __DIR__."/install.lib.php";

echo "Installing sink wrappers on Wordpress... This might take several minutes...\n";
flush();
SinkInstaller::$installMode=true;
$changed=SinkInstaller::process($wpdir);

if (count($changed))
{
    file_put_contents($wpdir."/wp-sql-sink.php", file_get_contents(__DIR__."/wp-sql-sink.php"));
    file_put_contents($wpdir."/wp-config.php", "<?"."php\nrequire_once __DIR__.'/wp-sql-sink.php';\n".substr(file_get_contents($wpdir."/wp-config.php"),6));

    foreach ($changed as $c)
    {
        echo "'{$c}' was overwritten.".PHP_EOL;
    }
    echo "Wrapper successfully installed on sinks.".PHP_EOL;
    echo "Don't forget to add your custom sink code to wp-sql-sink.php file in your wordpress root folder.".PHP_EOL;
}
else
{
    echo "Wrappers were already installed on all sinks, nothing happened.".PHP_EOL;
}