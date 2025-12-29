<?php

function plugin_autoassign_item_add_group_ticket($item) {
    if ($item->fields['type'] != CommonITILActor::ASSIGN) {
        return true;
    }
    
    $ticket_id = $item->fields['tickets_id'];
    $group_id = $item->fields['groups_id'];
    
    plugin_autoassign_assign_technician($ticket_id, $group_id);
    
    return true;
}

function plugin_autoassign_item_update_ticket($item) {
    if (isset($item->fields['status']) && $item->fields['status'] == CommonITILObject::INCOMING) {
        $ticket_id = $item->fields['id'];
        
        global $DB;
        $iterator = $DB->request([
            'FROM' => 'glpi_groups_tickets',
            'WHERE' => [
                'tickets_id' => $ticket_id,
                'type' => CommonITILActor::ASSIGN
            ]
        ]);
        
        foreach ($iterator as $group) {
            plugin_autoassign_assign_technician($ticket_id, $group['groups_id']);
            break;
        }
    }
    
    return true;
}

function plugin_autoassign_assign_technician($ticket_id, $group_id) {
    global $DB;
    
    error_log("AutoAssign: Iniciando atribuição para ticket $ticket_id e grupo $group_id");
    
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticket_id)) {
        error_log("AutoAssign: Ticket $ticket_id não encontrado");
        return;
    }
    
    $category_id = $ticket->fields['itilcategories_id'];
    
    $config_iterator = $DB->request([
        'FROM' => 'glpi_plugin_autoassign_configs',
        'WHERE' => ['id' => 1]
    ]);
    
    if (count($config_iterator) == 0) {
        error_log("AutoAssign: Configuração não encontrada");
        return;
    }
    
    $config = $config_iterator->current();
    
    // CORRIGIDO: Agora as categorias marcadas são as que NÃO devem atribuir
    $disabled_categories = json_decode($config['enabled_categories'], true) ?: [];
    
    if (!empty($disabled_categories) && in_array($category_id, $disabled_categories)) {
        error_log("AutoAssign: Categoria $category_id está bloqueada para atribuição automática");
        return;
    }
    
    $existing = $DB->request([
        'FROM' => 'glpi_tickets_users',
        'WHERE' => [
            'tickets_id' => $ticket_id,
            'type' => CommonITILActor::ASSIGN
        ]
    ]);
    
    if (count($existing) > 0) {
        error_log("AutoAssign: Ticket $ticket_id já possui técnico atribuído");
        return;
    }
    
    $technicians_iterator = $DB->request([
        'SELECT' => ['gu.users_id'],
        'FROM' => 'glpi_groups_users AS gu',
        'INNER JOIN' => [
            'glpi_users AS u' => [
                'ON' => [
                    'gu' => 'users_id',
                    'u' => 'id'
                ]
            ]
        ],
        'WHERE' => [
            'gu.groups_id' => $group_id,
            'u.is_active' => 1,
            'u.is_deleted' => 0
        ]
    ]);
    
    $technicians = [];
    foreach ($technicians_iterator as $row) {
        $technicians[] = $row['users_id'];
    }
    
    if (empty($technicians)) {
        error_log("AutoAssign: Nenhum técnico encontrado no grupo $group_id");
        return;
    }
    
    error_log("AutoAssign: Técnicos do grupo: " . implode(', ', $technicians));
    
    // Filtro por perfil PRIMEIRO
    $allowed_profiles = json_decode($config['allowed_profiles'] ?? '[]', true) ?: [];
    
    if (!empty($allowed_profiles)) {
        $users_with_profiles = $DB->request([
            'SELECT' => ['users_id'],
            'DISTINCT' => true,
            'FROM' => 'glpi_profiles_users',
            'WHERE' => [
                'profiles_id' => $allowed_profiles,
                'users_id' => $technicians
            ]
        ]);
        
        $filtered_by_profile = [];
        foreach ($users_with_profiles as $row) {
            $filtered_by_profile[] = $row['users_id'];
        }
        
        $technicians = $filtered_by_profile;
        error_log("AutoAssign: Técnicos após filtro de perfil: " . implode(', ', $technicians));
        
        if (empty($technicians)) {
            error_log("AutoAssign: Nenhum técnico do grupo possui os perfis permitidos");
            return;
        }
    }
    
    // Filtro de exclusão/inclusão
    $use_inclusion = (int)$config['use_inclusion_mode'];
    
    if ($use_inclusion) {
        $included_users = json_decode($config['included_users'], true) ?: [];
        $technicians = array_intersect($technicians, $included_users);
        error_log("AutoAssign: Modo inclusão - Técnicos após filtro: " . implode(', ', $technicians));
    } else {
        $excluded_users = json_decode($config['excluded_users'], true) ?: [];
        $technicians = array_diff($technicians, $excluded_users);
        error_log("AutoAssign: Modo exclusão - Técnicos após filtro: " . implode(', ', $technicians));
    }
    
    if (empty($technicians)) {
        error_log("AutoAssign: Nenhum técnico disponível após todos os filtros");
        return;
    }
    
    $assignments = [];
    foreach ($technicians as $user_id) {
        $count_result = $DB->request([
            'COUNT' => 'total',
            'FROM' => 'glpi_tickets_users AS tu',
            'INNER JOIN' => [
                'glpi_tickets AS t' => [
                    'ON' => [
                        'tu' => 'tickets_id',
                        't' => 'id'
                    ]
                ]
            ],
            'WHERE' => [
                'tu.users_id' => $user_id,
                'tu.type' => CommonITILActor::ASSIGN,
                'NOT' => ['t.status' => [Ticket::SOLVED, Ticket::CLOSED]]
            ]
        ])->current();
        
        $assignments[$user_id] = (int)$count_result['total'];
    }
    
    asort($assignments);
    
    error_log("AutoAssign: Distribuição de chamados: " . json_encode($assignments));
    
    $min_assignments = min($assignments);
    $available = array_keys(array_filter($assignments, function($count) use ($min_assignments) {
        return $count == $min_assignments;
    }));
    
    $selected = $available[array_rand($available)];
    
    error_log("AutoAssign: Técnico selecionado: $selected");
    
    $ticket_user = new Ticket_User();
    $result = $ticket_user->add([
        'tickets_id' => $ticket_id,
        'users_id' => $selected,
        'type' => CommonITILActor::ASSIGN
    ]);
    
    if ($result) {
        error_log("AutoAssign: Técnico $selected atribuído com sucesso ao ticket $ticket_id");
        
        $change_status = (int)($config['change_status_to_new'] ?? 0);
        if ($change_status) {
            $ticket->update([
                'id' => $ticket_id,
                'status' => CommonITILObject::INCOMING
            ]);
            error_log("AutoAssign: Status do ticket $ticket_id alterado para 'Novo'");
        }
    } else {
        error_log("AutoAssign: ERRO ao atribuir técnico $selected ao ticket $ticket_id");
    }
}

?>
