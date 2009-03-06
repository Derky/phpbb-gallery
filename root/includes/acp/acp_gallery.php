<?php
/**
*
* @package phpBB Gallery
* @version $Id$
* @copyright (c) 2007 nickvergessen nickvergessen@gmx.de http://www.flying-bits.org
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package acp
*/
class acp_gallery
{
	var $u_action;

	function main($id, $mode)
	{
		global $gallery_config, $db, $template, $user;
		global $gallery_root_path, $phpbb_root_path, $phpEx;
		$gallery_root_path = GALLERY_ROOT_PATH;

		include($phpbb_root_path . $gallery_root_path . 'includes/constants.' . $phpEx);
		include($phpbb_root_path . $gallery_root_path . 'includes/functions.' . $phpEx);
		include($phpbb_root_path . $gallery_root_path . 'includes/permissions.' . $phpEx);
		$gallery_config = load_gallery_config();

		$user->add_lang('mods/gallery_acp');
		$user->add_lang('mods/gallery');
		$this->tpl_name = 'gallery_main';
		add_form_key('acp_gallery');

		switch ($mode)
		{
			case 'overview':
				$title = 'ACP_GALLERY_OVERVIEW';
				$this->page_title = $user->lang[$title];

				$this->overview();
			break;

			case 'album_permissions':
				$title = 'ALBUM_AUTH_TITLE';
				$this->tpl_name = 'gallery_permissions';
				$this->page_title = $user->lang[$title];

				$submode = request_var('submode', '');
				if ($submode == 'set')
				{
					$this->set_permissions();
				}
				else
				{
					$this->permissions();
				}
			break;

			case 'import_images':
				$title = 'ACP_IMPORT_ALBUMS';
				$this->page_title = $user->lang[$title];

				$this->import();
			break;

			case 'cleanup':
				$title = 'ACP_GALLERY_CLEANUP';
				$this->page_title = $user->lang[$title];

				$this->cleanup();
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}
	}

	function overview()
	{
		global $gallery_config, $template, $user, $db, $phpbb_root_path, $config, $auth;

		$action = request_var('action', '');
		$id = request_var('i', '');
		$mode = 'overview';

		if (!confirm_box(true))
		{
			$confirm = false;
			$album_id = 0;
			switch ($action)
			{
				case 'images':
					$confirm = true;
					$confirm_lang = 'RESYNC_IMAGECOUNTS_CONFIRM';
				break;
				case 'personals':
					$confirm = true;
					$confirm_lang = 'CONFIRM_OPERATION';
				break;
				case 'stats':
					$confirm = true;
					$confirm_lang = 'CONFIRM_OPERATION';
				break;
				case 'last_images':
					$confirm = true;
					$confirm_lang = 'CONFIRM_OPERATION';
				break;
				case 'reset_rating':
					$album_id = request_var('reset_album_id', 0);
					$album_data = get_album_info($album_id);
					$confirm = true;
					$confirm_lang = sprintf($user->lang['RESET_RATING_CONFIRM'], $album_data['album_name']);
				break;
				case 'purge_cache':
					$confirm = true;
					$confirm_lang = 'GALLERY_PURGE_CACHE_EXPLAIN';
				break;
			}

			if ($confirm)
			{
				confirm_box(false, (($album_id) ? $confirm_lang : $user->lang[$confirm_lang]), build_hidden_fields(array(
					'i'			=> $id,
					'mode'		=> $mode,
					'action'	=> $action,
					'reset_album_id'	=> $album_id,
				)));
			}
		}
		else
		{
			switch ($action)
			{
				case 'images':
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$total_images = 0;
					$sql = 'UPDATE ' . GALLERY_USERS_TABLE . '
						SET user_images = 0';
					$db->sql_query($sql);

					$sql = 'SELECT COUNT(image_id) num_images, image_user_id user_id
						FROM ' . GALLERY_IMAGES_TABLE . '
						WHERE image_status = ' . IMAGE_APPROVED . '
						GROUP BY image_user_id';
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$total_images += $row['num_images'];
						$sql = 'UPDATE ' . GALLERY_USERS_TABLE . '
							SET user_images = ' . $row['num_images'] . '
							WHERE user_id = ' . $row['user_id'];
						$db->sql_query($sql);

						if ($db->sql_affectedrows() <= 0)
						{
							$sql_ary = array(
								'user_id'				=> $row['user_id'],
								'user_images'			=> $row['num_images'],
							);
							$sql = 'INSERT INTO ' . GALLERY_USERS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
							$db->sql_query($sql);
						}
					}
					$db->sql_freeresult($result);

					set_config('num_images', $total_images, true);
					trigger_error($user->lang['RESYNCED_IMAGECOUNTS'] . adm_back_link($this->u_action));
				break;

				case 'personals':
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$sql = 'UPDATE ' . GALLERY_USERS_TABLE . "
						SET personal_album_id = 0";
					$db->sql_query($sql);

					$sql = 'SELECT album_id, album_user_id
						FROM ' . GALLERY_ALBUMS_TABLE . '
						WHERE album_user_id <> 0
							AND parent_id = 0
						GROUP BY album_user_id';
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$sql = 'UPDATE ' . GALLERY_USERS_TABLE . '
							SET personal_album_id = ' . $row['album_id'] . '
							WHERE user_id = ' . $row['album_user_id'];
						$db->sql_query($sql);

						if ($db->sql_affectedrows() <= 0)
						{
							$sql_ary = array(
								'user_id'				=> $row['album_user_id'],
								'personal_album_id'		=> $row['album_id'],
							);
							$sql = 'INSERT INTO ' . GALLERY_USERS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
							$db->sql_query($sql);
						}
					}
					$db->sql_freeresult($result);

					trigger_error($user->lang['RESYNCED_PERSONALS'] . adm_back_link($this->u_action));
				break;

				case 'stats':
					if (!$auth->acl_get('a_board'))
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					// Hopefully this won't take to long!
					$sql = 'SELECT image_id, image_filename, image_thumbnail
						FROM ' . GALLERY_IMAGES_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$sql_ary = array(
							'filesize_upload'		=> @filesize($phpbb_root_path . GALLERY_UPLOAD_PATH . $row['image_filename']),
							'filesize_medium'		=> @filesize($phpbb_root_path . GALLERY_MEDIUM_PATH . $row['image_thumbnail']),
							'filesize_cache'		=> @filesize($phpbb_root_path . GALLERY_CACHE_PATH . $row['image_thumbnail']),
						);
						$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
							SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
							WHERE ' . $db->sql_in_set('image_id', $row['image_id']);
						$db->sql_query($sql);
					}
					$db->sql_freeresult($result);

					redirect($this->u_action);
				break;

				case 'last_images':
					$sql = 'SELECT album_id
						FROM ' . GALLERY_ALBUMS_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						// 5 sql's per album, but you don't run this daily ;)
						update_album_info($row['album_id']);
					}
					$db->sql_freeresult($result);
					trigger_error($user->lang['RESYNCED_LAST_IMAGES'] . adm_back_link($this->u_action));
				break;

				case 'reset_rating':
					$album_id = request_var('reset_album_id', 0);
					$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
						SET image_rates = 0,
							image_rate_points = 0,
							image_rate_avg = 0
						WHERE image_album_id = ' . $album_id;
					$db->sql_query($sql);

					$image_ids = array();
					$sql = 'SELECT image_id
						FROM ' . GALLERY_IMAGES_TABLE . '
						WHERE image_album_id = ' . $album_id;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$image_ids[] = $row['image_id'];
					}
					$db->sql_freeresult($result);

					$sql = 'DELETE FROM ' . GALLERY_RATES_TABLE . '
						WHERE ' . $db->sql_in_set('rate_image_id', $image_ids);
					$db->sql_query($sql);

					trigger_error($user->lang['RESET_RATING_COMPLETED'] . adm_back_link($this->u_action));
				break;

				case 'purge_cache':
					if ($user->data['user_type'] != USER_FOUNDER)
					{
						trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$cache_dir = @opendir($phpbb_root_path . GALLERY_CACHE_PATH);
					while ($cache_file = @readdir($cache_dir))
					{
						if (preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $cache_file))
						{
							@unlink($phpbb_root_path . GALLERY_CACHE_PATH . $cache_file);
						}
					}
					@closedir($cache_dir);

					$medium_dir = @opendir($phpbb_root_path . GALLERY_MEDIUM_PATH);
					while ($medium_file = @readdir($medium_dir))
					{
						if (preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $medium_file))
						{
							@unlink($phpbb_root_path . GALLERY_MEDIUM_PATH . $medium_file);
						}
					}
					@closedir($medium_dir);

					$sql_ary = array(
						'filesize_medium'		=> @filesize($phpbb_root_path . GALLERY_MEDIUM_PATH . $row['image_thumbnail']),
						'filesize_cache'		=> @filesize($phpbb_root_path . GALLERY_CACHE_PATH . $row['image_thumbnail']),
					);
					$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
						SET ' . $db->sql_build_array('UPDATE', $sql_ary);
					$db->sql_query($sql);

					trigger_error($user->lang['PURGED_CACHE'] . adm_back_link($this->u_action));
				break;
			}
		}

		$boarddays = (time() - $config['board_startdate']) / 86400;
		$images_per_day = sprintf('%.2f', $config['num_images'] / $boarddays);

		$sql = 'SELECT COUNT(album_user_id) num_albums
			FROM ' . GALLERY_ALBUMS_TABLE . '
			WHERE album_user_id = 0';
		$result = $db->sql_query($sql);
		$num_albums = (int) $db->sql_fetchfield('num_albums');
		$db->sql_freeresult($result);

		$sql = 'SELECT SUM(filesize_upload) as stat, SUM(filesize_medium) as stat_medium, SUM(filesize_cache) as stat_cache
			FROM ' . GALLERY_IMAGES_TABLE;
		$result = $db->sql_query($sql);
		$dir_sizes = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'S_GALLERY_OVERVIEW'			=> true,
			'ACP_GALLERY_TITLE'				=> $user->lang['ACP_GALLERY_OVERVIEW'],
			'ACP_GALLERY_TITLE_EXPLAIN'		=> $user->lang['ACP_GALLERY_OVERVIEW_EXPLAIN'],

			'TOTAL_IMAGES'			=> $config['num_images'],
			'IMAGES_PER_DAY'		=> $images_per_day,
			'TOTAL_ALBUMS'			=> $num_albums,
			'TOTAL_PERSONALS'		=> $gallery_config['personal_counter'],
			'GUPLOAD_DIR_SIZE'		=> get_formatted_filesize($dir_sizes['stat']),
			'MEDIUM_DIR_SIZE'		=> get_formatted_filesize($dir_sizes['stat_medium']),
			'CACHE_DIR_SIZE'		=> get_formatted_filesize($dir_sizes['stat_cache']),
			'GALLERY_VERSION'		=> $gallery_config['phpbb_gallery_version'],

			'S_FOUNDER'				=> ($user->data['user_type'] == USER_FOUNDER) ? true : false,
		));
	}

	function permissions()
	{
		global $db, $template, $user, $cache;
		global $phpbb_admin_path, $phpEx;

		// Send contants to the template
		$template->assign_vars(array(
			'C_OWN_PERSONAL_ALBUMS'	=> OWN_GALLERY_PERMISSIONS,
			'C_PERSONAL_ALBUMS'		=> PERSONAL_GALLERY_PERMISSIONS,
		));

		$submit = (isset($_POST['submit'])) ? true : false;
		$delete = (isset($_POST['delete'])) ? true : false;
		$album_ary = request_var('album_ids', array(''));
		$album_list = implode(', ', $album_ary);
		$group_ary = request_var('group_ids', array(''));
		$group_list = implode(', ', $group_ary);
		$step = request_var('step', 0);
		$perm_system = request_var('perm_system', 0);
		if ($perm_system > 1)
		{
			$album_ary = array();
		}
		if ($delete)
		{
			if (!check_form_key('acp_gallery'))
			{
				trigger_error('FORM_INVALID');
			}

			// User dropped the permissions
			$drop_perm_ary = request_var('drop_perm', array(''));
			$drop_perm_string = implode(', ', $drop_perm_ary);
			if ($drop_perm_string && $album_list)
			{
				$sql = 'DELETE FROM ' . GALLERY_PERMISSIONS_TABLE . '
					WHERE ' . $db->sql_in_set('perm_group_id', $drop_perm_ary) . '
						AND ' . $db->sql_in_set('perm_album_id', $album_ary) . '
						AND perm_system = ' . $perm_system;
				$db->sql_query($sql);
			}
			else if ($drop_perm_string)
			{
				$sql = 'DELETE FROM ' . GALLERY_PERMISSIONS_TABLE . '
					WHERE ' . $db->sql_in_set('perm_group_id', $drop_perm_ary) . '
						AND perm_system = ' . $perm_system;
				$db->sql_query($sql);
			}
			$step = 1;
		}

		$album_name_ary = array();
		// Build the array with some kind of order.
		$permissions = $permission_parts['misc'] = $permission_parts['m'] = $permission_parts['c'] = $permission_parts['i'] = array();
		if ($perm_system != OWN_GALLERY_PERMISSIONS)
		{
			$permission_parts['i'] = array_merge($permission_parts['i'], array('i_view'));
		}
		$permission_parts['i'] = array_merge($permission_parts['i'], array('i_watermark', 'i_upload'));
		if ($perm_system != PERSONAL_GALLERY_PERMISSIONS)
		{
			// Note for myself, do not hide the i_upload on other personals. It's used for the moving-permissions
			$permission_parts['i'] = array_merge($permission_parts['i'], array('i_approve', 'i_edit', 'i_delete'));
		}
		$permission_parts['i'] = array_merge($permission_parts['i'], array('i_report', 'i_rate'));
		$permission_parts['c'] = array_merge($permission_parts['c'], array('c_read', 'c_post', 'c_edit', 'c_delete'));
		$permission_parts['m'] = array_merge($permission_parts['m'], array('m_comments', 'm_delete', 'm_edit', 'm_move', 'm_report', 'm_status'));
		$permission_parts['misc'] = array_merge($permission_parts['misc'], array('a_list'));
		if ($perm_system != PERSONAL_GALLERY_PERMISSIONS)
		{
			$permission_parts['misc'] = array_merge($permission_parts['misc'], array('i_count'));
		}
		if ($perm_system == OWN_GALLERY_PERMISSIONS)
		{
			$permission_parts['misc'] = array_merge($permission_parts['misc'], array('album_count'));
		}
		$permissions = array_merge($permissions, $permission_parts['i'], $permission_parts['c'], $permission_parts['m'], $permission_parts['misc']);
		$template->assign_var('S_PERM_CAT_ROWS', sizeof($permission_parts));
		for ($i = 0; $i < sizeof($permission_parts); $i++)
		{
			$template->assign_block_vars('c_rows', array());
		}

		$albums = $cache->obtain_album_list();

		if ($step == 0)
		{
			$template->assign_var('ALBUM_LIST', gallery_albumbox(true, '', SETTING_PERMISSIONS));
			$step = 1;
		}
		else if ($step == 1)
		{
			if (request_var('uncheck', '') == '')
			{
				if (!check_form_key('acp_gallery'))
				{
					trigger_error('FORM_INVALID');
				}
			}
			else
			{
				$album_ary = array(request_var('album_id', 0));
			}
			if ($perm_system == 0)
			{
				foreach ($albums as $album)
				{
					if (in_array($album['album_id'], $album_ary))
					{
						$template->assign_block_vars('albumrow', array(
							'ALBUM_ID'				=> $album['album_id'],
							'ALBUM_NAME'			=> $album['album_name'],
						));
					}
				}
			}

			$sql = 'SELECT group_id, group_type, group_name, group_colour
				FROM ' . GROUPS_TABLE;
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$row['group_name'] = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'];
				$template->assign_block_vars('grouprow', array(
					'GROUP_ID'				=> $row['group_id'],
					'GROUP_NAME'			=> $row['group_name'],
				));
				$group[$row['group_id']]['group_name'] = $row['group_name'];
				$group[$row['group_id']]['group_colour'] = $row['group_colour'];
			}
			$db->sql_freeresult($result);

			if (!isset($album_ary[1]))
			{
				$where = '';
				if ($perm_system == 0)
				{
					if (!isset($album_ary[0]))
					{
						trigger_error('NO_ALBUM_SELECTED', E_USER_WARNING);
					}
					$where = 'perm_album_id = ' . $album_ary[0];
				}
				else
				{
					$where = 'perm_system = ' . $perm_system;
				}
				$sql2 = 'SELECT * FROM ' . GALLERY_PERMISSIONS_TABLE . "
					WHERE $where
						AND perm_group_id <> 0";
				$result2 = $db->sql_query($sql2);
				while ($row = $db->sql_fetchrow($result2))
				{
					$template->assign_block_vars('perm_grouprow', array(
						'GROUP_ID'				=> $row['perm_group_id'],
						'GROUP_COLOUR'			=> $group[$row['perm_group_id']]['group_colour'],
						'GROUP_NAME'			=> $group[$row['perm_group_id']]['group_name'],
					));
				}
				$db->sql_freeresult($result2);
			}
			$step = 2;
		}
		else if ($step == 2)
		{
			if (!check_form_key('acp_gallery'))
			{
				trigger_error('FORM_INVALID');
			}
			$template->assign_vars(array(
				'S_VIEWING_PERMISSIONS'		=> true,
			));
			$groups_ary = array();
			$sql = 'SELECT group_id, group_type, group_name, group_colour
				FROM ' . GROUPS_TABLE . "
				WHERE group_id IN ($group_list)";
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$row['group_name'] = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'];
				$new_groups[] = array(
					'group_id'				=> $row['group_id'],
					'group_name'			=> $row['group_name'],
					'group_colour'			=> $row['group_colour'],
				);
			}
			$db->sql_freeresult($result);
			//Album names
			foreach ($albums as $album)
			{
				if (in_array($album['album_id'], $album_ary))
				{
					$template->assign_block_vars('c_mask', array(
						'ALBUM_ID'				=> $album['album_id'],
						'ALBUM_NAME'			=> $album['album_name'],
						'INHERIT_ALBUMS'		=> $this->inherit_albums($albums, $album_ary, $album['album_id']),
					));
					foreach ($new_groups as $row)
					{
						$template->assign_block_vars('c_mask.v_mask', array(
							'VICTIM_ID'				=> $row['group_id'],
							'VICTIM_NAME'			=> '<span' . (($row['group_colour']) ? (' style="color: #' . $row['group_colour'] . '"') : '') . '>' . $row['group_name'] . '</span>',
							'INHERIT_VICTIMS'		=> $this->inherit_victims($albums, $album_ary, $new_groups, $album['album_id'], $row['group_id']),
						));
						foreach ($permission_parts as $perm_groupname => $permission)
						{
							$template->assign_block_vars('c_mask.v_mask.category', array(
								'CAT_NAME'				=> $user->lang['PERMISSION_' . strtoupper($perm_groupname)],
								'PERM_GROUP_ID'			=> $perm_groupname,
							));
							$string = implode(', ', $permission);
							foreach ($permission_parts[$perm_groupname] as $permission)
							{
								$template->assign_block_vars('c_mask.v_mask.category.mask', array(
									'PERMISSION'			=> $user->lang['PERMISSION_' . strtoupper($permission)],
									'S_FIELD_NAME'			=> 'setting[' . $album['album_id'] . '][' . $row['group_id'] . '][' . $permission . ']',
									'S_NO'					=> ((isset($perm_ary[$permission]) && ($perm_ary[$permission] == 0)) ? true : false),
									'S_YES'					=> ((isset($perm_ary[$permission]) && ($perm_ary[$permission] == 1)) ? true : false),
									'S_NEVER'				=> ((isset($perm_ary[$permission]) && ($perm_ary[$permission] == 2)) ? true : false),
									'S_VALUE'				=> ((isset($perm_ary[$permission])) ? $perm_ary[$permission] : 0),
									'S_COUNT_FIELD'			=> (substr($permission, -6, 6) == '_count') ? true : false,
								));
							}
						}
					}
				}
			}
			//Group names
			if (!$group_list)
			{
				trigger_error($user->lang['PERMISSION_NO_GROUP'] . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$sql = 'SELECT group_id, group_type, group_name, group_colour
				FROM ' . GROUPS_TABLE . "
				WHERE group_id IN ($group_list)";
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$row['group_name'] = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'];
				$template->assign_block_vars('grouprow', array(
					'GROUP_ID'				=> $row['group_id'],
					'GROUP_NAME'			=> $row['group_name'],
					'GROUP_COLOUR'			=> $row['group_colour'],
				));
			}
			$db->sql_freeresult($result);
			$template->assign_vars(array(
				'S_ALBUMS'			=> (sizeof($album_ary) > 1) ? true : false,
				'S_GROUPS'			=> (sizeof($group_ary) > 1) ? true : false,
			));
			if ((!isset($album_ary[1])) && (!isset($group_ary[1])))
			{
				$where = '';
				if ($perm_system == 0)
				{
					$where = 'p.perm_album_id = ' . $album_ary[0];
				}
				else
				{
					$where = 'p.perm_system = ' . $perm_system;
				}
				$sql = 'SELECT pr.*
					FROM ' . GALLERY_PERMISSIONS_TABLE . ' p
					LEFT JOIN ' .  GALLERY_ROLES_TABLE .  " pr
						ON p.perm_role_id = pr.role_id
					WHERE p.perm_group_id = {$group_ary[0]}
						AND $where";
				$result = $db->sql_query_limit($sql, 1);
				$perm_ary = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
			}

			// Permissions
			foreach ($permission_parts as $perm_groupname => $permission)
			{
				$template->assign_block_vars('perm_group', array(
					'PERMISSION_GROUP'			=> $user->lang['PERMISSION_' . strtoupper($perm_groupname)],
					'PERM_GROUP_ID'				=> $perm_groupname,
				));
				$string = implode(', ', $permission);
				foreach ($permission_parts[$perm_groupname] as $permission)
				{
					$template->assign_block_vars('perm_group.permission', array(
						'PERMISSION'			=> $user->lang['PERMISSION_' . strtoupper($permission)],
						'S_FIELD_NAME'			=> $permission,
						'S_NO'					=> ((isset($perm_ary[$permission]) && ($perm_ary[$permission] == 0)) ? true : false),
						'S_YES'					=> ((isset($perm_ary[$permission]) && ($perm_ary[$permission] == 1)) ? true : false),
						'S_NEVER'				=> ((isset($perm_ary[$permission]) && ($perm_ary[$permission] == 2)) ? true : false),
						'S_VALUE'				=> ((isset($perm_ary[$permission])) ? $perm_ary[$permission] : 0),
						'S_COUNT_FIELD'			=> (substr($permission, -6, 6) == '_count') ? true : false,
					));
				}
			}
			$step = 3;
		}

		if ($perm_system)
		{
			$hidden_fields = build_hidden_fields(array(
				'album_ids'			=> $album_ary,
				'group_ids'			=> $group_ary,
				'step'				=> $step,
				'perm_system'		=> $perm_system,
			));
		}
		else
		{
			$hidden_fields = build_hidden_fields(array(
				'album_ids'			=> $album_ary,
				'group_ids'			=> $group_ary,
				'step'				=> $step,
			));
		}

		$template->assign_vars(array(
			'S_HIDDEN_FIELDS'		=> $hidden_fields,
			'ALBUMS'				=> implode(', ', $album_name_ary),
			'GROUPS'				=> implode(', ', $group_ary),
			'STEP'					=> $step,
			'PERM_SYSTEM'			=> $perm_system,
			'S_ALBUM_ACTION' 		=> ($step == 3) ? append_sid("{$phpbb_admin_path}index.$phpEx", 'i=gallery&amp;mode=permissions&amp;submode=set') : $this->u_action,
		));
	}

	function set_permissions()
	{
		global $db, $template, $user, $cache;
		global $phpbb_admin_path, $phpEx;

		// Send contants to the template
		$template->assign_vars(array(
			'C_OWN_PERSONAL_ALBUMS'	=> OWN_GALLERY_PERMISSIONS,
			'C_PERSONAL_ALBUMS'		=> PERSONAL_GALLERY_PERMISSIONS,
		));

		$submit = (isset($_POST['submit'])) ? true : false;
		/**
		* Can we put this away?
		*/
		// Build the array with some kind of order.
		$permissions = $permission_parts['misc'] = $permission_parts['m'] = $permission_parts['c'] = $permission_parts['i'] = array();
		if ($perm_system != OWN_GALLERY_PERMISSIONS)
		{
			$permission_parts['i'] = array_merge($permission_parts['i'], array('i_view'));
		}
		$permission_parts['i'] = array_merge($permission_parts['i'], array('i_watermark', 'i_upload'));
		if ($perm_system != PERSONAL_GALLERY_PERMISSIONS)
		{
			// Note for myself, do not hide the i_upload on other personals. It's used for the moving-permissions
			$permission_parts['i'] = array_merge($permission_parts['i'], array('i_approve', 'i_edit', 'i_delete'));
		}
		$permission_parts['i'] = array_merge($permission_parts['i'], array('i_report', 'i_rate'));
		$permission_parts['c'] = array_merge($permission_parts['c'], array('c_read', 'c_post', 'c_edit', 'c_delete'));
		$permission_parts['m'] = array_merge($permission_parts['m'], array('m_comments', 'm_delete', 'm_edit', 'm_move', 'm_report', 'm_status'));
		$permission_parts['misc'] = array_merge($permission_parts['misc'], array('a_list'));
		if ($perm_system != PERSONAL_GALLERY_PERMISSIONS)
		{
			$permission_parts['misc'] = array_merge($permission_parts['misc'], array('i_count'));
		}
		if ($perm_system == OWN_GALLERY_PERMISSIONS)
		{
			$permission_parts['misc'] = array_merge($permission_parts['misc'], array('album_count'));
		}
		$permissions = array_merge($permissions, $permission_parts['i'], $permission_parts['c'], $permission_parts['m'], $permission_parts['misc']);

		if ($submit)
		{
			if (!check_form_key('acp_gallery'))
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}
			$coal = $cache->obtain_album_list();

			/**
			* Grab the permissions
			*
			* includes/acp/acp_permissions.php says:
			* // We obtain and check $_POST['setting'][$ug_id][$forum_id] directly and not using request_var() because request_var()
			* // currently does not support the amount of dimensions required. ;)
			*/
			//		$auth_settings = request_var('setting', array(0 => array(0 => array('' => 0))));
			$p_mask_count = 0;
			$auth_settings = $p_mask_storage = $c_mask_storage = $v_mask_storage = array();
			foreach ($_POST['setting'] as $c_mask => $v_sets)
			{
				$c_mask = (int) $c_mask;
				$c_mask_storage[] = $c_mask;
				$auth_settings[$c_mask] = array();
				foreach ($v_sets as $v_mask => $p_sets)
				{
					$v_mask = (int) $v_mask;
					$v_mask_storage[] = $v_mask;
					$auth_settings[$c_mask][$v_mask] = array();
					$is_moderator = false;
					foreach ($p_sets as $p_mask => $value)
					{
						if (!in_array($p_mask, $permissions))
						{
							// An admin tried to set a non-existing permission. Hacking attempt?!
							trigger_error('HACKING_ATTEMPT', E_USER_WARNING);
						}
						// Casted all values to integer and checked all strings whether they are permissions!
						// Should be fine than for the .com MOD-Team now =)
						$value = (int) $value;
						if (substr($p_mask, -6, 6) == '_count')
						{
							$auth_settings[$c_mask][$v_mask][$p_mask] = $value;
						}
						else
						{
							$auth_settings[$c_mask][$v_mask][$p_mask] = ($value == ACL_YES) ? GALLERY_ACL_YES : (($value == ACL_NEVER) ? GALLERY_ACL_NEVER : GALLERY_ACL_NO);
							// Do we have moderators?
							if ((substr($p_mask, 0, 2) == 'm_') && ($value == ACL_YES))
							{
								$is_moderator = true;
							}
						}
					}
					$p_mask_storage[$p_mask_count]['p_mask'] = $auth_settings[$c_mask][$v_mask];
					$p_mask_storage[$p_mask_count]['is_moderator'] = $is_moderator;
					$p_mask_storage[$p_mask_count]['usage'][] = array('c_mask' => $c_mask, 'v_mask' => $v_mask);
					$auth_settings[$c_mask][$v_mask] = $p_mask_count;
					$p_mask_count++;
				}
			}
			/**
			* Inherit the permissions
			*/
			foreach ($_POST['inherit'] as $c_mask => $v_sets)
			{
				$c_mask = (int) $c_mask;
				foreach ($v_sets as $v_mask => $i_mask)
				{
					if (($v_mask == 'full') && $i_mask)
					{
						// Inherit all permissions of an other c_mask
						if (isset($auth_settings[$i_mask]))
						{
							if ($this->inherit_albums($coal, $c_mask_storage, $c_mask, $i_mask))
							{
								foreach ($auth_settings[$c_mask] as $v_mask => $p_mask)
								{
									// You are not able to inherit a later c_mask, so we can remove the p_mask from the storage,
									// and just use the same p_mask
									unset($p_mask_storage[$auth_settings[$c_mask][$v_mask]]);
									$auth_settings[$c_mask][$v_mask] = $auth_settings[$i_mask][$v_mask];
									$p_mask_storage[$auth_settings[$c_mask][$v_mask]]['usage'][] = array('c_mask' => $c_mask, 'v_mask' => $v_mask);
								}
								// We take all permissions of another c_mask, so:
								break;
							}
							else
							{
								// The choosen option was disabled: Hacking attempt?!
								trigger_error('HACKING_ATTEMPT', E_USER_WARNING);
							}
						}
					}
					elseif ($i_mask)
					{
						// Inherit permissions of one [c_mask][v_mask]
						$v_mask = (int) $v_mask;
						list($ci_mask,$vi_mask) = explode("_", $i_mask);
						$ci_mask = (int) $ci_mask;
						$vi_mask = (int) $vi_mask;
						if (isset($auth_settings[$ci_mask][$vi_mask]))
						{
							if ($this->inherit_victims($coal, $c_mask_storage, $v_mask_storage, $c_mask, $v_mask, $ci_mask, $vi_mask))
							{
								// You are not able to inherit a later c_mask, so we can remove the p_mask from the storage,
								// and just use the same p_mask
								unset($p_mask_storage[$auth_settings[$c_mask][$v_mask]]);
								$auth_settings[$c_mask][$v_mask] = $auth_settings[$ci_mask][$vi_mask];
								$p_mask_storage[$auth_settings[$c_mask][$v_mask]]['usage'][] = array('c_mask' => $c_mask, 'v_mask' => $v_mask);
							}
							else
							{
								// The choosen option was disabled: Hacking attempt?!
								trigger_error('HACKING_ATTEMPT', E_USER_WARNING);
							}
						}
					}
				}
			}
			unset($auth_settings);

			/*
			// Need to set a defaults here: view your own personal albums
			if ($perm_system == OWN_GALLERY_PERMISSIONS)
			{
				$sql_ary['i_view'] = 1;
			}
			*/

			// Get the possible outdated p_masks
			$sql = 'SELECT perm_role_id
				FROM ' . GALLERY_PERMISSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('perm_album_id', $c_mask_storage) . '
					AND ' . $db->sql_in_set('perm_group_id', $v_mask_storage);
			$result = $db->sql_query($sql);

			$outdated_p_masks = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$outdated_p_masks[] = $row['perm_role_id'];
			}
			$db->sql_freeresult($result);

			// Delete the permissions and moderators
			$sql = 'DELETE FROM ' . GALLERY_PERMISSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('perm_album_id', $c_mask_storage) . '
					AND ' . $db->sql_in_set('perm_group_id', $v_mask_storage);
			$db->sql_query($sql);
			$sql = 'DELETE FROM ' . GALLERY_MODSCACHE_TABLE . '
				WHERE ' . $db->sql_in_set('album_id', $c_mask_storage) . '
					AND ' . $db->sql_in_set('group_id', $v_mask_storage);
			$db->sql_query($sql);

			// Check for further usage
			$sql = 'SELECT perm_role_id
				FROM ' . GALLERY_PERMISSIONS_TABLE . '
				WHERE ' . $db->sql_in_set('perm_role_id', $outdated_p_masks, false, true);
			$result = $db->sql_query($sql);

			$still_used_p_masks = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$still_used_p_masks[] = $row['perm_role_id'];
			}
			$db->sql_freeresult($result);

			// Delete the p_masks, which are no longer used
			$sql = 'DELETE FROM ' . GALLERY_ROLES_TABLE . '
				WHERE ' . $db->sql_in_set('role_id', $outdated_p_masks, false, true) . '
					AND ' . $db->sql_in_set('role_id', $still_used_p_masks, true, true);
			$db->sql_query($sql);

			// Get group_name's for the GALLERY_MODSCACHE_TABLE
			$sql = 'SELECT group_id, group_name
				FROM ' . GROUPS_TABLE . '
				WHERE ' . $db->sql_in_set('group_id', $v_mask_storage);
			$result = $db->sql_query($sql);

			$group_names = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$group_names[$row['group_id']] = $row['group_name'];
			}
			$db->sql_freeresult($result);

			$sql_permissions = $sql_moderators = array();
			foreach ($p_mask_storage as $p_set)
			{
				// Check whether the p_mask is already in the DB
				$sql_where = '';
				foreach ($p_set['p_mask'] as $p_mask => $value)
				{
					$sql_where .= (($sql_where) ? ' AND ' : '') . $p_mask . ' = ' . $value;
				}

				$role_id = 0;
				$sql = 'SELECT role_id
					FROM ' . GALLERY_ROLES_TABLE . "
					WHERE $sql_where";
				$result = $db->sql_query_limit($sql, 1);
				$role_id = (int) $db->sql_fetchfield('role_id');
				$db->sql_freeresult($result);

				if (!$role_id)
				{
					// Note: Do not collect the roles to insert, to deny doubles!
					$sql = 'INSERT INTO ' . GALLERY_ROLES_TABLE . ' ' . $db->sql_build_array('INSERT', $p_set['p_mask']);
					$db->sql_query($sql);
					$role_id = $db->sql_nextid();
				}

				foreach ($p_set['usage'] as $usage)
				{
					$sql_permissions[] = array(
						'perm_role_id'	=> $role_id,
						'perm_album_id'	=> $usage['c_mask'],
						'perm_group_id'	=> $usage['v_mask'],
					);
					if ($p_set['is_moderator'])
					{
						$sql_moderators[] = array(
							'album_id'		=> $usage['c_mask'],
							'group_id'		=> $usage['v_mask'],
							'group_name'	=> $group_names[$usage['v_mask']],
						);
					}
				}
			}
			$db->sql_multi_insert(GALLERY_PERMISSIONS_TABLE, $sql_permissions);
			$db->sql_multi_insert(GALLERY_MODSCACHE_TABLE, $sql_moderators);

			$cache->destroy('sql', GALLERY_PERMISSIONS_TABLE);
			$cache->destroy('sql', GALLERY_ROLES_TABLE);
			$cache->destroy('sql', GALLERY_MODSCACHE_TABLE);

			trigger_error('PERMISSIONS_STORED' . adm_back_link($this->u_action));
		}
		trigger_error('HACKING_ATTEMPT', E_USER_WARNING);
	}

	/**
	* Create the drop-down-options to inherit the c_masks
	* or check, whether the choosen option is valid
	*/
	function inherit_albums($cache_obtain_album_list, $allowed_albums, $album_id, $check_inherit_album = 0)
	{
		global $user;
		$disabled = false;

		$return = '';
		$return .= '<option value="0" selected="selected">' . $user->lang['NO_INHERIT'] . '</option>';
		foreach ($cache_obtain_album_list as $album)
		{
			if (in_array($album['album_id'], $allowed_albums))
			{
				// We found the requested album: return true!
				if ($check_inherit_album && ($album['album_id'] == $check_inherit_album))
				{
					return true;
				}
				if ($album['album_id'] == $album_id)
				{
					$disabled = true;
					// Could we find the requested album so far? No? Hacking attempt?!
					if ($check_inherit_album)
					{
						return false;
					}
				}
				$return .= '<option value="' . $album['album_id'] . '"';
				if ($disabled)
				{
					$return .= ' disabled="disabled" class="disabled-option"';
				}
				$return .= '>' . $album['album_name'] . '</option>';
			}
		}
		// Could we find the requested album even here?
		if ($check_inherit_album)
		{
			// Something went really wrong here!
			return false;
		}
		return $return;
	}

	/**
	* Create the drop-down-options to inherit the v_masks
	* or check, whether the choosen option is valid
	*/
	function inherit_victims($cache_obtain_album_list, $allowed_albums, $allowed_groups, $album_id, $group_id, $check_inherit_album = 0, $check_inherit_group = 0)
	{
		global $user;
		$disabled = false;
		// We submit a "wrong" array on the check (to make it more easy) so we convert it here
		if ($check_inherit_album && $check_inherit_group)
		{
			$converted_groups = array();
			foreach ($allowed_groups as $group)
			{
				$converted_groups[] = array(
					'group_id'		=> $group,
					'group_name'	=> '',
				);
			}
			$allowed_groups = $converted_groups;
			unset ($converted_groups);
		}

		$return = '';
		$return .= '<option value="0" selected="selected">' . $user->lang['NO_INHERIT'] . '</option>';
		foreach ($cache_obtain_album_list as $album)
		{
			if (in_array($album['album_id'], $allowed_albums))
			{
				$return .= '<option value="0" disabled="disabled" class="disabled-option">' . $album['album_name'] . '</option>';
				foreach ($allowed_groups as $group)
				{
					// We found the requested album: return true!
					if ($check_inherit_album && $check_inherit_group && (($album['album_id'] == $check_inherit_album) && ($group['group_id'] == $check_inherit_group)))
					{
						return true;
					}
					if (($album['album_id'] == $album_id) && ($group['group_id'] == $group_id))
					{
						$disabled = true;
						// Could we find the requested album_group so far? No? Hacking attempt?!
						if ($check_inherit_album && $check_inherit_group)
						{
							return false;
						}
					}
					$return .= '<option value="' . $album['album_id'] . '_' . $group['group_id'] . '"';
					if ($disabled)
					{
						$return .= ' disabled="disabled" class="disabled-option"';
					}
					$return .= '>&nbsp;&nbsp;&nbsp;' . $album['album_name'] . ' >>> ' . $group['group_name'] . '</option>';
				}
			}
		}
		// Could we find the requested album_group even here?
		if ($check_inherit_album && $check_inherit_group)
		{
			// Something went really wrong here!
			return false;
		}
		return $return;
	}

	function import()
	{
		global $gallery_config, $config, $db, $template, $user;
		global $gallery_root_path, $phpbb_root_path, $phpEx;

		$images = request_var('images', array(''), true);
		$images_string = request_var('images_string', '', true);
		$images = ($images_string) ? explode('&quot;', utf8_decode($images_string)) : $images;
		$submit = (isset($_POST['submit'])) ? true : ((empty($images)) ? false : true);

		$directory = $phpbb_root_path . GALLERY_IMPORT_PATH;

		if (!$submit)
		{
			$sql = 'SELECT username, user_id
				FROM ' . USERS_TABLE . "
				ORDER BY user_id ASC";
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$template->assign_block_vars('userrow', array(
					'USER_ID'				=> $row['user_id'],
					'USERNAME'				=> $row['username'],
					'SELECTED'				=> ($row['user_id'] == $user->data['user_id']) ? true : false,
				));
			}
			$db->sql_freeresult($result);

			$handle = opendir($directory);

			while ($file = readdir($handle))
			{
				if (!is_dir($directory . "$file") && (
				((substr(strtolower($file), '-4') == '.png') && $gallery_config['png_allowed']) ||
				((substr(strtolower($file), '-4') == '.gif') && $gallery_config['gif_allowed']) ||
				((substr(strtolower($file), '-4') == '.jpg') && $gallery_config['jpg_allowed'])
				))
				{
					$template->assign_block_vars('imagerow', array(
						'FILE_NAME'				=> utf8_encode($file),
					));
				}
			}
			closedir($handle);

			$template->assign_vars(array(
				'S_IMPORT_IMAGES'				=> true,
				'ACP_GALLERY_TITLE'				=> $user->lang['ACP_IMPORT_ALBUMS'],
				'ACP_GALLERY_TITLE_EXPLAIN'		=> $user->lang['ACP_IMPORT_ALBUMS_EXPLAIN'],
				'L_IMPORT_DIR_EMPTY'			=> sprintf($user->lang['IMPORT_DIR_EMPTY'], GALLERY_IMPORT_PATH),
				'S_ALBUM_IMPORT_ACTION'			=> $this->u_action,
				'S_SELECT_IMPORT' 				=> gallery_albumbox(true, 'album_id', false, false, false, 0, ALBUM_UPLOAD),
			));
		}
		else
		{
			/**
			* Commented to allow the loop
			if (!check_form_key('acp_gallery'))
			{
				trigger_error('FORM_INVALID');
			}
			*/

			$done_images_string = request_var('done_images_string', '', true);
			$done_images = explode('&quot;', utf8_decode($done_images_string));
			$album_id = request_var('album_id', 0);
			if(!$album_id)
			{
				trigger_error('IMPORT_MISSING_ALBUM');
			}
			$user_id = request_var('user_id', 0);

			$sql = 'SELECT username, user_colour
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . (int) $user_id;
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$username = $row['username'];
				$user_colour = $row['user_colour'];
			}
			$db->sql_freeresult($result);

			$results = array();
			$images_per_loop = 0;
			// This time we do:
			foreach ($images as $image)
			{
				if (($images_per_loop < 10) && !in_array($image, $done_images))
				{
					$results[] = $image;
					$images_per_loop++;
				}
			}

			$image_count = count($results);
			$counter = request_var('counter', 0);
			$image_num = request_var('image_num', 0);

			foreach ($results as $image)
			{
				$image_path = $directory . utf8_decode($image);

				$filetype = getimagesize($image_path);
				$image_width = $filetype[0];
				$image_height = $filetype[1];

				switch ($filetype['mime'])
				{
					case 'image/jpeg':
					case 'image/jpg':
					case 'image/pjpeg':
						$image_filetype = '.jpg';
						break;

					case 'image/png':
					case 'image/x-png':
						$image_filetype = '.png';
						break;

					case 'image/gif':
						$image_filetype = '.gif';
						break;

					default:
						break;
				}
				$image_filename = md5(unique_id()) . $image_filetype;

				copy($image_path, $phpbb_root_path . GALLERY_UPLOAD_PATH . $image_filename);
				@chmod($phpbb_root_path . GALLERY_UPLOAD_PATH . $image_filename, 0777);

				// The source image is imported, so we delete it.
				@unlink($image_path);

				$no_time = time();
				$time = request_var('time', 0);
				$time = ($time) ? $time : $no_time;

				$sql_ary = array(
					'image_filename' 		=> $image_filename,
					'image_thumbnail'		=> '',
					'image_desc'			=> '',
					'image_desc_uid'		=> '',
					'image_desc_bitfield'	=> '',
					'image_user_id'			=> $user_id,
					'image_username'		=> $username,
					'image_user_colour'		=> $user_colour,
					'image_user_ip'			=> $user->ip,
					'image_time'			=> $time + $counter,
					'image_album_id'		=> $album_id,
					'image_status'			=> IMAGE_APPROVED,
					'image_exif_data'		=> '',
				);
				$sql_ary['image_name'] = (request_var('filename', '') == 'filename') ? str_replace("_", " ", utf8_substr($image, 0, -4)) : str_replace('{NUM}', $image_num + $counter, request_var('image_name', '', true));
				if ($sql_ary['image_name'] == '')
				{
					$sql_ary['image_name'] = str_replace("_", " ", utf8_substr($image, 0, -4));
				}

				$db->sql_query('INSERT INTO ' . GALLERY_IMAGES_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
				$counter++;
				$done_images[] = $image;
				$done_images_string .= (($done_images_string) ? '%22' : '') . urlencode($image);
			}
			$left = count($images) - count($done_images);

			if ($counter)
			{
				$sql = 'UPDATE ' . GALLERY_USERS_TABLE . "
					SET user_images = user_images + $counter
					WHERE user_id = " . $user_id;
				$db->sql_query($sql);
				if ($db->sql_affectedrows() <= 0)
				{
					$sql_ary = array(
						'user_id'				=> $user_id,
						'user_images'			=> $counter,
					);
					$sql = 'INSERT INTO ' . GALLERY_USERS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
					$db->sql_query($sql);
				}
				set_config('num_images', $config['num_images'] + $counter, true);
			}
			update_album_info($album_id);

			$images_string			= urlencode(implode('"', $images));
			$done_images_string		= substr(urlencode(implode('"', $done_images)), 3, strlen($done_images_string));
			$images_to_do = str_replace('%22' . $done_images_string, "", '%22' . $images_string);
			if ('%22' . $images_string != $images_to_do)
			{
				$images_to_do = str_replace($done_images_string, "", $images_string);
				$images_to_do = substr($images_to_do, 3, strlen($images_to_do));
			}
			if ($images_to_do)
			{
				$imagename = request_var('image_name', '');
				$filename = request_var('filename', '');
				$forward_url = $this->u_action . "&amp;album_id=$album_id&amp;time=$time&amp;image_num=$image_num&amp;counter=$counter&amp;user_id=$user_id" . (($filename) ? '&amp;filename=' . request_var('filename', '') : '') . (($imagename && !$filename) ? '&amp;image_name=' . request_var('image_name', '') : '') . "&amp;images_string=$images_to_do";
				meta_refresh(1, $forward_url);
				trigger_error(sprintf($user->lang['IMPORT_DEBUG_MES'], $counter, $left + 1));
				
			}
			else
			{
				trigger_error(sprintf($user->lang['IMPORT_FINISHED'], $counter) . adm_back_link($this->u_action));
			}
		}
	}


	function cleanup()
	{
		global $db, $template, $user, $cache, $auth, $phpbb_root_path;

		$delete = (isset($_POST['delete'])) ? true : false;
		$submit = (isset($_POST['submit'])) ? true : false;

		$missing_sources = request_var('source', array(0));
		$missing_entries = request_var('entry', array(''), true);
		$missing_authors = request_var('author', array(0), true);
		$missing_comments = request_var('comment', array(0), true);
		$missing_personals = request_var('personal', array(0), true);
		$personals_bad = request_var('personal_bad', array(0), true);
		$s_hidden_fields = build_hidden_fields(array(
			'source'		=> $missing_sources,
			'entry'			=> $missing_entries,
			'author'		=> $missing_authors,
			'comment'		=> $missing_comments,
			'personal'		=> $missing_personals,
			'personal_bad'	=> $personals_bad,
		));

		if ($submit)
		{
			if ($missing_authors)
			{
				$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . ' 
					SET image_user_id = ' . ANONYMOUS . ",
						image_user_colour = ''
					WHERE " . $db->sql_in_set('image_id', $missing_authors);
				$db->sql_query($sql);
			}
			if ($missing_comments)
			{
				$sql = 'UPDATE ' . GALLERY_COMMENTS_TABLE . ' 
					SET comment_user_id = ' . ANONYMOUS . ",
						comment_user_colour = ''
					WHERE " . $db->sql_in_set('comment_id', $missing_comments);
				$db->sql_query($sql);
			}
			trigger_error($user->lang['CLEAN_CHANGED'] . adm_back_link($this->u_action));
		}

		if (confirm_box(true))
		{
			$message = '';
			if ($missing_sources)
			{
				$sql = 'DELETE FROM ' . GALLERY_IMAGES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $missing_sources);
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . GALLERY_COMMENTS_TABLE . ' WHERE ' . $db->sql_in_set('comment_image_id', $missing_sources);
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . GALLERY_RATES_TABLE . ' WHERE ' . $db->sql_in_set('rate_image_id', $missing_sources);
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . GALLERY_REPORTS_TABLE . ' WHERE ' . $db->sql_in_set('report_image_id', $missing_sources);
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . GALLERY_FAVORITES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $missing_sources);
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . GALLERY_WATCH_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $missing_sources);
				$db->sql_query($sql);
				$message .= $user->lang['CLEAN_SOURCES_DONE'];
			}
			if ($missing_entries)
			{
				foreach ($missing_entries as $missing_image)
				{
					@unlink($phpbb_root_path . GALLERY_UPLOAD_PATH . utf8_decode($missing_image));
				}
				$message .= $user->lang['CLEAN_ENTRIES_DONE'];
			}
			if ($missing_authors)
			{
				$deleted_images = array();
				$sql = 'SELECT image_id, image_thumbnail, image_filename
					FROM ' . GALLERY_IMAGES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $missing_authors);
				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					// Delete the files themselves
					@unlink($phpbb_root_path . GALLERY_CACHE_PATH . $row['image_thumbnail']);
					@unlink($phpbb_root_path . GALLERY_MEDIUM_PATH . $row['image_filename']);
					@unlink($phpbb_root_path . GALLERY_UPLOAD_PATH . $row['image_filename']);
					$deleted_images[] = $row['image_id'];
				}
				// we have all image_ids in $deleted_images which are deleted
				// aswell as the album_ids in $deleted_albums
				// so now drop the comments, ratings, images and albums
				if ($deleted_images)
				{
					$sql = 'DELETE FROM ' . GALLERY_COMMENTS_TABLE . ' WHERE ' . $db->sql_in_set('comment_image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_FAVORITES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_IMAGES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_RATES_TABLE . ' WHERE ' . $db->sql_in_set('rate_image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_REPORTS_TABLE . ' WHERE ' . $db->sql_in_set('report_image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_WATCH_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $deleted_images);
					$db->sql_query($sql);
				}
				$message .= $user->lang['CLEAN_AUTHORS_DONE'];
			}
			if ($missing_comments)
			{
				$sql = 'DELETE FROM ' . GALLERY_COMMENTS_TABLE . ' WHERE ' . $db->sql_in_set('comment_id', $missing_comments);
				$db->sql_query($sql);
				$message .= $user->lang['CLEAN_COMMENTS_DONE'];
			}
			if ($missing_personals || $personals_bad)
			{
				$delete_albums = array_merge($missing_personals, $personals_bad);

				$deleted_images = $deleted_albums = array(0);
				$sql = 'SELECT album_id
					FROM ' . GALLERY_ALBUMS_TABLE . '
					WHERE ' . $db->sql_in_set('album_user_id', $delete_albums);
				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					$deleted_albums[] = $row['album_id'];
				}
				$sql = 'SELECT image_id, image_thumbnail, image_filename
					FROM ' . GALLERY_IMAGES_TABLE . '
					WHERE ' . $db->sql_in_set('image_album_id', $deleted_albums);
				@$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					@unlink($phpbb_root_path . GALLERY_CACHE_PATH . $row['image_thumbnail']);
					@unlink($phpbb_root_path . GALLERY_MEDIUM_PATH . $row['image_filename']);
					@unlink($phpbb_root_path . GALLERY_UPLOAD_PATH . $row['image_filename']);
					$deleted_images[] = $row['image_id'];
				}
				$db->sql_freeresult($result);
				if ($deleted_images)
				{
					$sql = 'DELETE FROM ' . GALLERY_COMMENTS_TABLE . ' WHERE ' . $db->sql_in_set('comment_image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_FAVORITES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_IMAGES_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_RATES_TABLE . ' WHERE ' . $db->sql_in_set('rate_image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_REPORTS_TABLE . ' WHERE ' . $db->sql_in_set('report_image_id', $deleted_images);
					$db->sql_query($sql);
					$sql = 'DELETE FROM ' . GALLERY_WATCH_TABLE . ' WHERE ' . $db->sql_in_set('image_id', $deleted_images);
					$db->sql_query($sql);
				}
				$sql = 'DELETE FROM ' . GALLERY_ALBUMS_TABLE . ' WHERE ' . $db->sql_in_set('album_id', $deleted_albums);
				$db->sql_query($sql);
				$sql = 'UPDATE ' . GALLERY_USERS_TABLE . '
					SET personal_album_id = 0
					WHERE ' . $db->sql_in_set('user_id', $delete_albums);
				$db->sql_query($sql);
				if ($missing_personals)
				{
					$message .= $user->lang['CLEAN_PERSONALS_DONE'];
				}
				if ($personals_bad)
				{
					$message .= $user->lang['CLEAN_PERSONALS_BAD_DONE'];
				}
			}

			$cache->destroy('sql', GALLERY_ALBUMS_TABLE);
			$cache->destroy('sql', GALLERY_COMMENTS_TABLE);
			$cache->destroy('sql', GALLERY_FAVORITES_TABLE);
			$cache->destroy('sql', GALLERY_IMAGES_TABLE);
			$cache->destroy('sql', GALLERY_RATES_TABLE);
			$cache->destroy('sql', GALLERY_REPORTS_TABLE);
			$cache->destroy('sql', GALLERY_WATCH_TABLE);
			$cache->destroy('_albums');
			trigger_error($message . adm_back_link($this->u_action));
		}
		else if (($delete) || (isset($_POST['cancel'])))
		{
			if (isset($_POST['cancel']))
			{
				trigger_error($user->lang['CLEAN_GALLERY_ABORT'] . adm_back_link($this->u_action), E_USER_WARNING);
			}
			else
			{
				$user->lang['CLEAN_GALLERY_CONFIRM'] = $user->lang['CONFIRM_CLEAN'];
				if ($missing_sources)
				{
					$user->lang['CLEAN_GALLERY_CONFIRM'] = $user->lang['CONFIRM_CLEAN_SOURCES'] . '<br />' . $user->lang['CLEAN_GALLERY_CONFIRM'];
				}
				if ($missing_entries)
				{
					$user->lang['CLEAN_GALLERY_CONFIRM'] = $user->lang['CONFIRM_CLEAN_ENTRIES'] . '<br />' . $user->lang['CLEAN_GALLERY_CONFIRM'];
				}
				if ($missing_authors)
				{
					$user->lang['CLEAN_GALLERY_CONFIRM'] = $user->lang['CONFIRM_CLEAN_AUTHORS'] . '<br />' . $user->lang['CLEAN_GALLERY_CONFIRM'];
				}
				if ($missing_comments)
				{
					$user->lang['CLEAN_GALLERY_CONFIRM'] = $user->lang['CONFIRM_CLEAN_COMMENTS'] . '<br />' . $user->lang['CLEAN_GALLERY_CONFIRM'];
				}
				if ($personals_bad || $missing_personals)
				{
					$sql = 'SELECT album_name, album_user_id
						FROM ' . GALLERY_ALBUMS_TABLE . '
						WHERE ' . $db->sql_in_set('album_user_id', array_merge($missing_personals, $personals_bad));
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						if (in_array($row['album_user_id'], $personals_bad))
						{
							$personals_bad_names[] = $row['album_name'];
						}
						else
						{
							$missing_personals_names[] = $row['album_name'];
						}
					}
					$db->sql_freeresult($result);
				}
				if ($missing_personals)
				{
					$user->lang['CLEAN_GALLERY_CONFIRM'] = sprintf($user->lang['CONFIRM_CLEAN_PERSONALS'], implode(', ', $missing_personals_names)) . '<br />' . $user->lang['CLEAN_GALLERY_CONFIRM'];
				}
				if ($personals_bad)
				{
					$user->lang['CLEAN_GALLERY_CONFIRM'] = sprintf($user->lang['CONFIRM_CLEAN_PERSONALS_BAD'], implode(', ', $personals_bad_names)) . '<br />' . $user->lang['CLEAN_GALLERY_CONFIRM'];
				}
				confirm_box(false, 'CLEAN_GALLERY', $s_hidden_fields);
			}
		}

		$requested_source = array();
		$sql = 'SELECT gi.image_id, gi.image_name, gi.image_filemissing, gi.image_filename, gi.image_username, u.user_id
			FROM ' . GALLERY_IMAGES_TABLE . ' gi
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = gi.image_user_id';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['image_filemissing'])
			{
				$template->assign_block_vars('sourcerow', array(
					'IMAGE_ID'		=> $row['image_id'],
					'IMAGE_NAME'	=> $row['image_name'],
				));
			}
			if (!$row['user_id'])
			{
				$template->assign_block_vars('authorrow', array(
					'IMAGE_ID'		=> $row['image_id'],
					'AUTHOR_NAME'	=> $row['image_username'],
				));
			}
			$requested_source[] = $row['image_filename'];
		}
		$db->sql_freeresult($result);

		$check_mode = request_var('check_mode', '');
		if ($check_mode == 'source')
		{
			$source_missing = array();

			// Reset the status: a image might have been viewed without file but the file is back
			$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
				SET image_filemissing = 0';
			$db->sql_query($sql);

			$sql = 'SELECT image_id, image_filename, image_filemissing
				FROM ' . GALLERY_IMAGES_TABLE;
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				if (!file_exists($phpbb_root_path . GALLERY_UPLOAD_PATH . $row['image_filename']))
				{
					$source_missing[] = $row['image_id'];
				}
			}
			$db->sql_freeresult($result);
			if ($source_missing)
			{
				$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . "
					SET image_filemissing = 1
					WHERE " . $db->sql_in_set('image_id', $source_missing);
				$db->sql_query($sql);
			}
		}
		if ($check_mode == 'entry')
		{
			$directory = $phpbb_root_path . GALLERY_UPLOAD_PATH;
			$handle = opendir($directory);
			while ($file = readdir($handle))
			{
				if (!is_dir($directory . "$file") &&
				((substr(strtolower($file), '-4') == '.png') || (substr(strtolower($file), '-4') == '.gif') || (substr(strtolower($file), '-4') == '.jpg'))
				&& !in_array($file, $requested_source)
				)
				{
					$template->assign_block_vars('entryrow', array(
						'FILE_NAME'				=> utf8_encode($file),
					));
				}
			}
			closedir($handle);
		}


		$sql = 'SELECT gc.comment_id, gc.comment_image_id, gc.comment_username, u.user_id
			FROM ' . GALLERY_COMMENTS_TABLE . ' gc
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = gc.comment_user_id';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			if (!$row['user_id'])
			{
				$template->assign_block_vars('commentrow', array(
					'COMMENT_ID'	=> $row['comment_id'],
					'IMAGE_ID'		=> $row['comment_image_id'],
					'AUTHOR_NAME'	=> $row['comment_username'],
				));
			}
		}
		$db->sql_freeresult($result);

		$sql = 'SELECT ga.album_id, ga.album_user_id, ga.album_name, u.user_id, SUM(ga.album_images_real) images
			FROM ' . GALLERY_ALBUMS_TABLE . ' ga
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = ga.album_user_id
			WHERE ga.album_user_id <> 0
			GROUP BY ga.album_user_id';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			if (!$row['user_id'])
			{
				$template->assign_block_vars('personalrow', array(
					'USER_ID'		=> $row['album_user_id'],
					'ALBUM_ID'		=> $row['album_id'],
					'AUTHOR_NAME'	=> $row['album_name'],
				));
			}
			$template->assign_block_vars('personal_bad_row', array(
				'USER_ID'		=> $row['album_user_id'],
				'ALBUM_ID'		=> $row['album_id'],
				'AUTHOR_NAME'	=> $row['album_name'],
				'IMAGES'		=> $row['images'],
			));
		}
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'S_GALLERY_MANAGE_RESTS'		=> true,
			'ACP_GALLERY_TITLE'				=> $user->lang['ACP_GALLERY_CLEANUP'],
			'ACP_GALLERY_TITLE_EXPLAIN'		=> $user->lang['ACP_GALLERY_CLEANUP_EXPLAIN'],
			'CHECK_SOURCE'			=> $this->u_action . '&amp;check_mode=source',
			'CHECK_ENTRY'			=> $this->u_action . '&amp;check_mode=entry',

			'S_FOUNDER'				=> ($user->data['user_type'] == USER_FOUNDER) ? true : false,
		));
	}

}

?>