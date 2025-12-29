<?php

define('PLUGIN_AUTOASSIGN_VERSION', '1.0.0');
define('PLUGIN_AUTOASSIGN_MIN_GLPI', '10.0');

require_once(__DIR__ . '/hook/hook.php');

function plugin_init_autoassign() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['autoassign'] = true;
    
    $PLUGIN_HOOKS['item_add']['autoassign'] = [
        'Group_Ticket' => 'plugin_autoassign_item_add_group_ticket'
    ];
    
    $PLUGIN_HOOKS['item_update']['autoassign'] = [
        'Ticket' => 'plugin_autoassign_item_update_ticket'
    ];
    
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['autoassign'] = 'front/config.php';
    }
    
    Plugin::registerClass('PluginAutoassignConfig');
}

function plugin_version_autoassign() {
    return [
        'name'           => 'Auto Assignment Technicians',
        'version'        => PLUGIN_AUTOASSIGN_VERSION,
        'author'         => 'Joao Vitor Lopes',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/joaovitorlopes/autoassign',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_AUTOASSIGN_MIN_GLPI
            ]
        ]
    ];
}

function plugin_autoassign_check_prerequisites() {
    return true;
}

function plugin_autoassign_check_config($verbose = false) {
    return true;
}

function plugin_autoassign_install() {
    global $DB;

    $migration = new Migration(PLUGIN_AUTOASSIGN_VERSION);
    $table = 'glpi_plugin_autoassign_configs';
    
    if (!$DB->tableExists($table)) {
        $migration->displayMessage("Criando tabela $table");
        
        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        
        // TABELA COMPLETA COM TODOS OS CAMPOS
        $query = "CREATE TABLE `$table` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `enabled_categories` text,
            `excluded_users` text,
            `included_users` text,
            `use_inclusion_mode` tinyint NOT NULL DEFAULT 0,
            `change_status_to_new` tinyint NOT NULL DEFAULT 0,
            `allowed_profiles` text,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        
        $migration->addPostQuery($query);
        
        // Insere configuração padrão
        $migration->addPostQuery(
            $DB->buildInsert(
                $table,
                [
                    'id' => 1,
                    'enabled_categories' => '[]',
                    'excluded_users' => '[]',
                    'included_users' => '[]',
                    'use_inclusion_mode' => 0,
                    'change_status_to_new' => 0,
                    'allowed_profiles' => '[]'
                ]
            )
        );
    } else {
        // SE A TABELA JÁ EXISTE, adiciona os campos que faltam (para atualizações)
        $migration->displayMessage("Verificando campos da tabela $table");
        
        if (!$DB->fieldExists($table, 'change_status_to_new')) {
            $migration->addField($table, 'change_status_to_new', 'tinyint', [
                'value' => 0,
                'after' => 'use_inclusion_mode'
            ]);
        }
        
        if (!$DB->fieldExists($table, 'allowed_profiles')) {
            $migration->addField($table, 'allowed_profiles', 'text', [
                'after' => 'change_status_to_new'
            ]);
        }
    }
    
    $migration->executeMigration();
    
    return true;
}

function plugin_autoassign_uninstall() {
    global $DB;
    
    $migration = new Migration(PLUGIN_AUTOASSIGN_VERSION);
    $table = 'glpi_plugin_autoassign_configs';
    
    if ($DB->tableExists($table)) {
        $migration->displayMessage("Removendo tabela $table");
        $migration->addPostQuery("DROP TABLE IF EXISTS `$table`");
    }
    
    $migration->executeMigration();
    
    return true;
}

?>
