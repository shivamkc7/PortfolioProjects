<?php

namespace VentureProducts\Laravel\Models\Syspro\Wip;

use VentureProducts\Laravel\Models\Syspro\SysproModel as Model;
use VentureProducts\Database\DatabaseConnection as DB;


class JobSchedule extends Model
{
	protected $connection = 'sysproVpi';
    protected $table = 'WipJobSchedule';
	protected $primaryKey = 'Job';//and Operation
	protected $fillable = ['Job','Operation','Zone','ZoneRow','TimeScheduled','StartDate','Notes','ModifiedDate','ModifiedUserId','Status'];
    
    function robotColor(){
        if ($this->Zone=="R05") return "red";
    }

}