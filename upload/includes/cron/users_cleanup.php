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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
    exit;
}

// ############################# REQUIRE ##################################
require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/adminfunctions.php');
require_once(DIR . '/includes/functions_users_cleanup.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$db =& $vbulletin->db;

$res = $db->query("
    SELECT *
    FROM " . TABLE_PREFIX . "userscleanup
    WHERE `active` = '1'
    ORDER BY displayorder
    ");

while ($rule = $db->fetch_array($res))
{

    $criteria = uc_get_cleanup_criterias($rule['userscleanupid']);
    $users = uc_get_users($criteria);

    foreach ($users as $user)
    {
        // check user is not set in the $undeletable users string
        if (!is_unalterable_user($user['userid']))
        {
            $info = fetch_userinfo($user['userid']);
            if ($info)
            {
                $userdm =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
                $userdm->set_existing($info);
                $userdm->delete();
                unset($userdm);
            }
        }
    }

    $db->free_result($users);
}

$db->free_result($res);

log_cron_action('', $nextitem, 1);

?>
