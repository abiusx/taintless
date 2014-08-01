<?php
// require_once __DIR__."/../vendor/greenlion/php-sql-parser/src/PHPSQLParser/PHPSQLParser.php";
$quiet=isset($options['q']);
$string=$options['s'];
$fragments=explode(PHP_EOL,file_get_contents($fragments));
$payload=strtolower($string);

function bareFragment($fragment)
{
    return strtolower(trim($fragment));
}
function removeDuplicates($fragments)
{
    $out=array();
    for ($i=0;$i<count($fragments);++$i)
    {
        $t=bareFragment($fragments[$i]);
        if (isset($out[$t]))
        {

            if (strlen($out[$t])>strlen($fragments[$i]))
                $out[$t]=$fragments[$i]; //keep the shorter
        }
        else
            $out[$t]=$fragments[$i];

    }
    return $out;
}

function findMatches($fragments,$payload)
{
    $matches=array();
    $n=0;
    foreach ($fragments as $fragment)
    {
        $n++;
        $begin=-1;
        $trimmed=bareFragment($fragment);
        if ($trimmed)
        while ( ($begin=strpos($payload, $trimmed,++$begin) )!==false)
        {
            if (strlen($trimmed<=2) and !ctype_alnum($trimmed)) //its a symbol!
                $Standalone=true;
            else
                if (($begin && ctype_alnum($payload[$begin-1]))
                 or 
                ($begin<strlen($payload) and ctype_alnum($payload[$begin+strlen($trimmed)])) )
                    continue; //a piece inside a token, useless

            $element=array('original'=>$fragment,
                    'len'=>strlen($fragment),'trimmed'=>$trimmed,'start'=>$begin,'end'=>strlen($trimmed)+$begin);
            if (isset($matches[$trimmed]))
                if (strlen($fragment)>$matches[$trimmed]['len']) continue; //already matches this fragment, this is just a bigger copy
                elseif ($fragment===$matches[$trimmed]['original']) //same thing, must be different location
                        $matches[]=$element;
                else //smaller, replace
                    $matches[$trimmed]=$element;
            else
                $matches[$trimmed]=$element;

        }
    }
    uasort($matches,function($t1,$t2){return $t1['start']>$t2['start'];});
    return $matches;
}

function findOverlaps($matches)
{
    $overlaps=array();
    foreach ($matches as $k1=>$v1)
        foreach ($matches as $k2=>$v2)
            if ($k1!=$k2 and $v2['start']>=$v1['start'] 
                and $v2['start']<$v1['end'])
            { //$v2 overlaps $v1
                if (isset($overlaps[$k2]))
                    $overlaps[$k2][]=$k1;
                else
                    $overlaps[$k2]=array($k1);
            }
    uasort($overlaps,function($t1,$t2){return count($t1)>count($t2);});
    return $overlaps;    
}
function is_whitespace($c)
{
    return $c==chr(32) or $c=='\t' or $c==PHP_EOL;
}
function build($payload,$matches)
{
    usort($matches,function($t1,$t2){return $t1['start']>$t2['start'];});
    $result=substr($payload,0,$matches[0]['start']).$matches[0]['original'];
    $n=1;
    $pieces=str_repeat(" ",$matches[0]['start']).str_repeat($n, strlen($matches[0]['original']));
    $cover=str_repeat(".",$matches[0]['start']).str_repeat("✓", strlen($matches[0]['original']));
    $flag=false;
    foreach ($matches as $k=>$v)
    {
        if ($flag==false)
            $flag=true;            
        else
        {
            $len=$v['start']-$preV['end'];
            $uncovered=substr($payload,$preV['end'],$len);
            if (substr($preV['original'],-strlen($uncovered))===$uncovered)
                $uncovered="";
            $pieces.=str_repeat(" ", strlen($uncovered)).str_repeat(++$n, strlen($v['original']));
            $cover.=str_repeat(".", strlen($uncovered)).str_repeat("✓", strlen($v['original']));
            $result.=$uncovered.$v['original'];
        }
        $preV=$v;
    }
    return array($result,$pieces,$cover);

}

// $sql= "select * from users where username='" . ($payload="1' union all select 1,2,3 -- ") . " ' ";

if (!$quiet) echo "Filtering duplicate fragments... ";
$prevCount=count($fragments);
$fragments=removeDuplicates($fragments);
if (!$quiet) echo "Succesfully filtered ".($prevCount-count($fragments))." duplicates!".PHP_EOL;

if (!$quiet) echo "Matching fragments... ";
$matches=findMatches($fragments,$payload);
if (!$quiet) echo "done.".PHP_EOL;

if (!$matches)
    if ($quiet) die("");
    else die("Unable to find any matching fragments.".PHP_EOL);
#TODO: resolve overlaps better!
while ($overlaps=findOverlaps($matches))
{
    if (!$quiet) echo "Resolving ".count($overlaps)." confusions... ";
    foreach ($overlaps as $k=>$v)
    {
        unset($matches[$k]);
        break;
    }
    if (!$quiet)echo "done.".PHP_EOL;

}

if (!$quiet) echo "Found all useful bits and pieces, gluing... ";
list($result,$pieces,$cover)=build($payload,$matches);
if (!$quiet)echo "done.".PHP_EOL;


if (!$quiet) 
    echo 
    "Coverage: ".$cover.PHP_EOL.
    "Result  : ".$result.PHP_EOL.
    "Pieces  : ".$pieces.PHP_EOL;
else echo $result;

