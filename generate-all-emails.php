<?php

include dirname(__FILE__).'/../../config/config.inc.php';
include dirname(__FILE__).'/ps_emailgenerator.php';

$psEmailGenerator = new Ps_EmailGenerator();
echo $psEmailGenerator->generateAllEmail();
