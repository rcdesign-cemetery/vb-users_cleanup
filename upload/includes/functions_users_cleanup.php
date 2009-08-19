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


if (!isset($GLOBALS['vbulletin']->db))
{
  exit;
}


#
#   Check permissions
#

function users_cleanup_CheckPermissions (&$do, &$admin, &$return_value)
{
  foreach($do AS $field)
  {
    if ($field == 'adminuserscleanup')
    {
      $return_value = $admin['admin_users_cleanup'] ? true : false;

      break;
    }
  }
}


#
#   Build between criteria query
#

function users_cleanup_query_criteria_between($value, $cond1, $cond2, $wrap_cmp = false)
{
  $query_where = '';

  $min = !empty($cond1) ? intval($cond1) : null;
  $max = !empty($cond2) ? intval($cond2) : null;

  if (!is_null($min) AND !is_null($max))
  {
    $min_value = $min < $max ? $min : $max;
    $max_value = $min > $max ? $min : $max;

    $query_where =
      ' AND ( '
      . $value . ( $wrap_cmp ? ' >= ' : ' <= ' ) . $max_value
      . ' AND '
      . $value . ( $wrap_cmp ? ' <= ' : ' >= ' ) . $min_value
      . ' ) ';

    unset($max_value, $min_value);
  }
  else
  if (!is_null($min) AND is_null($max))
  {
    $query_where =
      ' AND ( '
      . $value . ( $wrap_cmp ? ' <= ' : ' >= ' ) . $min
      . ' ) ';
  }
  else
  if (is_null($min) AND !is_null($max))
  {
    $query_where =
      ' AND ( '
      . $value . ( $wrap_cmp ? ' >= ' : ' <= ' ) . $max
      . ' ) ';
  }

  unset($min, $max, $value, $cond1, $cond2);

  return $query_where;
}


#
#   Build SQL query
#

function users_cleanup_Build_SQL_query ($criteria, $offset = 0, $limit = false, $count = false)
{
  if (!is_array($criteria) OR empty($criteria))
    return false;

  $query =
    $query_select      = $query_from      = $query_where      =
    $query_hook_select = $query_hook_from = $query_hook_where = '';


  #
  #   Registration date
  #

  if ($criteriaid = 'has_x_reg_days'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    if (!empty($cr['condition1']) AND intval($cr['condition1']) >= 0)
      $cr['condition1'] = TIMENOW - $cr['condition1'] * 86400;

    if (!empty($cr['condition2']) AND intval($cr['condition2']) >= 0)
      $cr['condition2'] = TIMENOW - $cr['condition2'] * 86400;

    $query_where .= users_cleanup_query_criteria_between(
      '`user`.`joindate`', $cr['condition1'], $cr['condition2']
    );

    unset($cr, $criteriaid);
  }


  #
  #   Last visit date
  #

  if ($criteriaid = 'no_visit_in_x_days'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    if(!empty($cr['condition1']) AND intval($cr['condition1']) > 0)
    {
      $query_where .=
        ' AND ( '
        . '`user`.`lastactivity` < ' . ( TIMENOW - intval($cr['condition1']) * 86400 )
        . ' ) ';
    }

    unset($cr, $criteriaid);
  }


  #
  #   User belongs to main usergroup
  #

  if ($criteriaid = 'in_main_usergroup_x'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    if(!empty($cr['condition1']) AND intval($cr['condition1']) >= 0)
    {
      $query_where .=
        ' AND ( '
        . '`user`.`usergroupid` = ' . intval($cr['condition1'])
        . ' ) ';
    }

    $in_main_groupid = intval($cr['condition1']);

    unset($cr, $criteriaid);
  }


  #
  #   User does not belong to main usergroup
  #

  if ($criteriaid = 'not_in_main_usergroup_x'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    if(!empty($cr['condition1']) AND intval($cr['condition1']) >= 0)
    {
      $query_where .=
        ' AND ( '
        . '`user`.`usergroupid` != ' . intval($cr['condition1'])
        . ' ) ';
    }

    $not_in_main_groupid = intval($cr['condition1']);

    unset($cr, $criteriaid);
  }


  #
  #   User belongs to secondary usergroup
  #

  if ($criteriaid = 'in_second_usergroup_x'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    if(!empty($cr['condition1']) AND intval($cr['condition1']) >= 0
       AND $in_main_groupid !== intval($cr['condition1']))
    {
      $cr['condition1'] = intval($cr['condition1']);

      $query_where .=
        ' AND ( '
        . '`user`.`membergroupids` = "'      . $cr['condition1'] . '"'
        . ' OR '
        . '`user`.`membergroupids` LIKE "%,' . $cr['condition1'] . ',%"'
        . ' OR '
        . '`user`.`membergroupids` LIKE "'   . $cr['condition1'] . ',%"'
        . ' OR '
        . '`user`.`membergroupids` LIKE "%,' . $cr['condition1'] . '"'
        . ' ) ';
    }

    unset($cr, $criteriaid);
  }


  #
  #   User does not belong to secondary usergroup
  #

  if ($criteriaid = 'not_in_second_usergroup_x'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    if(!empty($cr['condition1']) AND intval($cr['condition1']) >= 0
       AND $not_in_main_groupid !== intval($cr['condition1']))
    {
      $cr['condition1'] = intval($cr['condition1']);

      $query_where .=
        ' AND ( '
        . '`user`.`membergroupids` != "'         . $cr['condition1'] . '"'
        . ' AND '
        . '`user`.`membergroupids` NOT LIKE "%,' . $cr['condition1'] . ',%"'
        . ' AND '
        . '`user`.`membergroupids` NOT LIKE "'   . $cr['condition1'] . ',%"'
        . ' AND '
        . '`user`.`membergroupids` NOT LIKE "%,' . $cr['condition1'] . '"'
        . ' ) ';
    }

    unset($cr, $criteriaid);
  }


  #
  #   User has never posted
  #

  if ($criteriaid = 'has_never_posted'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"])
  {
    $query_where .=
      ' AND ( '
      . '`user`.`lastpost` = 0'
      . ' ) ';

    unset($cr, $criteriaid);
  }


  #
  #   User has N posts-count
  #

  if ($criteriaid = 'has_x_postcount'
      AND !empty($criteria["$criteriaid"]) AND $cr =& $criteria["$criteriaid"]
      AND $criteriaid = 'has_never_posted'
      AND empty($criteria["$criteriaid"]))
  {
    // if we should count deleted posts as well
    if ($criteriaid = 'count_deleted_posts'
        AND !empty($criteria["$criteriaid"]))
    {
      $query_where .= users_cleanup_query_criteria_between(
        '( SELECT COUNT(P.`userid`) '
        . 'FROM `' . TABLE_PREFIX . 'post` AS P '
        . 'WHERE P.`userid` = `user`.`userid` )',
        $cr['condition1'], $cr['condition2']
      );
    }
    else
    {
      $query_where .= users_cleanup_query_criteria_between(
        '`user`.`posts`', $cr['condition1'], $cr['condition2']
      );
    }

    unset($cr, $criteriaid);
  }

  if (intval($limit) > 0)
  {
    $offset = intval($offset);
    $limit  = intval($limit);
  }

  $query = "
    SELECT " . ( $count ? "COUNT(*) AS users" : "user.* $query_select $query_hook_select" ) . "
    FROM `" . TABLE_PREFIX . "user` AS user $query_from $query_hook_from
    WHERE 1 $query_where $query_hook_where
    " . ( (!$count AND intval($limit) > 0) ? "LIMIT $offset, $limit" : "" ) . "
  ";

  return $query;
}
