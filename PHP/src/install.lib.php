<?php
class SinkInstaller extends PHPParser_NodeVisitorAbstract
{
	static $sinks=array();
    static $functions=array(
        "mysql_query",
        "mysqli_query",
        "mysqli_real_query",
        "mysqli_multi_query",
        "mysql_db_query"
        );
    static $classes=array(
        "PDO",
        "mysqli"
        );
    static $changed=false;
    static $installMode=true; //whether in install mode or uninstall mode
    public function leaveNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Stmt_Class) # class definition, check for extends
        {
            if (isset($node->extends))
            {
                $baseClass=$node->extends;
                if (!self::$installMode)
                    $baseClass=substr($baseClass,0,-1); //remove the trailing _
                if (in_array($baseClass, self::$classes))
                {
                    self::$changed=true;   
                    $node->extends=$baseClass.(self::$installMode?"_":"");
                }
            }
        }
        elseif ($node instanceof PHPParser_Node_Expr_FuncCall) # function call
        {
            if (isset($node->name) && isset($node->name->parts))
            {
                $functionName=$node->name->parts[0];
                if (!self::$installMode)
                    $functionName=substr($functionName,0,-1); //remove the trailing _
                if (in_array($functionName, self::$functions))
                {
                    self::$changed=true;   
                    $node->name->parts[0]=$functionName.(self::$installMode?"_":"");
                }
            }
        }
    }
    static function getComments($file) 
    {
        $code=file_get_contents($file);
        $docComments = array_filter(token_get_all($code), function($entry)
        {   
            return $entry[0] == T_COMMENT;
        });
        if ($docComments)
        {
            $t=array_shift($docComments);
            return $t[1];
        }
        else 
            return null;
    }
    static function process($wpdir)
    {
        $files=(getAllPhpFiles($wpdir));

        $parser = new PHPParser_Parser(new PHPParser_Lexer);    
        $traverser     = new PHPParser_NodeTraverser;
        $prettyPrinter = new PHPParser_PrettyPrinter_Default;

        $traverser->addVisitor(new SinkInstaller);
        $n=0;
        $changes=array();
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
                    self::$changed=false;
            $filtered = $traverser->traverse($syntax_tree);
            if (self::$changed)
            {
                $comments=self::getComments($file);
                $newCode = '<?php ' .$comments.PHP_EOL.$prettyPrinter->prettyPrint($filtered);
                file_put_contents($file, $newCode);   
                $changes[]=$file;
            }
        }
        echo PHP_EOL;
        return $changes;
    }
}
