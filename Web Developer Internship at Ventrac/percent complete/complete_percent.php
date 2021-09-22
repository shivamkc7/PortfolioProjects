<?php
ini_set("max_execution_time","300");

global $WipBinStatus,$statusShow;

require_once $_SERVER['SERVER_ROOT'].'/security/connections/pdoWeb2.php'; // returns $syspro
use VentureProducts\Database\DatabaseConnection as DB;
//$StatusArray=array(0=>"Not Started",1=>"Job Started", 4=>"Jobs Completed", 5=>"Bin Completed", 6=>"Bin Completed (Rush)");
use VentureProducts\Entity\Tracking\Clock\Batch;
use VentureProducts\Utilities\Style\Color; // not needed if Color:: moved to Batch function
use VentureProducts\Laravel\Models\Auth\User;
require_once($_SERVER['SERVER_ROOT'].'/dealers/codereuse/documents/WorkOrderDocument.php');
require_once($_SERVER['SERVER_ROOT'].'/dealers/codereuse/classes/productionschedule/ProductionScheduleBin.php');


//{Function:"WipBinStatusInsert",WipBinId:WipBinId,Status:Status,Type:Type}
if ($_POST['Function']=="WipBinStatusInsert"){
	//insert DB code
	if ($_POST['Id']==''){
        $stmt = DB::get('sysproVpi')->prepare("INSERT INTO WipBinStatus (WipBinId, Type, UserId, DateUpdated, Status, Note) 
        VALUES (:WipBinId, :Type, :UserId, :DateUpdated, :Status, :Note)");
    }else{
        $stmt = DB::get('sysproVpi')->prepare("UPDATE WipBinStatus SET WipBinId =:WipBinId, Type=:Type, UserId=:UserId, Status=:Status, DateUpdated=:DateUpdated, Note=:Note  WHERE Id =:Id");
        $stmt->bindParam(':Id',$_POST['Id']);
    }  
   
    $stmt->bindParam(':WipBinId',$_POST["WipBinId"]);
    $stmt->bindParam(':Type',$_POST["Type"]);
    $stmt->bindParam(':UserId',auth()->user()->id);
    $stmt->bindParam(':DateUpdated',date("Y-m-d H:i:s"));
    $stmt->bindParam(':Status',$_POST["Status"]);
    $stmt->bindParam(':Note',$_POST["Note"]);
    $stmt->execute();
    DB::get('sysproVpi')->lastInsertIdSql();
	
    print updatedbywho(auth()->user()->id,date("Y-m-d H:i:s"));
	
	exit();
}



$pages=array("Bins"=>"Bin Status","Range"=>"Range","WCToDo"=>"Work Centre To Do","WCStatus"=>"WC Status","Bins2"=>"Bin Beta");


$p = (isset($_GET['p'])?$_GET['p']:key($pages));
$title=($p?$pages[$p]." - ":"")."Completions";
$css[]="tabs.css";
$css[]="manufacture.css";
## for popup box
$js[]='timepicker/jquery-1.8.2.min.js';$js[]='timepicker/jquery-ui.min.js';

?><script type="text/javascript">
	jQuery.noConflict();
</script><?

require_once($_SERVER['DOCUMENT_ROOT'] . '/codereuse/template/pages.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/codereuse/classes/productionschedule/CompanyCalendar.php');
include($_SERVER['SERVER_ROOT'].'/security/connections/dbConnectODBC.php'); //returns variable $odbcr
include $_SERVER['SERVER_ROOT'].'/security/connections/servicecon.inc.php'; //$service_con

global $wc_used,$wc_stats,$mfgdetail,$mfgs,$nestListmfg; 

/*
if ($_GET['view']=='detail' && $_GET['binLabel'] && false){
	if ($_GET['wc']) $where[]="WL.WorkCentre='".$_GET['wc']."'";	
	if ($_GET['binLabel']){
		$where[]="B.BinLabel='".addslashes($_GET['binLabel'])."'";
	}
	
	$SQL="Select J.Job,J.Component,B.BinLabel,B.Color,J.Qty,	J.QtyReceived,WL.WorkCentre,WL.PlannedStartDate,WL.PlannedEndDate,OperCompleted,ActualFinishDate,WL.ICapacityReqd,WL.RunTimeIssued,WL.ICapacityReqd*(CASE WHEN OperCompleted='Y' THEN 0 ELSE 1 END) as TimeExpected
From ".db_dbo('sysproVpi')."WipBinJob J  
Inner JOIN ".db_dbo('sysproVpi')."WipBin B ON B.Id=J.WipBinId
INNER JOIN WipMaster WM ON J.Job=WM.Job 
INNER JOIN WipJobAllLab WL ON WM.Job=WL.Job
WHERE  ".implode(" AND ",$where)." Order BY J.Job";
$list= DB::get('syspro')->all($SQL);

print "<div style='font-size:30px; font-weight:bold;margin-bottom:10px;'>".WorkOrderDocument::BinLabel_HTML($_GET['binLabel'],$list[0]->Color,5)
	." <img style=\"background-color:#fff;width:40px;\" src=\"/images/manufacturing/Operations/450/color/".$_GET['wc'].".png\" alt=\"".$_GET['wc']."\"></div>";	
	
	
?><table class="vpi"><tr><th>Job</th><th>Component</th><th>BinLabel</th><th>Qty</th><th>WorkCentre</th><th>PlannedStartDate</th><th>PlannedEndDate</th><th>OperCompleted</th><th>ActualFinishDate</th><th></th></tr><?
	foreach($list as $r){
		?><tr><td><a target="lookup" href="/Manufacturing/WIP/JobProcessor.php?p=L&job=<?=$r->Job?>"><?=$r->Job*1?></a></td><td><a target="lookup" href="/lookup.php?sc=<?=$r->Component?>"><?=$r->Component?></a></td><td><?=WorkOrderDocument::BinLabel_HTML($r->BinLabel,$r->Color,1)?></td><td><?=$r->Qty?></td><td><?=$r->WorkCentre?></td><td><?=dateonly($r->PlannedStartDate)?></td><td><?=dateonly($r->PlannedEndDate)?></td><td><?=$r->OperCompleted?></td><td><?=$r->ActualFinishDate?dateonly($r->ActualFinishDate):""?></td><td></td></tr><?
	}
	?></table><?
	return;
	
}else*/

if ($_GET['view']=='detail'){	
	
	if ($_GET['wc']) $where[]="WL.WorkCentre='".$_GET['wc']."'";
	if ($_GET['mfgID'])   $where[]="BL.MfgOrder='".$_GET['mfgID']."'";
	if ($_GET['date'])   $where[]="WL.PlannedEndDate = '".$_GET['date']." 00:00:00'";
	if ($_GET['bin'])   $where[]="(BL.DefaultFabBin)='".($_GET['bin']=="_blank"?"":$_GET['bin'])."'";

	//dump($where);
	// if ($_GET['binLabel']){
	// 	print "Shouldn't be here..."; exit();
	// }
	if ($_GET['bin']=='P$') {
		$mfg = ProductionScheduleBin::get($_GET['mfgID'],true);
		?>
		<style>
			table.vpi tr:nth-child(even) {background: #cccccc;}
			table.vpi tr:nth-child(odd) {background: #dddddd;}
		</style>

	
		<h2>
			<span style="padding:5px;"><?=$mfg->batch?> <?=$mfg->alternateKey1?></span> 
			<?=$mfg->stockCode?> <?=$mfg->description?> 
			Mfg Order: <?=$mfg->id?>
		</h2>
		<h3>Purchased Parts</h3>
		<table class="vpi">
			<tr><th>StockCode</th><th>Description</th><th>Qty Completed</th><th>Total Qty</th><th>Completed</th></tr>
			<? foreach ($mfg->components as $c) { 
				if (!(substr($c->componentPlanner,0,2)<='88'||substr($c->sequenceNum,0,2)=='B0')) { continue; } // don't show untracked items!
				
				$complete = ($c->qtyReceived>=$c->totalQtyReqd);
				$bg = ($complete?'background-color:green; color:white;':($c->qtyReceived>0?'background-color:yellow;':'background-color:red; color:white;'));
				?>
				<tr>
					<td><?=$c->component?></td>
					<td><?=$c->componentDescription?></td>
					<td style="text-align:right"><?=round($c->qtyReceived,2)?></td>
					<td style="text-align:right"><?=round($c->totalQtyReqd,2)?></td>
					<td style="<?=$bg?>"><?=($complete?'Yes':'No')?></td>
				</tr>
			<? } ?>
		</table>
		<?
	} else {	
		$ops=Batch::jobDetails($where);
		if ($_GET['wc']=='73.06'){
			foreach ($ops as $op){
				if ($op['Completed']!=1){
					$jobList[$op['ComponentJob']]+=$op['QtyToMake']-$op['QtyCompleted'];
				}				
			}			
			if (sizeof($jobList)){				
				$stmt = DB::get('sigmaNest')->prepare("Select WONumber,PartName,PIP.QtyInProcess,P.TaskName From PIP 
	INNER JOIN Program P ON P.ProgramName=PIP.ProgramName WHERE WONumber IN ('".implode("','",array_keys($checkPIP))."')");
				
				$stmt->execute();
				while($r = $stmt->fetchObject()){
					$nestedList[$r->WONumber][$r->PartName]['qty']+=$r->QtyInProcess;
					$nestedList[$r->WONumber][$r->PartName]['taskName'][$r->TaskName]++;
					
				}
			}
		}
		?>
		<div style='font-size:24px; font-weight:bold;margin-bottom:10px;'><?=WorkOrderDocument::BinLabel_HTML($_GET['binLabel'],$ops[0]['Color'],5)?>
	 <img style="background-color:#fff;width:40px;" src="/images/manufacturing/Operations/450/color/<?=$_GET['wc']?>.png" alt="<?=$_GET['wc']?>"> <span style="font-size:18px"><?=$ops[0]['StockCode']?> <?=$ops[0]['Description']?> Mfg Order: <?=$_GET['mfgID']?> Job: <a href="/Manufacturing/WIP/JobProcessor?p=L&job=<?=$ops[0]['MasterJob']*1?>"><?=$ops[0]['MasterJob']*1?></a></span></div>	
		<h3>Component List</h3>
		<table class="vpi"><tr><th>Job</th><th>StockCode</th><th>Description</th><th>Qty Completed</th><th>Total Qty</th><th>Exp. Time</th><th>Actual Time</th><th>Planned End</th><th>Actual End</th><th>Completed</th></tr>
		<?
		foreach ($ops as $op){
			if ($_GET['binLabel'] && trim($op['BinLabel']) != $_GET['binLabel']) {
				continue;
			}

			$totals['ICapacityReqd']+=$op['ICapacityReqd'];
			$totals['RunTimeIssued']+=$op['RunTimeIssued'];
			?>				
			<tr><td><a href="/Manufacturing/WIP/JobProcessor.php?p=L&job=<?=$op['ComponentJob']?>"><?=($op['ComponentJob']*1)?></a></td><td><?=$op['ComponentStockCode']?></td><td><?=$op['ComponentDescription']?></td><td><?=$op['QtyCompleted']*1?></td><td><?=$op['QtyToMake']*1?></td>
			<td><?=$op['ICapacityReqd']*1?></td>
			<td><?=$op['RunTimeIssued']*1?></td>
			<td><?=substr($op['PlannedEndDate'],0,10)?></td><?
			$daysBetween= (strtotime($op['PlannedEndDate'])-strtotime($op['ActualFinishDate']))/ (60 * 60 * 24);
			?>
			<td style="<?=color_style($daysBetween, -8,8)?>"><?=substr($op['ActualFinishDate'],0,10)?></td>
			
			<?
			if ($op['Completed']==1){
				$style="background-color:green; color:white;"; $label="Yes";
			}elseif($op['PunchCount']>0){
				$style="background-color:yellow;"; 
				//if ($op['RunTimeIssue']>0){ // future check, is this running now? This is isn't the right criteria to find this.
					$label="Started";
				/*}else{
					$label="Started";
				}*/		
				
				
			}elseif($nestedList[$op['ComponentJob']][$op['ComponentStockCode']]){
				$style="background-color:yellow;";
				$label="Nested".($nestedList[$op['ComponentJob']][$op['ComponentStockCode']]['qty']!=$op['QtyToMake']-$op['QtyCompleted']?" Qty: ".$nestedList[$op['ComponentJob']][$op['ComponentStockCode']]['qty']:"")." Task: ";
				foreach ($nestedList[$op['ComponentJob']][$op['ComponentStockCode']]['taskName'] as $task=>$count){
					$label.=' <a href="/Manufacturing/WIP/Fab.php?layoutPdf=layout01'.$task.'.PDF">'.$task.'</a>';
				}
			
			}else{
				$style="background-color:red; color:white;"; $label="No";
			}
				?><td<?=($style?" style='".$style."'":"")?>><?=$label?></td></tr><?		
		}?>
			<tr class="foot"><td colspan=5><strong>Totals</strong></td><td><?=$totals['ICapacityReqd']?></td><td><?=$totals['RunTimeIssued']?></td><td></td><td></td><td></td></tr>
		</table>
		<?
	}
} else {
	switch ($p){
		case "WCToDo":
			$wcs=array('73.02','73.06','73.12','73.16','73.22','73.24','73.30','73.35');
		
			?>
			<form action="" method="GET" name="shcriteria" class="noprint"><input type="hidden" name="p" value="<?=$p?>"/>
			<strong>Work Centre</strong> <select name="wc"><option value="0">ALL</option><?
			foreach ($wcs as $wc){	
				?><option value="<?=$wc?>"<?=($wc==$_GET['wc']?" selected":"")?>><?=$wc." - ".Batch::workCentreDescription($wc)?></option><?
			}
			?></select><button type="submit">Go</button>
			</form>
			<script type="text/javascript">
				function JobDetailsWC(wh,date,mfgID,bin,view){
					dialog('modalbox','Job View','<b>test</b>');
				}
			</script>
			<?
			
			
			$wcToUse=$_GET['wc'];
			if ($wcToUse){
				$ops=Batch::binsByDepartment();//array('73.06','73.02','73.12')
				foreach($ops as $op){
					if ($op['Completed']<$op['Total'] && $op['PlannedEndDate']<=date("Y-m-d 00:00:00",strtotime('+10 days'))){ 
						if ($op['DefaultFabBin']=="") $op['DefaultFabBin']=="_blank";
						$wcView[$op['WorkCentre']][substr($op['PlannedEndDate'],0,10)][$op['MfgOrder']][$op['DefaultFabBin']][]=$op;
						$mfgdetail[$op['MfgOrder']]=$op; // used to find pull mfg level detail... 
						$wcHrsLeft[$op['WorkCentre']][substr($op['PlannedEndDate'],0,10)][$op['MfgOrder']]+=$op['TimeExpectedLeft'];
					}
				}
	
				### WC View
	
				//$op['PlannedEndDate']][$op['MfgOrder']][$op['DefaultFabBin']][]=$op;	
				?><div id='loaderDiv' style='display:none;'></div>
				<h1><?=$wcToUse?> - <?=Batch::workCentreDescription($wcToUse)?></h1>
				<table class="vpi">
				<?
				ksort($wcView[$wcToUse]);
				foreach ($wcView[$wcToUse] as $datePriority => $mfgIDs){			
					foreach($mfgIDs as $mfgID => $bins){
						$mfgInfo=$mfgdetail[$mfgID];
						?><tr><?
						if ($lastDatePriority!=$datePriority){
							?><td rowspan=<?=sizeof($mfgIDs)?> style="<?=WorkOrderDocument::calculateDateColor($datePriority)?>;text-align:center;"><?
								$thistime=strtotime($datePriority);
								print "<div style='font-size:16px'>".date("M",$thistime)."</div>";
								print "<div style='font-size:36px'><strong>".date("j",$thistime)."</strong></div>";						
								print "<div style='font-size:12px'>".date("l",$thistime)."</div>";
								print "<div style='font-size:14px'>".date("Y",$thistime)."</div>";
	
	
					?></td><?
							$lastDatePriority=$datePriority;
						}
						?><td style="padding: 5px;background-color:<?=$mfgInfo['Color']?>;color:<?=Color::getTextColor($mfgInfo['Color'])?>; "><div style="display:inline-block; float:right; font-size:11px;">Left:<br><?=round($wcHrsLeft[$wcToUse][$datePriority][$mfgID],2)?>hr</div>
						<div style="font-size: 24px;">
							<strong><?=$mfgInfo['Batch']?> - <?=(trim($mfgInfo['AlternateKey1'])!=""?$mfgInfo['AlternateKey1']:$mfgInfo['StockCode'])?></strong>
							<div style="font-size:10px;">MFid: <?=$mfgID?> - <?=shortenProductDescription($mfgInfo['Description'],$mfgInfo['AlternateKey1'])?> </div>
						</div>
						</td><td><?
						ksort($bins);
						foreach($bins as $bin => $a){
							foreach($a as $r){
								
								print "<div style='width:125px; float:left; ".Batch::binStyle($bin).";'><div style='float:left;display: inline;font-size: 30px; padding: 2px 20px;'><strong>".$bin."</strong></div>
								<div style='font-size:10px; padding: 5px; display:inline; float:left;'>";
								$completed=$r['Completed']==$r['Total']?true:false;							
								
								print ' <a target="detail" style="font-size:14px;'.($r['Completed']>0?"background-color:green; color:#fff; padding: 1px 5px;":Batch::binStyle($bin)).'" 
								href="?view=detail&wc='.$wcToUse.'&date='.$datePriority.'&mfgID='.$mfgID.'&bin='.$bin.'">'.$r['Completed']."</a> of 
								<a style=\"font-size:14px;".Batch::binStyle($bin)."\">".$r['Total']."</a><br>";
								
								if (!$completed){ 
									print round($r['TimeExpectedLeft'],2)." hrs left<br>";
								}else{
									print ($completed?round($r['TimeActual'],2)." of ":"").round($r['TimeExpected'],2)." hrs<br>";
								}
								if ($completed && $r['TimeExpected']>0 &&  $r['Batch']!='Pilot' && $r['TimeActual']>0){
									$per=($r['TimeActual']-$r['TimeExpected'])*100/$r['TimeExpected'];
									print " <span style='padding:1px 5px;".color_style($per*-1, -100,100)."'>".round($per,1)."%</span>";
								}
	
								//print "</div>";
	
								print "</div></div>";						
	
	
							}	
						}
	
						?></td></tr><?
					}
	
				}
				?></table><?
			}
			break;
		case "Bins":
					
			/*
	
			Round 2 -> 
			Make macro view clickable -> see what the jobs 1-1 was of that operation.
			Make Work Station View-> Order by Due Date, Then top level due date.
			
			Calculation for if they are on target or not:
			Green - not past due on anything. What about partial progress on the curent due date? Average due date ahead
			Yellow - currenty working on things due today (or tomorrow).
			Red - 
			What are they currently working on, and what is due 
		
			('73.02','73.06','73.12','73.16','73.22','73.24','73.30','73.35'
			*/
			//print Batch::workCentreDescription('73.02'); exit();
			$deptWC['fab']=array('73.02','73.06','73.12','73.16');
			$deptWC['weld']=array('73.22','73.24');	
			$deptWC['paint']=array('73.30','73.35');	
			
			$status['s']['label']='By Bin';
			$status['s']['rules'] = array();
			$status[1]["label"]="Needs Laser Nest";
			//$status[1]["rules"]["fab"]['complete']=false;
			$status[1]["rules"]["fab"]['nestcomplete']=false;
			
			$status[3]["label"]="In Fab";
			$status[3]["rules"]["fab"]['complete']=false;
			//$status[3]["rules"]["weld"]['started']=false;
			
			$status[5]["label"]="Ready For Weld";
			$status[5]["rules"]["fab"]['complete']=true;
			$status[5]["rules"]["weld"]['started']=false;
			
			$status[7]["label"]="In Weld";
			$status[7]["rules"]["weld"]['started']=true;
			$status[7]["rules"]["weld"]['complete']=false;
			
			$status[9]["label"]="Ready For Paint";
			$status[9]["rules"]["fab"]['complete']=true;
			$status[9]["rules"]["weld"]['complete']=true;
			$status[9]["rules"]["paint"]['started']=false;
			
			$status[12]["label"]="Painting";
			$status[12]["rules"]["paint"]['started']=true;
			$status[12]["rules"]["paint"]['complete']=false;
			
			$status[15]["label"]="Ready for Assembly";
			$status[15]["rules"]["fab"]['complete']=true;
			$status[15]["rules"]["weld"]['complete']=true;
			$status[15]["rules"]["paint"]['complete']=true;
			if (!$_GET['dt']) $_GET['dt']=date("Y-m-d",strtotime("+1 month"));
			?><form action="" method="GET" name="shcriteria" class="noprint"><input type="hidden" name="p" value="<?=$p?>"/>
				<strong>View:</strong> 
				<select name="v">
					<option value="0">ALL</option>
					<? foreach($status as $statusId=>$a){ ?>
						<option value="<?=$statusId?>"<?=($statusId==$_GET['v']?" selected":"")?>><?=$a['label']?></option>
					<? } ?>
				</select>
				<input type="checkbox" name="bo" value="true" <?=($_GET['bo']=='true'?'checked':'')?>/> Include Purchased Bin
				<br>
				Job Start Date Before: <input type="date" name="dt" value="<?=$_GET['dt']?>"/>
				<strong>Search:</strong>
				<input name="s" value="<?=$_GET['s']?>"/>
				<button type="submit">Go</button>
			</form><?			
			
			//dump($wcStats);exit();
			$where = array();
			if ($_GET['dt']){
				$where[]="WMP.StartDate <= '".$_GET['dt']."'";
			}
			if ($_GET['s']) {
				if (ctype_digit($_GET['s'])) {
					$ors[] = "WMP.Id='".$_GET['s']."'";
					$ors[] = "BL.Job LIKE '%".$_GET['s']."'";
					//$ors[] = "WMP.Id=(SELECT TOP 1 ProductionScheduleId From ".db_dbo('sysproVpi')."ProductionScheduleDetail WHERE Job LIKE '%".$_GET['s']."' AND Status<>'-1')";
				}
				$ors[] = "WMP.StockCode='".$_GET['s']."'";
				$ors[] = "WMP.StockCode IN (SELECT StockCode FROM InvMaster IMP WHERE IMP.Description LIKE '%".$_GET['s']."%' OR IMP.AlternateKey2 LIKE '%".$_GET['s']."%')";
				$where[] = "(".implode(" OR ",$ors).")";
			}
			
			$ops=Batch::binsByDepartment(array(),$where);//array('73.06','73.02','73.12')
			foreach($ops as $op){
				$binIds[$op['WipBinId']]++;
				$op['BinLabel']=trim($op['BinLabel']);
				if ($op['BinLabel']!=""){ 
					$bL=explode("|",$op['BinLabel']);
					if (trim($op['DefaultFabBin'])!=trim($bL[2])){
						//print $op['DefaultFabBin']."->".$bL[2]." on Job: ".$op['Job']."<br>";
					}					
					$op['DefaultFabBin']=$bL[2];
				}
				if ($op['DefaultFabBin']=="") $op['DefaultFabBin']="_blank";
				//$jobToMfg[$op['ComponentJob']]=$op['MfgOrder'];
				if ($op['ComponentJob']=="000000000479403"){
					//dump($op); exit();
				}
				if ($op['WorkCentre']=='73.06'){
					$op['Nested']=$op['Completed'];
					if (!$op['Completed']){ ### if laser option, add to list to look at on PIP on signmanest		
						$checkPIP[$op['ComponentJob']][$op['MfgOrder']]=$op;
	
					}
				}
				
				### Calculated status of wc center (ahead or behind?) 				
				
				if ($op['Batch']!="Pilot"){				
					$daysOff=0;		
					
					### GEt Work Center Status -> May want to consider going a different direciton:
					### could just look at last 2 days, and display average. However this would hide old jobs that got skipped... 
					if ($op['Completed']<$op['Total']){ //jobs not done yet						
						if ($op['PlannedEndDate']<date("Y-m-d")." 00:00:00"){ 
							$daysOff=CompanyCalendar::getDaysBetween(date("Y-m-d"),$op['PlannedEndDate']); 
							$timeweight=$op['TimeExpected']-$op['TimeActual'];						
							$wc_stats[$op['WorkCentre']]['daysOff']+=$daysOff*$timeweight;
							$wc_stats[$op['WorkCentre']]['totalItems']+=$timeweight; //$op['Total']-$op['Completed'];
							
						}
					}elseif($op['PlannedEndDate']>date("Y-m-d")." 00:00:00"){ //IF Jobs are done AND DONE ahead of schedule
						//positively affect number if jobs are done ahead of time.
						$daysOff=CompanyCalendar::getDaysBetween(date("Y-m-d"),$op['PlannedEndDate']); 
						$timeweight=$op['TimeActual'];
						$wc_stats[$op['WorkCentre']]['daysOff']+=$daysOff*$timeweight;
						$wc_stats[$op['WorkCentre']]['totalItems']+=$timeweight; //$op['Total']-$op['Completed'];
					}else{ //completed in the past					
					}
	
				}
				if ($daysOff!=0){
					//print $daysOff."->"; dump($op); 
				}
	
				
				if (!$mfgs[$op['MfgOrder']][$op['DefaultFabBin']][$op['WorkCentre']]){
					$op['DueDateMin']=$op['DueDateMax']=$op['PlannedEndDate'];								
				}else{
					$prevEntry=$mfgs[$op['MfgOrder']][$op['DefaultFabBin']][$op['WorkCentre']];				
					$op['Completed']+=$prevEntry['Completed'];
					$op['Nested']+=$prevEntry['Completed'];
					$op['Total']+=$prevEntry['Total'];
					$op['TimeExpected']+=$prevEntry['TimeExpected'];
					$op['TimeActual']+=$prevEntry['TimeActual'];
					$op['DueDateMin']=($prevEntry['DueDateMin']<$op['PlannedEndDate']?$prevEntry['DueDateMin']:$op['PlannedEndDate']);
					$op['DueDateMax']=($prevEntry['DueDateMax']>$op['PlannedEndDate']?$prevEntry['DueDateMax']:$op['PlannedEndDate']);
				}			
				// if ($op['MfgOrder']==24795 && $op['DefaultFabBin']=='1-B' && $op['WorkCentre']=='73.06'){
				// 	dump($op);
				// }
				
				$mfgs[$op['MfgOrder']][$op['DefaultFabBin']][$op['WorkCentre']]=$op;
				
				
				$mfgdetail[$op['MfgOrder']]=$op; // used to find pull mfg level detail... 
				$wc_used[$op['WorkCentre']]=$op['WorkCentreDesc'];
				if ($op['TimeActual']>0 && $op['TimeExpected']>0 && $op['Batch']!='Pilot'){				
					$wc_stats[$op['WorkCentre']]['TimeActual']+=$op['TimeActual'];
					$wc_stats[$op['WorkCentre']]['TimeExpected']+=$op['TimeExpected'];
					
				}							
			}

			$SQL="SELECT * FROM WipBinStatus WHERE WipBinId IN('".implode("','",array_keys($binIds))."') ORDER BY Id";
    		$result=DB::get('sysproVpi')->all($SQL);

    		foreach($result as $r){
				$WipBinStatus[$r->WipBinId][$r->Type]=$r;
    		}			
			//dump($mfgs[24795]['1-B']['73.06']);
			
			### Check SignmaNest parts in process for nested items.
			if (sizeof($checkPIP)>0){
				$stmt = DB::get('sigmaNest')->prepare("Select WONumber,PartName,PIP.QtyInProcess,P.TaskName From ".db_dbo('sigmaNest')."PIP 
	INNER JOIN ".db_dbo('sigmaNest')."Program P ON P.ProgramName=PIP.ProgramName WHERE WONumber IN ('".implode("','",array_keys($checkPIP))."')");
				$stmt->execute();
				while($r = $stmt->fetchObject()){
					//$nestedList[$r->WONumber][$r->PartName]['qty']+=$r->QtyInProcess;
					//$nestedList[$r->WONumber][$r->PartName]['taskName'][]=$r->TaskName;					
					foreach($checkPIP[$r->WONumber] as $mfgId=>$op){
						if (!$nestListmfg[$mfgId][$op['DefaultFabBin']][$op['WorkCentre']][$r->WONumber]['taskName'][$r->TaskName]){
							$mfgs[$op['MfgOrder']][$op['DefaultFabBin']][$op['WorkCentre']]['Nested']++;
						}
						$nestListmfg[$mfgId][$op['DefaultFabBin']][$op['WorkCentre']][$r->WONumber]['qty']+=$r->QtyInProcess;
						$nestListmfg[$mfgId][$op['DefaultFabBin']][$op['WorkCentre']][$r->WONumber]['taskName'][$r->TaskName]++;					
					}
					
				}				
			}	
		
			### sort this list 
			foreach($mfgs as $mfgID=>$mfg){
				foreach ($mfg as $bin=>$wcs){
					$lostBin=true;
					//$wcArray=array();
					foreach($deptWC as $dept =>$dwcs){					
						$deptStat[$dept]['complete']=true;
						$deptStat[$dept]['nestcomplete']=true;
						$deptStat[$dept]['started']=false;
						
						$foundInDept=false;
						foreach ($dwcs as $wc){						
							if ($wcs[$wc]['Total']){
								$foundInDept=true;
								if ($wcs[$wc]['Completed']<$wcs[$wc]['Total']){
									$deptStat[$dept]['complete']=false;
									if ($wc=='73.06' &&$wcs[$wc]['Nested']<$wcs[$wc]['Total']){
										$deptStat[$dept]['nestcomplete']=false;
									}									
								}
								if($wcs[$wc]['Completed']>0){
									$deptStat[$dept]['started']=true;
								}								
							}						
						}
						if (!$foundInDept){
							$deptStat[$dept]['started']=true;
						}
					}
				
					
					### sort jobs by status by department ### 			
					foreach($status as $statusId=>$a){					
						$thispass=true;
						$deptfound=false;
						foreach ($a["rules"] as $dept=>$types){	
							if (isset($deptStat[$dept])) $deptfound=true;
							foreach ($types as $type=>$bool){									
								if (isset($deptStat[$dept][$type]) && $deptStat[$dept][$type]!=$bool){
									$thispass=false;
								}
							}							
						}
						###bug if dept grouping doesn't exist...
						if ($thispass==true && $deptfound){
							$statusShow[$statusId][$mfgID][$bin]=true;						
							$lostBin=false;
						}
					}				
					
					if ($lostBin==true){
						$statusShow['lost'][$mfgID][$bin]=true;
					}
					$statusShow[0][$mfgID][$bin]=true;
				}
			}
			
			if ($_GET['bo']=='true') {
				$wc_used['P$'] = 'Purchased Parts';
				$mfgOrders = ProductionScheduleBin::get(array_keys($mfgs));
				foreach ($mfgOrders as $o) {
					$ob = array(
						'MfgOrder' => $o->bin,
						'ComponentJob' => $o->bin,
						'DefaultFabBin' => 'P$',
						'Completed' => $o->completeCount,
						'Total' => $o->completeCount+$o->uncompleteCount
					);
					$mfgs[$o->bin]['P$']['P$'] = $ob;
					$ops[] = $ob;
					foreach ($statusShow as $k=>$s) {
						if (isset($s[$o->bin])) {
							$statusShow[$k][$o->bin]['P$'] = 1;
						}
					}
				}
			}
			
			if ($_GET['v']=='s') {
				foreach ($mfgs as $mfgOrder=>$m) {
					foreach ($m as $bin=>$b) {
						foreach ($b as $w) {
							$bins[$mfgOrder][$bin]['Complete'] += $w['Completed'];
							$bins[$mfgOrder][$bin]['Total'] += $w['Total'];
						}
					}
				}
				showAllBins($bins);
			} else if ($_GET['v']>0) {			
				showAllOpsBins($statusShow[$_GET['v']],$status[$_GET['v']]["label"]);
			} else {
				showAllOpsBins($statusShow[0],'All Manufacturing');
			}	
			//ksort($statusShow);
			//showDeptBins($statusShowWC[1]);
			/*
			foreach($statusShow as $statusID=>$mfgIDs){
				showAllOpsBins($mfgIDs,$status[$statusID]["label"]);			
			}
			*/
			//dump($statusShow);
	
			
			
			break;
			
		case "Range":
	
			$jobs=($_POST['jobs']?$_POST['jobs']:$_GET['j']);
			 ?>
			<form action="" method="POST" name="shcriteria" class="noprint"><input type="hidden" name="p" value="<?=$p?>"/>
			<strong>Job(s)#</strong>  <input name="jobs" type="text" value="<?php print $jobs;?>" >  <span style="font-size:9px;"><strong>Ex:</strong> 29720-29751, 29801-29851, 29861</span>
			<input type="submit" name="Submit" value="Submit" />
			</form>
			<hr />
			<?
			# Job Range
			if ($jobs){
				$rangcriteria[] = keyquery($jobs,'Job','J.Job'); // much easier
				if (!$rangcriteria){ "<h2>Please enter a range</h2>"; exit();}
				//$SQL="Select J.Job,J.Complete,IExpUnitRunTim,WorkCentre,WorkCentreDesc FROM WipMaster J INNER JOIN WipJobAllLab A ON J.Job = A.Job  WHERE (".implode (" OR ",$rangcriteria).")";
				//print $SQL;	
				$SQL="Select J.Job,A.OperCompleted AS Complete,IExpUnitRunTim,WorkCentre,WorkCentreDesc FROM WipMaster J INNER JOIN WipJobAllLab A ON J.Job = A.Job  WHERE (".implode (" OR ",$rangcriteria).")";	
	
				$result = odbc_exec($odbcr, $SQL) or die_ex("$SQL - SQL failed.");	//$odbcr variable from connection, $SQL statement
				while($j=odbc_fetch_array($result)){	
					trim_odbc_array($j,$result);
					$workcentre=$j['WorkCentre'];
					if ($workcentre >= '73.01' && $workcentre <= '73.19')		$op='Fab';
					elseif ($workcentre >= '73.20' && $workcentre <= '73.29') $op='Weld';
					else {$op = 'Other';}
	
	
					if ($j['Complete']=="Y"){
						$wc[$op][$workcentre]['c']++;
						$wc[$op][$workcentre]['h']+=$j['IExpUnitRunTim'];
						$oc[$op]['c']++;
						$oc[$op]['h']+=$j['IExpUnitRunTim'];
	
						$t['c']['h']+=$j['IExpUnitRunTim'];
						$t['c']['c']++;
					}else{
						$wu[$op][$workcentre]['c']++;
						$wu[$op][$workcentre]['h']+=$j['IExpUnitRunTim'];
						$ou[$op]['c']++;
						$ou[$op]['h']+=$j['IExpUnitRunTim'];
	
						$t['u']['h']+=$j['IExpUnitRunTim'];
						$t['u']['c']++;
	
					}
					$wt[$op][$workcentre]['c']++;
					$wt[$op][$workcentre]['h']+=$j['IExpUnitRunTim'];
					$wt[$op][$workcentre]['d']=$j['WorkCentreDesc'];
					$ot[$op]['c']++;
					$ot[$op]['h']+=$j['IExpUnitRunTim'];
	
					$t[$workcentre]['c']++;
					$t['t']['h']+=$j['IExpUnitRunTim'];
					$t['t']['c']++;
				}
				?>
				<table class="vpi"><tr><th>WorkCentre</th><th colspan=2>Total</th><th colspan=2>Completed</th><th colspan=2>Remaining</th></tr>
				<tr class="smalltitle"><th>WorkCentre</th><th>Hours</th><th>#</th><th>Hours</th><th>#</th><th>Hours</th><th>#</th></tr>
				<?
				ksort($wt);
				while($op=key($wt)){ ?>
				<tr class="yellow">
					<td><strong><?=$op?></strong></td>
					<td><?=$ot[$op]['h']?></td><td><?=$ot[$op]['c']?></td>
					<td><?=$oc[$op]['h']?> (<?=number_format($oc[$op]['h']/$ot[$op]['h']*100,2)?>%)</td><td><?=$oc[$op]['c']?> (<?=number_format($oc[$op]['c']/$ot[$op]['c']*100,2)?>%)</td>
					<td><?=$ou[$op]['h']?> (<?=number_format($ou[$op]['h']/$ot[$op]['h']*100,2)?>%)</td><td><?=$ou[$op]['c']?> (<?=number_format($ou[$op]['c']/$ot[$op]['c']*100,2)?>%)</td>
	
				</tr>
	
				<?
					ksort($wt[$op]);
					while ($w=key($wt[$op])){
					?>
					<tr><td><strong><?=$w?></strong> -  <?=$wt[$op][$w]['d']?></td>
					<td><?=$wt[$op][$w]['h']?></td><td><?=$wt[$op][$w]['c']?></td>
					<td><?=$wc[$op][$w]['h']?> (<?=number_format($wc[$op][$w]['h']/$wt[$op][$w]['h']*100,2)?>%)</td><td><?=$wc[$op][$w]['c']?> (<?=number_format($wc[$op][$w]['c']/$wt[$op][$w]['c']*100,2)?>%)</td>
					<td><?=$wu[$op][$w]['h']?> (<?=number_format($wu[$op][$w]['h']/$wt[$op][$w]['h']*100,2)?>%)</td><td><?=$wu[$op][$w]['c']?> (<?=number_format($wu[$op][$w]['c']/$wt[$op][$w]['c']*100,2)?>%)</td>
					</tr>
					<?
						next ($wt[$op]);
					}
					next($wt);
				}?>
				</table>
				<?		
			}
			
		break;
		case "WCStatus":
			$statstest=Batch::DepartmentStats();
			foreach ($statstest as $r){
				if ($r['ActualFinishDate']){
					$daysOff=CompanyCalendar::getDaysBetween($r['ActualFinishDate'],$r['PlannedEndDate']); 
					$wcStats[$r['WorkCentre']][$r['ActualFinishDate']]['daysOff']+=$daysOff*$r['TimeActual'];
					$wcStats[$r['WorkCentre']][$r['ActualFinishDate']]['denom']+=$r['TimeActual'];
				}
			}
			foreach($wcStats as $wc=>$a){
				/*?></table><div style='font-size:9px;'><img style='background-color:#fff;width:150px' src="/images/manufacturing/Operations/450/color/<?=$wc?>.png" alt="<?=$wc?>"/></div><table><?*/
				foreach($a as $date=>$s){
					if ($date>=date("Y-m-d 00:00:00",strtotime("-15 days"))){
					//print "<tr><td>".$date."</td><td>"; print_r($s); print "</td><td>".round($s['daysOff']/$s['denom'],2)."</td></tr>";
						$g[$date][$wc]=($s['denom']?round($s['daysOff']/$s['denom'],4):0);
						$wcG[$wc]++;
					}
				}
				
			}
			ksort($wcG);
			ksort($g);
			?>
			  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
		<script type="text/javascript">
				  google.charts.load('current', {'packages':['line']});
		  google.charts.setOnLoadCallback(drawChart);
	
		function drawChart() {
	
		  var data = new google.visualization.DataTable();
		  data.addColumn('string', 'Day');
		<? foreach ($wcG as  $wc=>$c){?>
		  data.addColumn('number', '<?=$wc." - ".Batch::workCentreDescription($wc)?>');
		 <? }?>
		  data.addRows([
		<? $p=array();
			foreach($g as $date =>$wcs){
				$thisline="['".substr($date,5,5)."'";
				foreach ($wcG as  $wc=>$c){
					// if this isn't set, pull last value for previous date.
					
					if (isset($g[$date][$wc])) $lastvalue[$wc]=$g[$date][$wc];			
					$thisline.=",".($lastvalue[$wc]?$lastvalue[$wc]:0);
				}
				$thisline.="]";
				$p[]=$thisline;
			}
									 
			print implode(", ",$p);
			?>
		  ]);
	
		  var options = {
			chart: {
			  title: 'WC Days',
			  subtitle: 'how ahead or behind a WorkCentre is'
			},
			width: 900,
			height: 500
		  };
	
		  var chart = new google.charts.Line(document.getElementById('linechart_material'));
	
		  chart.draw(data, google.charts.Line.convertOptions(options));
		} </script>
			<div id="linechart_material" style="width: 900px; height: 500px"></div>
			<?
			break;
		case "Bins2":
			// Following query resulted in duplicates!
			/*
			$SQL="SELECT 
					B.Id AS BinId,
					B.BinLabel,
					B.Color,
					WL.WorkCentre,
					SUM(CASE WHEN J.Component<>'' THEN 1 ELSE 0 END) as Components,
					MIN(WL.PlannedStartDate) as StartDate,
					MAX(WL.PlannedEndDate) as EndDate, 
					MIN(WL.PlannedEndDate) as EndDateMin, 
					SUM(CASE WHEN OperCompleted='Y' THEN 1 ELSE 0 END) as Completed, 
					COUNT(*) as Total,MAX(ActualFinishDate) as LastCompletedDate,
					SUM(WL.ICapacityReqd) as TimeExpected, SUM(WL.RunTimeIssued) as TimeActual,
					SUM(WL.ICapacityReqd*(CASE WHEN OperCompleted='Y' THEN 0 ELSE 1 END)) as TimeExpectedLeft
				FROM ".db_dbo('sysproVpi')."WipBinJob J  
				INNER JOIN ".db_dbo('sysproVpi')."WipBin B ON B.Id=J.WipBinId
				INNER JOIN WipMaster WM ON J.Job=WM.Job 
				INNER JOIN WipJobAllLab WL ON WM.Job=WL.Job
				WHERE J.WipBinId IN (SELECT DISTINCT WipBinId FROM ".db_dbo('sysproVpi')."WipBinJob INNER JOIN WipJobAllLab ON WipBinJob.Job=WipJobAllLab.Job WHERE WipJobAllLab.OperCompleted='N')
				GROUP BY B.Id,B.BinLabel,B.Color,WL.WorkCentre
				ORDER BY MAX(WL.PlannedStartDate)";
			*/
			$SQL="SELECT 
				BinId,
				BinLabel,
				MfgOrder,
				Color,
				WL.WorkCentre,
				SUM(Components) AS Components,
				MIN(WL.PlannedStartDate) as StartDate,
				MAX(WL.PlannedEndDate) as EndDate, 
				MIN(WL.PlannedEndDate) as EndDateMin, 
				SUM(CASE WHEN OperCompleted='Y' THEN 1 ELSE 0 END) as Completed, 
				COUNT(*) as Total,MAX(ActualFinishDate) as LastCompletedDate,
				SUM(WL.ICapacityReqd) as TimeExpected, SUM(WL.RunTimeIssued) as TimeActual,
				SUM(WL.ICapacityReqd*(CASE WHEN OperCompleted='Y' THEN 0 ELSE 1 END)) as TimeExpectedLeft
			FROM (
				SELECT 
					B.Id AS BinId,
					B.BinLabel,
					B.MfgOrder,
					B.Color,
					J.Job,
					SUM(CASE WHEN J.Component<>'' THEN 1 ELSE 0 END) as Components
				FROM ".db_dbo('sysproVpi')."WipBinJob J  
				INNER JOIN ".db_dbo('sysproVpi')."WipBin B ON B.Id=J.WipBinId
				WHERE J.WipBinId IN (SELECT DISTINCT WipBinId FROM ".db_dbo('sysproVpi')."WipBinJob INNER JOIN WipJobAllLab ON WipBinJob.Job=WipJobAllLab.Job WHERE WipJobAllLab.OperCompleted='N')
				GROUP BY B.Id,B.BinLabel,B.MfgOrder,B.Color,J.Job
			) B
			INNER JOIN WipMaster WM ON B.Job=WM.Job 
			INNER JOIN WipJobAllLab WL ON WM.Job=WL.Job
			GROUP BY BinId,BinLabel,MfgOrder,Color,WL.WorkCentre
			ORDER BY MAX(WL.PlannedStartDate)";
			
			//STUFF((SELECT ','+J.Job FROM ".db_dbo('sysproVpi')."WipBinJob J WHERE J.WipBinId=BinId FOR XML PATH (''), root('MyString'), type).value('/MyString[1]','varchar(8000)'), 1, 1, '') AS Jobs,
			
			$stmt = DB::get('syspro')->prepare($SQL);
			$stmt->execute();
			while($r = $stmt->fetchObject()){
				$column=$r->WorkCentre;	
				$binParts=explode("|",$r->BinLabel);
				$q[$binParts[0]." ".$binParts[1]][$binParts[2]?$binParts[2]:"_blank"][$column][]=$r;
				$columnCount[$column]++;
				$binColor[$binParts[0]." ".$binParts[1]]=str_replace("#","",$r->Color);
				
				if ($r->MfgOrder!='') {
					$mfgOrders[$r->MfgOrder] = $r->MfgOrder;
					$binMfgOrder[$binParts[0]." ".$binParts[1]][$r->MfgOrder] = $r->MfgOrder;
				}
				
			}
			if ($_SESSION['guard_username']=='caleb') {
				$stmt = DB::get('syspro')->prepare("SELECT L.*,I.AlternateKey2,I.UserField1,P.StartDate FROM BatchLog L INNER JOIN ".db_dbo('sysproVpi')."ProductionSchedule P ON P.Id=L.MfgOrder INNER JOIN InvMaster I ON I.StockCode=L.StockCode WHERE MfgOrder IN ('".implode("','", $mfgOrders)."')");
				$stmt->execute();
				while ($j = $stmt->fetchObject()) {
					$batches[$j->MfgOrder] = $j;
				}
			}
			
			ksort($columnCount);
			?>
			<table class="vpi">
				<tr class="sticky">
					<th>Bin</th>
					<th></th>
					<? foreach ($columnCount as $column=>$count){ ?>
						<th><img style="background-color:#fff;width:99%" src="/images/manufacturing/Operations/450/color/<?=$column?>.png" alt="<?=$column?>"></th>
					<? } ?>	
				</tr>
				<?
				foreach ($q as $binIdRoot=>$subBins){
					$qBatches = array();
					if (isset($binMfgOrder[$binIdRoot])) {
						foreach ($binMfgOrder[$binIdRoot] as $b) {
							$qBatches[$b] = $batches[$b];
						}
					}
					if (sizeof($qBatches)==1) {
						$b = current($qBatches);
						ob_start();
						?>
						<td style="padding: 5px;background-color:<?=$b->Color?>;color:#000000;" rowspan="<?=sizeof($subBins)?>">	
							<div style="font-size: 24px;">
								<strong><?=$b->Batch?></strong> - <?=$b->UserField1?>
							</div>
							<div style="font-size:12px;font-weight:bold;">
								<?=$b->Description?><br>
								Job: <a target="detail" href="/Manufacturing/WIP/JobProcessor.php?p=L&amp;job=<?=$b->Job?>"><?=ltrim($b->Job,'0')?></a> 
								Mfg Order: <a target="detail" href="/Manufacturing/WIP/ProductionSchedule.php?p=S&amp;view=6&amp;go=go&amp;search=<?=$b->MfgOrder?>"><?=$b->MfgOrder?></a><br>
								Qty: <?=$b->Qty?> 
								Asb. Date: <?=date('n/j',strtotime($b->StartDate))?>		
							</div>
						</td>
						<?
						$firstPass = ob_get_clean();
					} else {
						$bgcolor=$binColor[$binIdRoot]?$binColor[$binIdRoot]:"000000";
						$color=WorkOrderDocument::hexbg2color($bgcolor);
						$firstPass='<td rowspan="'.sizeof($subBins).'" style="text-align:right;font-size:16px;background-color:#'.$bgcolor.';color:'.$color.'"><nobr>'.$binIdRoot.(sizeof($qBatches)>1?' Multi Batch':'').'</nobr></td>';
					}
					ksort($subBins);
					foreach ($subBins as $subBin=>$a){
						$span = WorkOrderDocument::defaultBinLabel($subBin,5);
						preg_match('/style="(.*)"/',$span,$matches);
						$style = $matches[1];
						$span = preg_replace('/style="(.*)"/','',$span);
						?><tr>
							<?=$firstPass?>
							<td style="font-size:16px;text-align:center;vertical-align:middle;<?=$style?>"><?=($subBin!="_blank"?$span:"")?></td>
							<?
							$firstPass="";
							foreach ($columnCount as $column=>$count){
								if ($a[$column][0]){
									$r=$a[$column][0];
									$completed=false;						
	
									if ($r->Completed==$r->Total){
										$bgc='4ec400';
										$completed=true;
									} elseif($r->Completed==0) {
										$bgc='ff7b74';
									} else {
										$bgc='edea00';
									}
									?>
									<td style="background-color:#<?=$bgc?>; text-align: center; vertical-align: center; ">
										<div style="font-size:10px;"><span style="font-size:16px;">
										<a target="detail" href="?view=detail&wc=<?$column?>&binLabel=<?$r->BinLabel?>">
										<?=$r->Completed?></span> of <span style="font-size:16px;"><?=$r->Total?></a></span></div><?
										if ($wc=='P$') {
											?>
											<div style='font-size:9px;'><?=round(($r->Completed/$r->Total)*100,1)?>% received</div>
											<div style='font-size:9px;'>&nbsp;</div>
											<?
										} else {
											?><div style='font-size:9px;'><?=($completed?round($r->TimeActual,2)." of ":"").round($r->TimeExpected,2)?> hrs<?
											if ($completed && $r->TimeExpected>0 &&  $r->Batch!='Pilot' && $r->TimeActual>0){
												$per=($r->TimeActual-$r->TimeExpected)*100/$r->TimeExpected;
												?><span style='padding:1px 5px;<?=color_style($per*-1, -100,100)?>'><?=round($per,1)?>%</span><?
											}
											?>
											</div>
											<div style="font-size:9px;">
											<?
											if ($completed){
												?>Comp. <?=date('M-j',strtotime($r->LastCompletedDate))?><?
											}else{ 
												$bgcolor = (substr($r->DueDateMax,0,10)<date("Y-m-d")?"red; color:white":(substr($r->DueDateMax,0,10)==date("Y-m-d")?"yellow":""));
												?>
												Due: <?=($r->EndDateMin!=$r->EndDate?date('M-j',strtotime($r->EndDateMin))." - ":"")?><span style="<?=($bgcolor?"background-color:".$bgcolor:"")?>"><?=date('M-j',strtotime($r->EndDate))?></span>
												<?
											}
											?></div><?
										}		
									?></td><?
								}else{
									?><td style="background-color:gray;"></td><?
								}
							}		
						?></tr><?
					}
				}
			?></table><?
		break;	
	}
	
	
}





function showDeptBins($mfgIDs,$label){
	dump($mfgIDs);
	
}
function shortenProductDescription($desc,$model){
	return substr($desc,strpos($desc,$model)+strlen($model));
}

function showAllBins($bins) {
	global $wc_used,$wc_stats,$mfgdetail,$mfgs; 
	?>
	<h2>Complete View</h2>
	<table class='vpi persist-area sortable'>
		<tr class='persist-header'><th style='width:200px;'>Batch - ID</th><th style='width:75px;'>Bin</th><th>Complete</th></tr>
		<? foreach ($bins as $mfgID=>$b) { 
			$r=$mfgdetail[$mfgID];
			$first = true;
			ksort($b);
			foreach ($b as $bin=>$i) { 
				$bg = ($i['Complete']==$i['Total']?'4ec400':($i['Complete']==0?'ff7b74':'edea00'));
				?>
				<tr>
					<? if ($first) { ?>
						<? 
						showMfgInfo($r,sizeof($b));
						$first = false;
						?>
					<? } ?>
					<td style="font-size: 18px;padding: 4px;<?=Batch::binStyle($bin);?>;text-align: center;vertical-align:center;"><?=$bin?></td>		
					<td style="text-align:center;font-size:10px;background-color:#<?=$bg?>">
						<?=($bin=='P$'?'Parts':'Jobs')?>: 
						<span style="font-size:16px"><?=$i['Complete']?></span> 
						of 
						<span style="font-size:16px"><?=$i['Total']?></span>
					</td>
				</tr>
			<? } ?>
		<? } ?>
	</table>
	<?
}

function showMfgInfo($r,$span=1) {
	?>
	<td style="padding: 5px;<?='background-color:'.$r['Color'].';color:'.Color::getTextColor($r['Color']).';'?>" rowspan="<?=$span?>">
		<div style="font-size: 24px;">
			<strong><?=$r['Batch']?></strong> - <?=(trim($r['AlternateKey1'])!=""?$r['AlternateKey1']:$r['StockCode'])?>
		</div>
		<div style="font-size:12px;font-weight:bold;">
			<?=$r['Description']?><br>
			Job: <a target="detail" href='/Manufacturing/WIP/JobProcessor.php?p=L&job=<?=$r['Job']?>'><?=($r['Job']*1)?></a> 
			Mfg Order: <a target="detail" href="/Manufacturing/WIP/ProductionSchedule.php?p=S&view=6&go=go&search=<?=$r['MfgOrder']?>"><?=$r['MfgOrder']?></a><br>
			Qty: <?=($r['QtyToMake']*1)?> 
			Asb. Date: <?=date("Y-m-d",strtotime($r['JobStartDate']))?>
		</div>
	</td>
	<?
}

function showAllOpsBins($mfgIDs,$label=""){
	global $nestListmfg,$WipBinStatus,$statusShow;
	
	if (sizeof($mfgIDs)==0) return false;
	if ($label){
		print "<h2>".$label."</h2>";
	}
	global $wc_used,$wc_stats,$mfgdetail,$mfgs; 
	ksort($wc_used);
	print "<table class='vpi persist-area sortable'><tr class='persist-header'><th style='width:200px;'>Batch - ID</th><th style='width:75px;'>Bin</th>";
	foreach ($wc_used as $wc=>$desc){
		if ($wc=='73.22'){
			print "<th>Fab Status</th>";
		}
		if ($wc=='73.30'){
			print "<th>Weld Status</th>";
		}
		$daysOff=$perline="";
		if ($wc_stats[$wc]['totalItems']){
			$per=$wc_stats[$wc]['daysOff']/$wc_stats[$wc]['totalItems'];			
			$daysOff= " <div style='font-weight:normal;font-size: 12px; padding:1px 5px;".color_style($per, -3,2)."'>Days: ".round($per,2)."</div>";
					//$wc_stats[$op['WorkCentre']]['daysOff']+=$daysOff;
					//$wc_stats[$op['WorkCentre']]['totalItems']+=$op['Completed'];
		}
		if ($wc_stats[$wc]['TimeExpected']){
			$per=($wc_stats[$wc]['TimeActual']-$wc_stats[$wc]['TimeExpected'])*100/$wc_stats[$wc]['TimeExpected'];
			$perline= " <div style='font-weight:normal;font-size: 9px; padding:1px 5px;".color_style($per*-1, -50,50)."'>Time: ".round($per,1)."%<br> (".round($wc_stats[$wc]['TimeActual'],1)." of ".round($wc_stats[$wc]['TimeExpected'],1)." hrs )</div>";
		}
		// removed days off and perline totals per arlins request 9/19
		//print "<th valign='bottom' style='width:110px;' >".$daysOff.$perline."<div style='font-size:9px;'><img style='background-color:#fff;width:99%' src=\"/images/manufacturing/Operations/450/color/".$wc.".png\" alt=".$wc." - ".$desc."/></div></th>";
		print "<th valign='bottom' style='width:110px;' ><div style='font-size:9px;'><img style='background-color:#fff;width:99%' src=\"/images/manufacturing/Operations/450/color/".$wc.".png\" alt=".$wc." - ".$desc."/></div></th>";
	}
	print "</tr>";
	
$StatusArray=array(0=>"Not Started",1=>"Job Started", 3=>"Jobs Completed",4=>"In Process", 5=>"Bin Completed", 6=>"Bin Completed (Rush)");
		
	
	?><script>
	
	function changeWBStatus(WipBinId,Status,ColType) {
		if (ColType=="F"){
			var Type='D-Fab';
		}else{
			var Type='D-Weld';
		}
		if (Status==5){
			document.getElementById('td_'+ColType+'_'+WipBinId).style.backgroundColor = '#4ec400';
		}

		else if (Status==1){
			document.getElementById('td_'+ColType+'_'+WipBinId).style.backgroundColor = '#c9632f';
		}

		else if (Status==6){
			document.getElementById('td_'+ColType+'_'+WipBinId).style.backgroundColor = '#b05aa4';
		}

		else if (Status==3){
			document.getElementById('td_'+ColType+'_'+WipBinId).style.backgroundColor = '#f7dd3f';
		}
		else if (Status==4){
			document.getElementById('td_'+ColType+'_'+WipBinId).style.backgroundColor = '#b7f73f';
		}

		else{
			document.getElementById('td_'+ColType+'_'+WipBinId).style.backgroundColor = '#ececec';
		}
		post('?p=Bins',{Function:"WipBinStatusInsert",WipBinId:WipBinId,Status:Status,Type:Type}).then(function(response) {            
           if (response){
				document.getElementById('statusinfo_'+ColType+'_'+WipBinId).innerHTML = response;
				
				//	   
			   //response = JSON.parse(response);
		   }else{
			   alert('Problem saving');
		   }	
            
        });
	}
	</script><?
	foreach($mfgIDs as $mfgID=>$bins){
		$m=0; // row start counter, resets each mfg
		$r=$mfgdetail[$mfgID];	
		ksort($bins);
		
		foreach ($bins as $bin=>$bool){
			$wcs=$mfgs[$mfgID][$bin];	
			print "<tr>";
			if ($m==0){
				showMfgInfo($r,sizeof($bins));
			}
			$binParts=explode("-",trim($bin));
			//$binLogic=WorkOrderDocument::defaultBinLabelLogic($bin);
			?>
			<td rowspan=1 style="font-size: 18px; padding: 4px;<?=Batch::binStyle($binParts[0]);?>; text-align: center; vertical-align:center;"><span style='padding:0px 10px;'><?=$binParts[0]?></span><?=($binParts[1]?"<span style='padding:0px 10px;".Batch::binStyle($binParts[1])."'>".$binParts[1]."</span>":"")?>
			<div style="font-size:9px; font-weight:normal;">Bin Id: <?=$wcs[key($wcs)]['WipBinId']?></div>
			</td>					
			<?
			foreach ($wc_used as $wc=>$c){ //$wc_used is the work centre column names with numbers!
				if ($wc=='73.22'|| $wc=='73.30'){
					$wipBinId=$wcs[key($wcs)]['WipBinId'];
					$type=($wc=='73.22'?"D-Fab":"D-Weld");
					$currentStatus=$WipBinStatus[$wipBinId][$type]->Status; 
					unset($newAutoStatus);
					if ($wc=='73.22'){ 
						$colType="F";
						if ($statusShow[5][$mfgID][$bin]){						
							//print "ready for weld";
							if ($currentStatus<4) { 								
								$newAutoStatus=$currentStatus=3;
								
							}
						}elseif($statusShow[3][$mfgID][$bin]){
							//print "started";
							if ($currentStatus<1) {
								$newAutoStatus=$currentStatus=1;
								
							}
	
						}
					}else{
						$colType="W";
						if ($statusShow[9][$mfgID][$bin]){	
							if ($currentStatus<3) { 								
								$newAutoStatus=$currentStatus=3;
								//print "test Scenro".$mfgID."<br>";								
							}
						}
					}
					
					if ($newAutoStatus){
    					//DB::get('sysproVpi')->all($iSQL);

						$x = '0';

						$stmt = DB::get('sysproVpi')->prepare("INSERT INTO WipBinStatus (WipBinId, Type, UserId, DateUpdated, Status) 
        				VALUES (:WipBinId, :Type, :UserId, :DateUpdated, :Status)");
						$stmt->bindParam(':WipBinId',$wipBinId);
						$stmt->bindParam(':Type',$type);
						$stmt->bindParam(':UserId', $x);
						$stmt->bindParam(':DateUpdated',date("Y-m-d H:i:s"));
						$stmt->bindParam(':Status',$newAutoStatus);
						//$stmt->bindParam(':Note',"");
						$stmt->execute();
						//$result=DB::get('sysproVpi')->lastInsertIdSql();

						// $iSQL="UPDATE WipBinStatus SET UserId=-1 WHERE WipBinId =$wipBinId";
						// print "UPDATE WipBinStatus SET UserId=-1 WHERE WipBinId =$wipBinId";
					}
					//dump($result);
					
					?><td id="td_<?=$colType?>_<?=$wipBinId?>" style="background-color:<?
					switch($currentStatus){
						case "5":
							print "#4ec400";
							break;
						case "1":
							print "#c9632f";
							break;
						case "6":
							print "#b05aa4";
							break;
						case "4":
							print "#b7f73f";
							break;
						case "3":
							print "#f7dd3f";
							break;
						default:
							print "#ececec";
						break;
					}
					
					?>"><?
					//dump($WipBinStatus[$wipBinId][$type]);
					
					?>
					<select id="Status" name="Status" form="tableform" onchange="changeWBStatus(<?=$wipBinId?>,this.value,'<?=$colType?>');"><?php
					foreach($StatusArray as $x => $x_value) {?>
						<option value="<?=$x?>"
						<?php 
						if($x==$currentStatus){
							print "selected";
						}
						?>
						>
						<?=$x_value?></option>						
						<?php
					}
					?>
					</select>
					<br>
					<?php
					//dump($WipBinStatus[$wipBinId]);
					?>
					<p id="statusinfo_<?=$colType?>_<?=$wipBinId?>"><?
					print updatedbywho($WipBinStatus[$wipBinId][$type]->UserId,$WipBinStatus[$wipBinId][$type]->DateUpdated);
					
					//print $WipBinStatus[$wipBinId]->UserId.' '.$WipBinStatus[$wipBinId]->DateUpdated;
					?></p>
					</td><?
				}
				//$mfgs[$op['MfgOrder']][$op['DefaultFabBin']][$op['WorkCentre']]=$op;
				
				if ($wcs[$wc]){		
					$completed=false;
					if ($wcs[$wc]['Completed']==$wcs[$wc]['Total']){
						$bgc='4ec400';
						$completed=true;
					}
					elseif($wcs[$wc]['Completed']==0) $bgc='ff7b74';
					else $bgc='edea00';
					print '<td style="background-color:#'.$bgc.'; text-align: center; vertical-align: center; "><div style="font-size:10px;">'.($wc=='P$'?'Parts':'Jobs').': <span style="font-size:16px;">';
					print '<a target="detail" href="?view=detail&wc='.$wc.'&mfgID='.$mfgID.'&binLabel='.$wcs[$wc]['BinLabel'].'">';
					
					print $wcs[$wc]['Completed']."</span> of <span style=\"font-size:16px;\">".$wcs[$wc]['Total']."</a></span></div>";
					if ($mfgID=='17838' && $bin=="4" && $wc=='73.06'){
						//dump($nestListmfg[$mfgID][$bin]);
						
					}
					if ($nestListmfg[$mfgID][$bin][$wc]){
						print "<div>Nested: ".(sizeof($nestListmfg[$mfgID][$bin][$wc])+$wcs[$wc]['Completed'])." of ".$wcs[$wc]['Total']."</div>";				
					}
					if ($wc=='P$') {
						print "<div style='font-size:9px;'>".round(($wcs[$wc]['Completed']/$wcs[$wc]['Total'])*100,1)."% received</div>";
						print "<div style='font-size:9px;'>&nbsp;</div>";
					} else {
						print "<div style='font-size:9px;'>".($completed?round($wcs[$wc]['TimeActual'],2)." of ":"").round($wcs[$wc]['TimeExpected'],2)." hrs";
						if ($completed && $wcs[$wc]['TimeExpected']>0 &&  $wcs[$wc]['Batch']!='Pilot' && $wcs[$wc]['TimeActual']>0){
							$per=($wcs[$wc]['TimeActual']-$wcs[$wc]['TimeExpected'])*100/$wcs[$wc]['TimeExpected'];
							print " <span style='padding:1px 5px;".color_style($per*-1, -100,100)."'>".round($per,1)."%</span>";
						}

						print "</div>";
						print "<div style='font-size:9px;'>";
						if ($completed){
							print "Comp. ".date('M-j',strtotime($wcs[$wc]['LastCompletedDate']));
						}else{ 
							if (substr($wcs[$wc]['DueDateMax'],0,10)<date("Y-m-d")){
								$bgcolor="red; color:white";
							}elseif (substr($wcs[$wc]['DueDateMax'],0,10)==date("Y-m-d")){
								$bgcolor="yellow";
							}else{
								$bgcolor="";
							}
							print " Due: ".($wcs[$wc]['DueDateMin']!=$wcs[$wc]['DueDateMax']?date('M-j',strtotime($wcs[$wc]['DueDateMin']))." - ":"")."<span style='".($bgcolor?"background-color:".$bgcolor:"")."'>".date('M-j',strtotime($wcs[$wc]['DueDateMax']))."</span>";
						}
						print "</div>";
					}
					//print_r($wcs[$wc]);
					/*TimeExpected, SUM(WL.RunTimeIssued) as TimeActual,Min(WL.PlannedEndDate) as DueDateMin,MAX(WL.PlannedEndDate) as DueDateMax */

					print "</td>";
				}else{
					print "<td style='background-color:#AAAAAA;'></td>";
				}
			}				

			$m++;				
			print "</tr>";
			

		}


	}
	print "</table>";
}



function updatedbywho($userId, $date) {
	global $userNameCache;
	if($userId){
		if (!$userNameCache[$userId]){
			$user=User::find($userId);
			$userNameCache[$userId]=$user->fullName('l, f');
			//print "lookup for $userId<br>";
		}
		
		if ($userNameCache[$userId]){
			$return= "Updated By: ".$userNameCache[$userId];
		}
	}
	if ($date){
		$return.=" on ".date("Y-m-d H:i:s",strtotime($date));
	}
	return $return;

}
?>

