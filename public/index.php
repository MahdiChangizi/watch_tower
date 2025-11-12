<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/sync_programs.php';

use App\Enum\Enurmation;
use App\Ns\NameResolotion;
use App\Http\HttpDiscovery;
use App\Nuclei\NucleiProgram;
use App\Ports\PortScanner;


$enum = new Enurmation();
$enum->enurmation_all_programs();

$nameResolution = new NameResolotion();
$nameResolution->name_resolotion_all_programs();

$httpDiscovery = new HttpDiscovery();
$httpDiscovery->discover_http();

$portScanner = new PortScanner();
$portScanner->run();


// $nuclei = new NucleiProgram();
// $nuclei->run_nuclei_on_program();