<?php
use VentureProducts\Database\DatabaseConnection as DB;
global $QUERY,$stat,$TIME,$START,$event,$table_key,$table_key_val;
$fields="RowNumber,EventClass,TextData,CPU,Reads,Writes,Duration 	,ClientProcessID ,SPID,StartTime,EndTime";
$tableList=DB::get('syspro')->all("SELECT  TABLE_NAME as [table] FROM  tests.INFORMATION_SCHEMA.TABLES Order BY TABLE_NAME");
$tbl=$_REQUEST['tbl'];
$tbl2=$_REQUEST['tbl2'];
$colors=array("ff0033","ff00cc","cc00ff","6600ff","3300ff","00ccff","00ffcc","00ff33","ffff00","ff9900","00cc00","999900","006699");
//dd($tableList);
?><h2>Analysis of Select statements only (not Update/Insert, etc)</h2>
<form method="get"><input type="hidden" name="p" value="<?=$_GET['p']?>"/> Profiler Selection: <select name="tbl">
<? foreach ($tableList as $table){?>
<option value="<?=$table->table?>" <?=$table->table==$tbl?"selected":""?>><?=$table->table?></option>
<? } ?>
</select>

Compare to:
<select name="tbl2"><option value="">--Don't compare--</option>
<? foreach ($tableList as $table){?>
<option value="<?=$table->table?>" <?=$table->table==$tbl2?"selected":""?>><?=$table->table?></option>
<? } ?>
</select>

Top: <input type=number name="top" value="<?=$_GET['top']?>"/><button>Go</button> <em>Must be saved to "tests" database</em> <br>
View time range: <input  type=time name="timestart" value="<?=$_GET['timestart']?>"> to <input type=time name="timeend" value="<?=$_GET['timeend']?>"><input type="submit" value="Submit">
</form><?

if ($tbl){
    $SQL="Select ".($_GET['top']>0?" TOP ".$_GET['top']:"")." $fields FROM [tests].[dbo].[$tbl] WHERE EndTime <>'' AND (TextData LIKE '%SELECT%' OR TextData LIKE '%UPDATE%' OR TextData LIKE '%INSERT%' OR TextData LIKE '%REPLACE%' OR TextData LIKE '%DELETE%')";
    $stmt = DB::get('syspro')->prepare($SQL);
    $stmt->execute();
    while ($r=$stmt->fetchObject()) {       
        processblock("A",$r);
    }
    arsort($QUERY['A']);
    ?><h2>Analyze Profile: <?=$tbl?></h2><table border=1 style="font-size:12px;"><tr><th></th><th>Table</th><th> #</th><th>time (sec)</th></tr><?
        foreach ($QUERY['A'] as $q=>$c){ 
            ?><tr><td ><?=chr($table_key[$q]+64)?></td><td><?=$q?></td><td><?=$c?></td><td align=right><?=round($TIME['A'][$q]/1000000,4)?></td></tr>
            <?                                
        }   
    ?></table><?
    if ($tbl2){
        $SQL="Select ".($_GET['top']>0?" TOP ".$_GET['top']:"")." $fields FROM [tests].[dbo].[$tbl2] WHERE EndTime <>'' AND (TextData LIKE '%SELECT%' OR TextData LIKE '%UPDATE%' OR TextData LIKE '%INSERT%' OR TextData LIKE '%REPLACE%' OR TextData LIKE '%DELETE%')";
        $stmt = DB::get('syspro')->prepare($SQL);
        $stmt->execute();
        while ($r=$stmt->fetchObject()) {       
            processblock("B",$r);
        }
        arsort($QUERY['B']);
        ?><h2>Compare to: <?=$tbl2?></h2><table border=1 style="font-size:12px;"><tr><th></th><th>Table</th><th> #</th><th>time (sec)</th></tr><?
        foreach ($QUERY['B'] as $q=>$c){ 
            ?><tr><td ><?=chr($table_key[$q]+64)?></td><td><?=$q?></td><td><?=$c?></td><td align=right><?=round($TIME['B'][$q]/1000000,4)?></td></tr>
            <?                                
        }   
        ?></table><?    
    }?>   
        
        
        
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script type="text/javascript">
      google.charts.load('current', {'packages':['timeline','controls']});
      google.charts.setOnLoadCallback(drawChart);
      function drawChart() {

        var dashboard = new google.visualization.Dashboard(
        document.getElementById('dashboard'));

        //var container = document.getElementById('timeline');
        //var chart = new google.visualization.Timeline(container);
        var chart = new google.visualization.ChartWrapper({
                    chartType: 'Timeline',
                    containerId: 'timeline'
        });
        var dataTable = new google.visualization.DataTable();
        var options = {
            // hAxis: { 
            //     format: 'S'
            // }
            // hAxis: { gridlines: { count: 4 } }
        };
        dataTable.addColumn({ type: 'string', id: 'Server' });
        dataTable.addColumn({ type: 'string', id: 'Event' });
        dataTable.addColumn({ type: 'number', id: 'Start' , label:'Start'});
        dataTable.addColumn({ type: 'number', id: 'End' });
        dataTable.addRows([<?=implode(",\n",$event)?>]);
        var slider = new google.visualization.ControlWrapper({
            'controlType': 'NumberRangeFilter',
            'containerId': 'slider',
            'options':{
                'filterColumnLabel': 'Start',
                 'ui': {'format':{'pattern':'ss'}}
            }
        })

        //
        

        // var programmaticChart  = new google.visualization.ChartWrapper({
        //   'chartType': 'PieChart',
        //   'containerId': 'programmatic_chart_div',
        //   'options': {
        //     'width': 300,
        //     'height': 300,
        //     'legend': 'none',
        //     'chartArea': {'left': 15, 'top': 15, 'right': 0, 'bottom': 0},
        //     'pieSliceText': 'value'
        //   }
        // });

        // var data = google.visualization.arrayToDataTable([
        //   ['Name', 'Donuts eaten'],
        //   ['Michael' , 5],
        //   ['Elisa', 7],
        //   ['Robert', 3],
        //   ['John', 2],
        //   ['Jessica', 6],
        //   ['Aaron', 1],
        //   ['Margareth', 8]
        // ]);

        dashboard.bind(slider, chart);
        dashboard.draw(dataTable);
        //
        // chart.draw(dataTable,options);
      }
    </script>
      <div id="dashboard">
        <div id="slider" style="width:100%; height: 100px;"></div>
        <div id="timeline" style="width:100%; height: 900px;"></div>
    </div>
    
    
    <?
}

function processblock($table,$r){ 
    $r=(array)$r;
    global $QUERY,$stat,$TIME,$START,$event,$table_key,$table_key_val;
    $text=strtoupper($r['TextData']);
    $time=$r['Duration'];
    
    if (!$START[$table]){ 
        $START[$table]=$r['StartTime'];
    } 

    $regex = [
        'select'=>'/select.*from[ \t]+([a-zA-Z0-9\._]+)[ \t]+/mi',
        'update'=>'/update[ \t]+([a-zA-Z0-9\._]+)[ \t]+set/mi',
        'delete'=>'/delete[ \t]+from[ \t]+([a-zA-Z0-9\._]+)[ \t]+/mi', //m=multiple lines; i=case insensitive
        'insert'=>'/insert[ \t]+into[ \t]+([a-zA-Z0-9\._]+)[ \t]+/mi'
    ];
    //To do: in a join siutation. example table1 INNer JOIN table2 on a=b INNer JOIN table3 on b=c.
    //OUtpute of regex table name becomes: table1, table2, table3
    foreach ($regex as $type=>$reg) {
        if (preg_match_all($reg,$text,$results)) {
            foreach($results[1] as $tableName){
                $key=strtoupper($type).'_'.$tableName;
                if (!$table_key[$key]){
                    $table_key[$key]=$table_key_val+1;	
                    $table_key_val++;
                }
                $QUERY[$table][$key]++;
                $TIME[$table][$key]+=$time/(sizeof($results[1]));	
                //dd($START,$START[$table],$r);
                $event[]="['".$table.". ".strtoupper($type)."','".$tableName."-".chr($table_key[$key]+64)."', ".displayJavascriptMicroTime($START[$table],$r['StartTime']).",".displayJavascriptMicroTime($START[$table],$r['EndTime'])."]"; 
            }
        }
    } 
}

function displayJavascriptMicroTime($f1,$f2){
    $ex1 = explode('.',$f1);
    $ex2 = explode('.',$f2);
    $d1 = strtotime($ex1[0]).str_pad((floatval('.'.$ex1[1])*1000),3,"0",STR_PAD_LEFT);
    $d2 = strtotime($ex2[0]).str_pad((floatval('.'.$ex2[1])*1000),3,"0",STR_PAD_LEFT);
    //dd($f1,$f2,$ex1,$ex2,$d1,$d2);
    $d2 = $d2-$d1;
    return $d2;
    return "new Date(".$d2.")";        
}