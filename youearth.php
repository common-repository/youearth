<?php
	/*
	Plugin Name: YouEarth
	Plugin URI: 
	Description: YouEarth is a plugin what allows you to search for youtube videos with GEOlocation information, and plot them on Google Earth
	Version: 0.1
	Author: Dan Kennedy (Delete London)
	Author URI: http://www.deletelondon.com
	License: GPL2
	
	
	Copyright 2010  Dan Kennedy (Delete London)  (email : dan@deletelondon.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	*/

	global $wpdb;
	global $table_name;
	$table_name = $wpdb->prefix.'youearth';

	require_once( ABSPATH . WPINC . '/pluggable.php' );
	
	// Check if the user is submitting the form, and check the nonce
	if ((isset($_POST)) and (!empty($_POST)) and (($_POST['formAction'] == 'saveSettings') or ($_POST['formAction'] == 'findImportVideos')))
	{
		// Don't assume the user is logged in
		if ((!is_user_logged_in()) or (!current_user_can('manage_options')))
		{
			wp_die(__('You don\'t have the correct rights to alter the YouEarth settings.', 'youEarth'));
		}
	
		if ($_POST['formAction'] == 'saveSettings')
		{
			// Validate the user request
			$nonce = $_REQUEST['_wpnonce'];
			check_admin_referer('youearth_updatesettings');
			
			// Assume the user is allowed here if they got here
			// Check the property exists
			if (((get_option('youearth_google_earth_api_key', 'empty')) !== false) and (isset($_POST['youearth_google_earth_api_key'])) and (trim($_POST['youearth_google_earth_api_key']) != ''))
			{
				update_option('youearth_google_earth_api_key', $wpdb->prepare($_POST['youearth_google_earth_api_key']));
			}
			
			if (((get_option('youearth_google_icon', 'empty')) !== false) and (isset($_POST['youearth_google_icon'])) and (trim($_POST['youearth_google_icon']) != ''))
			{
				update_option('youearth_google_icon', $wpdb->prepare($_POST['youearth_google_icon']));
			}		
		}
		else
		if ($_POST['formAction'] == 'findImportVideos')
		{
			// Validate the user request
			$nonce = $_REQUEST['_wpnonce'];
			check_admin_referer('youearth_findimportvideos');
		
			// Assume the user is allowed here if they got here
			// Check the property exists
			if (isset($_POST['youearth_youtube_search_string']))
			{
				update_option('youearth_youtube_search_string', $wpdb->prepare($_POST['youearth_youtube_search_string']));
			}
			
			if (isset($_POST['youearth_youtube_search_author']))
			{
				update_option('youearth_youtube_search_author', $wpdb->prepare($_POST['youearth_youtube_search_author']));
			}
			
			if (isset($_POST['youearth_youtube_search_category_tag']))
			{
				update_option('youearth_youtube_search_category_tag', $wpdb->prepare($_POST['youearth_youtube_search_category_tag']));
			}
			
			if (isset($_POST['youearth_youtube_search_video_id']))
			{
				update_option('youearth_youtube_search_video_id', $wpdb->prepare($_POST['youearth_youtube_search_video_id']));
			}
			
			
			// Build the search string for YouTube
			$searchOption = 'q='.urlencode(cleanSearchString(get_option('youearth_youtube_search_string')));
			
			$categoryTag = get_option('youearth_youtube_search_category_tag');
			if (trim($categoryTag) != '')
			{
				$searchOption .= '&category='.urlencode(cleanSearchString($categoryTag));
			}
			
			$author = get_option('youearth_youtube_search_author');
			if (trim($author) != '')
			{
				$searchOption .= '&author='.urlencode(cleanSearchString($author));
			}
			
			// The last part of this search uses YouTubes experimental partial responses from here:
			// http://code.google.com/apis/youtube/2.0/developers_guide_protocol_partial.html#Fields_Formatting_Rules
			// It tells the search system to return those videos that have location data OR coordinates.  Either
			//  is enough to place it on the map
			$searchOption .= '&max-results=50&v=2&fields[yt:location%20or%20georss:where/gml:Point/gml:pos]';
			
			$videoId = get_option('youearth_youtube_search_video_id');
			
			if ((isset($videoId)) and (trim($videoId) != ''))
			{
				$feed = file_get_contents('http://gdata.youtube.com/feeds/api/videos/'.$videoId);
			}
			else
			{
				$feed = file_get_contents('http://gdata.youtube.com/feeds/api/videos?'.$searchOption);
			}
			
			if ($feed)
			{
				$xml = new SimpleXMLElement($feed);
				
				// Loop through all the XML nodes and check which ones are entries.  They're the videos
				// If the user hasn't confirmed they want to import, then just count them and ask for confirmation
				if ((isset($_POST['submit'])) and ($_POST['submit'] == 'Import videos'))
				{
					// If the top level of XML is an entry, we're only getting one video
					if (strcasecmp($xml->getName(), 'entry') == 0)
					{
						youearth_addEditVideo(youearth_parseXMLYoutubeVideo($xml), $videoId);
					}
					else
					foreach($xml->children() as $entry)
					{
						if (strcasecmp($entry->getName(), 'entry') == 0)
						{
							// Pass them on to be parsed and added
							youearth_addEditVideo(youearth_parseXMLYoutubeVideo($entry));
						}
					}
				}
				else
				{
					global $iVideoCounter;
					$iVideoCounter = 0;
				
					// If the top level of XML is an entry, we're only getting one video
					if (strcasecmp($xml->getName(), 'entry') == 0)
					{
						if (youearth_parseXMLYoutubeVideo($xml))
						{
							$iVideoCounter++;
						}
					}
					else
					{
						foreach($xml->children() as $entry)
						{
							if (strcasecmp($entry->getName(), 'entry') == 0)
							{
								if (youearth_parseXMLYoutubeVideo($entry) !== false)
								{
									$iVideoCounter++;
								}
							}
						}
					}					
				}
			}
			else
			{
				global $errorString;
				$errorString = 'There was a problem with your search (YouTube didn\'t like something). Please adjust your search fields.';
			}
		}
	}
	
	// Check if the user is toggling the visibilty of a video on the map
	if ((isset($_GET['action'])) and ($_GET['action'] == 'toggle_visibility') and (isset($_GET['video_id'])))
	{
		// Don't assume the user is logged in
		if ((!is_user_logged_in()) or (!current_user_can('manage_options')))
		{
			wp_die(__('You don\'t have the correct rights to alter the YouEarth settings.', 'youEarth'));
		}
	
		// Validate the user request
		check_admin_referer('youearth_togglevisibility');
	
		// Load the video
		$video = youearth_loadVideo($_GET['video_id']);
		
		if ($video)
		{
			if ($video->showOnMap == 1)
			{
				$updateArray = array('showOnMap' => 0);
			}
			else
			{
				$updateArray = array('showOnMap' => 1);
			}
			
			$updateArray['id'] = $_GET['video_id'];
			$updateArray['youtubeId'] = $video->youtubeId;
			
			// Update the video
			youearth_addEditVideo($updateArray);
			
			// If we toggled it, redirect so we clear the $_GET parameters
			header('location: ?page=youearth_settings');
		}
	}


	/**
	* Takes a string to be passed to the YouTube search system and returns it, cleaned up
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	*
	* @param string $stringToClean
	* @return string
	*
	*/
	function cleanSearchString($stringToClean)
	{
		$returnString = $stringToClean;
		$stringsToSearchFor = array(',', '-', '|');
		
		foreach($stringsToSearchFor as $character)
		{
			$returnString = str_replace('  '.$character, $character, $returnString);
			$returnString = str_replace($character.'  ', $character, $returnString);
			$returnString = str_replace(' '.$character, $character, $returnString);
			$returnString = str_replace($character.' ', $character, $returnString);
		}
		
		return $returnString;
	}
	
	
	/**
	* Creates the database table required to store all the videos and their locations
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	*
	*/
	function youearth_install()
	{
  		global $table_name;
		global $wpdb;
		
		if ($wpdb->get_var('show tables like "'.$table_name.'"') != $table_name) 
		{	
			$sql = 'CREATE TABLE '.$table_name.' (
				`id` INT NOT NULL AUTO_INCREMENT,
				`publishedDate` DATETIME,
				`author` VARCHAR(255),
				`xcoordinate` decimal(20,15) DEFAULT NULL,
  				`ycoordinate` decimal(20,15) DEFAULT NULL,
				`URL` VARCHAR(255),
				`thumbnail` VARCHAR(255),
				`youtubeId` VARCHAR(255),
				`videoTitle` VARCHAR(255),
				`showOnMap` TINYINT(4) DEFAULT 1,
				`favouriteCount` INT,
				`viewCount` INT,
				UNIQUE KEY id (id));';
				
			require_once(ABSPATH.'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		
		add_option('youearth_db_version', '0.1');
		add_option('youearth_youtube_search_string', 'Eric Whitacre');
		add_option('youearth_youtube_search_category_tag', '');
		add_option('youearth_youtube_search_author', '');
		add_option('youearth_google_earth_api_key', '');
		add_option('youearth_google_icon', '');
		add_option('youearth_youtube_search_video_id', '');
	}


	// Install the database table when the plugin is activated
	register_activation_hook(__FILE__, 'youearth_install');


	function youearth_menu()
	{
		global $wpdb;
		include 'youearth-admin.php';
	}
	 
	function youearth_admin_actions()
	{
		add_options_page('YouEarth', 'YouEarth', 'manage_options', 'youearth_settings', 'youearth_menu');
	}
	 
	add_action('admin_menu', 'youearth_admin_actions');
	
	
	/**
	* Function to print out the import video form
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	*
	*/
	function youearth_importForm() 
	{
		echo('<form method="post" action="">'); 
		echo('<input type="hidden" name="formAction" value="findImportVideos" />');
		
		// Add request validation
		wp_nonce_field('youearth_findimportvideos');
		
		echo('<table class="form-table">
			<tr valign="top">
				<th scope="row">'.__('Youtube search title/caption', 'youEarth').':</th>
				<td><input type="text" name="youearth_youtube_search_string" value="'.htmlentities(stripslashes(get_option('youearth_youtube_search_string'))).'" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">'.__('YouTube search author', 'youEarth').':</th>
				<td><input type="text" name="youearth_youtube_search_author" value="'.htmlentities(stripslashes(get_option('youearth_youtube_search_author'))).'" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">'.__('Youtube search category/tag', 'youEarth').':</th>
				<td><input type="text" name="youearth_youtube_search_category_tag" value="'.htmlentities(stripslashes(get_option('youearth_youtube_search_category_tag'))).'" />
				<label><br />Search strings as per YouTube\'s <a href="http://code.google.com/apis/youtube/2.0/reference.html#Searching_for_videos" target="_blank">search API</a></label></td>
			</tr>
			<tr valign="top">
				<th scope="row">'.__('YouTube video ID', 'youEarth').':</th>
				<td><input type="text" name="youearth_youtube_search_video_id" value="'.htmlentities(stripslashes(get_option('youearth_youtube_search_video_id'))).'" />
				<label><br />Or you can enter the video ID, which is the characters at the end<br />of the video URL: http://www.youtube.com/watch?v=<strong>Fe751kMBwms</strong><br />
				If entered, this will override any search criteria above.</label></td>
			</tr>
		</table>');
		
		global $errorString;
		
		if ((isset($errorString)) and (trim($errorString) != ''))
		{
			echo('<p>'.$errorString.'</p>');
		}
		
		echo('<p class="submit"><input type="submit" class="button-primary" value="'.__('Find videos', 'youEarth').'" /></p>');
		
		global $iVideoCounter;
		
		
		// If we've set some text to show here, show it
		if (isset($iVideoCounter))
		{
			if ($iVideoCounter == 1)
			{
				echo('<p class="submit">'.
					__('One video found', 'youEarth').'. '.__('Would you like to import it', 'youEarth').'</a>?
					<input name="submit" type="submit" class="button-primary" value="'.__('Import videos', 'youEarth').'" /></p>');
			}
			else
			if ($iVideoCounter >= 50)
			{
				echo('<p class="submit"><em>'.__('We found ', 'youEarth').$iVideoCounter.__(' videos.  YouTube won\'t return more than that, so you might like to refine your search to make sure you aren\'t missing any', 'youEarth').'.</em><br />'.$iVideoCounter.' '.__('videos found', 'youEarth').'. '.__('Would you like to import them', 'youEarth').'</a>?
					<br /><input name="submit" type="submit" class="button-primary" value="'.__('Import videos', 'youEarth').'" /></p>');
			}
			else
			if ($iVideoCounter > 1)
			{
				echo('<p class="submit">'.$iVideoCounter.' '.__('videos found', 'youEarth').'. '.__('Would you like to import them', 'youEarth').'</a>?
					<input name="submit" type="submit" class="button-primary" value="'.__('Import videos', 'youEarth').'" /></p>');
			}
			else
			{
				_e('No videos found using that search string');
			}
		}
		
		echo('</form>');
	} 
	
	/**
	* Function to print out the settings form
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	*
	*/
	function youearth_settingsForm() 
	{
		echo('<form method="post" action="">');
		 
		 	echo('<input type="hidden" name="formAction" value="saveSettings" />');
		 
			// Add request validation
			wp_nonce_field('youearth_updatesettings');
			
			echo('
			<table class="form-table">
				<tr valign="top">
					<th scope="row">'.__('Google Earth API key', 'youEarth').':</th>
					<td><input type="text" name="youearth_google_earth_api_key" value="'.urldecode(get_option('youearth_google_earth_api_key')).'" />
					<label><br />'.__('This is required for YouEarth to work. <a href="http://code.google.com/apis/maps/signup.html" target="_blank">Get your API key here</a>', 'youEarth').'</label></td>
				</tr>
			</table>
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row">'.__('Map icon', 'youEarth').':</th>
					<td><input type="text" name="youearth_google_icon" value="'.urldecode(get_option('youearth_google_icon')).'" />
					<label><br />'.__('Give an absolute URL of the image to use as an icon.  A PNG works best', 'youEarth').'</label></td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" class="button-primary" value="'.__('Save settings', 'youEarth').'" />
			</p>
			
		</form>');
	} 
	
	/**
	* Loads all the Youtube videos stored in the database
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	*
	*/
	function youearth_loadAllVideos() 
	{
		global $table_name;
		global $wpdb;
	
		$videos = $wpdb->get_results('SELECT * FROM '.$table_name.' ORDER BY `showOnMap` DESC, `publishedDate` DESC');
		
		return $videos;
	}
	
	
	/**
	* Loads a single Youtube video stored in the database
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	* @param int $videoId ID of the video we want to load
	*
	*/
	function youearth_loadVideo($videoId) 
	{
		global $table_name;
		global $wpdb;
	
		$video = $wpdb->get_row('SELECT * FROM '.$table_name.' WHERE `id` = '.(int)$videoId);
		
		return $video;
	}
	
	
	/**
	* Takes an XMLreader object representing a youtube video and parses it
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	* @return array
	*
	*/
	function youearth_parseXMLYoutubeVideo($videoSimpleXML) 
	{
		$video = array();
	
		
		// Get the normal values (outside namespaces)
		foreach($videoSimpleXML->children() as $videoSubNode)
		{
			if (strcasecmp($videoSubNode->getName(), 'published') == 0)
			{
				$video['publishedDate'] = (string)$videoSubNode;
			}
			else
			if (strcasecmp($videoSubNode->getName(), 'author') == 0)
			{
				$video['author'] = (string)$videoSubNode->name;
			}
			else
			if (strcasecmp($videoSubNode->getName(), 'title') == 0)
			{
				$video['videoTitle'] = (string)$videoSubNode;
			}
		}
		
		// Get the values for media
		foreach($videoSimpleXML->children('http://search.yahoo.com/mrss/') as $videoSubNode)
		{
			// Get the YouTube namespace sub-nodes
			$youTubeNodes = $videoSubNode->children('http://gdata.youtube.com/schemas/2007');
			
			$video['youtubeId'] = (string)$youTubeNodes->videoid;
		}
		
		// Get the values from the YouTube namespace
		foreach($videoSimpleXML->children('http://gdata.youtube.com/schemas/2007') as $videoSubNode)
		{
			if (strcasecmp($videoSubNode->getName(), 'statistics') == 0)
			{
				$statisticsAttribues = $videoSubNode->attributes();
				$video['favouriteCount'] = (string)$statisticsAttribues->favoriteCount;
				$video['viewCount'] = (string)$statisticsAttribues->viewCount;
			}
			else
			// There should only be a location if there's no GEO location data
			if (strcasecmp($videoSubNode->getName(), 'location') == 0)
			{
				$feed = file_get_contents('http://maps.googleapis.com/maps/api/geocode/xml?address='.urlencode((string)$videoSubNode).'&sensor=false');
			
				if ($feed)
				{
					$googleLocationData = new SimpleXMLElement($feed);
					
					if ((isset($googleLocationData->result->geometry->location->lat)) and (isset($googleLocationData->result->geometry->location->lng)))
					{
						$video['xcoordinate'] = (string)$googleLocationData->result->geometry->location->lat;
						$video['ycoordinate'] = (string)$googleLocationData->result->geometry->location->lng;
					}
				}
			}
		}
		
		// And get the GEO Namespace
		foreach($videoSimpleXML->children('http://www.georss.org/georss') as $videoSubNode)
		{
			foreach($videoSubNode->children('http://www.opengis.net/gml') as $geoPositionSubNode)
			{
				// Coordinates are separated by a space
				$coordinates = explode(' ', (string)$geoPositionSubNode->pos);
				
				$video['xcoordinate'] = $coordinates[0];
				$video['ycoordinate'] = $coordinates[1];
			}
		}
		
		// Only return videos that have coordinates
		if ((isset($video['xcoordinate'])) and (is_numeric($video['xcoordinate'])) 
			and (isset($video['ycoordinate'])) and (is_numeric($video['ycoordinate'])))
		{
			return $video;
		}
		else
		{
			return false;
		}
	}
	

	/**
	* Takes an array representing a video, and adds or edits it depending on if
	*  there is an 'id' key.  The keys of the array must match the field names
	*  in the table
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	* @param array $videoArray Associative array representing a YouTube video
	* @param string $overrideYoutubeId Pass in a new Youtube ID to override before saving
	* @return boolean
	*
	*/
	function youearth_addEditVideo($videoArray, $overrideYoutubeId = null)
	{
		// Look globally for a variable named...
		global $table_name;
		global $wpdb;
		
		if (!is_array($videoArray))
		{
			return false;
		}
		
		$videoQuery = '';
		
		if ($overrideYoutubeId != null)
		{
			$videoArray['youtubeId'] = $overrideYoutubeId;
		}
		
		// Check to see if this video has a YouTube video ID, and if so, update
		if ((!isset($videoArray['youtubeId'])) or (trim($videoArray['youtubeId']) == ''))
		{
			return false;
		}
		
		// Clean out any spaces before checking if it exists
		$existingVideo = $wpdb->get_row('SELECT * FROM '.$table_name.' WHERE `youtubeId` = "'.str_replace(' ', '', $videoArray['youtubeId']).'"');
		
		// Set the ID in the videoArray if a row was found
		if (!is_null($existingVideo))
		{
			$videoArray['id'] = $existingVideo->id;
		}
		
		// Check if there's an ID field, and if so, it's set
		if ((array_key_exists('id', $videoArray)) and (is_numeric($videoArray['id'])))
		{
			$updateWhere = array('id' => $videoArray['id']);
			
			// We're editing
			return $wpdb->update($table_name, $videoArray, $updateWhere) !== false;
		}
		else
		{
			// We're adding a new video
			return $wpdb->insert($table_name, $videoArray);
		}
	}
	
	
	/**
	* Hook function to include the Google Earth JS files.  Unfortunately, this
	*  will be run on ALL pages - I haven't found a way around that yet
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	*
	*/
	function youearth_includeJSFiles()
	{
		wp_enqueue_script('googleearth', 'http://www.google.com/jsapi?key='.get_option('youearth_google_earth_api_key').'');
	}
	 
	add_action('wp_print_scripts', 'youearth_includeJSFiles');
	
	
	/**
	* Shortcode function for taking the shortcode from a post and replacing it with 
	*  a Google Earth instance
	* 
	* @author Dan Kennedy (dan@deletelondon.com)
	* @package WordPress
 	* @since 0.1
	* @param array $atts I assume the attributes passed in from the shortcode
	* @return string The content to be printed out
	*
	*/
	function youearth_showEarthShortcode($atts) 
	{
		// Before we get started, check the user has added a Google Earth API key
		if ((!get_option('youearth_google_earth_api_key')) or (trim(get_option('youearth_google_earth_api_key') == '')))
		{
			return '<em>Before YouEarth can work, you need to enter your Google Earth API key in the settings area.</em>';
		}
		
		// Get the values for YouEarth.  Default the width and height
		//  of the earth to 400x400
		extract(shortcode_atts(array(
			'width' => '400',
			'height' => '400',
		), $atts));
	
		$returnString = '<!-- YouEarth Wordpress plugin built by DeleteLondon. Visit DeleteLondon.com to see what else we\'ve been up to. --><div id="youEarth_map" style="height: '.$height.'px; width: '.$width.'px;"></div>';
		
		$returnString .= '<script type="text/javascript">
			var ge;
			var counter = 0;
			
			google.load("earth", "1");
			
			function init() {
				google.earth.createInstance(\'youEarth_map\', initCB, failureCB);
			}
			
			function initCB(instance) {
				ge = instance;
				ge.getWindow().setVisibility(true);
				
				// add a navigation control
  				ge.getNavigationControl().setVisibility(ge.VISIBILITY_AUTO);
				';
				
		// Load all the videos and add them to the map 
		$allVideos = youearth_loadAllVideos();
		$bFirstItem = true;
		
		foreach ($allVideos as $video)
		{
			if ($video->showOnMap == 1)
			{
				// Make sure the name isn't TOO long
				$maxTitleLength = 25;
				$newTitle = $video->videoTitle; 
				
				// Get and show the location of the first video
				if ($bFirstItem)
				{
					$firstVideoLocationx = $video->xcoordinate;
					$firstVideoLocationy = $video->ycoordinate;
					
					$bFirstItem = false;
				}
				
				if (strlen($newTitle) > $maxTitleLength)
				{
					$newTitle = substr($newTitle, 0, 25).'...';
				}
				
				// Get the date
				$tempDate = strtotime($video->publishedDate);
				$newDate = date('jS \o\f F, Y', $tempDate);
				
			
				$returnString .= 'youearth_createPlacemark('.$video->xcoordinate.', '.$video->ycoordinate.', \''.addslashes($newTitle).'\', \''.htmlentities($video->author).'\', \''.htmlentities($video->youtubeId).'\', \''.$newDate.'\');';
				$returnString .= "\r\n";
			}
		}
		
		// If bFirstItem is false, then we at least have one video to show
		if (!$bFirstItem)
		{
			$returnString .= '
				// look at the placemark we created
				  var la = ge.createLookAt(\'\');
				  la.set('.$firstVideoLocationx.', '.$firstVideoLocationy.',
					0, // altitude
					ge.ALTITUDE_RELATIVE_TO_GROUND,
					0, // heading
					0, // straight-down tilt
					8000000 // range (inverse of zoom)
					);
				  ge.getView().setAbstractView(la);';
		}
		
		$returnString .= '}
			
			function failureCB(errorCode) {
			}
			
			google.setOnLoadCallback(init);';
		
		$returnString .= 'function youearth_createPlacemark(xCoordinate, yCoordinate, title, author, youTubeId, publishedDate) 
			{
				var placemark = ge.createPlacemark(\'\');
				placemark.setName(\'\');
				ge.getFeatures().appendChild(placemark);
				
				// Create style map for placemark
				var icon = ge.createIcon(\'\');
				icon.setHref(\''.get_option('youearth_google_icon').'\');
			
				var style = ge.createStyle(\'\');
				style.getIconStyle().setIcon(icon);
				placemark.setStyleSelector(style);
				
				// Create point
				var la = ge.getView().copyAsLookAt(ge.ALTITUDE_RELATIVE_TO_GROUND);
				var point = ge.createPoint(\'\');
				point.setLatitude(xCoordinate);
				point.setLongitude(yCoordinate);
				placemark.setGeometry(point);
				
				counter++;
			
				google.earth.addEventListener(placemark, \'click\', function(event) 
				{
					// prevent the default balloon from popping up
					event.preventDefault();
				
					var balloon = ge.createHtmlStringBalloon(\'\');
					balloon.setFeature(placemark); // optional
					balloon.setMaxWidth(400);
				
					// YouTube video embed... the &nbsp; in the beginning is a fix for IE6
					balloon.setContentString(
						\'&nbsp;<span class="balloonName">By <strong>\'+author+\'</strong> on the \'+publishedDate+\'</span><object width="'.($width-50).'" height="'.($height-50).'"><param name="movie" \'
						+ \'value="http://www.youtube.com/v/\'+youTubeId+\'&hl=en&fs=1"/>\'
						+ \'<param name="allowFullScreen" value="true"/>\'
						+ \'<embed src="http://www.youtube.com/v/\'+youTubeId+\'&hl=en&fs=1" \'
						+ \'type="application/x-shockwave-flash" allowfullscreen="true" \'
						+ \'width="400" height="300"></embed></object>\');
				
					ge.setBalloon(balloon);
				  });
			}';
			
			$returnString .= '
			  
			  </script><!-- YouEarth Wordpress plugin built by DeleteLondon. Visit DeleteLondon.com to see what else we\'ve been up to. -->';
			
		return $returnString;
	}
	
	add_shortcode('youearth', 'youearth_showEarthShortcode');