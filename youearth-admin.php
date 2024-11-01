<div class="wrap">
	
	<?php
    
		echo('<h2>'.__('YouEarth', 'youEarth').'</h2>');
	
		echo('<h3>How to use YouEarth</h3>
		<p>YouEarth allows you to plot YouTube videos on a Google Earth implementation.  This relies on the videos holding GEO-location details, which can be added to any
		YouTube video by editing it and placing a marker on the Google Map found on that page.</p>
		<p>Using the Find/import videos boxes below, you will be able to find movies using their title, caption, category, tag or author.  For advanced searches, you can use a pipe ("|") 
			for "OR", and a minus ("-") for "NOT".  A comma (",") denotes "AND".  You need to ensure you only use spaces where necessary, specifically not directly before or after these special characters.
			Advanced instructions can be found in YouTube\'s <a href="http://code.google.com/apis/youtube/2.0/reference.html#Searching_for_videos" target="_blank">search API</a>.</p>
			<p>When some videos have been returned from your search, you can click on the "Import videos" button, which will import the videos into the list.  Videos can be switched off
			if they are not required on the map.</p>
			<p>When you have imported some videos, you can place a Google Earth map into a page or a post using <code>[youearth]</code>.  You can specify the width and height by passing them 
			in e.g. <code>[youearth width=800 height=300]</code>.</p>
		<p><strong>Notes:</strong>
		<ul>
		<li>- YouEarth will only return movies that have GEO-location data</li>
		<li>- YouTube can only return 50 movies at a time.  If your search returns more than 50 videos, then only the first 50 results will be displayed, and you may miss other videos
		you were expecting to find in the results.  Alter your search criteria so the search returns less than 50 videos to make sure you get all of them</li>
		<li>- Videos already imported will be updated with any changed data for that video</li>
		</ul>
		</p>');
	
		echo('<h3>'.__('Find/import videos', 'youEarth').'</h3>');
		
		
        // Print out the form
        youearth_importForm();
		
		
		echo('<h3>'.__('Settings', 'youEarth').'</h3>');
		// Print out the form
		youearth_settingsForm();
		
		
		
		$videos = youearth_loadAllVideos();
		$videoCount = count($videos);
    
    
		echo('<h3>'.__('Videos', 'youEarth').'</h3>');
		
		if ($videoCount == 0)
		{
			echo('<p><em>'.__('No videos in the YouEarth system.  Try adding some using the find/import videos area above.', 'youEarth').'</em></p>');
		}
		else
		{
			echo('<table class="widefat">
					<thead>
						<tr>
							<th scope="col" width="20%">Video title</th>
							<th scope="col" width="20%">Author</th>
							<th scope="col" width="20%">Published</th>
							<th scope="col" width="20%">Favourite/viewed</th>
							<th scope="col" width="20%">Options</th>					
						</tr>
					</thead>');
					
			// Loop through the videos and add them
			foreach($videos as $video)
			{
				echo('<tr>
						<td><a href="http://www.youtube.com/watch?v='.$video->youtubeId.'" target="_blank" title="See \''.$video->videoTitle.'\' on YouTube (opens in a new window)">'.$video->videoTitle.'</a></td>			
						<td>'.$video->author.'</td>
						<td>'.date('jS M, Y', strtotime($video->publishedDate)).'</td>
						<td>Viewed: '.number_format($video->viewCount).' | Favourites: '.$video->favouriteCount.'</td>
						<td><a href="'.wp_nonce_url('?page=youearth_settings&action=toggle_visibility&video_id='.$video->id, 'youearth_togglevisibility').'">');
						
						if ($video->showOnMap == 1)
						{
							echo('Remove from map');
						}
						else
						{
							echo('Show on map');	
						}
						
						echo('</a></td>		
					</tr>');
			}
		
			echo('</table>');	
		}
	
	?>
    
</div>