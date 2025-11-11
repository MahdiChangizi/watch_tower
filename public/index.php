<?php

require_once __DIR__ . '/../bootstrap.php';
// require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/sync_programs.php';

use App\Enum\Enurmation;
use App\Ns\NameResolotion;
use App\Http\HttpDiscovery;


$enum = new Enurmation();
$enum->enurmation_all_programs();

$nameResolution = new NameResolotion();
$nameResolution->name_resolotion_all_programs();

$httpDiscovery = new HttpDiscovery();
$httpDiscovery->discover_http();