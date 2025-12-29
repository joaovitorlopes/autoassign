<?php

include ('../../../inc/includes.php');

Session::checkRight('config', READ);

Html::header('Auto Assign', $_SERVER['PHP_SELF'], 'config', 'plugins');

$config = new PluginAutoassignConfig();
$config->showForm(1);

Html::footer();

?>
