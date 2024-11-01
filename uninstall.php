<?php

	/*
	* This file cleans up the youearth installation.
	*/

	if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN'))
	{
    	exit();
	}

	global $wpdb;
		
	$table_name = $wpdb->prefix.'youearth';
	
	// Remove the table if necessary
	if ($wpdb->get_var('SHOW TABLES LIKE "'.$table_name.'"') == $table_name) 
	{	
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		
		if ($wpdb->query('DROP TABLE '.$table_name) === false)
		{
			//echo('I tried to clean up your YouEarth database table ("'.$table_name.'"), but I couldn\'t.  You may need to delete this table from your database manually.');
		}
	}
	
	// Now remove any options added to the database
	if (!delete_option('youearth_db_version'))
	{
		
	}
	
	if (!delete_option('youearth_youtube_search_string'))
	{
		
	}
	
	if (!delete_option('youearth_youtube_search_author'))
	{
		
	}
	
	if (!delete_option('youearth_youtube_search_category_tag'))
	{
		
	}
	
	if (!delete_option('youearth_google_icon'))
	{
		
	}
	
	if (!delete_option('youearth_google_earth_api_key'))
	{
		
	}
	
	if (!delete_option('youearth_youtube_search_video_id'))
	{
		
	}