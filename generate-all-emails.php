<?php

include dirname(__FILE__).'/../../config/config.inc.php';
error_reporting(E_ERROR | E_WARNING | E_PARSE);
include dirname(__FILE__).'/ps_emailgenerator.php';

$psEmailGenerator = new Ps_EmailGenerator();
$psEmailGenerator->generateAllEmail();
