<?php

// ensure this script is executed from command line
if (!defined('STDIN') ) {
    print "This script is designed to run from CLI only!\n";
    exit(1);
}

// header
require_once('header.php');
require_once(APP_PATH.'/ArgvReader.php');
require_once(APP_PATH.'/OdinAuth.php');
require_once(APP_PATH.'/MyTickets.php');
require_once(LIB_PATH.'/Function/System.php');

// read inputs from stdin
$reader = new ArgvReader();
$reader->readArgs($argv);
if ($reader->getOdinPwd()=='') {
    $reader->passwordPrompt("Your ODIN Password: ");
}

// ODIN bot to crawl PDX websites with given authentication
$odinAuth = new OdinAuth($reader->getOdinUsr(), $reader->getOdinPwd(), 'ODIN_AUTH');
$odinBot = $odinAuth->getAuth();

// Track object receives $odinBot in order to browse our TRACK website
$track = new MyTickets($odinBot);
$track->setDateFrom($reader->getDateFrom());
$track->setDateTo($reader->getDateTo());
while ($track->fetchNext()) {
    continue;
}