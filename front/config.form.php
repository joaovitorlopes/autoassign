<?php

include ('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    global $DB;
    
    $enabled_categories = isset($_POST['enabled_categories']) ? $_POST['enabled_categories'] : [];
    $excluded_users = isset($_POST['excluded_users']) ? $_POST['excluded_users'] : [];
    $included_users = isset($_POST['included_users']) ? $_POST['included_users'] : [];
    $use_inclusion = isset($_POST['use_inclusion_mode']) ? (int)$_POST['use_inclusion_mode'] : 0;
    $change_status = isset($_POST['change_status_to_new']) ? 1 : 0;
    $allowed_profiles = isset($_POST['allowed_profiles']) ? $_POST['allowed_profiles'] : [];
    
    $DB->update('glpi_plugin_autoassign_configs', [
        'enabled_categories' => json_encode($enabled_categories),
        'excluded_users' => json_encode($excluded_users),
        'included_users' => json_encode($included_users),
        'use_inclusion_mode' => $use_inclusion,
        'change_status_to_new' => $change_status,
        'allowed_profiles' => json_encode($allowed_profiles)
    ], ['id' => 1]);
    
    Session::addMessageAfterRedirect('Configurações salvas com sucesso!');
}

Html::back();

?>
