<?php

use Illuminate\Support\Facades\Storage;
use SplFileInfo;

$directory = '/PDF/MANUALS_WEB';
$filesz = collect(Storage::disk('publishing')->allFiles($directory))->map(function($i) {
	return new SplFileInfo($i);
})->filter(function($i) {
	return $i->getExtension()=='pdf';
})->map(function ($i) {
	$ex = explode('.',$i->getBasename('.pdf'));
	$ex2 = explode('_',$ex[0]);
	$nondotSC= array_shift($ex2);
	if (strlen($nondotSC)<=9){
		$stockCode=substr_replace($nondotSC, '.', 2, 0);
	}
	else{
		$stockCode=$nondotSC;
	}
	$version = array_pop($ex2);
	$model = implode('_',$ex2);
	$type = (stripos($model,'operators')!==false ? 'owners' : (stripos($model,'parts')!==false ? 'parts' : 'combined'));
	return (object) [
		'file' => $i,
		'stockCode' => $stockCode, 
		'version' => $version,
		'model' => $model,
		'type' => $type,
		'language' => $ex[1] ?? 'EN',
	];
})->groupBy('stockCode')->sortKeys();

global $allied,$uploaddir,$files;
$admin = user_list('resources.manuals','collect|id')->contains(auth()->user()->id);


$uploaddir = '//fs1/Publishing/PDF/MANUALS_WEB';

$extra[0]="";
$extra[1]="/ON_HOLD";
$extra[2]="/NON_PRODUCTION";
include($_SERVER['SERVER_ROOT'].'/security/connections/dbConnectODBC.php'); //returns variable $odbcr
$css[]="resources.css";
//exclude:: \\fs1\Publishing\PDF\MANUALS_WEB\Production Manuals not on Website

for ($d=0;$d<sizeof($extra);$d++){
	$pdf_directory = opendir($uploaddir.$extra[$d]);
	while($filename = readdir($pdf_directory)){
		$extension=strtolower(internal_findexts($filename));
		if ($extension=="pdf") {
			$fparse=explode("_",substr($filename,0,-4));
			$stockcode=$fparse[0][0].$fparse[0][1].".";
			for ($i=2;$i<strlen($fparse[0]);$i++) $stockcode.=$fparse[0][$i];
			$files[$stockcode]['File']=$filename;
			$files[$stockcode]['Dir']=$extra[$d];
			$files[$stockcode]['Size']=number_format((filesize($uploaddir.$extra[$d]."/".$filename))/(1000))."K";
		}
	} unset ($pdf_directory);
}
$newfilecheck=$files;


if ($admin &&$_POST['function']=="submit"){

	$post="PartNum|Cat|Name|Model|Type|Serial|EuropeOnly";
	$PageValues = PostToArray ($post);
	$SQL = UpdateRow("resources_manuals", array_keys($PageValues), array_values($PageValues), "ID=".$_POST['ID']);
	print $SQL;
	$update = mysql_query($SQL,$service_con) or die_ex('Update Manual - My SQL Error: ' . mysql_error());
}

if ($admin && $_GET['e']) {
	$SQL="SELECT `ID`,`PartNum`, `Cat`, `Name`, `Model`, `Type`, `Serial`, `Downloads`, `PDownload`, `EuropeOnly` FROM `resources_manuals` WHERE `ID`='".$_GET['e']."'";
	$result=mysql_query($SQL,$service_con);
	if ($r=mysql_fetch_array($result)){?>
	<form action="manuals.php" method="post"><input name="ID" type="hidden" value="<?=$r['ID']?>" />
	<table class="vpi">
	<tr><td class="r">Manual PartNumber</td>
	<td><input name="PartNum" type="text" value="<?=$r['PartNum']?>" /><input name="PartNum_was" type="hidden" value="<?=$r['PartNum']?>" /></td></tr>
	<tr><td class="r">Name</td>
	<td><input name="Name" type="text" value="<?=$r['Name']?>" size="35" /></td></tr>
	<tr><td class="r">Model</td>
	<td><input name="Model" type="text" value="<?=$r['Model']?>" /></td></tr>
	<tr><td class="r">Serial Range</td>
	<td><input name="Serial" type="text" value="<?=$r['Serial']?>" /></td></tr>
	<tr><td class="r">Category</td>
	<td><?
		$resultc=mysql_query("SELECT Distinct Cat FROM `resources_manuals` Order by Cat",$service_con);
		while ($rc=mysql_fetch_row($resultc)){?>
		<label><input name="Cat" type="radio" value="<?=$rc[0]?>" <?=($r['Cat']==$rc[0]?"checked":"")?> /> <?=$rc[0]?></label>
		<? }
	?></td></tr>
	<tr><td class="r">Manual Type</td>
	<td><?
		$resultc=mysql_query("SELECT Distinct `Type` FROM `resources_manuals`  WHERE 1 Order by `Type`",$service_con)or die_ex(mysql_error());
		while ($rc=mysql_fetch_row($resultc)){?>
		<label><input name="Type" type="radio" value="<?=$rc[0]?>" <?=($r['Type']==$rc[0]?"checked":"")?> /> <?=$rc[0]?></label>
		<? }
	?></td></tr>
	<tr><td class="r">Europe Only</td>
	<td><input name="EuropeOnly" type="checkbox" value="1" <?=($r['EuropeOnly']?"checked":"")?> /></td></tr>
	<tr><td class="r">Download Count</td>
	<td><?=$r['Downloads']?></td></tr>
	</table>
	<input name="function" type="submit" value="submit" /> <input name="function" type="submit" value="cancel" />
	</form>


	<?
	}else print "<h3>Admin note: could not find ".$_GET['e']." to edit.</h3>";

}

?>
<script language="JavaScript">
<!--
function download(filename,folderid,sec){
	document.querySelector('#downloaderForm').File.value=filename;
	document.querySelector('#downloaderForm').Download.value="Manual";
	if (folderid==1) document.querySelector('#downloaderForm').Folder.value="/ON_HOLD";
	else if (folderid==2) document.querySelector('#downloaderForm').Folder.value="/NON_PRODUCTION";
	else if (folderid==99) document.querySelector('#downloaderForm').Folder.value="/ALLIED";
	document.querySelector('#downloaderForm').submit();
}
function downloadal(filename){
	document.querySelector('#downloaderForm').File.value=filename;
	document.querySelector('#downloaderForm').Download.value="ManualAllied";\
	//if (folderid==1) document.querySelector('#downloaderForm').Folder.value="/ON_HOLD";
	//else if (folderid==2) document.querySelector('#downloaderForm').Folder.value="/NON_PRODUCTION";
	document.querySelector('#downloaderForm').submit();
}
//-->
</script>
<form id="downloaderForm" action="/codereuse/security/download.php" method="post">
<input name="File" type="hidden" value="">
<input name="Folder" type="hidden" value="">
<input name="Download" type="hidden" value="Manual">
</form>

<? if ($_SESSION['guard_UserType']<>12){?>
<h3><a href="/Parts/Resources/briggs.php"><img src="/images/briggs_smaller.gif"> Download Briggs and Stratton Engine Quick Reference Guide <img src="/images/briggs_smaller.gif"></a></h3>
<? } ?>
<?
	$cat=array('ATTACH','PWRU','EPWRU','EATTACH','ACCR','NONPRO');
	if ($_SESSION['guard_UserType']<6 || $_SESSION['guard_UserType']==11){
		 $exclude=array();
	}elseif($_SESSION['guard_UserType']==12){ $exclude=array('ATTACH','PWRU','EPWRU','ACCR','NONPRO','EATTACH');

		if ($_SESSION['CustNum']=="252144"){

			?>
            <h3>ISM manual(s)</h3><table><tr class="table_low l">
			<td><strong>ISM CONTOUR MOWER</strong> - XJ840i</td>
			<td class="nopad"><div class="part"><a href="javascript:download('0910076_XJ840 ISM.pdf','',1);">Owners (2,884K)</a> <span class="gray">#09.10076</span></div></td></tr>
			<?
			//print show_one_manual('09.10076'); //still needs work...
			?>
            </table><?
		}else{
				print "Contact ".email_list('it.webmaster','from')." to get this running.";
		}
	}
	else $exclude=array('EPWRU','EATTACH');


### get allied product list
$am_result=mysql_query("SELECT `file`, `name` FROM `resources_manuals_allied` WHERE 1",$service_con);
while ($am=mysql_fetch_array($am_result)) $allied[]=$am;


for ($c=0;$c<sizeof($cat);$c++){

  if (!in_array($cat[$c], $exclude)){
	if ($cat[$c]=="ACCR") showallied();
	print "\n".'<div '.($cat[$c]=="ATTACH"?'style="float:right;"':"").'>'."\n";
	?>
<table class="vpi" style="margin-right:10px; margin-top:10px;">
   <tr>
	<th colspan="<?=($cat[$c]=="PWRU"?3:2)?>" scope="col"><div align="center"><strong><?php
	if ($cat[$c]=="PWRU") print "Power Units";
	elseif ($cat[$c]=="EPWRU") print "International Tractors";
	elseif ($cat[$c]=="EATTACH") print "International Attachments";
	elseif ($cat[$c]=="NONPRO") print "Out of Production";
	elseif ($cat[$c]=="ACCR") print "Accessories";
	else print "Attachments";
	?></strong></div></th>
   </tr>
	<tr class="smalltitle">
	<th scope="col"><div align="center">Name-Model</div></th>
<?	if ($cat[$c]=="PWRU"){	?>
	<th scope="col"><div align="center">Owner</div></th>
	<th scope="col"><div align="center">Parts</div></th>
<?	}else{	?>
	<th scope="col"><div align="center">Download Manual</div></th>
<? } ?>
   </tr>
<?
}
	$SQL="SELECT `ID`,`PartNum`, `Name`, `Model`, `Type`, `Serial`, `Downloads` FROM `resources_manuals` WHERE `Cat` = '$cat[$c]'  ORDER BY Name,Type,Serial";
	$result=mysql_query($SQL,$service_con);
	while ($row=mysql_fetch_array($result)){

		if (is_array($files[$row['PartNum']])){ //file exists
			$manual[$cat[$c]][$row['Model']][$row['Type']][]=$row['ID'];
		}else{
			$emanual[$cat[$c]][$row['Model']][$row['Type']][]=$row['ID']; //empty manuals
		}

		$lastmodel[$row['Model']]=$row['Name'];
		$info[$row['ID']]['Serial']=$row['Serial'];
		$info[$row['ID']]['Downloads']=$row['Downloads'];
		$info[$row['ID']]['PartNum']=$row['PartNum'];

	}

	while ($model=key($manual[$cat[$c]])){$j++;
		$srow.='<tr class="'.($j%2==0?"table_low":"table_high").' l topline">
			<td><strong>'.$lastmodel[$model].'</strong> - '.$model.'</td>
			<td class="nopad">';
			$n=0;
			for ($i=0;$i<sizeof($manual[$cat[$c]][$model]['OWNERS']);$i++){
				$id=$manual[$cat[$c]][$model]['OWNERS'][$i];
				$partnum=$info[$id]['PartNum'];
				if ($info[$id]['Serial']){
					$srow.= "<div class='blueblock'>Serial #".$info[$id]['Serial']."</div>";
				}elseif ($n>0) $srow.= "<hr>";
					$file=$files[$partnum]['File'];
				if ($admin) {$srow.= '<a href="?e='.$id.'" style="float:right;"><i class="fas fa-edit"></i></a>'; }
				$srow.= "<div class='part'><a href=\"https://".config('app.domain')."/d/?rid=".urlencode($file)."&baseid=manuals&foldid=".dirtest($files[$partnum]['Dir'])."\">Owners (".$files[$partnum]['Size'].")</a> <span class='gray'>#".$info[$id]['PartNum']."</span>";
				$srow.=showManualLanguages($filesz[$partnum]);
				$srow.= "</div>";
				$n++;
				unset($newfilecheck[$partnum]);

			}unset ($manual[$cat[$c]][$model]['OWNERS']);
			if ($cat[$c]=="PWRU"){   $n=0; $srow.='</td><td class="nopad">';}
			for ($i=0;$i<sizeof($manual[$cat[$c]][$model]['PARTS']);$i++){
				$id=$manual[$cat[$c]][$model]['PARTS'][$i];
				$partnum=$info[$id]['PartNum'];
				if ($info[$id]['Serial']){
					$srow.="<div class='blueblock'>Serial #".$info[$id]['Serial']."</div>";
				}elseif ($n>0) $srow.="<hr>";
				$file=$files[$partnum]['File'];
				if ($admin) {$srow.= '<a href="?e='.$id.'" style="float:right;"><i class="fas fa-edit"></i></a>'; }
				$srow.= "<div class='part'><a href=\"https://".config('app.domain')."/d/?rid=".urlencode($file)."&baseid=manuals&foldid=".dirtest($files[$partnum]['Dir'])."\">Parts (".$files[$partnum]['Size'].")</a> <span class='gray'>#".$info[$id]['PartNum']."</span>";
				$srow.=showManualLanguages($filesz[$partnum]);
				$srow.= "</div>";
				$n++;
				unset($newfilecheck[$partnum]);

			}unset ($manual[$cat[$c]][$model]['PARTS']);

			$srow.='</td></tr>';

		if (sizeof($manual[$cat[$c]][$model])>0) $leftover[$cat[$c]][$model]=$manual[$cat[$c]][$model]; //clean up array to see what is left
		next($manual[$cat[$c]]);
	}
	if (!in_array($cat[$c], $exclude)){
	print $srow;
	?></table><?
	print '</div>';
	}
	unset($srow);


}


?>
<br style="clear:both;" />
<? 	if (sizeof($newfilecheck)>0){
	print "<h2>Files missing from listing:</h2>";
	}
	while ($stockcode=key($newfilecheck)){
		if(_PartDescription($stockcode,$Description,$LongDesc,$PriceCode,$odbcr)){
			$e=($PriceCode=="EVP"?1:0);
			if (strtoupper(substr($Description,0,7))=="MANUAL,") $Description=substr($Description,7);
			if (strtoupper(substr($LongDesc,0,7))=="TRACTOR" || $LongDesc== "VENTRAC 300") $cat="PWRU";//
			else $cat="ATTACH";
			if ($e) $cat="E".$cat;
			$d=explode(" ",trim($Description));
			$model=trim($d[0]);
			for ($i=1;$i<sizeof($d)-1;$i++) $model.=$d[$i];
			$t=$d[sizeof($d)-1];
			if ($t=="EUROPEAN") $t=$d[sizeof($d)-2]; //ignore european label...
			if ($t=="OWNER") $t="OWNERS";
			if ($newfilecheck[$stockcode]['Dir'] == '/NON_PRODUCTION'){
				$cat="NONPRO";
			}
			if ($cdexport) 	$file=str_replace("#",'',$newfilecheck[$stockcode]['File']);
			else			$file=$newfilecheck[$stockcode]['File'];
			print "<div><strong>".$LongDesc."</strong> - $model <a href=\"https://".config('app.domain')."/d/?rid=".urlencode($file)."&baseid=manuals&foldid=".dirtest($newfilecheck[$stockcode]['Dir'])."\">".$t." (".$newfilecheck[$stockcode]['Size'].")</a></div>";

			$result=mysql_query("SELECT `ID`,`PartNum` FROM `resources_manuals` WHERE `PartNum`='".$stockcode."'",$service_con);
			if ($r=mysql_fetch_assoc($result)){
				 if ($admin){
				 	print '<a href="?e='.$r['ID'].'">edit</a>';
				 }
			}else{
				$SQL="INSERT INTO `resources_manuals` ( `PartNum` , `Cat` , Name,`Model` , `Type` , `Serial` , `Downloads`,`EuropeOnly` )
	VALUES ('$stockcode', '$cat', '".trim($LongDesc)."', '$model', '$t', '', '0','$e');";
				//print $SQL;

				 mysql_query($SQL,$service_con);
					 if ($admin){

						print $SQL.'Inserted - <a href="?e='.mysql_insert_id ($service_con).'">edit</a><hr>';
					}

				}
		}
		next($newfilecheck);
	}


if (InGroup("ServiceAdmin") && $_SESSION['guard_UserType']==1){
	//print "<h2>Files missing from listing:</h2>";
		//print_r($files);
	if (is_array($emanual)){
		print"<h2>Extraneous fields in database that aren't physically available:</h2>";
		print_r($emanual);
	}
	if (is_array($leftover)){
		print"<h2>Manual type other than owner or parts?</h2>";
		print_r($leftover);
	}
}



function _PartDescription($StockCode,&$Description,&$LongDesc,&$PriceCode,$odbcr = 0){
  $DealerPrice=""; $Description="";
  $StockCode=strtoupper($StockCode);
  if (($odbcr)==0) include($_SERVER['SERVER_ROOT'].'/security/connections/dbConnectODBC.php'); //returns variable $odbcr
  $SQL="SELECT Description, LongDesc,AlternateKey2 FROM InvMaster WHERE StockCode='".$StockCode."';";
  $resultInv=odbc_exec($odbcr,$SQL) or die_ex("SQL ERROR: ".$SQL.", odbc error:".odbc_errormsg());
  if ($rsInv = odbc_fetch_array($resultInv)){
    $Description=trim($rsInv["Description"]);
	if (!is_numeric($_SESSION['CustNum'])) $LongDesc=trim($rsInv["LongDesc"]);
	$PriceCode=trim($rsInv["AlternateKey2"]);
	return true;
  }
  else return false;
}
//borrow



function dirtest($d){
	switch ($d){
		case "/ON_HOLD": return 1; break;
		case "/NON_PRODUCTION": return 2; break;
		default: return ""; break;
	}
}

function showallied(){ global $allied,$uploaddir;

	if (is_array($allied)){
?><br />
<table class="vpi" style="margin-right:10px; width:350px;">
   <tr>
	<th colspan="2" scope="col"><div align="center"><strong>Allied Products</strong></div></th>
   </tr>
	 <tr><td colspan=2 style="background-color:#FC9;"><div><em>Parts for these products should be obtained through our allied partner</em></div></td></tr>
    <tr class="smalltitle">
	<th scope="col"><div align="center">Name-Model</div></th>
	<th scope="col"><div align="center">Download Manual</div></th>
	</tr>
	<?
		for ($i=0;$i<sizeof($allied);$i++){?>
		<tr  class="<?=($i%2==0?"table_low":"table_high")?> l"><td><?=$allied[$i]['name']?></td>
		<td><?
		//if ($admin) { print '<a href="?e='.$part.'"> <img style="float:right;" src="/images/icons/edit_16x16.gif" alt="edit" /></a>'; }
					print "<div class='part'><a href=\"https://".config('app.domain')."/d/?rid=".urlencode($allied[$i]['file'])."&baseid=manuals&foldid=99\">Download (".number_format((filesize($uploaddir."/ALLIED"."/".$allied[$i]['file']))/(1000))."K".")</a>

					<a href=\"javascript:download('".$allied[$i]['file']."',99,'');\"></a>";
		?></td></tr>
		<?
		}
		?>
		</table>
		<?
		}
}
function internal_findexts($filename){
	$filename = strtolower($filename) ;
	$exts = explode(".", $filename) ;
	$n = count($exts)-1;
	$exts = $exts[$n];
	if ($n>0) return $exts;
	else return ""; //case no file extension
}

function show_one_manual($partnum){ global $files;
	$SQL="SELECT `PartNum`, `Name`, `Model`, `Type`, `Serial`, `Downloads` FROM `resources_manuals` WHERE `PartNum` = '$partnum'  ORDER BY Name,Type,Serial";
	//print $SQL;
	$result=mysql_query($SQL,$service_con);
	while ($row=mysql_fetch_array($result)){

		if (is_array($files[$row['PartNum']])){ //file exists

		}

		$lastmodel[$row['Model']]=$row['Name'];
		$info[$row['PartNum']]['Serial']=$row['Serial'];
		$info[$row['PartNum']]['Downloads']=$row['Downloads'];

	}
	print_r($manual);

}

function showManualLanguages($fileszV){
	if ($fileszV){	
		foreach($fileszV as $f){
			if($f->language!="EN"){ 
				$folderid=
				str_replace("PDF/MANUALS_WEB/","",$f->file->getPath());
				//$folderid=$f->language;				
				$links[]= "<a href=\"/d/?rid=".urlencode($f->file->getFilename())."&baseid=manuals&foldid=".urlencode($folderid)."\">".$f->language."</a>";
			}		
		}
		if (sizeof($links)>0){
			return "<div style='background-color:white; padding:0px 5px;'>Language".(sizeof($links)==1?"":"s").": ".implode(" | ",$links)."</div>";
		}
	}
}
?>
