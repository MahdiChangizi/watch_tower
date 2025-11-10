<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db/db.php';

global $db;
$db = Database::connect();
Database::createTables();