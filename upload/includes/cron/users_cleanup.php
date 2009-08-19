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

$userscleanup_result = $db->query("
  SELECT *
  FROM " . TABLE_PREFIX . "userscleanup
  WHERE `active` = '1'
  ORDER BY displayorder, title
");

$userscleanup_count = $db->num_rows($userscleanup_result);

if ($userscleanup_count)
{
  while ($rule = $db->fetch_array($userscleanup_result))
  {
    $criteria = array();

    $criteria_result = $db->query_read("
      SELECT *
      FROM " . TABLE_PREFIX . "userscleanupcriteria
      WHERE userscleanupid = " . intval($rule['userscleanupid'])
    );

    while ($criteria_res = $db->fetch_array($criteria_result))
    {
      $criteria_res['active'] = '1';
      $criteria["$criteria_res[criteriaid]"] = $criteria_res;
    }

    $db->free_result($criteria_result);

    $searchquery = users_cleanup_Build_SQL_query($criteria);
    $users       = $db->query_read($searchquery);

    while ($user = $db->fetch_array($users))
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
}

$db->free_result($userscleanup_result);

log_cron_action('', $nextitem, 1);

?>
