<?php

/*
To Do:
* resetting jobs always go to 8am. Go off of current time for anything not started.
* If a job has NO active programs connected to it, then display somehow.
* Look at status of a program, and ignore deleted or out of comission.
* Add a default/priority machine to a part - when selecting a machine(robot), use the "default" first.
add "Priority" to SELECT * FROM BomOperationProgram
    10->Use First
* Handle Manual weld. MW01 only machine setup. Need to add other available stations, and figure out rules
* Confirm "0" day settings are working for Kanban vs Production.
* Highlight items that are due (based on zero day)
{#1201 ▼
  +"Job": "000000000744166"
  +"Complete": "N"
  +"WorkCentre": "73.24"
  +"WorkCentreDesc": "RW, WELDING - ROBOTIC"
  +"OperCompleted": "N"
  +"StockCode": "KJ-6014"
  +"PlannedStartDate": "2021-07-30 00:00:00.000"
  +"JobClassification": "WIP"
  +"Warehouse": "MN"
  +"Id": "27068"
  +"BinLabel": "50|KJ|1                       "
  +"Color": "#b7ff6f   "
  +"Component": ""
  +"Status": "5"
  +"WipBinId": "27068"
  +"TotalTime": "8.959200000000"
  +"Operation": "1"
  +"Zone": "R10"
  +"ZoneRow": "0"
  +"TimeScheduled": "32253.12"
  +"StartDate": "2021-08-19 08:30:00.000"
  +"DateDiff": "-21"
  +"PriorOpDone": "1"
}
* 

*/
namespace VentureProducts\Laravel\Models\Syspro\Wip;
use VentureProducts\Laravel\Models\Syspro\Wip\JobSchedule;
use VentureProducts\Entity\Tracking\Clock\ShiftHours as ShiftHours;
require_once($_SERVER['SERVER_ROOT'] ."/dealers/codereuse/classes/productionschedule/CompanyCalendar.php");
require_once ($_SERVER['SERVER_ROOT'].'/dealers/codereuse/documents/WorkOrderDocument.php');
use VentureProducts\Database\DatabaseConnection as DB;
use VentureProducts\Entity\Tracking\Clock\Program as Program;

class WeldRecommendedSchedule
{
    public $machines;
    public $minStartDate;
    public $maxEndDate;
    public $jobOnSchedule;
    public $hrstopixel=100;
    
    function __construct($where,$settings){
        $this->where=$where;
        $this->settings=$settings;        
        $this->lookup();
    }
    function lookup(){
        $where=$this->where;
        $settings=$this->settings;
        $this->machines=self::weldMachineList($where);  
        $this->programs=self::stockCodesWeldedAtSameTime($where);         
        $this->minStartDate=$settings['minStartDate']??date("Y-m-d H:00:00");
        $this->maxEndDate=$settings['maxEndDate']??date("Y-m-d H:00:00",strtotime("+5 days")); 
        $SQL=self::weldUpcomingJobsSQL($where,$settings);
        $result=DB::get('syspro')->all($SQL);
        foreach($result as $index=>$r){
            $key=$r->Job."_".$r->Operation;
            $this->jobInfo[$key]=$r;
            if ($r->Zone || $this->sharedPrograms[$r->StockCode][$r->PlannedStartDate]['machine']){
                if (!$r->Zone){
                    $r->Zone=$this->sharedPrograms[$r->StockCode][$r->PlannedStartDate]['machine'];
                    /*$otherParts=[];
                    $programId=$this->programs['stockCodeToProgram'][$r->StockCode][$r->Zone];
                    foreach($this->programs['programIds'][$programId] as $run){
                        if ($run->StockCode!=$r->StockCode){ 
                            $otherParts[]=$run->StockCode;
                        }
                    }
                    print display_notice("Assigned ".$r->StockCode." to ".$r->Zone." based on program running another stockcode already assigned to this: ".implode(", ",
                    $otherParts));
                    */
                   //dd($r);
                }
                
                $machineToUse=$r->Zone;
                if ($r->StartDate){
                    $date=date("Y-m-d H:i:s",strtotime($r->StartDate));
                    $this->jobOnSchedule['slots'][$r->Zone][$r->ZoneRow][$date][]=$key;
                    $this->jobOnSchedule['jobs'][$key]=$this->machines[$r->StockCode];                                      
                    if ($this->minStartDate>$date) $this->minStartDate=$date;
                    if ($this->maxEndDate<$date) $this->maxEndDate=$date;
                    $r->output.=" <strong>".$machineToUse."</strong> already assigned<br>";
                }else{
                    $r->output.=" <strong>".$machineToUse."</strong> assigned based on shared ProgramId<br>";
                }

               
            }else{                
                unset($machineToUse,$leastMachineHours);
                foreach($this->machines[$r->StockCode] as $machine){ 
        
                    $thisMachineScheduleHours=array_sum($this->scheduledMachines[$machine]);
                    if ($machineToUse) $leastMachineHours=array_sum($this->scheduledMachines[$machineToUse]);
        
                    if ($thisMachineScheduleHours<$leastMachineHours || !$machineToUse){
                        if($machineToUse){
                            $r->output.= $machine." at ".$thisMachineScheduleHours." has less scheduled hours than ".$machineToUse." ".$leastMachineHours."<br>";
                        }               
                        $machineToUse=$machine;
                    }                   
                }
                
                #check for any other stockcodes made by the same program that makes this stockcode
                $programId=$this->programs['stockCodeToProgram'][$r->StockCode][$machineToUse];
                //dd($r->StockCode,$machineToUse,$this->programs);
                //dump($programId); // what program the stockcode runs on=> programId [why are there so many nulls?]
                if ($machineToUse && $programId && sizeof($this->programs['programIds'][$programId])>1){
                    foreach($this->programs['programIds'][$programId] as $run){
                        //dd($run);
                        if ($run->StockCode!=$r->StockCode){ // what is $run->StockCode and $r->StockCode?
                            //dd($run->StockCode,$r->StockCode);
                            $this->sharedPrograms[$run->StockCode][$r->PlannedStartDate]['machine']=$machine;
                        }
                    }
                }
                
            }
            
            if ($this->settings['maxScheduleOutDays'])  {
                $this->maxEndDate=\CompanyCalendar::getDeliveryDate(date("Y-m-d"),$this->settings['maxScheduleOutDays']);
               
            }        
            
            if ($machineToUse){
                $r->output.= " <strong>".$machineToUse."</strong> is the winner!";
                $this->scheduledMachines[$machineToUse][$r->Job."_".$r->Operation]=$r->TotalTime;
            }
            
            $this->jobs[]=$r;
        }
        //dd($this->jobInfo);
    }
    
    function displayJobs(){
        ?><table class="vpi"><tr><th>Job</th><th>WorkCentre</th><th>PlannedStartDate</th><th>JobClassification</th><th>StockCode</th><th>Programs</th></th><th>Status</th><th>WipBinId</th><th>DateDiff</th><th>TotalTime</th><th>SuggestedMachines</th><th></th></tr>
        <?php
        foreach($this->jobs as $r){            
            ?><tr>
            <td><?=$r->Job?></td>
            <td>Op: <?=$r->Operation?> Comp: <?=$r->OperCompleted?> WC:<?=$r->WorkCentre?></td>
            <td><?=$r->PlannedStartDate?></td>
            <td><?=$r->JobClassification?></td>
            <td><?=$r->StockCode?></td>
            <td><?
            if ($this->machines[$r->StockCode]){
                foreach($this->machines[$r->StockCode] as $machine){                                                                   
                    print $machine."<br>";
                }
            }  
            ?></td>
            <td><?=$r->Status?></td>
            <td><?=$r->WipBinId?> <?=$r->BinLabel?></td><td><?=$r->DateDiff?></td>
            <td><?=$r->TotalTime?></td>
            <td><?=$r->output?></td><td><?=$r->PriorOpDone?></td>
            </tr><?php 
        
        }
        ?></table><?
    }

    function shiftToQuarterHours($wc='73.24',$machine){
        if (!$this->shifttoDay[$wc][$machine]){
            $this->shifttoDay[$wc][$machine]=ShiftHours::findHours($wc,$machine);
        }       
        return $this->shifttoDay[$wc][$machine]->quarterHourWorkedAll();
    }

    function timeBlocks($startTime,$hours){
        //array[0]=date of first block
        //array[x]=date of block x
        for($i=0; $i<$hours;$i++) {
            if(date("H:i:s",strtotime($statustime))<date("H:i:s",$close_time)){          
                $hour=date("H",$open_time)+$i%$totalHours;
                $day=floor($i/$totalHours); 
                $return[$i]=strtotime($workDays[$day]." +".$hour." hours");
            }else{
                $return[$i]=$open_time+($x*60*60)+(floor(($y-1)/($totalHours+1))*24*60*60);
            }
        }
        return $return;
    }

    function currentQuarterIndex($quarterHoursInDay){
        if (date("H")< $quarterHoursInDay[0]['time']) return 0;
        elseif (date("H")>$quarterHoursInDay[sizeof($quarterHoursInDay)-1]['time']) return sizeof($quarterHoursInDay);
        else{ //match current time slot.
            foreach($quarterHoursInDay as $i=>$a){
                if ($a['time']==round(4*(date("H")+date("m")/60))/4){
                    return round(4*(date("H")+date("m")/60))/4;
                    break;
                }
            }
        }
    }
    static function quartersUsedCalc($originalQuarterHourIndex,$quarterHoursInDay,$hoursConsumed){
        $breakQuarters=0; //number of quarters that will be breaks.

        $startSlotFound=false;
        $quarterHourIndex=$originalQuarterHourIndex;
        //40 quarters in a day (10 hours). 38 index +4 =42
        for($q=$originalQuarterHourIndex;$q<=$originalQuarterHourIndex+round($hoursConsumed*4);$q++){
            $qDayIndex=$q%sizeof($quarterHoursInDay);
            if (!$quarterHoursInDay[$qDayIndex]['working']){
                $breakQuarters++;                
            }
            if (!$startSlotFound){
                $quarterHourIndex++;
                if ($quarterHoursInDay[$quarterHourIndex%sizeof($quarterHoursInDay)]['working']){
                    $startSlotFound=true;
                }
            }
        }
        if(!$startSlotFound){
            $quarterHourIndex++;
            if (!$quarterHoursInDay[$quarterHourIndex%sizeof($quarterHoursInDay)]['working']){
                $quarterHourIndex++;
                // for ($i=$quarterHourIndex%sizeof($quarterHoursInDay);$i<sizeof($quarterHoursInDay);$i++){
                //     if (!$quarterHoursInDay[$quarterHourIndex%sizeof($quarterHoursInDay)]['working']){
                //         $quarterHourIndex++;///fixe this
                //     }
                // }
               
            }            
        }
        return ['quarterHourIndex'=>$quarterHourIndex,'breakQuarters'=>$breakQuarters];    
    }
    
    function reassignAll($wc='73.24',$rowCount=3){      
        foreach($this->scheduledMachines as $machine=>$jobOps){
            $quarterHoursInDay=$this->shiftToQuarterHours($wc,$machine);             
            $cc=\CompanyCalendar::get(date("Y-m-d"),\CompanyCalendar::getDeliveryDate(date("Y-m-d"),1+ceil(array_sum($jobOps)/(sizeof($quarterHoursInDay)/4)))); //instead of end date, should look at x works days out where x=ceil(array_sum($jobOps)/$shift->totalHours())
            //dump($cc);
            $workDays=array_keys($cc->workDates);
            //dd($workDays);
            //find Quarter now... 
            
            $q=$this->currentQuarterIndex($quarterHoursInDay);//*4; //quarter marker...
           
            $row=0; //alternate zone this is on..
            $lastStartDate=''; //tracks last start date so that if we go back to row 0, we don't overlapt what was on row 3
            foreach($jobOps as $jobOp=>$hoursConsumed){
                //convert $q to a date;
                $dateIndex=floor($q/sizeof($quarterHoursInDay));
                $quarterHourIndex=round($q%sizeof($quarterHoursInDay));
                //dd($q,$job,$hoursConsumed,$dateIndex,$quarterHourIndex);

                if ($workDays[$dateIndex]){
                    $qi=self::quartersUsedCalc($quarterHourIndex,$quarterHoursInDay,$hoursConsumed);
                    $quarterHourIndex=$qi['quarterHourIndex'];
                    if ($quarterHourIndex>=sizeof($quarterHoursInDay)){
                        $quarterHourIndex=$quarterHourIndex%sizeof($quarterHoursInDay);
                        $dateIndex++;
                    }
                    $time=strtotime($workDays[$dateIndex])+$quarterHoursInDay[$quarterHourIndex]['time']*60*60;
                    $date=date("Y-m-d H:i:s",$time);
                    if (!$workDays[$dateIndex]){ //can sometimes happen when we do $dateIndex++ above
                        print display_error("error assigning date to ".$jobOp." -> ".$time."->".$date);
                        continue;
                    }
                    if ($row%$rowCount==0 &&  $lastStartDate==$date){
                        $q+=1;
                        ### copied Logic from above
                        $qi=self::quartersUsedCalc($quarterHourIndex,$quarterHoursInDay,$hoursConsumed);
                        $quarterHourIndex=$qi['quarterHourIndex'];
                        if ($quarterHourIndex>=sizeof($quarterHoursInDay)){
                            $quarterHourIndex=$quarterHourIndex%sizeof($quarterHoursInDay);
                            $dateIndex++;
                        }
                        ### end copied logic from above.
                        $date=date("Y-m-d H:i:s", strtotime($workDays[$dateIndex])+$quarterHoursInDay[$quarterHourIndex]['time']*60*60); 
                    }
                   // $r=$this->jobInfo[$jobOp];
                    if ($this->sharedPrograms[$this->jobInfo[$jobOp]->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['dateSlot']){
                        $date=$this->sharedPrograms[$this->jobInfo[$jobOp]->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['dateSlot'];
                        $row=$this->sharedPrograms[$this->jobInfo[$jobOp]->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['row'];
                        $row++;
                        print display_notice("Found matching JobOp: ".$jobOp." date: $date  row: $row ");
                        foreach($this->programs['programIds'][$programId] as $run){
                           if ($run->StockCode!=$this->jobInfo[$jobOp]->StockCode){                                
                                $this->sharedPrograms[$run->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['row']=$row;
                            }
                        }
                        //$this->sharedPrograms[$run->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['row']=$row;

                    }else{
                        $programId=$this->programs['stockCodeToProgram'][$this->jobInfo[$jobOp]->StockCode][$this->jobInfo[$jobOp]->Zone];                    
                        if ($programId && sizeof($this->programs['programIds'][$programId])>1){
                            foreach($this->programs['programIds'][$programId] as $run){
                                //dd($run);
                                if ($run->StockCode!=$this->jobInfo[$jobOp]->StockCode){ // what is $run->StockCode and $r->StockCode?
                                    //dd($run->StockCode,$r->StockCode);
                                    $this->sharedPrograms[$run->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['dateSlot']=$date;
                                    $this->sharedPrograms[$run->StockCode][$this->jobInfo[$jobOp]->PlannedStartDate]['row']=$row;
                                }
                            }
                        }
                    }
                    
                    print "Assign Job ".$jobOp." on $machine  with total time ".$hoursConsumed." on Row: $row to Date Index: $dateIndex -> ".$date." : strtotime(".$workDays[$dateIndex]." +". $quarterHoursInDay[$quarterHourIndex]['time']." hours.) q: ".$q." + Breaks: ".$qi['breakQuarters']." Quareter Total in Day: ".sizeof($quarterHoursInDay)."Quarter Index:".$quarterHourIndex." date index:".$dateIndex."<hr>";
                    $q+=$qi['breakQuarters'];
                    $jobOpArray=explode("_", $jobOp);
                    //$this->makeJobDiv($jobOp,$hoursConsumed,$machine); //might need to change this
                    $js=JobSchedule::where('Job', $jobOpArray[0])->where('Operation',  $jobOpArray[1])->first();
                    if ($js->Status!=5){
                        if (!$js){
                            $js=new JobSchedule();
                            $js->Job=$jobOpArray[0];
                            $js->Operation=$jobOpArray[1];
                        }
                        if ($js->Status<4){
                            $js->Zone=$machine;
                            $js->ZoneRow=$row%$rowCount;
                            //$js->Status=1;
                        }  
                    
                        $js->TimeScheduled=$hoursConsumed*60*60;
                        $js->StartDate=$date;                  
                        $js->ModifiedDate=date("Y-m-d H:i:s");
                        $js->ModifiedUserId=auth()->user()->id;
                        
                        $js->save();
                    }
                    $lastStartDate=$date;                    
                    
                }else{
                    print "Skip Job ".$jobOp." on $machine because out of workdays.<br>";
                }
                
                //assign $jobOp to $q on row $row%$rowCount;
                $q+=round($hoursConsumed*4);
                $row++;
            }            
        }
        unset( $this->jobOnSchedule,$this->minStartDate,$this->maxEndDate);
        $this->lookup(); // redo lookup
        
    }

    function displaySchedule($wc='73.24'){    
        //print date("Y-m-d",time())."->".date("Y-m-d H:i:s",0); exit();     
        $robot=Program::robotSettings($wc);	
        $quarterHoursInDay=$this->shiftToQuarterHours($wc,$machine);  
        $q=$this->currentQuarterIndex($quarterHoursInDay);
        $cc=\CompanyCalendar::get($this->minStartDate,$this->maxEndDate);
        $workDays=array_keys($cc->workDates);        
        
        ?>
        <!-- <script>
        $(function () {
        $('[data-toggle="tooltip"]').tooltip()
        })
        </script> -->
        <form method=post><button name="Function" value="FixWeldSchedule">Fix Schedule</button></form>
        <style>
            .droptarget {
                opacity: .9;
                pointer-events: all;
            }
            </style>
        <?php
        ksort($this->scheduledMachines);              
        foreach($this->scheduledMachines as $machine=>$jobOps){
            print "<h2>"; // create tthe header of the table and styling first row
            foreach($robot as $r =>$details){
                if ($machine==$r){
                    print "<span style='padding:2px 4px; background-color:".$robot[$r]['color']."'>".$machine."</span>";	
                }                
            }
            
            print "- Hrs scheduled: ".array_sum($jobOps)." hrs</h2><table style='table-layout:fixed;width:".(sizeof($workDays)*sizeof($quarterHoursInDay)*$this->hrstopixel/4)."px'></tr>"; 
            // creating the content of the second row: 
            print "<tr>";
            $h=0;
            foreach($workDays as $day){
                foreach($quarterHoursInDay as $i=>$a){
                    if ($i%4==0){                        
                        print "<th colspan=4 style='border:1px solid #000; width:".$this->hrstopixel."px;overflow:hidden;'>Hour $h</th>";
                        $h++;
                    }
                }
            }            
            print "</tr>";
            print "<tr>";
            foreach($workDays as $day){
                foreach($quarterHoursInDay as $i=>$a){
                    $date=strtotime($day)+$a['time']*60*60;
                    if ($i%4==0){                        
                        print "<th colspan=4 style='border:1px solid #000; width:".$this->hrstopixel."px;overflow:hidden;'> ".date("m/d g:i A", $date)."</th>";
                    }
                }
            }            
            print "</tr>";
            for($rowsPerMachine=0;$rowsPerMachine<4;$rowsPerMachine++){                
                print "<tr>";               
                foreach($workDays as $day){
                    foreach($quarterHoursInDay as $i=>$a){
                                           
                        //print $day." ->".$a['time']." hours working: ".$a['working']."<br>";
                        //$date=date("Y-m-d H:i:s", strtotime($day." +".$a['time']." hours"));
                        $date=date("Y-m-d H:i:s", strtotime($day)+$a['time']*60*60);
                        print "<td class='droptarget' style='border-bottom: 1px solid #999;height:25px; width:".($this->hrstopixel/4)."px; padding:0px;";
                        if ($i%4==0){ print "border-left:1px solid black;";}
                        else{
                            print "border-left:1px solid #999;";
                        }
                        if ($currentQ==$i && date("Y-m-d")==$day){
                            print "background-color:green;";
                        }

                        if (!$a['working']){
                            print "background-color:gray;'";
                        }else{   
                            print "' ondrop='drop(event)' ondragover='allowDrop(event)'";
                         }
                         print " data-zone='".$machine."' data-ModifiedUserId='".auth()->user()->id."' data-zonerow='".$rowsPerMachine."' data-date='".$date."' id='td_".$machine."_".$rowsPerMachine."_".$i."'>";
                        if ($this->jobOnSchedule['slots'][$machine][$rowsPerMachine][$date]){
                            foreach($this->jobOnSchedule['slots'][$machine][$rowsPerMachine][$date] as $jobOp){
                                $this->makeJobDiv($jobOp,$jobOps[$jobOp],$machine);
                            }
                        }
                        print "</td>";
                        if (sizeof($quarterHoursInDay)-1==$i){ //on last quarter of the day
                            //   15.5-15=.5*4 = 2 quarters left in the day.
                            //   15.25-15-.25*4=1 quarter left in the day  -> Make 3 more quarters so 4-1=3
                            $endTime=$a['time']+.25;                                                  
                            $quarterEnd=4-(($endTime-floor($endTime))*4); //So 13.25 becomes 3 because thre are 3 quarters left in the hour
                            print "<td colspan='".$quarterEnd."' class='droptarget' style='border-bottom: 1px solid #999;height:25px; width:".($this->hrstopixel*$quarterEnd/4)."px; padding:0px;background-color:gray;'>Day End</td>";                                                    
                        }                                              
                    } 
                }
                print "</tr>";
            }

            print "</table>";
            PRINT "<table style='table-layout:fixed;width:".(array_sum($jobOps)*$this->hrstopixel)."px'></tr>"; //styling the final row
        
            //strtotime(date("Y-m-d H:00:00")." +".array_sum($jobOps)." hrs");
            foreach($jobOps as $jobOp=>$time){ // creating the content of the final row
                //dd($this->jobOnSchedule,$jobOps);
                ?><td  style="border:1px solid #000; width:<?=round($time*$this->hrstopixel,1)?>px;" ondrop='drop(event)' ondragover='allowDrop(event)'><?
                if (!$this->jobOnSchedule['jobs'][$jobOp]){
                    $this->makeJobDiv($jobOp,$time,$machine);
                }
                ?></td><?
                

            }?></tr></table><?           
            //print ($endTime-time())." secs ->".(($endTime-time())/60/60)." hrs at ".date("Y-m-d H:i:s",$endTime);           
        }
        

        ?>
        <script>            
            function drag(ev) {
                ev.dataTransfer.setData("text", ev.target.id);//non experiment
            }

            function drop(ev) {
                ev.preventDefault(); // what does this do? prevents from element dropping?
                var data = ev.dataTransfer.getData("text"); // data: job_000000000750145 [unique div id]
                
                var jobDiv=document.getElementById(data); //  hte whole div element: <div> ...<div>
               
                ev.target.appendChild(jobDiv);        
                var td= document.getElementById(ev.target.id); // it is the cell element of each cell!
                console.log(data, jobDiv,ev.target.id,td);                
                var job= jobDiv.dataset.job;
                var operation= jobDiv.dataset.operation; 
                var TimeScheduled= jobDiv.dataset.scheduledtime;  
                var zone= td.dataset.zone;      
                      
                var zonerow=td.dataset.zonerow; 
                var date= td.dataset.date;
                //console.log(job,zone,zonerow,date,TimeScheduled);
                post('?p=Schedule',{Function:"UpdateJobSchedule",Job:job,Operation:operation,Zone:zone,ZoneRow:zonerow,StartDate:date,TimeScheduled:TimeScheduled,Status:4});                
            }

            function setMachine(el){       
                // alert("Job: "+el.dataset.job+" operation: "+el.dataset.operation+" Machine: "+el.dataset.machine);
                showWait(true);
                post('?p=Schedule',{Function:"SetMachineOnJob",Job:el.dataset.job,Operation:el.dataset.operation,Zone:el.dataset.machine,Status:4}).then(function(response) {
                    showWait(false);
                    var data = JSON.parse(response);
                    var td = document.querySelector('[data-zone="'+data.Zone+'"][data-zonerow="'+data.ZoneRow+'"][data-date="'+data.StartDate.replace('.000','')+'"]');
                    //move the div with Javascript here
                    //document.querySelector('#job_'+el.dataset.job+"_"+el.dataset.operation);
                    var jobDiv=document.getElementById("job_"+el.dataset.job+"_"+el.dataset.operation); // is it hte whole div element?
                    td.appendChild(jobDiv); 
                    //el.target.appendChild(jobDiv);     // el function doesnt seem to wrok because el and ev are different  
                    //ev.target.id="td_"+el.dataset.operation+"_"
                    // var td= document.getElementById(ev.target.id); // i must get td =, which is the cell element             
                    // var job= el.dataset.job; // i must change this and following?
                    // var operation= el.dataset.operation; 
                    //console.log(response,data, td, jobDiv); // seeems to get what i want
                    //var TimeScheduled= jobDiv.dataset.scheduledtime;  
                    // var zone= el.dataset.machine;      
                        
                    // var zonerow=el.dataset.zonerow; 
                    //var date= td.dataset.date;
                });
                

            }

            function statusToIconJS(el){  //status    
                if (el.value=="1") el.value= "4";
                else if (el.value=="4") el.value= "5";
                else el.value="1";
                console.log(el.value);
                el.innerHTML=el.value;
                
                post('?p=Schedule',{Function:"SetStatusOnJob",Job:el.dataset.job,Operation:el.dataset.operation,Status:el.value}).then(function(response) {  
                    el.innerHTML=response; 
                    console.log(response);
                });           
            }

            function allowDrop(ev) {
                ev.preventDefault();
            }
        </script><?

       
    }
    static function statusToIcon($status){
        switch($status){
            
            case 4: //locked -> don't change machine (maybe retain priority?)
                return $status."<i class='fas fa-lock'></i>";
            
            break;
            case 5: //locked -> never change on reassign
                return $status."<i style='color:red;' class='fas fa-lock'></i>";
            
            break;
            case 1: //unlocked -> freely assign to new machines/timeslots when reassigning
            default:
            
                return $status."<i class='fas fa-lock-open-alt'></i>";
            
            break;
        }
    }
    function makeJobDiv($jobOp,$time,$machine){
        $robot=Program::robotSettings('73.24');
        //data-toggle="tooltip"
        $dt = new \DateTime($this->jobInfo[$jobOp]->PlannedStartDate);    
        $programId=$this->programs['stockCodeToProgram'][$this->jobInfo[$jobOp]->StockCode][$this->jobInfo[$jobOp]->Zone];
        ?>       
        <div 
        data-toggle="tooltip" 
        data-html="true" 
        data-operation="<?=$this->jobInfo[$jobOp]->Operation?>"
        data-trigger="click" title="Job: <a href='/manufacturing/wip/jobprocessor?p=L&job=<?=$this->jobInfo[$jobOp]->Job?>' target='_blank' rel='noopener noreferrer'><?=(int)$this->jobInfo[$jobOp]->Job?></a><br> Stock Code: <a target='lookup' href='/lookup?sc=<?=$this->jobInfo[$jobOp]->StockCode?>'><?=$this->jobInfo[$jobOp]->StockCode?></a><br>Mach/Prog: <?=$machine?> <?=$this->programs['programIds'][$programId][0]->ProgramFile??$programId?><?=($programId && sizeof($this->programs['programIds'][$programId])>1?"<i class='fas fa-project-diagram'></i> multiple":"")?> <br> Status: <button data-operation='<?=$this->jobInfo[$jobOp]->Operation?>' data-job='<?=$this->jobInfo[$jobOp]->Job?>' onclick='statusToIconJS(this)' value='<?=$this->jobInfo[$jobOp]->Status?>'><?=static::statusToIcon($this->jobInfo[$jobOp]->Status)?></button> <? // $this->jobInfo[$jobOp]->Status might be value to change buttons right?
        if ($this->jobInfo[$jobOp]->WipBinId){
            print "<div>WipBinId: ".$this->jobInfo[$jobOp]->WipBinId."</div>";
         }?>Start Date: <?=$dt->format('Y-m-d')?> <br> Total Time: <?=round($time,2)?> <div>BinLabel: <?=$this->jobInfo[$jobOp]->BinLabel?></div> Runs On: <?
        if ($this->machines[$this->jobInfo[$jobOp]->StockCode]){
            $i=0;
            foreach($this->machines[$this->jobInfo[$jobOp]->StockCode] as $machinez){                                                                   
                if ($i!=0) print " | ";
                print "<a href='#' data-operation='".$this->jobInfo[$jobOp]->Operation."' data-job='".$this->jobInfo[$jobOp]->Job."' data-machine='".$machinez."' onclick='setMachine(this);return false;'>";
                if($machinez==$machine){
                    print "<strong>".$machinez."</strong>";
                }else{
                    print $machinez;
                }
                print "</a>";
                $i++;
                
            }
        }  
        ?>" id="job_<?=$this->jobInfo[$jobOp]->Job?>_<?=$this->jobInfo[$jobOp]->Operation?>" ondrop="drop(event)"data-job="<?=$this->jobInfo[$jobOp]->Job?>" 
        data-scheduledtime="<?=$time*60*60?>" draggable='true' ondragstart="drag(event)" ondragover="allowDrop(event)" 
        style="border:1px solid #000; width:<?=round($time*$this->hrstopixel,1)?>px; cursor:default; display:block; background-color:<?=$robot[$machine]['color']?>"><?php
        ## add "*" if multiple program entry.
        if ($programId && sizeof($this->programs['programIds'][$programId])>1){
            print "<i class='fas fa-project-diagram'></i>";
        }
        ?><?=($this->jobInfo[$jobOp]->BinLabel?\WorkOrderDocument::BinLabel_HTML($this->jobInfo[$jobOp]->BinLabel,$this->jobInfo[$jobOp]->Color,0):$this->jobInfo[$jobOp]->Job*1)?><?php
        if (sizeof($this->machines[$this->jobInfo[$jobOp]->StockCode])>1){
            print "x".sizeof($this->machines[$this->jobInfo[$jobOp]->StockCode]);
        }
        ?></div><? //work here

       
    }
    
    static function weldBaseSQL($where=[]){
        return "FROM WipMaster AS WM 
            INNER JOIN WipJobAllLab AS WJL ON WM.Job=WJL.Job 
            LEFT JOIN ".db_dbo('sysproVpi')."WipBinJob WBJ ON WM.Job = WBJ.Job  AND WBJ.Component=''
            LEFT JOIN ".db_dbo('sysproVpi')."WipBin WB ON WBJ.WipBinId = WB.Id
            LEFT JOIN ".db_dbo('sysproVpi')."WipBinStatus AS WBS ON WBJ.WipBinId = WBS.WipBinId AND WBS.Id=(
                SELECT MAX(WBSB.Id) FROM ".db_dbo('sysproVpi')."WipBinStatus WBSB WHERE WBSB.WipBinId=WBJ.WipBinId)
            LEFT JOIN  ".db_dbo('sysproVpi')."WipJobSchedule WJS ON WJS.Job=WM.Job AND WJS.Operation=WJL.Operation
            WHERE ".implode(" AND ",$where);
    }

    static function weldMachineList($where=[]){        
        ### Find List of Programs Needed
        $SQLMachine="Select BOP.StockCode,BOP.Machine,BOP.Priority FROM ".db_dbo('sysproVpi')."BomOperationProgram BOP INNER JOIN ".db_dbo('sysproVpi')."BomProgram BP ON BP.ProgramId=BOP.ProgramId AND BP.Status=5 WHERE BOP.StockCode IN (Select StockCode ".self::weldBaseSQL($where).") 
        AND BOP.WorkCentre IN ('73.22','73.24') Group BY BOP.StockCode,BOP.Machine,BOP.Priority";       
        $result=DB::get('syspro')->all($SQLMachine);
        foreach($result as $i =>$row){
            $machines[$row->StockCode][]=$row->Machine;    
        }
        return $machines;
    }

    static function stockCodesWeldedAtSameTime($where=[]){        
        ### Find List of Programs Needed
        $SQLMachine="Select BOP.StockCode,BOP.Machine,BOP.ProgramId,BP.ProgramFile,BOP.Priority,BP.Status FROM ".db_dbo('sysproVpi')."BomOperationProgram BOP INNER JOIN ".db_dbo('sysproVpi')."BomProgram BP ON BP.ProgramId=BOP.ProgramId AND BP.Status=5 WHERE StockCode IN (Select StockCode ".self::weldBaseSQL($where).") 
        AND BOP.WorkCentre IN ('73.22','73.24') Group BY BOP.StockCode,BOP.Machine,BOP.ProgramId,BP.ProgramFile,BOP.Priority,BP.Status";       
        $result=DB::get('syspro')->all($SQLMachine);
        foreach($result as $i =>$row){
            $return['programIds'][$row->ProgramId][]=$row;
            $return['stockCodeToProgram'][$row->StockCode][$row->Machine]=$row->ProgramId;
            //$machines[$row->StockCode][]=$row->Machine;    
        }
        return $return;
    }



    static function weldUpcomingJobsSQL($where,$settings){
        $priorOptCheck="(CASE WHEN WJL.Operation=1 THEN 1 ELSE (
            CASE WHEN (Select OperCompleted FROM WipJobAllLab WHERE Job=WM.Job AND Operation=WJL.Operation-1)='Y' THEN 1 ELSE 0 END
        ) END)";
        $StatusCriteria="CASE WHEN WM.JobClassification='KAN' AND WBS.Status IS NULL THEN 5 ELSE WBS.Status END";
        
        ### Find Jobs Needing Scheduled
        $SQL="SELECT WM.Job, WM.Complete, WJL.WorkCentre, WJL.WorkCentreDesc, WJL.OperCompleted, WM.StockCode, WJL.PlannedStartDate, WM.JobClassification, WM. Warehouse, WB.Id, WB.BinLabel, WB.Color,  WBJ.Component, ".$StatusCriteria." as StatusCriteria, WJS.Status, WBJ.WipBinId,WJL.
        IExpUnitRunTim*WM.QtyToMake as TotalTime, WJL.Operation, WJS.Zone, WJS.ZoneRow,WJS.TimeScheduled, WJS.StartDate,
        CASE WHEN WM.JobClassification='KAN' THEN DATEDIFF(day,'".$settings['kanbanZeroDate']."',WJL.PlannedStartDate) ELSE  DATEDIFF(day,'".$settings['productionZeroDate']."',WJL.PlannedStartDate) END as DateDiff, ".$priorOptCheck." as PriorOpDone
        ".self::weldBaseSQL($where)." Order BY ".$StatusCriteria." + ".$priorOptCheck."*3 DESC,DateDiff,  WM.JobClassification,WJS.StartDate,WJS.ZoneRow";
        return $SQL;        
    }

}