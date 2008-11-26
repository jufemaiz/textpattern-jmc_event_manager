<?php
# --- BEGIN PLUGIN CODE ---
// Plugin code goes here.  No need to escape quotes.

	// Global textpattern variables + textile for use throughout
	global $txp_user, $vars, $txpcfg, $textile;
	// Textile construct
	include_once $txpcfg['txpath'].'/lib/classTextile.php';
	$textile = new Textile();

	// Add the jmc_event_manager tab to the Content area.
	if (@txpinterface === 'admin') {
		// Check for existence of tables - if none, install
		jmc_check_installation();	  
		
		// Add new tabs under "Gig Guide" for "Gigs", "Add a Gig", "Venue" and "Add a Venue"
		add_privs('jmc_event_manager','1,2,3,4,5,6');
		register_tab("content", "jmc_event_manager", "Event Manager");
		// Register Callbacks to the plugin
		register_callback("jmc_event_manager", "jmc_event_manager");
		register_callback("jmc_event_manager", "jmc_event_categories");
		register_callback("jmc_event_manager", "jmc_event_manager_venue");
		// 
//		add_privs('tab.jmc_event_manager_prefs','1,2');
//		register_tab("extensions", "jmc_event_manager_prefs", "jmc_e_m prefs");
		register_callback("jmc_event_manager_prefs", "jmc_event_manager_prefs");
	}

	// Events
	class JmcEvents {
		var $events = null;
		
		// Limits
		var $category_id = null;
		var $category = null;
		var $venue_id = null;
		var $venue = null;
		// Event times
		var $future = true;
		var $present = true;
		var $past = false;
		// Pagination
		var $article_list_pageby = 25;
		var $page = 0;
		var $pages = 0;
		var $offset = 0;
		var $limit = null;
		// Sort Order
		var $sort = "start";
		var $dir = "desc";
		var $crit = null;
		var $time = null;
		var $edit_method = null;
		
		function JmcEvents($a = null) {
			if(sizeof($a) > 0) {
				foreach ($a as $k=>$v) {
					$this->k = $v;
				}
			} else {
				$this->get_filters();
			}
			
			$events = safe_rows(
				"txp_jmc_event.ID",
				"txp_jmc_event, txp_jmc_event_venue, txp_jmc_event_category, txp_users",
				"txp_jmc_event.VenueID = txp_jmc_event_venue.ID AND txp_jmc_event.CategoryID = txp_jmc_event_category.ID AND {$this->date_filter()} order by {$this->sort_sql()} {$this->dir} limit {$this->offset},{$this->limit}"
			);
			$this->events = null;
			foreach($events as $e) {
				$this->events[] = new JmcEvent($e['ID']);
			}
			
		}
		
		function get_filters() {
			// Taken from existing TXP Code
			extract(get_prefs());
			$lvars = array("page","sort","dir","crit",'edit_method');
			extract(gpsa($lvars));
			global $step;

			$this->future = (($step == 'event_past')?false:true);

			if($sort) {
				$this->sort = $sort;
			}

			if($dir) {
				$this->dir = $dir;
			}

			$total = getCount('txp_jmc_event',$this->date_filter());

			if($article_list_pageby) {
				$this->$article_list_pageby = $article_list_pageby;
			}
			$this->limit = $this->$article_list_pageby;

			if($total > 0) {
				$this->pages = ceil($total/$this->limit);
				$this->page = ($page != null) ? 1 : $page;
			}
			// Offset is slightly different to the page (since we start with the zeroth record on page one)
			$offset = ($this->page - 1) * $this->limit;
		}
		
		function date_filter() {
			$f = "";
			if(!$this->future || !$this->past) {
				if($this->future) {
					$f = " unix_timestamp(start_DateTime) >".($this->present?"= ":" ").time()." ";
				} else {
					$f = " unix_timestamp(start_DateTime) <".($this->present?"= ":" ").time()." ";
				}
			}
			return $f;
		}
		
		function sort_dir() {
			switch($this->dir){
				case 'desc':
					return 'asc';
				default:
					return 'desc';
			}
		}
		
		function sort_sql() {
			switch($this->sort) {
				case 'category':
					return 'txp_jmc_event_category.name';
				case 'name':
					return 'txp_jmc_event.name';
				case 'start':
					return 'txp_jmc_event.start_DateTime';
				case 'end':
					return 'txp_jmc_event.finish_DateTime';
				case 'venue':
					return 'txp_jmc_event_venue.name';
				case 'price':
					return 'txp_jmc_event.Price';
				case 'author':
					return 'txp_jmc_event.AuthorID';
				default:
					return 'txp_jmc_event.start_DateTime';
			}
		}
	}


	// Venues
	class JmcVenues {
		var $venues = null;
		
		// Limits
		var $category_id = null;
		var $category = null;
		// Pagination
		var $article_list_pageby = 25;
		var $page = 0;
		var $pages = 0;
		var $offset = 0;
		var $limit = null;
		// Sort Order
		var $sort = "name";
		var $dir = "asc";
		var $crit = null;
		var $edit_method = null;
		
		function JmcVenues($a = null) {
			if(sizeof($a) > 0) {
				foreach ($a as $k=>$v) {
					$this->k = $v;
				}
			} else {
				$this->get_filters();
			}
			
			$venues = safe_rows(
				"ID",
				"txp_jmc_event_venue",
				" 1 order by {$this->sort} {$this->dir} limit {$this->offset},{$this->limit} "
			);
			$this->venues = null;
			foreach($venues as $v) {
				$this->venues[] = new JmcVenue($v['ID']);
			}
			
		}
		
		function get_filters() {
			// Taken from existing TXP Code
			extract(get_prefs());
			$lvars = array("page","sort","dir","crit",'edit_method');
			extract(gpsa($lvars));
			global $step;

			if($sort) {
				$this->sort = $sort;
			}
			if($dir) {
				$this->dir = $dir;
			}

			$total = getCount('txp_jmc_event_venue', '1');

			if($article_list_pageby) {
				$this->$article_list_pageby = $article_list_pageby;
			}
			$this->limit = $this->$article_list_pageby;

			if($total > 0) {
				$this->pages = ceil($total/$this->limit);
				$this->page = (!$page) ? 1 : $page;
			}
			// Offset is slightly different to the page (since we start with the zeroth record on page one)
			$offset = ($this->page - 1) * $this->limit;
		}
		
		function sort_dir() {
			switch($this->dir){
				case 'desc':
					return 'asc';
				default:
					return 'desc';
			}
		}
	}

	// Venues
	class JmcCategories {
		var $categories = null;
		
		// Pagination
		var $article_list_pageby = 25;
		var $page = 0;
		var $pages = 0;
		var $offset = 0;
		var $limit = null;
		// Sort Order
		var $sort = "name";
		var $dir = "asc";
		var $crit = null;
		var $edit_method = null;
		
		function JmcCategories($a = null) {
			if(sizeof($a) > 0) {
				foreach ($a as $k=>$v) {
					$this->k = $v;
				}
			} else {
				$this->get_filters();
			}
			
			$categories = safe_rows(
				"ID",
				"txp_jmc_event_category",
				" 1 order by {$this->sort} {$this->dir} limit {$this->offset},{$this->limit} "
			);
			$this->categories = null;
			foreach($categories as $v) {
				$this->categories[] = new JmcCategory($v['ID']);
			}
			
		}
		
		function get_filters() {
			// Taken from existing TXP Code
			extract(get_prefs());
			$lvars = array("page","sort","dir","crit",'edit_method');
			extract(gpsa($lvars));
			global $step;

			if($sort) {
				$this->sort = $sort;
			}
			if($dir) {
				$this->dir = $dir;
			}

			$total = getCount('txp_jmc_event_category', '1');

			if($article_list_pageby) {
				$this->$article_list_pageby = $article_list_pageby;
			}
			$this->limit = $this->$article_list_pageby;

			if($total > 0) {
				$this->pages = ceil($total/$this->limit);
				$this->page = (!$page) ? 1 : $page;
			}
			// Offset is slightly different to the page (since we start with the zeroth record on page one)
			$offset = ($this->page - 1) * $this->limit;
		}
		
		function sort_dir() {
			switch($this->dir){
				case 'desc':
					return 'asc';
				default:
					return 'desc';
			}
		}
	}

	// Event, Venue and Category Classes
	
	// JmcEvent
	class JmcEvent {
		// MEMBER NAME					// DATABASE COLUMN
		var $id = null;					// ID
		// Relationships
		var $category_id = null;		// CategoryID
		var $author_id = null;			// AuthorID
		var $venue_id = null;			// VenueID
		var $lastmod_id = null;			// LastModID
		// Modification
		var $lastmod = null;			// LastMod
		// Elements
		var $name = null;				// name
		var $start = null;				// start_DateTime
		var $end = null;				// finish_DateTime
		var $price = null;				// Price
		var $other_bands = null;		// OtherBands
		var $other_bands_html = null;	// OtherBands_html
		var $other_info = null;			// OtherInfo
		var $other_info_html = null;	// OtherInfo_html
		// Messages
		var $message = null;			// N/A
		
		// Related Models
		var $category = null;
		var $author = null;
		var $venue = null;
		var $lastmod_user = null;
		
		function JmcEvent($id = null) {
			if($id != null) {
				$row = safe_row("*, ID as id, Price as price, OtherBands as other_bands, OtherBands_html as other_bands_html, OtherInfo as other_info, OtherInfo_html as other_info_html, CategoryID as category_id, AuthorID as author_id, VenueID as venue_id, LastModID as lastmod_id, unix_timestamp(start_DateTime) as start, unix_timestamp(finish_DateTime) as end, unix_timestamp(LastMod) as lastmod", "txp_jmc_event", "ID = $id");
				foreach($row as $n=>$v) {
					$this->$n = stripslashes($v);
				}
				$this->venue = new JmcVenue($this->venue_id);
				$this->category = new JmcCategory($this->category_id);
			}
		}
		
		function save() {
			if($this->check_times()) {
				$this->lastmod_id = $txp_user;
				if(safe_update("txp_jmc_event",
					"$this->start,
					$this->end,
					CategoryID = $this->category_id,
					name = '".addslashes($this->name)."',
					LastMod = now(),
					LastModID = '".addslashes($this->lastmod_id)."',
					VenueID = $this->venue_id,
					Price = '".addslashes($this->price)."',
					OtherBands = '".addslashes($this->other_bands)."',
					OtherBands_html = '$this->other_bands_html',
					OtherInfo = '".addslashes($this->other_info)."',
					OtherInfo_html = '$this->other_info_html'",
					"ID = '$this->id'")) {
					$this->message = "<div class=\"success\"><strong>Success:</strong> Event details saved.</div>";
					$this->venue->JmcVenue($this->venue_id);
					$this->venue->JmcCategory($this->category_id);
					return true;
				} else {
					$this->message = "<div class=\"error_warning error_save\"><strong>Error:</strong> Could not save event.</div>";
					return false;
				}
			} else {
				$this->message = "<div class=\"error_warning error_time\"><strong>Error:</strong> An event cannot begin after it has ended.</div>";
				return false;
			}
		}
		function create() {
			
		}
		
		function condition_string($conditions = array()) {
			
		}
				
		// Date details
		function start_date($date) {
			$this->start = date_array_to_value($date);
		}
		function end_date($date) {
			$this->end = date_array_to_value($date);
		}
		// Localised start and end
		function start_local() {
			return $this->start + tz_offset();
		}
		function end_local() {
			return $this->end + tz_offset();
		}
		
		
		function check_date($date) {
			if(checkdate($date['month'], $date['day'], $date['year']) && ($date['hour'] >= 0 && $date['hour'] < 24) && ($date['minute'] >= 0 && $date['minute'] < 60)){
				return true;
			} else {
				return false;
			}
		}
		
		function date_array_to_value($array) {
			if(check_date($array)) {
				return strtotime($date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00");
			} else {
				return null;
			}
		}
		
		function other_bands_textiled() {
			return $self->other_bands?$textile->TextileThis($self->other_bands):"";
		}
		function other_info_textiled() {
			return $self->other_info?$textile->TextileThis($self->other_info):"";
			
		}
	}
	
	// JmcVenue
	class JmcVenue {
		// MEMBER NAME					// DATABASE COLUMN
		var $id = null;					// ID
		// Relationships
		var $category_id = null;		// CategoryID
		var $image_id = null;			// ImageID
		// Elements
		var $name = null;				// name
		var $address_1 = null;			// address1
		var $address_2 = null;			// address2
		var $suburb = null;				// suburb
		var $state = null;				// state
		var $country = null;			// country
		var $postcode = null;			// postcode
		var $phone = null;				// phone
		var $url = null;				// url
		var $email = null;				// email
		var $other_info = null;			// OtherInfo
		var $other_info_html = null;	// OtherInfo_html
		// Messages
		var $message = null;			// N/A
		
		function JmcVenue($id = null) {
			if($id != null) {
				$row = safe_row("ID as id, CategoryID as category_id, ImageID as image_id, name as name, address1 as address_1, address2 as address_2, suburb as suburb, state as state, country as country, postcode as postcode, phone as phone, url as url, email as email, OtherInfo as other_info, OtherInfo_html as other_info_html", "txp_jmc_event_venue", "ID = $id");
				foreach($row as $n=>$v) {
					$this->$n = stripslashes($v);
				}
			}
		}
	}

	// JmcCategory
	class JmcCategory {
		// MEMBER NAME					// DATABASE COLUMN
		var $id = null;					// ID
		// Elements
		var $name = null;				// name
		var $other_info = null;			// OtherInfo
		var $other_info_html = null;	// OtherInfo_html
		// Messages
		var $message = null;			// N/A
		
		function JmcCategory($id = null) {
			if($id != null) {
				$row = safe_row("ID as id, name as name, OtherInfo as other_info, OtherInfo_html as other_info_html", "txp_jmc_event_category", "ID = $id");
				foreach($row as $n=>$v) {
					$this->$n = stripslashes($v);
				}
			}
		}
	}
	
	// Main function manager - acts essentially as a giant switch
	function jmc_event_manager($event, $step) {
		// Declare global variable arrays
		global $vars_events, $vars_venues, $vars_categories;
		$vars_events = array("ID", "CategoryID", "sDateTime", "fDateTime", "name", "AuthorID", "LastMod", "LastModID", "VenueID", "Price", "OtherBands", "OtherBands_html", "OtherInfo", "OtherInfo_html", "comments_count");
		$vars_venues = array("ID", "CategoryID", "imageID", "name", "address1", "address2", "suburb", "state", "country", "postcode", "phone", "url", "email", "OtherInfo", "OtherInfo_html", "comments_count");
		$vars_categories = array("ID", "name", "OtherInfo", "OtherInfo_html");


		// Create the top menu arrays
		$top_menu = array();
		$top_menu['main'] = tr(tdcs("",4)).tr(td("<a href=\"?event=jmc_event_manager\" class=\"navlink\">Event Manager</a>").td("<a href=\"?event=jmc_event_manager&#38;step=venue_manager\" class=\"navlink\">Venue Manager</a>").td("<a href=\"?event=jmc_event_manager&#38;step=category_manager\" class=\"navlink\">Category Manager</a>").td("<a href=\"?event=jmc_event_manager&#38;step=feed_manager\" class=\"navlink\">RSS Feed</a>"));
		$top_menu['events'] = tr(td("<a href=\"?event=jmc_event_manager&#38;step=event_add\" class=\"navlink\">Add Event</a>").td("<a href=\"?event=jmc_event_manager\" class=\"navlink\">Upcoming Events</a>").td("<a href=\"?event=jmc_event_manager&#38;step=event_past\" class=\"navlink\">Past Events</a>").td(""));
		$top_menu['venues'] = tr(td("").td("<a href=\"?event=jmc_event_manager&#38;step=venue_add\" class=\"navlink\">Add Venue</a>").td("<a href=\"?event=jmc_event_manager&#38;step=venue_manager\" class=\"navlink\">Venue Listing</a>").td(""));
		$top_menu['categories'] = tr(td("").td("<a href=\"?event=jmc_event_manager&#38;step=category_add\" class=\"navlink\">Add Category</a>").td("<a href=\"?event=jmc_event_manager&#38;step=category_manager\" class=\"navlink\">Category Listing</a>").td(""));
		$top_menu['rss_feeds'] = tr(tdcs("This feature is yet to be implemented. Sorry guys :(",4));

		/*
		The Mega Switch
		*/
		
		$content['title'] = "Event Manager";
		$content['menus'] = $top_menu['main'];
		$content['subtitle'] = "Events";
		$content['content'] = "";
		
		$step_details = (split("_",$step));

		switch($step_details[0]) {
			/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
			/* 		VENUE       MANAGER		 */
			/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
			case "venue":
				$content['title'] = "Venue Manager";
				$content['menus'] .= $top_menu['venues'];
				$content['subtitle'] = "Venues";
				switch($step_details[1]) {
					case "manager":
						$content['content'] = jmc_event_manager_venues();
						break;
					case "add":
						$content['subtitle'] = "New Venue";
						$content['content'] = jmc_event_manager_venue_form($step);
						break;
					case "post":
						$content['subtitle'] = "New Venue";
						$content['content'] = jmc_event_manager_venue_post();
						break;
					case "edit":
						$content['subtitle'] = "Edit Venue";
						$content['content'] = jmc_event_manager_venue_form($step, true);
						break;
					case "save":
						$content['subtitle'] = "Edit Venue";
						$content['content'] = jmc_event_manager_venue_save();
						break;
					case "multiedit":
						$content['content'] = jmc_event_manager_multiedit("txp_jmc_event_venue");
						break;
					default:
						$content['content'] = jmc_event_manager_venues();
						break;
				}
				break;
			/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
			/* 		CATEGORY   MANAGER		 */
			/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
			case "category":
				$content['title'] = "Category Manager";
				$content['menus'] .= $top_menu['categories'];
				$content['subtitle'] = "Categories";
				switch($step_details[1]) {
					case "manager":
						$content['content'] = jmc_event_manager_categories();
						break;
					case "add":
						$content['subtitle'] = "New Category";
						$content['content'] = jmc_event_manager_category_form($step);
						break;
					case "post":
						$content['subtitle'] = "New Category";
						$content['content'] = jmc_event_manager_category_post();
					case "edit":
						$content['subtitle'] = "Edit Category";
						$content['content'] = jmc_event_manager_category_form($step, true);
						break;
					case "save":
						$content['subtitle'] = "Edit Category";
						$content['content'] = jmc_event_manager_category_save();
						break;
					case "multiedit":
						$content['content'] = jmc_event_manager_multiedit("txp_jmc_event_category");
						break;
					default:
						$content['content'] = jmc_event_manager_categories();
						break;
				}
				break;
			/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
			/* 		EVENT       MANAGER		 */
			/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
			case "event":
				$content['title'] = "Event Manager";
				$content['menus'] .= $top_menu['events'];
				$content['subtitle'] = "Events";
				switch($step_details[1]) {
					case "manager":
						$content['content'] = jmc_event_manager_events();
						break;
					case "past":
						$content['subtitle'] = "Past Events";
						$content['content'] = jmc_event_manager_events($step);
						break;
					case "add":
						$content['subtitle'] = "New Event";
						$content['content'] = jmc_event_manager_event_new();
						break;
					case "post":
						$content['subtitle'] = "New Event";
						$content['content'] = jmc_event_manager_event_post();
					case "edit":
						$content['subtitle'] = "Edit Event";
						$content['content'] = jmc_event_manager_event_edit();
						break;
					case "save":
						$content['subtitle'] = "Edit Event";
						$content['content'] = jmc_event_manager_event_save();
						break;
					case "multiedit":
						$content['content'] = jmc_event_manager_multiedit("txp_jmc_event_event");
						break;
					default:
						$content['content'] = jmc_event_manager_events();
						break;
				}
				break;
			default:
				$content['title'] = "Event Manager";
				$content['menus'] .= $top_menu['events'];
				$content['subtitle'] = "Events";
				$content['content'] = jmc_event_manager_events();
				break;
		}
		
		// Output the top for debugging - will stay UNTIL full release
		pagetop("Textpattern", "Event: $event; Step: $step.");

		// Hug everything in a nice little DIV!
		print_r("<div style=\"margin-top:3em; margin: auto; width: 600px; text-align: center\">\r\n");
		print_r(tag($content['title'], "h2").startTable('list').$content['menus'].endTable().tag($content['subtitle'], "h3").$content['content']);
		print_r("</div>\r\n");
	}
	
	// Event Functions
	function jmc_event_manager_events($step = null) {
		$output_html = null;
		
		$filters = jmc_event_get_filters($step);
		
		$events = new JmcEvents();

		// If there are rows we'll make our table. It'd be nice for every second one to be a little better put together
		if (sizeof($events->events) > 0) {
			$output_html .= jmc_nav_form('jmc_event_manager', $step, $filters);

			// Start the form for multi editing
			// Start a table with the class 'list'
			// Set up the headers for the columns
			$output_html .= '<form action="index.php" method="post" name="longform" onsubmit="return verify(\''.gTxt('are_you_sure').'\')">'.
			startTable('list').
			'<tr>'.
				jmc_event_column_head($events,'Category', 'category', true).
				jmc_event_column_head($events,'Event', 'name', true).
				jmc_event_column_head($events,'Start', 'start', true).
				jmc_event_column_head($events,'End', 'end', true).
				jmc_event_column_head($events,'Venue', 'venue', true).
				jmc_event_column_head($events,'Price', 'price', true).
				jmc_event_column_head($events,'Posted By', 'author', true).
				td().
			'</tr>';
			// Foreach of the entries create it's information in the relevant columns
			foreach ($events->events as $event) {
				// Output the row
				$output_html .= "<tr>".n.
					td($event->category->name).
					td(eLink('jmc_event_manager','event_edit','id',$event->id,$event->name)).
					td(eLink('jmc_event_manager','event_edit','id',$event->id,date("d M y",$event->start_local()))." at ".date("g:i a",$event->start_local())).
					td(eLink('jmc_event_manager','event_edit','id',$event->id,date("d M y",$event->end_local()))." at ".date("g:i a",$event->end_local())).
					td(eLink('jmc_event_manager','event_edit','id',$event->id,$event->venue->name),100).
					td($event->price,75).
					td($event->author_id).
					td(fInput('checkbox','selected[]',$event->id,'','','','','',$event->id)).
				'</tr>'.n;
			}
			// Modified multiedit form based on existing one but with more options
			$output_html .= tr(tda(select_buttons().jmc_event_manager_multiedit_form('jmc_event_manager', 'event_multi_edit'),' colspan="7" style="text-align:right;border:0px"'));
			$output_html .= "</table></form>";
			// Pageby form
			$output_html .= pageby_form('list',$filters['article_list_pageby']);
			// Unsure why - was in the article list version...
			unset($sort);
		}
		// If there exists no entries, don't both with anything
		else
			$output_html .= '<p>There are no upcoming entries. Please <a href="?event=jmc_event_manager&step=event_add">add one</a>.</p>';
			
		return $output_html;
	}

	function jmc_event_manager_event_new() {
		$event = new JmcEvent();
		return jmc_event_manager_event_form($event);
	}
	function jmc_event_manager_event_post() {}
	function jmc_event_manager_event_edit() {
		$id = (gps('id')?gps('id'):'');
		$event = new JmcEvent($id);
		return jmc_event_manager_event_form($event);
	}
	function jmc_event_manager_event_save() {}

	function jmc_event_manager_event_form($event) {		
		$output_html = "";
		
		$venues = new JmcVenues();
		$categories = new JmcCategories();

		// Echo start of form (REVISE)
		$output_html .= '<form action="index.php" method="post">';
		$output_html .= '<input type="hidden" name="event" value="jmc_event_manager" />';
		$output_html .= '<input type="hidden" name="step" value="'.($event->id?'event_save':'event_post').'" />';
		$output_html .= '<input type="hidden" name="id" value="'.($event->id?$event->id:'').'" />';

		// Input Information
		$output_html .= startTable('edit');
		$output_html .= "<tr><th><label for=\"jmc_event_category_id\">Category</label></th><td><select id=\"jmc_event_category_id\" name=\"category_id\" tabindex=\"4\">".jmc_select_options($categories->categories,$event->category)."</select></td><td></td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_name\">Event Name</label></td><td>".fInput('text', 'name', $event->name, '', '', '', '50%', '4', 'jmc_event_name')."</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_start_date\">Start Date</label></td><td>"./*datetimedropdown("day", "event_start_day", date('d',$event->start_local()), '4').datetimedropdown("month", "event_start_month", date('m',$event->start_local()), '4').datetimedropdown("year", "event_start_year", date('Y',$event->start_local()), '4').*/"</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_start_time\">Start Time</label></td><td>"./*datetimedropdown("hour", "event_start_hour", date('H',$event->start_local()), '4').datetimedropdown("minute", "event_start_minute", date('i',$event->start_local()), '4').*/"</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_end_date\">End Date</label></td><td>"./*datetimedropdown("day", "event_finish_day", date('d',$event->end_local()), '4').datetimedropdown("month", "event_finish_month", date('m',$event->end_local()), '4').datetimedropdown("year", "event_finish_year", date('Y',$event->end_local()), '4').*/"</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_end_time\">End Time</label></td><td>"./*datetimedropdown("hour", "event_finish_hour", date('H',$event->end_local()), '4').datetimedropdown("minute", "event_finish_minute", date('i',$event->end_local()), '4').*/"</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_venue_id\">Venue</label></td><td><select id=\"jmc_event_venue_id\" name=\"venue_id\" tabindex=\"4\">".jmc_select_options($venues->venues,$event->venue)."</select></td><td></td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_price\">Price</label></td><td>".fInput('text', 'price', $event->price, '', '', '', '50%', '4', 'jmc_event_price')."</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_other_bands\">Other Bands</label></td><td>".'<textarea  id="jmc_event_other_bands" name="other_bands" cols="40" rows="7" tabindex="4">'.$event->other_bands.'</textarea>'."</td></tr>";
		$output_html .= "<tr><td><label for=\"jmc_event_other_info\">Other Info</label></td><td>".'<textarea id="jmc_event_other_info" name="other_info" cols="40" rows="7" tabindex="4">'.$event->other_info.'</textarea>'."</td></tr>";
		$output_html .= "<tr><td colspan=\"2\" style=\"text-align: center;\">".fInput('submit', '', ($event->id == '' ?'Add Event':'Save Event'), '', '', '', '50%', '4')."</td></tr>";

    	$output_html .= '</table></form>';
		
		return $output_html;
	}
	
	// Venue Functions
	function jmc_event_manager_venues() {}
	function jmc_event_manager_venue_form() {}
	function jmc_event_manager_venue_post() {}
	function jmc_event_manager_venue_save() {}
	
	// Categories Functions
	function jmc_event_manager_categories() {}
	function jmc_event_manager_category_form() {}
	function jmc_event_manager_category_post() {}
	function jmc_event_manager_category_save() {}

	// Multiedit
	function jmc_event_manager_multiedit() {}
	
	// Helpers
	function jmc_select_options($a, $v = null) {
		$output_html = "";
		$selected = "";

		$output_html .= '<option value=""></option>';
		foreach($a as $i) {
			if($v != null){
				$selected = (($v->id == $i->id)? ' selected="selected" ' : '');
			}
			$output_html .= '<option value="'.$i->id.'" '.$selected.'>'.$i->name.'</option>';
		}
		return $output_html;
	}
	/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
	// Function:		jmc_event_manager_install
	// Use:				Column header creator.
	// Inputs:			$value			Name of 
	//					$sort			sort by ?
	//					$current_event	TXP
	//					$islink			if it is a link
	//					$dir			direction of sort
	//					$current_step	TXP (aka $step)
	// Outputs:			$output_html	html to be output
	// Notes:			Hacked versions from TXP
	/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
	function jmc_event_column_head($object, $value, $sort, $islink)
	{
		global $step;
		$output_html = '<th class="small '.(($object->sort == $sort)?($object->dir):"").'"><strong>';
			if ($islink) {
				$output_html .= '<a href="index.php?event=jmc_event_manager'.a.'step='.$step;
				$output_html .= ($sort) ? a."sort=$sort":'';
				$output_html .= a.'dir='.(($sort == $object->sort) ? $object->sort_dir() : 'desc');
				$output_html .= "\">";
			}
		$output_html .= gTxt($value);
			if ($islink) { $output_html .= "</a>"; }
		$output_html .= '</strong></th>';
		return $output_html;
	}
	/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
	// Function:		jmc_event_manager_multiedit_form
	// Use:				Multiedit
	// Inputs:			$type			TXP
	//					$event			TXP
	// Outputs:			html to be output
	// Notes:			YET TO BE FULLY IMPLEMENTED
	/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
	function jmc_event_manager_multiedit_form($event,$step)
	{
		$method = ps('method');
		$methods = array('delete'=>gTxt('delete'));
		return
			gTxt('with_selected').sp.selectInput('edit_method',$methods,$method,1).
			eInput($event).sInput($step).fInput('submit','',gTxt('go'),'smallerbox');
	}

	/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
	// Function:		jmc_nav_form
	// Use:				Create the rolling pages.
	// Inputs:			$event		TXP
	//					$step		TXP
	//					$page		page being viewed
	//					$numPages	total number of pages
	//					$sort		sort by what
	//					$dir		sort direction
	// Outputs:			The navigation button set for
	//				multipage sorting.
	/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
	function jmc_nav_form($event, $step, $filters)
	{
		$nav[] = ($filters['page'] > 1)
			?	jmc_PrevNextLink($event, $step, $filters['page']-1,gTxt('prev'),'prev',$filters['sort'], $filters['dir'])
			:	'';

		$nav[] = sp.small($filters['page']. '/'.$filters['pages']).sp;

		$nav[] = ($filters['page'] != $filters['pages'])
			?	jmc_PrevNextLink($event, $step, $filters['page']+1,gTxt('next'),'next',$filters['sort'], $filters['dir'])
			:	'';

		if ($nav) return graf(join('',$nav),' align="center"');
	}
	/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
	// Function:		jmc_PrevNextLink
	// Use:				Create the buttons for the 
	//				rolling pages.
	// Inputs:			$event		TXP
	//					$step		TXP
	//					$topage		linked page
	//					$label		label used (eg 'next')
	//					$type		prev/next button
	//					$sort		sort by what
	//					$dir		sort direction
	// Outputs:			The navigation button for next or
	//				previous pages.
	/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
	function jmc_PrevNextLink($event,$step,$topage,$label,$type,$sort='',$dir='')
	{
		return join('',array(
			'<a href="?event='.$event.a.'step='.$step.a.'page='.$topage,
			($sort) ? a.'sort='.$sort : '',
			($dir) ? a.'dir='.$dir : '',
			'" class="navlink">',
			($type=="prev") ? '&#8249;'.sp.$label : $label.sp.'&#8250;',
			'</a>'
		));
	}
	//
	function jmc_event_get_filters($step) {
		// Taken from existing TXP Code
		extract(get_prefs());
		$lvars = array("page","sort","dir","crit",'edit_method');
		extract(gpsa($lvars));
		global $step;
		
		$is_future = (($step == 'event_past')?false:true);
		
		// Default grouping of events
		$time = "unix_timestamp(start_DateTime)".($is_future?">":"<").time()." ";
		// Default sort start_DateTime (time of event starting)
		if (!$sort) $sort = "start_DateTime";
		// Default direction of sort: descending
		if (!$dir) $dir = "desc";
		// Set the link fo headers to the opposite of the direction (could be optimised)
		if ($dir == "desc") { $linkdir = "asc"; } else { $linkdir = "desc"; }

		// Get number of events
		$total = getCount('txp_jmc_event',"$time");
		// Limit currently set using page's limit
		$limit = ($article_list_pageby) ? $article_list_pageby : 25;
		// Numer of pages is the rounded up value of the total entries divided by page limit
		$pages = ceil($total/$limit);
		// Set default page
		$page = (!$page) ? 1 : $page;

		// Offset is slightly different to the page (since we start with the zeroth record on page one)
		$offset = ($page - 1) * $limit;
		
		return array('article_list_pageby' => $article_list_pageby, 'page' => $page, 'pages' => $pages, 'offset' => $offset, 'limit' => $limit, 'sort' => $sort, 'dir' => $dir, 'linkdir' => $linkdir, 'crit' => $crit, 'time' => $time, 'edit_method' => $edit_method);
	}
	
	/* -_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_- */
	// Function:		jmc_select
	// Use:				Create a select element with a default
	// Inputs:			$event		TXP
	//					$step		TXP
	//					$page		page being viewed
	//					$numPages	total number of pages
	//					$sort		sort by what
	//					$dir		sort direction
	// Outputs:			The navigation button set for
	//				multipage sorting.
	/* _-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_ */
	

	
	// Date Picker
	function jmc_select_date($d) {
		
	}
	function jmc_select_time($a) {
		
	}
	
	// Installation - new non-populating installation
	function jmc_check_installation() {
		// (c) Chris Dean Textpattern database checker
		// START
		$version = mysql_get_server_info();
		//Use "ENGINE" if version of MySQL > (4.0.18 or 4.1.2)
		$tabletype = ( intval($version[0]) >= 5 || preg_match('#^4\.(0\.[2-9]|(1[89]))|(1\.[2-9])#',$version))
						? " ENGINE=MyISAM "
						: " TYPE=MyISAM ";

		// On 4.1 or greater use utf8-tables
		if ( isset($dbcharset) && (intval($version[0]) >= 5 || preg_match('#^4\.[1-9]#',$version)))
		{
			$tabletype .= " CHARACTER SET = $dbcharset ";
			if (isset($dbcollate))
				$tabletype .= " COLLATE $dbcollate ";
			mysql_query("SET NAMES ".$dbcharset);
		}
		
		$tables['txp_jmc_event'] = "CREATE TABLE `".PFX."txp_jmc_event` (
			`ID` int(11) NOT NULL auto_increment,
			`CategoryID` varchar(64) NOT NULL default '',
			`name` varchar(255) NULL default '',
			`start_DateTime` datetime NOT NULL default '0000-00-00 00:00:00',
			`finish_DateTime` datetime NOT NULL default '0000-00-00 00:00:00',
			`AuthorID` varchar(64) NOT NULL default '',
			`LastMod` datetime NOT NULL default '0000-00-00 00:00:00',
			`LastModID` varchar(64) NOT NULL default '',
			`VenueID` varchar(64) NOT NULL default '',
			`Price` varchar(255) NOT NULL default '',
			`OtherBands` mediumtext NOT NULL,
			`OtherBands_html` mediumtext NOT NULL,
			`OtherInfo` text NOT NULL,
			`OtherInfo_html` mediumtext NOT NULL,
			`comments_count` int(8) NOT NULL default '0',
			PRIMARY KEY  (`ID`),
			KEY `start_DateTime` (`start_DateTime`),
			KEY `finish_DateTime` (`finish_DateTime`),
			FULLTEXT KEY `searching` (`OtherBands`,`OtherInfo`)
			) $tabletype PACK_KEYS=1 AUTO_INCREMENT=2 ";

		$tables['txp_jmc_event_venue'] = "CREATE TABLE `".PFX."txp_jmc_event_venue` (
			`ID` int(11) NOT NULL auto_increment,
			`CategoryID` varchar(64) NOT NULL default '',
			`name` varchar(255) NOT NULL default '',
			`imageID` varchar(64) NULL default '',
			`address1` varchar(255) NOT NULL default '',
			`address2` varchar(255) NOT NULL default '',
			`suburb` varchar(255) NOT NULL default '',
			`state` varchar(255) NOT NULL default '',
			`country` varchar(255) NOT NULL default '',
			`postcode` varchar(10) NOT NULL default '',
			`phone` varchar(255) NOT NULL default '',
			`url` varchar(255) NOT NULL default '',
			`email` varchar(255) NOT NULL default '',
			`OtherInfo` text NOT NULL,
			`OtherInfo_html` mediumtext NOT NULL,
			`comments_count` int(8) NOT NULL default '0',
			PRIMARY KEY  (`ID`),
			KEY `name` (`name`),
			FULLTEXT KEY `searching` (`OtherInfo`)
			) $tabletype PACK_KEYS=1 AUTO_INCREMENT=2 ";

		$tables['txp_jmc_event_category'] = "CREATE TABLE `".PFX."txp_jmc_event_category` (
			`ID` int(11) NOT NULL auto_increment,
			`name` varchar(255) NOT NULL default '',
			`OtherInfo` text NOT NULL,
			`OtherInfo_html` mediumtext NOT NULL,
			PRIMARY KEY  (`ID`),
			KEY `name` (`name`),
			FULLTEXT KEY `searching` (`OtherInfo`)
			) $tabletype PACK_KEYS=1 AUTO_INCREMENT=2 ";
		
		foreach($tables as $tablename=>$structure) {
			$q = "SHOW TABLES LIKE '".PFX.$tablename."'";
			$tcheck = getRows($q);
			if (!$tcheck) {
				safe_query($structure);
			}
		}
	}	  
	
?>