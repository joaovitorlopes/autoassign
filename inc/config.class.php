<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginAutoassignConfig extends CommonDBTM {
    
    static $rightname = 'config';
    
    static function getTypeName($nb = 0) {
        return 'Auto Assign - Configuração';
    }
    
    function showForm($ID, array $options = []) {
        global $DB;
        
        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_autoassign_configs',
            'WHERE' => ['id' => 1]
        ]);
        
        if (count($iterator) == 0) {
            echo "<div class='center'><p>Erro ao carregar configurações.</p></div>";
            return;
        }
        
        $config = $iterator->current();
        $enabled_categories = json_decode($config['enabled_categories'], true) ?: [];
        $excluded_users = json_decode($config['excluded_users'], true) ?: [];
        $included_users = json_decode($config['included_users'], true) ?: [];
        $use_inclusion = (int)$config['use_inclusion_mode'];
        $change_status = (int)($config['change_status_to_new'] ?? 0);
        $allowed_profiles = json_decode($config['allowed_profiles'] ?? '[]', true) ?: [];
        
        $form_action = Plugin::getWebDir('autoassign') . "/front/config.form.php";
        
        echo "<div class='center'>";
        echo "<form method='post' action='$form_action'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        
        echo "<table class='tab_cadre_fixe' style='width: 80%;'>";
        echo "<tr><th colspan='2'>Configuração do Auto Assign</th></tr>";
        
        echo "<tr class='tab_bg_1'>";
        echo "<td style='width: 30%;'><strong>Status do Plugin:</strong></td>";
        echo "<td><span style='color: green; font-weight: bold;'>● Ativo</span></td>";
        echo "</tr>";
        
        echo "<tr class='tab_bg_2'>";
        echo "<td><strong>Modo de operação dos técnicos:</strong></td>";
        echo "<td>";
        echo "<select name='use_inclusion_mode' id='operation_mode' class='form-select'>";
        echo "<option value='0'" . ($use_inclusion == 0 ? ' selected' : '') . ">Exclusão (não atribuir a técnicos específicos)</option>";
        echo "<option value='1'" . ($use_inclusion == 1 ? ' selected' : '') . ">Inclusão (atribuir apenas a técnicos específicos)</option>";
        echo "</select>";
        echo "</td>";
        echo "</tr>";
        
        echo "<tr class='tab_bg_1'>";
        echo "<td><strong>Alterar status do chamado para 'Novo':</strong><br><small>Após atribuir o técnico automaticamente, o status do chamado será alterado para 'Novo'</small></td>";
        echo "<td>";
        echo "<input type='checkbox' name='change_status_to_new' value='1' " . ($change_status ? 'checked' : '') . "> ";
        echo "<label>Ativar mudança automática de status</label>";
        echo "</td>";
        echo "</tr>";
        
        // Filtro de perfis
        echo "<tr class='tab_bg_2'>";
        echo "<td style='vertical-align: top;'><strong>Perfis permitidos:</strong><br><small>Apenas usuários com estes perfis poderão ser sorteados automaticamente</small></td>";
        echo "<td>";
        
        $profile_iterator = $DB->request([
            'FROM' => 'glpi_profiles',
            'ORDER' => 'name ASC'
        ]);
        
        echo "<div style='max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px; background: white;'>";
        
        if (count($profile_iterator) > 0) {
            foreach ($profile_iterator as $profile) {
                $checked = in_array($profile['id'], $allowed_profiles) ? 'checked' : '';
                echo "<label style='display:block; margin:5px 0; cursor: pointer;'>";
                echo "<input type='checkbox' name='allowed_profiles[]' value='" . $profile['id'] . "' $checked> ";
                echo htmlspecialchars($profile['name']);
                echo "</label>";
            }
        } else {
            echo "<p>Nenhum perfil encontrado.</p>";
        }
        
        echo "</div>";
        echo "<small><em><strong>IMPORTANTE:</strong> Deixe vazio para listar usuários de todos os perfis. Se selecionar perfis, APENAS usuários com esses perfis poderão ser sorteados.</em></small>";
        echo "</td>";
        echo "</tr>";
        
        echo "<tr class='tab_bg_1'>";
        echo "<td style='vertical-align: top;'><strong>Não atribuir às categorias ITIL selecionadas:</strong><br><small>Marque as categorias onde o plugin NÃO deve atribuir automaticamente</small></td>";
        echo "<td>";
        
        $cat_iterator = $DB->request([
            'FROM' => 'glpi_itilcategories',
            'ORDER' => 'completename ASC'
        ]);
        
        echo "<div style='max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px; background: white;'>";
        
        if (count($cat_iterator) > 0) {
            foreach ($cat_iterator as $cat) {
                $checked = in_array($cat['id'], $enabled_categories) ? 'checked' : '';
                echo "<label style='display:block; margin:5px 0; cursor: pointer;'>";
                echo "<input type='checkbox' name='enabled_categories[]' value='" . $cat['id'] . "' $checked> ";
                echo htmlspecialchars($cat['completename']);
                echo "</label>";
            }
        } else {
            echo "<p>Nenhuma categoria encontrada.</p>";
        }
        
        echo "</div>";
        echo "</td>";
        echo "</tr>";
        
        // Lista de usuários filtrada por perfil
        $user_where = ['is_active' => 1, 'is_deleted' => 0];
        
        if (!empty($allowed_profiles)) {
            // Busca usuários que têm os perfis selecionados - CORRIGIDO
            $users_with_profiles = $DB->request([
                'SELECT' => ['users_id'],
                'DISTINCT' => true,
                'FROM' => 'glpi_profiles_users',
                'WHERE' => ['profiles_id' => $allowed_profiles]
            ]);
            
            $user_ids = [];
            foreach ($users_with_profiles as $row) {
                $user_ids[] = $row['users_id'];
            }
            
            if (!empty($user_ids)) {
                $user_where['id'] = $user_ids;
            } else {
                $user_where['id'] = [0]; // Nenhum usuário encontrado
            }
        }
        
        echo "<tr class='tab_bg_2' id='excluded_row' style='display:" . ($use_inclusion ? 'none' : 'table-row') . ";'>";
        echo "<td style='vertical-align: top;'><strong>Técnicos excluídos:</strong><br><small>Estes técnicos NÃO receberão atribuições automáticas</small></td>";
        echo "<td>";
        
        $user_iterator = $DB->request([
            'FROM' => 'glpi_users',
            'WHERE' => $user_where,
            'ORDER' => 'name ASC'
        ]);
        
        echo "<div style='max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px; background: white;'>";
        
        if (count($user_iterator) > 0) {
            foreach ($user_iterator as $usr) {
                $checked = in_array($usr['id'], $excluded_users) ? 'checked' : '';
                $name = formatUserName($usr['id'], $usr['name'], $usr['realname'], $usr['firstname']);
                echo "<label style='display:block; margin:5px 0; cursor: pointer;'>";
                echo "<input type='checkbox' name='excluded_users[]' value='" . $usr['id'] . "' $checked> ";
                echo htmlspecialchars($name);
                echo "</label>";
            }
        } else {
            echo "<p>Nenhum usuário encontrado com os perfis selecionados.</p>";
        }
        
        echo "</div>";
        echo "</td>";
        echo "</tr>";
        
        echo "<tr class='tab_bg_2' id='included_row' style='display:" . ($use_inclusion ? 'table-row' : 'none') . ";'>";
        echo "<td style='vertical-align: top;'><strong>Técnicos permitidos:</strong><br><small>Apenas estes técnicos receberão atribuições automáticas</small></td>";
        echo "<td>";
        
        echo "<div style='max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px; background: white;'>";
        
        $user_iterator2 = $DB->request([
            'FROM' => 'glpi_users',
            'WHERE' => $user_where,
            'ORDER' => 'name ASC'
        ]);
        
        if (count($user_iterator2) > 0) {
            foreach ($user_iterator2 as $usr) {
                $checked = in_array($usr['id'], $included_users) ? 'checked' : '';
                $name = formatUserName($usr['id'], $usr['name'], $usr['realname'], $usr['firstname']);
                echo "<label style='display:block; margin:5px 0; cursor: pointer;'>";
                echo "<input type='checkbox' name='included_users[]' value='" . $usr['id'] . "' $checked> ";
                echo htmlspecialchars($name);
                echo "</label>";
            }
        } else {
            echo "<p>Nenhum usuário encontrado com os perfis selecionados.</p>";
        }
        
        echo "</div>";
        echo "</td>";
        echo "</tr>";
        
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2'>";
        echo "<strong>Como Funciona (ordem de filtros):</strong><br>";
        echo "1. <strong>Perfis:</strong> Primeiro, filtra apenas usuários dos perfis selecionados (se nenhum perfil for selecionado, considera todos)<br>";
        echo "2. <strong>Categoria:</strong> Se a categoria estiver marcada, o plugin NÃO atribui (deixe desmarcado para atribuir)<br>";
        echo "3. <strong>Grupo:</strong> Busca os técnicos que pertencem ao grupo atribuído ao chamado<br>";
        echo "4. <strong>Exclusão/inclusão:</strong> Aplica o filtro de exclusão ou inclusão nos técnicos do grupo<br>";
        echo "5. <strong>Distribuição:</strong> Atribui ao técnico com menos chamados abertos<br>";
        echo "6. <strong>Aleatório:</strong> Em caso de empate, escolhe aleatoriamente<br>";
        echo "7. <strong>Status:</strong> Se ativado, muda o status do chamado para 'Novo'";
        echo "</td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='update' value='Salvar Configurações' class='btn btn-primary'>";
        echo "</td>";
        echo "</tr>";
        
        echo "</table>";
        Html::closeForm();
        echo "</div>";
        
        $js = <<<JS
        $(document).ready(function() {
            $('#operation_mode').change(function() {
                if ($(this).val() == '1') {
                    $('#excluded_row').hide();
                    $('#included_row').show();
                } else {
                    $('#excluded_row').show();
                    $('#included_row').hide();
                }
            });
        });
JS;
        echo Html::scriptBlock($js);
    }
}

?>
