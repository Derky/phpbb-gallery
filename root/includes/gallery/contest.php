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

class phpbb_gallery_contest
{
	const NUM_IMAGES = 3;

	/**
	* There are different modes to calculate who won the contest.
	* This value should be one of the constant-names below.
	*/
	private $mode = self::MODE_AVERAGE;

	/**
	* The image with the highest average wins.
	*/
	const MODE_AVERAGE = 1;
	/**
	* The image with the highest number of total points wins.
	*/
	const MODE_SUM = 2;

	static private function get_tabulation()
	{
		switch (self::$mode)
		{
			case self::MODE_AVERAGE:
				return 'image_rate_avg DESC, image_rate_points DESC, image_id ASC';
			case self::MODE_SUM:
				return 'image_rate_points DESC, image_rate_avg DESC, image_id ASC';
		}
	}

	static public function end($album_id, $contest_id, $end_time)
	{
		global $db;

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest = ' . phpbb_gallery_image::NO_CONTEST . '
			WHERE image_album_id = ' . $album_id;
		$db->sql_query($sql);

		$sql = 'SELECT image_id
			FROM ' . GALLERY_IMAGES_TABLE . '
			WHERE image_album_id = ' . $album_id . '
			ORDER BY ' . self::get_tabulation();
		$result = $db->sql_query_limit($sql, self::NUM_IMAGES);
		$first = (int) $db->sql_fetchfield('image_id');
		$second = (int) $db->sql_fetchfield('image_id');
		$third = (int) $db->sql_fetchfield('image_id');
		$db->sql_freeresult($result);

		$sql = 'UPDATE ' . GALLERY_CONTESTS_TABLE . '
			SET contest_marked = ' . phpbb_gallery_image::NO_CONTEST . ",
				contest_first = $first,
				contest_second = $second,
				contest_third = $third
			WHERE contest_id = " . (int) $contest_id;
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest_end = ' . (int) $end_time . ',
				image_contest_rank = 1
			WHERE image_id = ' . $first;
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest_end = ' . (int) $end_time . ',
				image_contest_rank = 2
			WHERE image_id = ' . $second;
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest_end = ' . (int) $end_time . ',
				image_contest_rank = 3
			WHERE image_id = ' . $third;
		$db->sql_query($sql);

		phpbb_gallery_config::inc('contests_ended', 1);
	}

	static public function resync_albums($album_ids)
	{
		if (is_array($album_ids))
		{
			$album_ids = array_map('intval', $album_ids);
			foreach ($album_ids as $album_id)
			{
				self::resync($album_id);
			}
		}
		else
		{
			self::resync((int) $album_ids);
		}
	}

	static public function resync($album_id)
	{
		global $db;

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest = ' . phpbb_gallery_image::NO_CONTEST . '
			WHERE image_album_id = ' . $album_id;
		$db->sql_query($sql);

		$sql = 'SELECT image_id
			FROM ' . GALLERY_IMAGES_TABLE . '
			WHERE image_album_id = ' . $album_id . '
			ORDER BY ' . self::get_tabulation();
		$result = $db->sql_query_limit($sql, self::NUM_IMAGES);
		$first = (int) $db->sql_fetchfield('image_id');
		$second = (int) $db->sql_fetchfield('image_id');
		$third = (int) $db->sql_fetchfield('image_id');
		$db->sql_freeresult($result);

		$sql = 'UPDATE ' . GALLERY_CONTESTS_TABLE . "
			SET contest_first = $first,
				contest_second = $second,
				contest_third = $third
			WHERE contest_album_id = " . (int) $album_id;
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest_rank = 1
			WHERE image_id = ' . $first;
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest_rank = 2
			WHERE image_id = ' . $second;
		$db->sql_query($sql);

		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_contest_rank = 3
			WHERE image_id = ' . $third;
		$db->sql_query($sql);
	}
}
