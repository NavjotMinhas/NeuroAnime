<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################

define('THIS_SCRIPT', 'series');
define('CSRF_PROTECTION', true);  
// change this depending on your filename

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array('series',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
// if your page is outside of your normal vb forums directory, you should change directories by uncommenting the next line
// chdir ('/path/to/your/forums');
require_once('./global.php');
require_once('modules.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###### YOUR CUSTOM CODE GOES HERE #####
$pagetitle = 'Neuroanime 404 Error';

// ###### NOW YOUR TEMPLATE IS BEING RENDERED ######

$templater = vB_Template::create('404error');
$templater->register_page_templates();
$templater->register('pagetitle', $pagetitle);
print_output($templater->render());

?>
