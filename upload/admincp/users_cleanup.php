<?php
/*======================================================================*\
|| #################################################################### ||
|| # Users Cleanup 0.1                                                # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright © 2009 Dmitry Titov, Vitaly Puzrin.                    # ||
|| # All Rights Reserved.                                             # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * To disable the Javascript-based disabling of criteria in the userscleanup
 * add/edit code, define userscleanup_CRITERIA_JS as 'false' in config.php
 */

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('cpuser', 'user', 'notice', /*'userscleanup'*/);
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
if (!can_administer('adminuserscleanup'))
{
    print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
    'ruleid' => TYPE_INT
));

log_admin_action(
    $vbulletin->GPC['ruleid'] != 0
    ? "userscleanup id = " . $vbulletin->GPC['ruleid']
    : ''
);


// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

require_once(DIR . '/includes/functions_users_cleanup.php');

if (empty($_REQUEST['do']))
{
    if ($vbulletin->GPC['ruleid'])
    {
        $_REQUEST['do'] = 'edit';
    }
    else
    {
        $_REQUEST['do'] = 'list';
    }
}
// #############################################################################

print_cp_header($vbphrase['uc_users_cleanup_rules_manager']);


// #############################################################################
// remove a rule
if ($_POST['do'] == 'remove')
{
    // delete criteria
    $sql = 
    $db->query_write("
        DELETE FROM " . TABLE_PREFIX . "userscleanupcriteria
        WHERE ruleid = " . $vbulletin->GPC['ruleid']
    );

    // delete rule
    $db->query_write("
        DELETE FROM " . TABLE_PREFIX . "userscleanup
        WHERE ruleid = " . $vbulletin->GPC['ruleid']
    );

    define('CP_REDIRECT', 'users_cleanup.php?do=list');
    print_stop_message('uc_deleted_users_cleanup_successfully');
}


// #############################################################################
// confirm deletion of a userscleanup rule
if ($_REQUEST['do'] == 'delete')
{
    print_delete_confirmation(
        'userscleanup',
        $vbulletin->GPC['ruleid'],
        'users_cleanup',
        'remove',
        'rule'
    );
}


// #############################################################################
// update or insert a rule
if ($_POST['do'] == 'update')
{
    $vbulletin->input->clean_array_gpc('p', array(
        'title'        => TYPE_NOHTML,
        'displayorder' => TYPE_UINT,
        'active'       => TYPE_BOOL,
        'criteria'     => TYPE_ARRAY
    ));

    $rule_id = $vbulletin->GPC['ruleid'];

    $createria_list = array();
    foreach ($vbulletin->GPC['criteria'] AS $criteria_id=>$criteria)
    {
        if ($criteria['active'])
        {
            $criteria_list[$criteria_id] = array_map('trim', $criteria);
        }
    }

    if (empty($criteria_list))
    {
        print_stop_message('uc_no_users_cleanup_criteria_active');
    }

    // save rule info
    if ($rule_id)
    {
        // update record
        $db->query_write("
            UPDATE " . TABLE_PREFIX . "userscleanup
            SET
                title        = '" . $db->escape_string($vbulletin->GPC['title']) . "',
                displayorder = " . $vbulletin->GPC['displayorder'] . ",
                active       = " . $vbulletin->GPC['active'] . "
            WHERE
                ruleid = " . $rule_id
        );

        // cleanup old criteria
        $db->query_write("
            DELETE FROM " . TABLE_PREFIX . "userscleanupcriteria
            WHERE ruleid = " . $rule_id
        );
    }
    else
    {
        // insert record
        $db->query_write("
            INSERT INTO " . TABLE_PREFIX . "userscleanup
            SET
                title        = '" . $db->escape_string($vbulletin->GPC['title']) . "',
                displayorder = " . $vbulletin->GPC['displayorder'] . ",
                active       = " . $vbulletin->GPC['active'] 
        );

        $rule_id = $db->insert_id();
    }

    // assemble criteria insertion query
    $criteria_sql = array();
    
    foreach ($criteria_list AS $criteria_id => $criteria)
    {
        if ($criteria['active'])
        {
            $criteria_sql[] = "(
                $rule_id,
                '" . $db->escape_string($criteria_id) . "',
                '" . $db->escape_string($criteria['condition1']) . "',
                '" . $db->escape_string($criteria['condition2']) . "',
                '" . $db->escape_string($criteria['condition3']) . "'
            )";
        }
    }


    // save criterias
    $db->query_write("
        INSERT INTO " . TABLE_PREFIX . "userscleanupcriteria
            (ruleid, criteriaid, condition1, condition2, condition3)
        VALUES " . implode(', ', $criteria_sql)
    );

    define('CP_REDIRECT', 'users_cleanup.php?do=list');
    print_stop_message('uc_saved_users_cleanup_x_successfully', $vbulletin->GPC['title']);
}


// #############################################################################
// edit a rule
if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{
    $rule_id = 0;
    if ($vbulletin->GPC_exists['ruleid'])
    {
        $rule_id = $vbulletin->GPC['ruleid'];
    }
    // are we editing or adding?
    if ($rule_id)
    {
        $rule = uc_get_rule($rule_id);
        if (!empty($rule))
        {
            $criteria_cache = uc_get_cleanup_criterias($rule_id);

            $table_title =
                $vbphrase['edit_userscleanup_rule']
                . ' <span class="normal">' . $rule['title'] . '</span>';
        }
    }
    if (empty($rule))
    {
        $sql = 'SELECT MAX(displayorder) AS displayorder FROM ' . TABLE_PREFIX . 'userscleanup' ;
        $res = $db->query_first($sql);
        // set some default values
        $rule = array(
            'displayorder' => $res['displayorder'] + 10,
            'active'       => 0,
        );


        $table_title = $vbphrase['add_new_userscleanup_rule'];
    }

    // build list of usergroup titles
    $usergroup_options = array();
    foreach ($vbulletin->usergroupcache AS $usergroup_id => $usergroup)
    {
        $usergroup_options[$usergroup_id] = $usergroup['title'];
    }

    // build the list of criteria options
    $criteria_options = array(
        'has_x_reg_days' => array(
            '<input type="text" name="criteria[has_x_reg_days][condition1]" size="5" class="bginput" tabindex="1" value="' .
            $criteria_cache['has_x_reg_days']['condition1'] .
            '" />',
            '<input type="text" name="criteria[has_x_reg_days][condition2]" size="5" class="bginput" tabindex="1" value="' .
            $criteria_cache['has_x_reg_days']['condition2'] .
            '" />'
        ),
        'no_visit_in_x_days' => array(
            '<input type="text" name="criteria[no_visit_in_x_days][condition1]" size="5" class="bginput" tabindex="1" value="' .
            (empty($criteria_cache['no_visit_in_x_days']) ? 30 : intval($criteria_cache['no_visit_in_x_days']['condition1'])) .
            '" />'
        ),
        'in_main_usergroup_x' => array(
            '<select name="criteria[in_main_usergroup_x][condition1]" tabindex="1">' .
            construct_select_options($usergroup_options, (empty($criteria_cache['in_main_usergroup_x']) ? 2 : $criteria_cache['in_main_usergroup_x']['condition1'])) .
            '</select>'
        ),
        'not_in_main_usergroup_x' => array(
            '<select name="criteria[not_in_main_usergroup_x][condition1]" tabindex="1">' .
            construct_select_options($usergroup_options, (empty($criteria_cache['not_in_main_usergroup_x']) ? 6 : $criteria_cache['not_in_main_usergroup_x']['condition1'])) .
            '</select>'
        ),
        'in_second_usergroup_x' => array(
            '<select name="criteria[in_second_usergroup_x][condition1]" tabindex="1">' .
            construct_select_options($usergroup_options, (empty($criteria_cache['in_second_usergroup_x']) ? 2 : $criteria_cache['in_second_usergroup_x']['condition1'])) .
            '</select>'
        ),
        'not_in_second_usergroup_x' => array(
            '<select name="criteria[not_in_second_usergroup_x][condition1]" tabindex="1">' .
            construct_select_options($usergroup_options, (empty($criteria_cache['not_in_second_usergroup_x']) ? 6 : $criteria_cache['not_in_second_usergroup_x']['condition1'])) .
            '</select>'
        ),
        'user_id_not_in' => array(
            '<input type="text" name="criteria[user_id_not_in][condition1]" size="5" class="bginput" tabindex="1" value="' .
            $criteria_cache['user_id_not_in']['condition1'] .
            '" />',
        ),
        'has_x_postcount' => array(
            '<input type="text" name="criteria[has_x_postcount][condition1]" size="5" class="bginput" tabindex="1" value="' .
            $criteria_cache['has_x_postcount']['condition1'] .
            '" />',
            '<input type="text" name="criteria[has_x_postcount][condition2]" size="5" class="bginput" tabindex="1" value="' .
            $criteria_cache['has_x_postcount']['condition2'] .
            '" />'
        ),
        'has_never_posted' => array(),
    );

    // build the editor form
    print_form_header('users_cleanup', 'update');
    construct_hidden_code('ruleid', $rule_id);
    print_table_header($table_title);

    print_input_row($vbphrase['title'] . '<dfn>' . $vbphrase['userscleanup_title_description'] . '</dfn>', 'title', $rule['title'], 0, 60);

    print_input_row($vbphrase['display_order'], 'displayorder', $rule['displayorder'], 0, 10);
    print_yes_no_row($vbphrase['active'], 'active', $rule['active']);
    print_description_row('<strong>' . $vbphrase['uc_userscleanup_if_elipsis'] . '</strong>', false, 2, 'tcat', '', 'criteria');


    foreach ($criteria_options AS $criteria_option_id => $criteria_option)
    { 
        $phrase_id = 'uc_' . $criteria_option_id . '_criteria';

        // the criteria options can't trigger the checkbox to change, we need to break out of the label
        $criteria_text = '<label>' . construct_phrase($vbphrase[$phrase_id],
            "</label>$criteria_option[0]<label>",
            "</label>$criteria_option[1]<label>",
            "</label>$criteria_option[2]<label>"
        ) . '</label>';

        $criteria_text = str_replace('<label>', "<label for=\"cb_$criteria_option_id\">", $criteria_text);

        print_description_row(
            "<input type=\"checkbox\" id=\"cb_$criteria_option_id\" tabindex=\"1\" name=\"criteria[$criteria_option_id][active]\" title=\"$vbphrase[criterion_is_active]\" value=\"1\"" . (empty($criteria_cache[$criteria_option_id]) ? '' : ' checked="checked"') . " />" .
            "<span id=\"span_$criteria_option_id\">$criteria_text</span>"
        );
    }


    if (!defined('USERSCLEANUP_CRITERIA_JS') OR USERSCLEANUP_CRITERIA_JS == true)
    {
        print_submit_row(
            '', '_default_', 2, '',
            "\t<input type=\"button\" id=\"submittest\" class=\"button\" tabindex=\"1\" "
            . "value=\"" . str_pad($vbphrase['test'], 8, ' ', STR_PAD_BOTH) . "\" accesskey=\"t\" "
            . "onclick=\"test_new_rule(this); return false;\" />\n"
        );
    }
    else
    {
        print_submit_row();
    }


    // should we do the snazzy criteria disabling Javascript?
    if (!defined('USERSCLEANUP_CRITERIA_JS') OR USERSCLEANUP_CRITERIA_JS == true)
    {
?>
  <!-- javascript to handle disabling elements for IE niceness -->
    <script type="text/javascript">
    <!--
        function test_new_rule(obj)
        {
            var form = obj.form;
            if (!form) { return false; }

            form.action = 'users_cleanup.php?do=test';
            form.target = '_blank';

            var inputs = document.getElementsByName("do");
            for (var i = 0; i < inputs.length; i++) { inputs[i].value = 'test'; }

            form.submit();

            form.action = 'users_cleanup.php?do=update';
            form.target = '';

            for (var i = 0; i < inputs.length; i++) { inputs[i].value = 'update'; }

            return false;
        }

        function init_checkboxes()
        {
            for (var i = 0; i < checkboxes.length; i++)
            {
                set_disabled(checkboxes[i]);
            }
        }

        function set_disabled_event(e)
        {
            set_disabled(this, true);
        }

        function set_disabled(element, focus_controls)
        {
            var span = YAHOO.util.Dom.get("span_" + element.id.substr(3));
            if (!span)
            {
                return;
            }
            if (element.checked)
            {
                YAHOO.util.Dom.removeClass(span, 'userscleanup_disabled');
            }
            else
            {
                YAHOO.util.Dom.addClass(span, 'userscleanup_disabled');
            }

            span.disabled = !element.checked;

            if (focus_controls && element.checked)
            {
                var inputs = span.getElementsByTagName("input");
                if (inputs.length > 0)
                {
                    inputs[0].select();
                    return;
                }

                var selects = span.getElementsByTagName("select");
                if (selects.length > 0)
                {
                    selects[0].focus();
                    return;
                }

                var textareas = span.getElementsByTagName("textarea");
                if (textareas.length > 0)
                {
                    textareas[0].select();
                    return;
                }
            }
        }

        function handle_reset()
        {
            setTimeout("init_checkboxes()", 100);
        }

        var checkboxes = new Array();
        var inputs = document.getElementsByTagName("input");
        for (var i = 0; i < inputs.length; i++)
        {
            if (inputs[i].type == "checkbox" && inputs[i].name.substr(0, String("criteria").length) == "criteria")
            {
                YAHOO.util.Event.on(inputs[i], "click", set_disabled_event);
                checkboxes.push(inputs[i]);
            }
        }

        YAHOO.util.Event.on("cpform", "reset", handle_reset);

        YAHOO.util.Event.addListener(window, 'load', init_checkboxes);
        init_checkboxes();
        //-->
        </script>
<?php
    }
}


// #############################################################################
// quick update of active and display order fields
if ($_POST['do'] == 'quickupdate')
{
    $vbulletin->input->clean_array_gpc('p', array(
        'active'            => TYPE_ARRAY_BOOL,
        'displayorder'      => TYPE_ARRAY_UINT,
        'displayorderswap'  => TYPE_CONVERT_KEYS
    ));

    $update_ids          = '0';
    $update_active       = '';
    $update_displayorder = '';
    $rules_dispord       = array();

    $userscleanup_result = $db->query_read("
        SELECT ruleid, displayorder, active
        FROM " . TABLE_PREFIX . "userscleanup
    ");

    while ($rule = $db->fetch_array($userscleanup_result))
    {
        $rules_dispord["$rule[ruleid]"] = $rule['displayorder'];

        if (intval($rule['active'])  != $vbulletin->GPC['active'][$rule['ruleid']]
            OR $rule['displayorder'] != $vbulletin->GPC['displayorder'][$rule['ruleid']])
        {
            $update_ids          .= ",$rule[ruleid]";
            $update_active       .= " WHEN $rule[ruleid] THEN " . intval($vbulletin->GPC['active'][$rule['ruleid']]);
            $update_displayorder .= " WHEN $rule[ruleid] THEN " . $vbulletin->GPC['displayorder'][$rule['ruleid']];
        }
    }

    $db->free_result($userscleanup_result);

    if (strlen($update_ids) > 1)
    {
        $db->query_write("
            UPDATE
                " . TABLE_PREFIX . "userscleanup
            SET
                active       = CASE ruleid $update_active       ELSE active END,
                displayorder = CASE ruleid $update_displayorder ELSE displayorder END
            WHERE
                ruleid IN($update_ids)");
    }

    // handle swapping
    if (!empty($vbulletin->GPC['displayorderswap']))
    {
        list($orig_rule_id, $swap_direction) = explode(',', $vbulletin->GPC['displayorderswap'][0]);

        if (isset($vbulletin->GPC['displayorder']["$orig_rule_id"]))
        {
            $rule_orig = array(
                'ruleid' => $orig_rule_id,
                'displayorder'   => $vbulletin->GPC['displayorder']["$orig_rule_id"]
            );

            switch ($swap_direction)
            {
                case 'lower':
                {
                    $comp = '<';
                    $sort = 'DESC';
                    break;
                }
                case 'higher':
                {
                    $comp = '>';
                    $sort = 'ASC';
                    break;
                }
                default:
                {
                    $comp = false;
                    $sort = false;
                }
            }
            $rule_swap = $db->query_first("
                SELECT ruleid, displayorder
                FROM " . TABLE_PREFIX . "userscleanup
                WHERE displayorder " . $comp . $rule_orig['displayorder'] ."
                ORDER BY displayorder $sort, title ASC
                LIMIT 1");
            if ($comp AND $sort AND $rule_swap)
            {
                $db->query_write("
                    UPDATE " . TABLE_PREFIX . "userscleanup
                    SET displayorder = CASE ruleid
                        WHEN $rule_orig[ruleid] THEN $rule_swap[displayorder]
                        WHEN $rule_swap[ruleid] THEN $rule_orig[displayorder]
                        ELSE displayorder END
                    WHERE ruleid IN($rule_orig[ruleid], $rule_swap[ruleid])");
            }
        }
    }

    $_REQUEST['do'] = 'list';
}

// #############################################################################
// test one rule
if ($_REQUEST['do'] == 'test')
{
    $vbulletin->input->clean_array_gpc('p', array(
        'criteria'       => TYPE_ARRAY,
    ));

    $criteria = array();

    if ($vbulletin->GPC['ruleid'] > 0 AND empty($vbulletin->GPC['criteria']))
    {
        // run test from rule manager 
        $criteria = uc_get_cleanup_criterias($vbulletin->GPC['ruleid']);
    }
    else
    {
        // run test from add/edit form
        foreach ($vbulletin->GPC['criteria'] AS $criteria_id => $criteria_res)
        {
            if ($criteria_res['active'])
            {
                $criteria[$criteria_id] = $criteria_res;
            }
        }
    }

    if (empty($criteria))
    {
        print_stop_message('uc_no_users_cleanup_criteria_active');
    }

    $users = uc_get_users($criteria);

    if (empty($users))
    {
        // no users found!
        print_stop_message('no_users_matched_your_query');
    }
    if (1 == count($users))
    {
        $user = current($users);
        // show a user if there is just one found
        exec_header_redirect(
            "user.php?"
            . $vbulletin->session->vars['sessionurl']
            . "do=edit&u=" . $user['userid']
        );
    }

?>
<script type="text/javascript">
<!--
    function js_alert_no_permission()
    {
        alert("<?php echo $vbphrase['you_may_not_delete_move_this_user']; ?>");
    }

    function js_usergroup_jump(userinfo)
    {
        var value = eval("document.cpform.u" + userinfo + ".options[document.cpform.u" + userinfo + ".selectedIndex].value");
        if (value != "")
        {
            switch (value)
            {
                case 'edit': page = "edit&u=" + userinfo; break;
                case 'kill': page = "remove&u=" + userinfo; break;
                case 'access': page = "editaccess&u=" + userinfo; break;
                default: page = "emailpassword&u=" + userinfo + "&email=" + value; break;
            }
            window.location = "user.php?<?php echo $vbulletin->session->vars['sessionurl_js']; ?>do=" + page;
        }
    }
//-->
</script>
<?php

    // hardcoded system usergroups such as guest, admin and etc
    $groups = $db->query_read("
        SELECT usergroupid, title
        FROM " . TABLE_PREFIX . "usergroup
        WHERE usergroupid NOT IN(1,3,4,5,6,7)
        ORDER BY title");

    $groups_list = '';

    while ($group = $db->fetch_array($groups))
    {
        $groups_list .= "\t<option value=\"$group[usergroupid]\">$group[title]</option>\n";
    }

    // display the column headings
    $header = array();
    $header[] = 'Userid';
    $header[] = $vbphrase['username'];
    $header[] = $vbphrase['email'];
    $header[] = $vbphrase['post_count'];
    $header[] = $vbphrase['last_activity'];
    $header[] = $vbphrase['join_date'];
    $header[] = '<input type="checkbox" name="allbox" '
        .'onclick="js_check_all(this.form)" title="'
        . $vbphrase['check_all']
        . '" checked="checked" />';

    // get number of cells for use in 'colspan=' attributes
    $colspan = sizeof($header);

    print_form_header('user', 'dopruneusers');
    print_table_header(
        construct_phrase(
            $vbphrase['showing_users_x_to_y_of_z'],
            1,
            $countusers['users'],
            $countusers['users']
        ),
        $colspan);
    print_cells_row($header, 1);

    // now display the results
    foreach ($users as $user)
    {
        $cell = array();

        $cell[] = $user['userid'];
        $cell[] = "<a href=\"user.php?" . $vbulletin->session->vars['sessionurl']
            . "do=edit&u=$user[userid]\" target=\"_blank\">$user[username]</a>"
            . "<br /><span class=\"smallfont\">$user[title]"
            . ($user['moderatorid'] ? ", " . $vbphrase['moderator'] : "" )
            . "</span>";
        $cell[] = "<a href=\"mailto:$user[email]\">$user[email]</a>";
        $cell[] = vb_number_format($user['posts']);
        $cell[] = vbdate($vbulletin->options['dateformat'], $user['lastactivity']);
        $cell[] = vbdate($vbulletin->options['dateformat'], $user['joindate']);

        if (   $user['userid'] == $vbulletin->userinfo['userid']
            OR $user['usergroupid'] == 6
            OR $user['usergroupid'] == 5
            OR $user['moderatorid']
            OR is_unalterable_user($user['userid']))
        {
            $cell[] = '<input type="button" class="button" value=" ! " '
                . 'onclick="js_alert_no_permission()" />';
        }
        else
        {
            $cell[] = "<input type=\"checkbox\" name=\"users[$user[userid]]\" "
                . "value=\"1\" checked=\"checked\" tabindex=\"1\" />";
        }

        print_cells_row($cell);
    }

    print_description_row('<center><span class="smallfont">
        <b>' . $vbphrase['action'] . ':
        <label for="dw_delete"><input type="radio" name="dowhat" value="delete"
        id="dw_delete" tabindex="1" />' . $vbphrase['delete'] . '</label>
        <label for="dw_move"><input type="radio" name="dowhat" value="move"
        id="dw_move" tabindex="1" />' . $vbphrase['move'] . '</label>
        <select name="movegroup" tabindex="1" class="bginput">' . $groups_list . '</select></b>
        </span></center>', 0, 7);

    print_submit_row($vbphrase['go'], $vbphrase['check_all'], $colspan);

    echo '<p>' . $vbphrase['this_action_is_not_reversible'] . '</p>';
}


// #############################################################################
// list existing rules
if ($_REQUEST['do'] == 'list')
{
    print_form_header('users_cleanup', 'quickupdate');
    print_column_style_code(array('width:100%', 'white-space:nowrap'));
    print_table_header($vbphrase['uc_users_cleanup_rules_manager']);

    $userscleanup_result = $db->query("SELECT * FROM " . TABLE_PREFIX . "userscleanup ORDER BY displayorder, title");
    $userscleanup_count  = $db->num_rows($userscleanup_result);

    if ($userscleanup_count)
    {
        print_description_row('<label><input type="checkbox" id="allbox" checked="checked" />' . $vbphrase['toggle_active_status_for_all'] . '</label><input type="image" src="../' . $vbulletin->options['cleargifurl'] . '" name="normalsubmit" />', false, 2, 'thead" style="font-weight:normal; padding:0px 4px 0px 4px');
        while ($rule = $db->fetch_array($userscleanup_result))
        {
            print_label_row(
                '<a href="users_cleanup.php?' . $vbulletin->session->vars['sessionurl'] . 'do=edit&amp;ruleid=' . $rule['ruleid'] . '" title="' . $vbphrase['edit_userscleanup_rule'] . '">' . $rule['title'] . '</a>',
                '<div style="white-space:nowrap">' .
                '<label class="smallfont"><input type="checkbox" name="active[' . $rule['ruleid'] . ']" value="1"' . ($rule['active'] ? ' checked="checked"' : '') . ' />' . $vbphrase['active'] . '</label> ' .
                '<input type="image" src="../cpstyles/' . $vbulletin->options['cpstylefolder'] . '/move_down.gif" name="displayorderswap[' . $rule['ruleid'] . ',higher]" />' .
                '<input type="text" name="displayorder[' . $rule['ruleid'] . ']" value="' . $rule['displayorder'] . '" class="bginput" size="4" title="' . $vbphrase['display_order'] . '" style="text-align:' . $stylevar['right'] . '" />' .
                '<input type="image" src="../cpstyles/' . $vbulletin->options['cpstylefolder'] . '/move_up.gif" name="displayorderswap[' . $rule['ruleid'] . ',lower]" />' .
                construct_link_code($vbphrase['edit'], 'users_cleanup.php?' . $vbulletin->session->vars['sessionurl'] . 'do=edit&amp;ruleid=' . $rule['ruleid']) .
                construct_link_code($vbphrase['test'], 'users_cleanup.php?' . $vbulletin->session->vars['sessionurl'] . 'do=test&amp;ruleid=' . $rule['ruleid']) .
                construct_link_code($vbphrase['delete'], 'users_cleanup.php?' . $vbulletin->session->vars['sessionurl'] . 'do=delete&amp;ruleid=' . $rule['ruleid']) .
                '</div>'
            );
        }
    }

    print_label_row(
        '<input type="button" class="button" value="' . $vbphrase['uc_add_new_rule'] . '" onclick="window.location=\'users_cleanup.php?' . $vbulletin->session->vars['sessionurl'] . 'do=add\';" />',
        ($userscleanup_count ? '<div align="' . $stylevar['right'] . '"><input type="submit" class="button" accesskey="s" value="' . $vbphrase['save'] . '" /> <input type="reset" class="button" accesskey="r" value="' . $vbphrase['reset'] . '" /></div>' : '&nbsp;'),
        'tfoot'
    );

    print_table_footer();

?>
    <script type="text/javascript">
    <!--
        function toggle_all_active(e)
        {
            for (var i = 0; i < this.form.elements.length; i++)
            {
                if (this.form.elements[i].type == "checkbox" && this.form.elements[i].name.substr(0, 6) == "active")
                {
                    this.form.elements[i].checked = this.checked;
                }
            }
        }

    YAHOO.util.Event.on("allbox", "click", toggle_all_active);
    //-->
    </script>
<?php
}


print_cp_footer();
