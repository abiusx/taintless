<?php
class Analyzer extends PHPParser_NodeVisitorAbstract
{
    public $results=array();
    public $total=array();
    public $nodeCount=0;
    static $sinkFunctions=array();
    static $sinkClasses=array();

    static $taintFunctions=array();
    function __construct()
    {
        if (self::$sinkFunctions===array())
            self::$sinkFunctions=explode(PHP_EOL,file_get_contents(__DIR__."/../data/sink-functions.txt"));
        if (self::$sinkClasses===array())
            self::$sinkClasses=explode(PHP_EOL,file_get_contents(__DIR__."/../data/sink-classes.txt"));
        if (self::$taintFunctions===array())
        {
            $out=[];
            $data=explode(PHP_EOL,file_get_contents(__DIR__."/../data/taint-functions.txt"));
            while ($data[0][0]=='#')
                array_shift($data); //comment line
            foreach ($data as $v)
            {
                if (!$v) continue;
                $x=preg_split("/\s+/",$v);
                self::$taintFunctions[$x[0]]=array('impact'=>$x[1],'tracking'=>$x[2],'inference'=>$x[3]);
            }
        }
    }


    protected function hasVariable( $arg)
    {
        if ($arg instanceof PhpParser\Node\Arg)
            return $this->hasVariable($arg->value);
        elseif ($arg instanceof PhpParser\Node\Expr\Variable)
            return true;
        elseif ($arg instanceof PhpParser\Node\Scalar)
            return false;
        elseif ($arg instanceof PhpParser\Node\Expr\FuncCall)
        {
            $res=false;
            for ($i=0;$i<count($arg->args);++$i)
                $res|=$this->hasVariable($arg->args[$i]);
            return $res;
        }
        elseif ($arg instanceof PhpParser\Node\Expr)
            return $this->hasVariable($arg->left)
                   || $this->hasVariable($arg->right);

    }
    public function leaveNode(PHPParser_Node $node) {
        $this->nodeCount++;
        if ($node instanceof PHPParser_Node_Stmt_Class or $node instanceof PhpParser\Node\Expr\New_) # class definition, check for extends
        {
            if ($node instanceof PHPParser_Node_Stmt_Class and isset($node->extends))
                $baseClass=$node->extends->parts[0];
            elseif ($node instanceof PhpParser\Node\Expr\New_ and isset($node->class))
                $baseClass=$node->class->parts[0];
            else
                $baseClass="";
            if ($baseClass)
            {
                if (in_array($baseClass, self::$sinkClasses))
                {
                    if (isset($this->results[$this->file]['sinks'][$baseClass]))
                        $this->results[$this->file]['sinks'][$baseClass]++;
                    else
                        $this->results[$this->file]['sinks'][$baseClass]=1;
                    if (isset($this->total['sinks'][$baseClass]))
                        $this->total['sinks'][$baseClass]++;
                    else
                        $this->total['sinks'][$baseClass]=1;
                }
            }
        }
        elseif ($node instanceof PHPParser_Node_Expr_FuncCall) # function call
        {
            if (isset($node->name) && isset($node->name->parts))
            {
                $functionName=$node->name->parts[0];
                if (in_array($functionName, self::$sinkFunctions))
                {
                    if (isset($this->results[$this->file]['sinks'][$functionName]))
                        $this->results[$this->file]['sinks'][$functionName]++;
                    else
                        $this->results[$this->file]['sinks'][$functionName]=1;
                    if (isset($this->total['sinks'][$functionName]))
                        $this->total['sinks'][$functionName]++;
                    else
                        $this->total['sinks'][$functionName]=1;
                }
                if (isset(self::$taintFunctions[$functionName]))
                {
                    if ($this->hasVariable($node))
                    {
                        //without any variables, this function is not propagating taint
                        if (isset($this->results[$this->file]['taint'][$functionName]))
                            $this->results[$this->file]['taint'][$functionName]++;
                        else
                            $this->results[$this->file]['taint'][$functionName]=1;
                        if (isset($this->total['taint'][$functionName]))
                            $this->total['taint'][$functionName]++;
                        else
                            $this->total['taint'][$functionName]=1;
                    }
                }
            }
        }
    }
    function process($path)
    {
        $this->nodeCount=0;
        $this->files=$files=(getAllPhpFiles($path));

        $parser = new PHPParser_Parser(new PHPParser_Lexer);    
        $traverser     = new PHPParser_NodeTraverser;
        $prettyPrinter = new PHPParser_PrettyPrinter_Default;

        $traverser->addVisitor($this);
        $n=0;
        foreach ($files as $file)
        {
            $n++;
            if (($n)%80==0) 
                echo PHP_EOL;
            else
                echo ".";
            try {
                $this->file=substr($file,strlen($path)+1);
                $syntax_tree = $parser->parse(file_get_contents($file));
                // $syntax_tree = $parser->parse('<?php $x=new \mysqli();');
            }
            catch (PHPParser_Error $e)
            {
                echo PHP_EOL."ERROR: Unable to parse {$file}: ".$e->getMessage().PHP_EOL;
                continue;
            }
            $filtered = $traverser->traverse($syntax_tree);
            // $newCode = '<?php ' .$comments.PHP_EOL.$prettyPrinter->prettyPrint($filtered);
        }
        echo PHP_EOL;
    }

    static private $sinkScore=10;
    function statistics()
    {
        if (empty($this->total))
            return "No data found.\n";
        echo "Generating statistics, please wait...".PHP_EOL;
        ob_start();
        $res=array();
        $total=array("count"=>0,"tracking"=>0,"score"=>0,"taint"=>0,"tracking"=>0,"inference"=>0,"sinks"=>0);
        foreach ($this->results as $file=>$data)
        {
            $tracking=0;
            $inference=0;
            if (isset($data['taint']))
            foreach ($data['taint'] as $taintFunction=>$count)
            {
                $t=self::$taintFunctions[$taintFunction];
                $tracking=$t['impact'] * $t['tracking'] * $count;
                $inference=$t['impact'] * $t['inference'] * $count;
            }
            $sinks=isset($data['sinks'])?array_sum($data['sinks']):0;
            $count=isset($data['taint'])?array_sum($data['taint']):0;
            $taint=$tracking+$inference;
            $res[$file]=array("file"=>$file,"score"=>$taint+$sinks*self::$sinkScore,"taint"=>$taint,
                "count"=>$count, "tracking"=>$tracking,"inference"=>$inference,'sinks'=>$sinks );

            foreach ($res[$file] as $k=>$v)
            {
                if ($k=="file") continue;
                $total[$k]+=$v;
            }
        }


        printf("%-60s%20d\n","Total taint-vulnerability score of scanned path ",$total['score']);
        printf("%-60s%20d\n", "Number of files analyzed ",count($this->files));
        printf("%-60s%20d\n", "Total number of sinks found ",$total['sinks']);
        printf("%-60s%20d\n", "Number of possible taint tampering functions ",$total['count']);
        printf("%-60s%20d\n", "Taint-tracking bypass score ",$total['tracking']);
        printf("%-60s%20d\n", "Taint-inference bypass score ",$total['inference']);
        printf("%-60s%20s\n", "PHP code elements analyzed ",number_format($this->nodeCount));
        echo PHP_EOL."**Based on this analysis, this application is ".$this->likelihood($total['score'],count($this->files)).
                " to break.".PHP_EOL;
        $tableHeader=sprintf("# %-57s %%  Score Sinks Funcs\n","File");
        $delimiter=str_repeat("-",80).PHP_EOL;
        echo $delimiter;
        echo "Most interesting files:".PHP_EOL;
        usort($res,function($t1,$t2){return $t1['score']<$t2['score'];});

        $totalShown=min(9,count($res)/9,count($res));
        echo $tableHeader;
        $fileLength=55;
        $n=0;
        for ($i=0;$i<$totalShown;++$i)
        {
            $t=$res[$i];
            $percentage=$t['score']/$total['score']*100;
            $f=$t['file'];
            if (strlen($f)>$fileLength)
            {
                $f=substr($f,0,strpos($f,"/"))."/...";
                $f.=substr($t['file'],-($fileLength-strlen($f)));
            }
            printf("%d %-{$fileLength}s %4.1f%% %4d %3d %4d\n",++$n,$f, $percentage,$t['score'],$t['sinks'],$t['count']);
        }

        echo PHP_EOL;
        echo $delimiter;
        echo "Most important sinks:".PHP_EOL;
        echo $tableHeader;
        $dup=$res;
        usort($dup,function($t1,$t2){return $t1['sinks']<$t2['sinks'];});
        $nn=0;
        for ($i=0;$i<min(3,count($dup));++$i)
        {
            $t=$dup[$i];
            if (!$t['sinks']) continue;
            $percentage=$t['score']/$total['score']*100;
            $f=$t['file'];
            if (strlen($f)>$fileLength)
            {
                $f=substr($f,0,strpos($f,"/"))."/...";
                $f.=substr($t['file'],-($fileLength-strlen($f)));
            }
            printf("%d %-{$fileLength}s %4.1f%% %4d %3d %4d\n",++$nn,$f, $percentage,$t['score'],$t['sinks'],$t['count']);
        }

        $report="";
        $report.= $delimiter;
        $report.= "Rest of the files:".PHP_EOL;
        $report.=$tableHeader;


        for ($i=$totalShown;$i<count($res);++$i)
        {
            $t=$res[$i];
            $percentage=$t['score']/$total['score']*100;
            $f=$t['file'];
            if (strlen($f)>$fileLength)
            {
                $f=substr($f,0,strpos($f,"/"))."/...";
                $f.=substr($t['file'],-($fileLength-strlen($f)));
            }
            $report.=sprintf("%".strlen(count($res))."d %-{$fileLength}s %4.1f%% %4d %3d %4d\n",++$n,$f, $percentage,$t['score'],$t['sinks'],$t['count']);
        }



        $this->report=$report;

        return ob_get_clean();
    }

    protected function likelihood($score,$files)
    {
        $avg=$score/(float)$files;
        if ($avg<1)
            $t="impossible";
        elseif ($avg<5)
            $t="highly unlikely";
        elseif ($avg<10)
            $t="unlikely";
        elseif ($avg<20)
            $t="somewhat unlikely";
        elseif ($avg<50)
            $t="possible";
        elseif ($avg<100)
            $t="likely";
        elseif ($avg<200)
            $t="very likely";
        else
            $t="definitely possible";



        return $t;
    }
}
echo "Analyzing... This might take several minutes...\n";
flush();
$analyzer=new Analyzer();
$analyzer->process($path);
echo $report=$analyzer->statistics();
$report.=$analyzer->report;
file_put_contents($options['o'], $report);
#TODO: show stats and likely files
