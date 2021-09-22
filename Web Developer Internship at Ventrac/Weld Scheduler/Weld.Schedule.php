<?php
use VentureProducts\Database\DatabaseConnection as DB;
use VentureProducts\Entity\Tracking\Clock\ShiftHours as ShiftHours;
use VentureProducts\Laravel\Models\Auth\User;
use VentureProducts\Laravel\Models\Syspro\Wip\JobSchedule;
use VentureProducts\Laravel\Models\Syspro\Wip\WeldRecommendedSchedule;
require_once($_SERVER['SERVER_ROOT'] ."/dealers/codereuse/classes/productionschedule/CompanyCalendar.php");

if ($_POST['Function']=="UpdateJobSchedule"){ // will this do both updating and inserting into?
    $js=JobSchedule::where('Job', $_POST["Job"])->where('Operation',$_POST['Operation'])->first();
    if (!$js){
        $js=new JobSchedule();
        $js->Job=$_POST["Job"];
        $js->Operation=$_POST["Operation"]; 
    } 
    
    $js->Zone=$_POST["Zone"];
    $js->ZoneRow=$_POST["ZoneRow"];
    $js->TimeScheduled=$_POST["TimeScheduled"];
    $js->StartDate=$_POST["StartDate"];                  
    $js->Notes=$_POST["Notes"];
    $js->ModifiedDate=date("Y-m-d H:i:s");
    $js->ModifiedUserId=auth()->user()->id;
    $js->Status=$_POST["Status"];
    $js->save();    
    exit();
}

if ($_POST['Function']=="SetMachineOnJob"){ // updating robots by clicking on the robots in pop-ups
    $js=JobSchedule::where('Job', $_POST["Job"])->where('Operation',$_POST['Operation'])->first();
    if (!$js){ // do i need to have this loop
        $js=new JobSchedule(); //do i needt to create a new one?
        $js->Job=$_POST["Job"];
        $js->Operation=$_POST["Operation"];
        
    }
    $js->Zone=$_POST["Zone"];  
    $js->ModifiedDate=date("Y-m-d H:i:s");
    $js->ModifiedUserId=auth()->user()->id;
    $js->save(); 
    
    //return response()->json($js); Caleb's another way of returning
    print json_encode($js);
    exit();
}

if ($_POST['Function']=="SetStatusOnJob"){ // updating robots by clicking on the robots in pop-ups
    $js=JobSchedule::where('Job', $_POST["Job"])->where('Operation',$_POST['Operation'])->first();
    if (!$js){ // do i need to have this loop
        $js=new JobSchedule(); //do i needt to create a new one?
        $js->Job=$_POST["Job"];
        $js->Operation=$_POST["Operation"];
    } 
    $js->Status=$_POST["Status"];
    $js->ModifiedDate=date("Y-m-d H:i:s");
    $js->ModifiedUserId=auth()->user()->id;
    $js->save(); 
    print WeldRecommendedSchedule::statusToIcon($js->Status);
    exit();
}

### Set default end dates.
if (!$_GET['pde']){
    $_GET['pde']=date("Y-m-d",strtotime("+7 days"));
    if (!$_GET['kde']) $_GET['kde']=date("Y-m-d",strtotime("+7 days"));
}
if (!$_GET['kd0']) $_GET['kd0']=date("Y-m-d");
if (!$_GET['pd0']) $_GET['pd0']=date("Y-m-d");
if (!$_GET['sdt']) $_GET['sdt']=5;
?><form method="get">
Production Schedule: Day 0: <input type="date" id="pd0" name="pd0" value="<?=$_GET['pd0']?>"/>   Last Start date: <input id="pde" type="date" name="dbe" value="<?=$_GET['pde']?>"/><?
?><br>
Kanban: Day 0: <input type="date" name="kd0" value="<?=$_GET['kd0']?>"/> Last Start date:<input id="kde" type="date" name="kde" value="<?=$_GET['kde']?>"/> 
<br>
Days to Schedule: <input type="number" name="sdt" value="<?=$_GET['sdt']?>"/>
View: 
<label><input type="checkbox" name="vt" value="t" <?=$_GET['vt']?" checked":""?>> Table </label>
<label><input type="checkbox" name="vs" value="t" <?=$_GET['vs']?" checked":""?>> Schedule </label>
<input type="hidden" name="p" value="<?=$_GET['p']?>"/>
Pixel Width: <input type="number" name="pw" value="<?=$_GET['pw']??100?>"/>
<button method="submit">Go</button>
</form><?

#### Set Criteria for Material view
if($_GET['pde'] || $_GET['kde'] ){
    $where[]="((WM.JobClassification<>'KAN'".($_GET['pde']?" AND WJL.PlannedStartDate <='".$_GET['pde']."'":"").") OR (WM.JobClassification='KAN'".($_GET['kde']?" AND WJL.PlannedStartDate <='".$_GET['kde']."'":"")."))";
       
}

//$where[]="(WJL.ActualStartDate='' OR WJL.ActualStartDate IS NULL)"; //remove started jobs
$where[]="WM.Complete<>'Y'";
$where[]="WJL.OperCompleted = 'N'";
$where[]="WM.Warehouse <> '**'";
$where[]="WJL.WorkCentre IN ('73.24')";



$wrs=new WeldRecommendedSchedule($where,$settings=
['kanbanZeroDate'=>$_GET['kd0'],
'productionZeroDate'=>$_GET['pd0'],
'maxScheduleOutDays'=>$_GET['sdt']  
]);

if ($_POST["Function"]=="FixWeldSchedule"){
    $wrs->reassignAll('73.24');
    //print "ran this";
}

if ($_GET['pw']) $wrs->hrstopixel=$_GET['pw'];
if ($_GET['vt']) $wrs->displayJobs();
//$wrs->reassignAll('73.24');
if ($_GET['vs']) $wrs->displaySchedule();

?>

<script>
    $(() => {
        $('[data-toggle="tooltip"]').tooltip({
            html: true,
           sanitize: false,
           placement: 'left'
        });
    });  

    
</script>

<style>
.tooltip-inner {
    background-color: white;
    color:black;
}
</style>
