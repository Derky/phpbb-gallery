<?php

/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2007 phpBB Gallery
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$album_root_path = $phpbb_root_path . 'gallery/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/gallery');


//
// Get general album information
//
include($album_root_path . 'includes/common.'.$phpEx);

$config['topics_per_page'] = 15;

$start = request_var('start', 0);

$mode = request_var('mode', 'joined');

$sort_order = request_var('order', 'ASC');

//
// Memberlist sorting
//
$mode_types_text = array($user->lang['SORT_JOINED'], $user->lang['SORT_USERNAME'], $user->lang['IMAGES'], $user->lang['LAST_IMAGE']);
$mode_types = array('joindate', 'username', 'pics', 'last_pic');

$select_sort_mode = '<select name="mode">';
for($i = 0; $i < count($mode_types_text); $i++)
{
	$selected = ( $mode == $mode_types[$i] ) ? ' selected="selected"' : '';
	$select_sort_mode .= '<option value="' . $mode_types[$i] . '"' . $selected . '>' . $mode_types_text[$i] . '</option>';
}
$select_sort_mode .= '</select>';

$select_sort_order = '<select name="order">';
if($sort_order == 'ASC')
{
	$select_sort_order .= '<option value="ASC" selected="selected">' . $user->lang['SORT_ASCENDING'] . '</option><option value="DESC">' . $user->lang['SORT_DESCENDING'] . '</option>';
}
else
{
	$select_sort_order .= '<option value="ASC">' . $user->lang['SORT_ASCENDING'] . '</option><option value="DESC" selected="selected">' . $user->lang['SORT_DESCENDING'] . '</option>';
}
$select_sort_order .= '</select>';

$template->assign_vars(array(
	'L_SELECT_SORT_METHOD' => $user->lang['SELECT_SORT_METHOD'],
	'L_ORDER' => $user->lang['ORDER'],
	'L_SORT' => $user->lang['SORT'],
  	'L_LAST_PIC_DATE' => $user->lang['LAST_IMAGE'],	
	'L_JOINED' => $user->lang['JOINED'],
	'L_PICS' => $user->lang['IMAGES'],
	'L_USERS_PERSONAL_GALLERIES' => $user->lang['USERS_PERSONAL_ALBUMS'],
	'S_MODE_SELECT' => $select_sort_mode,
	'S_ORDER_SELECT' => $select_sort_order,
	'S_MODE_ACTION' => append_sid("album_personal_index.$phpEx")
	)
);


switch( $mode )
{
	case 'joined':
		$order_by = "user_regdate ASC LIMIT $start, " . $config['topics_per_page'];
		break;
	case 'username':
		$order_by = "username $sort_order LIMIT $start, " . $config['topics_per_page'];
		break;
	case 'pics':
		$order_by = "pics $sort_order LIMIT $start, " . $config['topics_per_page'];
		break;
	case 'last_pic':
		$order_by = "last_pic $sort_order LIMIT $start, " . $config['topics_per_page'];
		break;
	default:
		$order_by = "user_regdate $sort_order LIMIT $start, " . $config['topics_per_page'];
		break;
}

$sql = "SELECT u.username, u.user_id, u.user_regdate, MAX(p.pic_id) as pic_id, p.pic_title, p.pic_user_id, COUNT(p.pic_id) AS pics, MAX(p.pic_time) as pic_time 
		FROM ". USERS_TABLE ." AS u, ". ALBUM_TABLE ." as p 
		WHERE u.user_id <> ". ANONYMOUS ." 
		AND u.user_id = p.pic_user_id 
		AND p.pic_cat_id = ". PERSONAL_GALLERY ." 
		GROUP BY user_id 
		ORDER BY $order_by"; 

$result = $db->sql_query($sql);

$memberrow = array(); 

while( $row = $db->sql_fetchrow($result) ) 
{ 
	$memberrow[] = $row; 
} 


for ($i = 0; $i < count($memberrow); $i++) 
{ 
	$pic_number = $memberrow[$i]['pics'];
	
	$pic_id = $memberrow[$i]['pic_id'];
	$sql = "SELECT * 
		FROM ". ALBUM_TABLE ." 
		WHERE pic_id = '$pic_id'"; 
	$result = $db->sql_query($sql);
	
	$thispic = $db->sql_fetchrow($result); 
	
	$pic_title = $thispic['pic_title']; 

/*
	$last_pic_info = '';

		$last_pic_info .= '<dfn>' . $user->lang['LAST_IMAGE'] . '</dfn> ';
		
		if( !isset($album_config['last_pic_title_length']) )
		{
			$album_config['last_pic_title_length'] = 25;
		}

		if (strlen($lastrow['pic_title']) > $album_config['last_pic_title_length'])
		{
			$lastrow['pic_title'] = substr($lastrow['pic_title'], 0, $album_config['last_pic_title_length']) . '...';
		}

		$last_pic_info .= '<a href="' . append_sid("./image_page.$phpEx?id=". $lastrow['pic_id']) .'">';
		$last_pic_info .= $lastrow['pic_title'] .'</a> ' . $user->lang['POST_BY_AUTHOR'] . ' ';

		// ----------------------------
		// Write username of last poster
		// ----------------------------

		if( ($lastrow['user_id'] == ALBUM_GUEST) or ($lastrow['username'] == '') )
		{
			$last_pic_info .= ($lastrow['pic_username'] == '') ? $user->lang['GUEST'] : $lastrow['pic_username'];
		}
		else
		{
			$last_pic_info .= '<a href="'. append_sid("../memberlist.$phpEx?mode=viewprofile&amp;u=" . $lastrow['user_id']) .'" class="username-coloured">'. $lastrow['username'] .'</a> ';
		}
//		$last_pic_info .= '<a href="'. append_sid("../memberlist.$phpEx?mode=viewprofile&amp;u=". $lastrow['user_id']) .'" style="color: #' . $user->data['user_colour'] . ';" class="username-coloured">'. $lastrow['username'] .'</a> ';
		$last_pic_info .= '<a href="' . append_sid("./image_page.$phpEx?id=". $lastrow['pic_id']) .'"><img src="../styles/prosilver/imageset/icon_topic_latest.gif" width="11" height="9" alt="' . $user->lang['VIEW_THE_LATEST_IMAGE'] . '" title="' . $user->lang['VIEW_THE_LATEST_IMAGE'] . '" /></a><br />';
		$last_pic_info .= $user->lang['POSTED_ON_DATE'] . ' ' . $user->format_date($lastrow['pic_time']);
*/

	if(!isset($album_config['last_pic_title_length'])) 
	{
		$album_config['last_pic_title_length'] = 25; 
	}
	$pic_title_full = $pic_title; 
	if (strlen($pic_title) > $album_config['last_pic_title_length']) 
	{
		$pic_title = substr($pic_title, 0, $album_config['last_pic_title_length']) . '...'; 
	}
	$last_pic_info = $user->lang['IMAGE_TITLE'] . ': <a href="'; 
	$last_pic_info .= ($album_config['fullpic_popup']) ? append_sid("image_page.$phpEx?pic_id=". $pic_id) .'" title="' . $pic_title_full . '">' : append_sid("image_page.$phpEx?pic_id=". $pic_id) .'" title="' . $pic_title_full . '">'; 
	$last_pic_info .= $pic_title . '</a><br />' . $user->lang['POSTED_ON_DATE'] . ' ' . $user->format_date($memberrow[$i]['pic_time']);

	$template->assign_block_vars('memberrow', array(
		'ROW_CLASS' => ( !($i % 2) ) ? 'bg1' : 'bg2',
		'USERNAME' => $memberrow[$i]['username'],
		'U_VIEWGALLERY' => append_sid("album_personal.$phpEx?user_id=". $memberrow[$i]['user_id']),
		'JOINED' => $user->format_date($memberrow[$i]['user_regdate']),
		'LAST_PIC' => $last_pic_info,
		'PICS' => $pic_number,
	));
}

$sql = "SELECT COUNT(DISTINCT u.user_id) AS total
		FROM ". USERS_TABLE ." AS u, ". ALBUM_TABLE ." AS p
		WHERE u.user_id <> ". ANONYMOUS ."
			AND u.user_id = p.pic_user_id
			AND p.pic_cat_id = ". PERSONAL_GALLERY;

$result = $db->sql_query($sql);

if ($total = $db->sql_fetchrow($result))
{
	$total_galleries = $total['total'];

	$pagination = generate_pagination("album_personal_index.$phpEx?mode=$mode&amp;order=$sort_order", $total_galleries, $config['topics_per_page'], $start);
}

$template->assign_vars(array(
	'PAGINATION' => $pagination,
	'PAGE_NUMBER' => sprintf($user->lang['PAGE_OF'], ( floor( $start / $config['topics_per_page'] ) + 1 ), ceil( $total_galleries / $config['topics_per_page'] ))
	)
);

$template->assign_block_vars('navlinks', array(
	'FORUM_NAME'	=> $user->lang['GALLERY'],
	'U_VIEW_FORUM'	=> append_sid("{$album_root_path}index.$phpEx"))
);

$template->assign_block_vars('navlinks', array(
	'FORUM_NAME'	=> $user->lang['PERSONAL_ALBUMS'],
	'U_VIEW_FORUM'	=> append_sid("{$album_root_path}album_personal_index.$phpEx"))
);

// Output page
$page_title = $user->lang['GALLERY'];

page_header($page_title);

$template->set_filenames(array(
	'body' => 'gallery_personal_index_body.html')
);

page_footer();

?>