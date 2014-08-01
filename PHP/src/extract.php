<?php
class StringExtractor extends PHPParser_NodeVisitorAbstract
{
	static $fragments=array();
	static function add($fragment)
	{
	    if (isset(self::$fragments[$fragment]))
        	self::$fragments[$fragment]++;
        else
        	self::$fragments[$fragment]=1;

	}
    static $sqlKeywords=null;
    static $sqlSymbols=null;
    private static function setup()
    {
        if (self::$sqlKeywords===null)
            self::$sqlKeywords=explode(PHP_EOL,file_get_contents(__DIR__."/../data/sqlkeywords.txt"));
        if (self::$sqlSymbols===null)
            self::$sqlSymbols=explode(PHP_EOL,file_get_contents(__DIR__."/../data/sqlsymbols.txt"));
    }
    static function isSQLFragment($fragment)
    {
        self::setup();
        foreach (self::$sqlKeywords as $keyword)
            //preg is 2 to 10 times slower than stripos, so first we check if its there, 
            //then we check if its whole word, because 99% don't match and one does
            if (stripos($fragment, $keyword)!==false)
                if (preg_match("/\b{$keyword}\b/i", $fragment))
                    return true;
        foreach (self::$sqlSymbols as $symbol)
            if (stripos($fragment, $symbol)!==false)
                return true;
        return false;
    }

    public function leaveNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Scalar_String) # plain strings
        {
        	self::add($node->value);
        }
        elseif ($node instanceof PHPParser_Node_Scalar_Encapsed) # php encapsulated strings
        {
        	foreach ($node->parts as $part)
        		if (is_string($part)) self::add($part);
        }
    }
}
echo "Extracting text fragments, this might take up to several minutes...\n";
flush();

$files=(getAllPhpFiles($path));
$parser = new PHPParser_Parser(new PHPParser_Lexer);	
$traverser     = new PHPParser_NodeTraverser;
$prettyPrinter = new PHPParser_PrettyPrinter_Default;

$traverser->addVisitor(new StringExtractor);
$n=0;
$totalFragments=array("core"=>array());
foreach ($files as $file)
{
	$n++;
	if (($n)%80==0) 
		echo PHP_EOL;
	else
		echo ".";
    try {
        $syntax_tree = $parser->parse(file_get_contents($file));
    }
    catch (PHPParser_Error $e)
    {
        echo PHP_EOL."ERROR: Unable to parse {$file}: ".$e->getMessage().PHP_EOL;
        continue;
    }
    $filtered = $traverser->traverse($syntax_tree);
    //this part is for separation of application parts
    // if (is_plugin($file))
    // {
    //     $name=plugin_name($file);
    //     if (!isset($totalFragments[$name])) 
    //         $totalFragments[$name]=array();
    //     $totalFragments[$name]=array_merge($totalFragments[$name],array_keys(StringExtractor::$fragments));
    // }
    // else
        $totalFragments["core"]=array_merge($totalFragments["core"],array_keys(StringExtractor::$fragments));
    StringExtractor::$fragments=array();
}
echo PHP_EOL;


// $nulls=array_filter($strings,function ($item) {return strpos($item,"\0")!==false;});
// $stringsWithoutNulls=array_diff($strings, $nulls);
// echo PHP_EOL.count($nulls)." Fragments were discarded due to containing null, dumping ".count($stringsWithoutNulls)." fragments to {$outfile}...";   
#TODO: filter only fragments that have either alphanumeric SQL whole words, or SQL special chars
echo "Filtering fragments to find those of interest... This might take very long...\n";
flush();
foreach ($totalFragments as $name=>$strings)
{
    $interestingFragments=array_filter($strings,"StringExtractor::isSQLFragment");
    $interestingFragments=array_filter($interestingFragments,function($str){return strpos($str, "\0")===false;}); //remove nulls
    $interestingFragments=array_unique($interestingFragments);

    echo "[{$name}] Total fragments: ".count($strings).", interesting (SQL-Like) fragments: ".count($interestingFragments);
    sort($interestingFragments);

    $outfile=$options['o'];
    echo "; dumping to {$outfile}...\n"; 
    file_put_contents($outfile, implode(PHP_EOL, $interestingFragments));
}
echo PHP_EOL."Done.".PHP_EOL;
