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
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'gallery/includes/common.'.$phpEx);

// Start session management
$user->session_begin(false);
$auth->acl($user->data);
$user->setup('mods/gallery');

/**
* Check the request
*/
$pic_id = request_var('image_id', request_var('pic_id', 0));
if (!$pic_id)
{
	die($user->lang['NO_IMAGE_SPECIFIED']);
}


// ------------------------------------
// Get this pic info
// ------------------------------------

$sql = 'SELECT *
	FROM ' . GALLERY_IMAGES_TABLE . '
	WHERE image_id = ' . $pic_id . '
	LIMIT 1';
$result = $db->sql_query($sql);

$thispic = $db->sql_fetchrow($result);

$album_id = $thispic['image_album_id'];
$user_id = $thispic['image_user_id'];
$pic_filetype = substr($thispic['image_filename'], strlen($thispic['image_filename']) - 4, 4);
$pic_filename = $thispic['image_filename'];
$pic_thumbnail = $thispic['image_thumbnail'];


if (empty($thispic) or !file_exists(ALBUM_UPLOAD_PATH . $pic_filename))
{
	die($user->lang['IMAGE_NOT_EXIST']);
}


// ------------------------------------
// Get the current Category Info
// ------------------------------------
$sql = 'SELECT *
	FROM ' . GALLERY_ALBUMS_TABLE . '
	WHERE album_id = ' . $album_id;
$result = $db->sql_query($sql);
$album_data = $db->sql_fetchrow($result);

if (empty($album_data))
{
	die($user->lang['ALBUM_NOT_EXIST']);
}


// ------------------------------------
// Check the permissions
// ------------------------------------
$album_user_access = (!$album_data['album_user_id']) ? album_user_access($album_id, $album_data, 1, 0, 0, 0, 0, 0) : personal_album_access($album_data['album_user_id']);// VIEW
if (!$album_user_access['view'])
{
	die($user->lang['NOT_AUTHORISED']);
}


// ------------------------------------
// Check Pic Approval
// ------------------------------------

if ($user->data['user_type'] <> USER_FOUNDER)
{
	if (($album_data['album_approval'] == ADMIN) || (($album_data['album_approval'] == MOD) && !$album_user_access['moderator']))
	{
		if (!$thispic['image_approval'])
		{
			die($user->lang['NOT_AUTHORISED']);
		}
	}
}


// ------------------------------------
// Check hotlink
// ------------------------------------

if ($album_config['hotlink_prevent'] && isset($HTTP_SERVER_VARS['HTTP_REFERER']))
{
	$check_referer = explode('?', $HTTP_SERVER_VARS['HTTP_REFERER']);
	$check_referer = trim($check_referer[0]);
	$good_referers = array();

	if ($album_config['hotlink_allowed'] <> '')
	{
		$good_referers = explode(',', $album_config['hotlink_allowed']);
	}

	$good_referers[] = $config['server_name'] . $config['script_path'];
	$errored = TRUE;

	for ($i = 0; $i < count($good_referers); $i++)
	{
		$good_referers[$i] = trim($good_referers[$i]);
		if ((strstr($check_referer, $good_referers[$i])) && ($good_referers[$i] <> ''))
		{
			$errored = FALSE;
		}
	}

	if ($errored)
	{
		die($user->lang['NOT_AUTHORISED']);
	}
}


/**
* Main work here...
*/


// ------------------------------------
// Send Thumbnail to browser
// ------------------------------------

	// --------------------------------
	// Check thumbnail cache. If cache is available we will SEND & EXIT
	// --------------------------------

	if (($album_config['thumbnail_cache']) && ($pic_thumbnail <> '') && file_exists(ALBUM_CACHE_PATH . $pic_thumbnail))
	{
		switch ($pic_filetype)
		{
			case '.gif':
			case '.jpg':
				header('Content-type: image/jpeg');
			break;
			
			case '.png':
				header('Content-type: image/png');
			break;
		}

		readfile(ALBUM_CACHE_PATH . $pic_thumbnail);
		exit;
	}

	// --------------------------------
	// Hmm, cache is empty. Try to re-generate!
	// --------------------------------

	$pic_size = getimagesize(ALBUM_UPLOAD_PATH . $pic_filename);
	$pic_width = $pic_size[0];
	$pic_height = $pic_size[1];
	$gd_errored = FALSE;
	switch ($pic_filetype)
	{
		case '.gif':
			$read_function = 'imagecreatefromgif';
			$pic_filetype = '.jpg';
		break;

		case '.jpg':
			$read_function = 'imagecreatefromjpeg';
		break;

		case '.png':
			$read_function = 'imagecreatefrompng';
		break;
	}
	$src = @$read_function(ALBUM_UPLOAD_PATH  . $pic_filename);

if (!$src)
{
	$gd_errored = TRUE;
	$pic_thumbnail = '';
}
else if (($pic_width > $album_config['thumbnail_size']) or ($pic_height > $album_config['thumbnail_size']))
{
	// ----------------------------
	// Resize it
	// ----------------------------

	if ($pic_width > $pic_height)
	{
		$thumbnail_width = $album_config['thumbnail_size'];
		$thumbnail_height = $album_config['thumbnail_size'] * ($pic_height/$pic_width);
	}
	else
	{
		$thumbnail_height = $album_config['thumbnail_size'];
		$thumbnail_width = $album_config['thumbnail_size'] * ($pic_width/$pic_height);
	}

	// Create thumbnail + 16 Pixel extra for imagesize text 
	if ($album_config['thumbnail_info_line'])
	{// Create image details credits to Dr.Death
		$thumbnail = ($album_config['gd_version'] == 1) ? @imagecreate($thumbnail_width, $thumbnail_height + 16) : @imagecreatetruecolor($thumbnail_width, $thumbnail_height + 16); 
	}
	else
	{
		$thumbnail = ($album_config['gd_version'] == 1) ? @imagecreate($thumbnail_width, $thumbnail_height) : @imagecreatetruecolor($thumbnail_width, $thumbnail_height);
	}
	$resize_function = ($album_config['gd_version'] == 1) ? 'imagecopyresized' : 'imagecopyresampled';
	@$resize_function($thumbnail, $src, 0, 0, 0, 0, $thumbnail_width, $thumbnail_height, $pic_width, $pic_height);

	if ($album_config['thumbnail_info_line'])
	{// Create image details credits to Dr.Death
		$dimension_font = 1;
		$dimension_filesize = filesize($phpbb_root_path . GALLERY_UPLOAD_PATH . $thispic['image_filename']);
		$dimension_string = $pic_width . "x" . $pic_height . "(" . intval($dimension_filesize/1024) . "KB)";
		$dimension_colour = ImageColorAllocate($thumbnail,255,255,255);
		$dimension_height = imagefontheight($dimension_font);
		$dimension_width = imagefontwidth($dimension_font) * strlen($dimension_string);
		$dimension_x = ($thumbnail_width - $dimension_width) / 2;
		$dimension_y = $thumbnail_height + ((16 - $dimension_height) / 2);
		imagestring($thumbnail, 1, $dimension_x, $dimension_y, $dimension_string, $dimension_colour);
	}
}
else
{
	$thumbnail = $src;
}

if (!$gd_errored)
{
	if ($album_config['thumbnail_cache'])
	{// Re-generate successfully. Write it to disk!

		$pic_thumbnail = $pic_filename;

		switch ($pic_filetype)
		{
			case '.jpg':
				@imagejpeg($thumbnail, ALBUM_CACHE_PATH . $pic_thumbnail, $album_config['thumbnail_quality']);
				break;
			case '.png':
				@imagepng($thumbnail, ALBUM_CACHE_PATH . $pic_thumbnail);
			break;
		}

		@chmod(ALBUM_CACHE_PATH . $pic_thumbnail, 0777);
	}


	/*
	* After write to disk, don't forget to send to browser also
	*/

	switch ($pic_filetype)
	{
		case '.jpg':
			@imagejpeg($thumbnail, '', $album_config['thumbnail_quality']);
		break;
		case '.png':
			@imagepng($thumbnail);
		break;
	}
	exit;
}
else
{
	/*
	* It seems you have not GD installed :(
	*/
	header('Content-type: image/jpeg');
	readfile('images/nothumbnail.jpg');
	exit;
}
?>