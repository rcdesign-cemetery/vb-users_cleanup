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

function uc_get_rule($rule_id)
{
    global $db;
    $sql = 'SELECT * 
            FROM
                ' . TABLE_PREFIX . 'userscleanup
            WHERE 
                ruleid = ' . (int)$rule_id;
    $rule = $db->query_first($sql);
    return $rule;
}

function uc_get_cleanup_criterias($rule_id)
{
    global $db;
    $sql = 'SELECT *
            FROM 
                ' . TABLE_PREFIX . 'userscleanupcriteria
            WHERE
                ruleid = ' . (int)$rule_id;

    $res = $db->query_read($sql);

    $result = array();
    while ($criteria = $db->fetch_array($res))
    {
        $result[$criteria['criteriaid']] = $criteria;
    }

    $db->free_result($res);
    return $result;
}

function uc_get_users($criteria)
{
    global $vbulletin, $db;

    if (!is_array($criteria) OR empty($criteria))
    {
        return false;
    }

    $users = array();
    $where = array();
    $join = array();
    foreach ($criteria as $id=>$conditions)
    {
        switch ($id)
        {
            case 'has_x_reg_days': //  Registration date
                if (0 < (int)$conditions['condition1'])
                {
                    $where[] = '`user`.`joindate` >=' . (TIMENOW - $conditions['condition1'] * 86400);
                }
                if (0 < (int)$conditions['condition2'])
                {
                    $where[] = '`user`.`joindate` <=' . (TIMENOW - $conditions['condition2'] * 86400);
                }
                break;
            case 'no_visit_in_x_days': // Last visit date
                if(0 < (int)$conditions['condition1'])
                {
                    $where []= '`user`.`lastactivity` < ' . ( TIMENOW - $conditions['condition1'] * 86400 );
                }
                break;
            case 'in_main_usergroup_x': //  User belongs to main usergroup
                if(0 < (int)$conditions['condition1'])
                {
                    $where[] = '`user`.`usergroupid` = ' . (int)$conditions['condition1'];
                }
                break;
            case 'not_in_main_usergroup_x': // User does not belong to main usergroup
                if(0 < (int)$conditions['condition1'])
                {
                    $where[] = '`user`.`usergroupid` != ' . (int)$conditions['condition1'];
                }
                break;
            case 'in_second_usergroup_x': // User belongs to secondary usergroup
                if(0 < (int)$conditions['condition1'])
                {
                    $group_id = (int)$conditions['condition1'];
                    $where[] = "FIND_IN_SET($group_id, `user`.membergroupids)";
                }
                break;
            case 'not_in_second_usergroup_x': // User does not belong to secondary usergroup
                if( 0 < (int)$conditions['condition1'])
                {
                    $group_id = (int)$conditions['condition1'];
                    $where[] = "NOT FIND_IN_SET($group_id, `user`.membergroupids)";
                }
                break;
            case 'has_x_postcount':
                if (0 < (int)$conditions['condition1'])
                {
                    $where[] = '`user`.`posts` >=' . (int)$conditions['condition1'];
                }
                if (0 < (int)$conditions['condition2'])
                {
                    $where[] = '`user`.`posts` <=' . (int)$conditions['condition2'];
                }
                break;
            case 'has_never_posted':
                // posts
                $where[] = '`user`.`posts` = 0';
                
                // social groups
                $join[] = 'LEFT JOIN ' . TABLE_PREFIX . 'groupmessage AS groupmessage ON `groupmessage`.postuserid = `user`.userid';
                $where[] = '`groupmessage`.gmid IS NULL';

                // blog
                if ($vbulletin->products['vbblog'])
                {
                    $join[] = 'LEFT JOIN ' . TABLE_PREFIX . 'blog_text AS blog_text ON `blog_text`.bloguserid = `user`.userid';
                    $where[] = '`blog_text`.blogtextid IS NULL';
                }
                break;
        }
    }

    // Exclude users
    $ids = explode(',', $vbulletin->options['uc_user_id_not_in']);
    if (!empty($ids))
    {
        $ids = array_map('intval', $ids);
        $ids = array_unique($ids);
        $where[] = '`user`.`userid` NOT IN (' . implode(', ', $ids) . ')';
    }

    if (empty($where))
    {
        return $users;
    }
    $sql = 'SELECT 
                `user`.*
            FROM 
                ' . TABLE_PREFIX . 'user AS user
            ' . (!empty($join)? implode("\n", $join) :'') . '
            WHERE 
                ' . implode (' AND ', $where);
    $res = $db->query_read($sql);

    while ($user = $db->fetch_array($res))
    {
        $users[$user['userid']] = $user;
    }
    $db->free_result($res);
    return $users;
}
