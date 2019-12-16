<?php
	/*
		Class: BigTree\Page
			Provides an interface for BigTree pages.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read bool $ChangesApplied
	 * @property-read int $ChangeID
	 * @property-read string $CreatedAt
	 * @property-read int $ID
	 * @property-read int $LastEditedBy
	 * @property-read Page $ParentPage
	 * @property-read PendingChange $PendingChange
	 * @property-read int $PendingID
	 * @property-read array $Tags
	 * @property-read string $UpdatedAt
	 * @property-read string $UserAccessLevel
	 */
	
	class Page extends SQLObject
	{
		
		protected $ChangesApplied = false;
		protected $ChangeID;
		protected $CreatedAt;
		protected $ID;
		protected $LastEditedBy;
		protected $PendingID;
		protected $UpdatedAt;
		
		public $AnalyticsPageViews;
		public $Archived;
		public $ArchivedInherited;
		public $Content;
		public $ExpireAt;
		public $External;
		public $InNav;
		public $MaxAge;
		public $MetaDescription;
		public $NavigationTitle;
		public $NewWindow;
		public $OriginalParent;
		public $Parent;
		public $Path;
		public $Position;
		public $PublishAt;
		public $Revision;
		public $Route;
		public $SEOInvisible;
		public $SEORecommendations;
		public $SEOScore;
		public $Template;
		public $Title;
		public $Trunk;
		
		public static $SEORules = [
			"Your page title should be unique. :count: other page(s) have the same title.",
			"Your page title should be no more than 72 characters and should contain at least 4 words.",
			"You should enter a page title.",
			"Your meta description should be no more than 165 characters. It is currently :count: characters.",
			"You should enter a meta description.",
			"You should enter a page header.",
			"You should enter at least 300 words of page content. You currently have :count: word(s).",
			"You should have at least one link for every 120 words of page content. You currently have :count: link(s). You should have at least :count2:.",
			"Having an external link helps build Page Rank.",
			"You should have at least one link in your content.",
			"Your readability score is :count:%.  Using shorter sentences and words with fewer syllables will make your site easier to read by search engines and users.",
			"You should enter page content.",
			"Your content is around :count: months old.  Updating your page more frequently will make it rank higher.",
		];
		public static $Table = "bigtree_pages";
		
		/*
			Constructor:
				Builds a Page object referencing an existing database entry.

			Parameters:
				page - Either an ID (to pull a record) or an array (to use the array as the record)
				on_fail - An optional callable to call on non-exist or bad data (rather than triggering an error).
				decode - Whether to decode content (true is default, false is faster if content isn't needed)
		*/
		
		public function __construct($page = null, ?callable $on_fail = null, bool $decode = true)
		{
			// Allow for loading the root (i.e. -1)
			if ($page === -1 || is_null($page)) {
				$this->ID = -1;
			} else {
				// Passing in just an ID
				if (!is_array($page)) {
					$page = SQL::fetch("SELECT * FROM bigtree_pages WHERE id = ?", $page);
				}
				
				// Bad data set
				if (!is_array($page)) {
					if ($on_fail) {
						return $on_fail();
					} else {
						trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
					}
				} else {
					// Allow for empty page creation (for creating a page from a pending entry)
					if (count($page) == 1) {
						$this->PendingID = $page["id"];
						
						return;
					}
					
					// Protected vars first
					$this->CreatedAt = $page["created_at"];
					$this->ID = intval($page["id"]);
					$this->LastEditedBy = $page["last_edited_by"];
					$this->UpdatedAt = $page["updated_at"];
					
					// Public vars
					$this->AnalyticsPageViews = $page["ga_page_views"];
					$this->Archived = $page["archived"] ? true : false;
					$this->ArchivedInherited = $page["archived_inherited"] ? true : false;
					$this->Content = $decode ? Link::decode(array_filter((array) @json_decode($page["content"], true))) : $page["content"];
					$this->ExpireAt = $page["expire_at"] ?: false;
					$this->External = $page["external"] ? Link::decode($page["external"]) : "";
					$this->InNav = $page["in_nav"] ? true : false;
					$this->MetaDescription = $page["meta_description"];
					$this->NavigationTitle = $page["nav_title"];
					$this->NewWindow = $page["new_window"] ? true : false;
					$this->OriginalParent = $this->Parent = intval($page["parent"]);
					$this->Path = $page["path"];
					$this->Position = intval($page["position"]);
					$this->PublishAt = $page["publish_at"] ?: false;
					$this->Route = $page["route"];
					$this->SEOInvisible = $page["seo_invisible"] ? true : false;
					$this->SEORecommendations = $page["seo_recommendations"] ? json_decode($page["seo_recommendations"], true) : [];
					$this->SEOScore = $page["seo_score"];
					$this->Template = $page["template"];
					$this->Title = $page["title"];
					$this->Trunk = $page["trunk"];
					
					// Make sure content is an array
					if (!is_array($this->Content)) {
						$this->Content = [];
					}
				}
			}
		}
		
		// Array conversion
		public function getArray(): array
		{
			$raw_properties = get_object_vars($this);
			$changed_properties = [];
			
			foreach ($raw_properties as $key => $value) {
				$changed_properties[$this->_camelCaseToUnderscore($key)] = $value;
			}
			
			$changed_properties["nav_title"] = $changed_properties["navigation_title"];
			$changed_properties["resources"] = $changed_properties["content"];
			
			return $changed_properties;
		}
		
		/*
			Function: allByTags
				Returns pages related to the given set of tags.

			Parameters:
				tags - An array of tags to search for.
				type - Whether to return Page objects ("object"), IDs ("id"), or Arrays ("array")

			Returns:
				An array of related pages sorted by relevance (how many tags get matched).
		*/
		
		public static function allByTags(array $tags, string $type = "object"): array
		{
			$results = [];
			$relevance = [];
			
			// Loop through each tag finding related pages
			foreach ($tags as $tag) {
				// In case a whole tag row was passed
				if (is_array($tag)) {
					$tag = $tag["tag"];
				}
				
				$tag_id = SQL::fetchSingle("SELECT id FROM bigtree_tags WHERE tag = ?", $tag);
				
				if ($tag_id) {
					$related_pages = SQL::fetchAllSingle("SELECT entry FROM bigtree_tags_rel 
														  WHERE tag = ? AND `table` = 'bigtree_pages'", $tag_id);
					
					foreach ($related_pages as $page_id) {
						// If we already have this result, add relevance
						if (in_array($page_id, $results)) {
							$relevance[$page_id]++;
						} else {
							$results[] = $page_id;
							$relevance[$page_id] = 1;
						}
					}
				}
			}
			
			// Sort by most relevant
			array_multisort($relevance, SORT_DESC, $results);
			
			if ($type == "id") {
				return $results;
			}
			
			// Get the actual page data for each result
			$items = [];
			
			foreach ($results as $result) {
				$page = new Page($result);
				
				if ($type == "array") {
					$items[] = $page->Array;
				} else {
					$items[] = $page;
				}
			}
			
			return $items;
		}
		
		/*
			Function: allIDs
				Returns all the IDs in bigtree_pages for pages that aren't archived.

			Returns:
				An array of page ids.
		*/
		
		public static function allIDs(): array
		{
			return SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE archived != 'on' ORDER BY id ASC");
		}
		
		/*
			Function: archive
				Archives the page and the page's children.

			See Also:
				<archiveChildren>
		*/
		
		public function archive(): void
		{
			// Archive the page and the page children
			SQL::update("bigtree_pages", $this->ID, ["archived" => "on"]);
			$this->archiveChildren();
			
			// Track
			AuditTrail::track("bigtree_pages", $this->ID, "update", "archived");
		}
		
		/*
			Function: archiveChildren
				Archives the page's children and sets the archive status to inherited.

			See Also:
				<archivePage>
		*/
		
		public function archiveChildren($recursion = false): void
		{
			$page_id = $recursion ?: $this->ID;
			
			// Track and recursively archive
			$children = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived != 'on'", $page_id);
			
			foreach ($children as $child_id) {
				AuditTrail::track("bigtree_pages", $child_id, "update", "inherited archive state from parent");
				$this->archiveChildren($child_id);
			}
			
			// Archive this level
			SQL::query("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' 
						WHERE parent = ? AND archived != 'on'", $page_id);
		}
		
		/*
			Function: auditAdminLinks
				Gets a list of pages that link back to the admin.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of pages that link to the admin.
		*/
		
		public static function auditAdminLinks(bool $return_arrays = false): array
		{
			global $bigtree;
			
			$admin_root = SQL::escape($bigtree["config"]["admin_root"]);
			$partial_root = SQL::escape(str_replace($bigtree["config"]["www_root"], "{wwwroot}", $bigtree["config"]["admin_root"]));
			
			$pages = SQL::fetchAll("SELECT * FROM bigtree_pages 
									WHERE content LIKE '%$admin_root%'
									   OR content LIKE '%$partial_root%'
									ORDER BY nav_title ASC");
			
			if (!$return_arrays) {
				foreach ($pages as &$page) {
					$page = new Page($page);
				}
			}
			
			return $pages;
		}
		
		/*
			Function: copyToPending
				Copies this page to a pending page draft with the titles changed.
		
			Parameters:
				title_change - The additional copy to add to the end of the navigation and page title (e.g. " (Copy)")
		
			Returns:
				PendingChange object
		*/
		
		public function copyToPending(string $title_change = ""): PendingChange
		{
			return PendingChange::createPage($this->Trunk, $this->Parent, $this->InNav, $this->NavigationTitle.$title_change,
											 $this->Title.$title_change, null, $this->MetaDescription, $this->SEOInvisible,
											 $this->Template, $this->External, $this->NewWindow, $this->Content,
											 $this->PublishAt, $this->ExpireAt, $this->MaxAge, $this->Tags, $this->OpenGraph);
			
		}
		
		/*
			Function: create
				Creates a page.

			Parameters:
				trunk - Trunk status (true or false)
				parent - Parent page ID
				in_nav - In navigation (true or false)
				nav_title - Navigation title
				title - Page title
				route - Page route (leave empty to auto generate)
				meta_description - Page meta description
				seo_invisible - Pass "X-Robots-Tag: noindex" header (true or false)
				template - Page template ID
				external - External link (or empty)
				new_window - Open in new window from nav (true or false)
				content - Array of page data
				publish_at - Publish time (or false for immediate publishing)
				expire_at - Expiration time (or false for no expiration)
				max_age - Content age (in days) allowed before alerts are sent (0 for no max)
				tags - An array of tags to apply to the page (optional)
				change_being_published - The ID of the change being published (optional)

			Returns:
				A Page object.
		*/
		
		public static function create(bool $trunk, ?int $parent, bool $in_nav, string $nav_title, string $title,
									  ?string $route, ?string $meta_description, bool $seo_invisible, string $template,
									  ?string $external, bool $new_window, ?array $content, ?string $publish_at,
									  ?string $expire_at, ?int $max_age, ?array $tags = [],
									  ?int $change_being_published = null): Page
		{
			// Clean up either their desired route or the nav title
			$route = Link::urlify($route ?: $nav_title);
			
			// Make sure route isn't longer than 250 characters
			$route = substr($route, 0, 250);
			
			// We need to figure out a unique route for the page.  Make sure it doesn't match a directory in /site/
			$original_route = $route;
			$x = 2;
			
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists(SERVER_ROOT."site/".$route."/")) {
					$route = $original_route."-".$x;
					$x++;
				}
				
				$reserved_routes = Router::getReservedRoutes();
				
				while (in_array($route, $reserved_routes)) {
					$route = $original_route."-".$x;
					$x++;
				}
			}
			
			// Make sure it doesn't have the same route as any of its siblings.
			$route = SQL::unique("bigtree_pages", "route", $route, ["parent" => $parent], true);
			
			// If we have a parent, get the full navigation path, otherwise, just use this route as the path since it's top level.
			if ($parent) {
				$path = SQL::fetchSingle("SELECT `path` FROM bigtree_pages WHERE id = ?", $parent)."/".$route;
			} else {
				$path = $route;
			}
			
			$trunk = ($trunk ? "on" : "");
			
			// Get seo ratings
			$seo = static::getSEORating(null, $template, $title, $meta_description, $content, date("Y-m-d H:i:s"));
			
			$insert = [
				"trunk" => $trunk,
				"parent" => $parent,
				"nav_title" => Text::htmlEncode($nav_title),
				"route" => $route,
				"path" => $path,
				"in_nav" => ($in_nav ? "on" : ""),
				"title" => Text::htmlEncode($title),
				"template" => $template,
				"external" => Text::htmlEncode(($external ? Link::encode($external) : "")),
				"new_window" => ($new_window ? "on" : ""),
				"content" => $content,
				"meta_description" => Text::htmlEncode($meta_description),
				"seo_invisible" => ($seo_invisible ? "on" : ""),
				"seo_score" => $seo["score"],
				"seo_recommendations" => $seo["recommendations"],
				"last_edited_by" => Auth::user()->ID,
				"created_at" => "NOW()",
				"publish_at" => ($publish_at ? date("Y-m-d", strtotime($publish_at)) : null),
				"expire_at" => ($expire_at ? date("Y-m-d", strtotime($expire_at)) : null),
				"max_age" => intval($max_age)
			];
			
			if (!is_null($change_being_published)) {
				$pending_change = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change_being_published);
				
				if ($pending_change) {
					$changes = Link::decode(json_decode($pending_change["changes"], true));
					$exact = true;
					
					foreach ($changes as $key => $value) {
						if ($key == "route" && empty($value)) {
							continue;
						}
						
						if (isset($insert[$key]) && $insert[$key] != $value) {
							$exact = false;
						}
					}
					
					if ($exact) {
						$insert["last_edited_by"] = $pending_change["user"];
					}
					
					SQL::delete("bigtree_pending_changes", $change_being_published);
				}
			} else {
				$pending_change = null;
			}
			
			// Create the page
			$id = SQL::insert("bigtree_pages", $insert);
			
			// Handle tags
			if (is_array($tags)) {
				$tags = array_unique(array_filter($tags));

				foreach ($tags as $tag) {
					SQL::insert("bigtree_tags_rel", [
						"table" => "bigtree_pages",
						"entry" => $id,
						"tag" => $tag
					]);
				}

				if (count($tags)) {
					Tag::updateReferenceCounts($tags);
				}
			}
			
			// If there was an old page that had previously used this path, dump its history so we can take over the path.
			SQL::delete("bigtree_route_history", ["old_route" => $path]);
			
			// Dump the cache, we don't really know how many pages may be showing this now in their nav.
			Router::clearCache();
			
			// Let search engines know this page now exists.
			Sitemap::pingSearchEngines();
			
			// Audit trail
			if ($pending_change && $pending_change["user"] != Auth::user()->ID && !empty($exact)) {
				AuditTrail::track("bigtree_pages", $id, "add", "created via publisher", $pending_change["user"]);
				AuditTrail::track("bigtree_pages", $id, "update", "published");
			} else {
				AuditTrail::track("bigtree_pages", $id, "add", "created");
			}
			
			return new Page($id);
		}
		
		/*
			Function: createChangeRequest
				Adds a pending change to the bigtree_pending_changes table for a given page.
				Determines what has changed and only stores the changed fields.

			Parameters:
				page - The page id or pending page id (prefixed with a "p")
				changes - An array of page changes
				tags - An array of tags changes
				open_graph - An array of open graph changes
		*/
		
		public static function createChangeRequest(string $page, array $changes, array $tags, array $open_graph): int
		{
			// Get the user creating the change
			$user = Auth::user()->ID;
			
			// Get existing information
			if ($page[0] == "p") {
				// It's still pending
				$pending = true;
				$existing_page = [];
				$existing_pending_change = substr($page, 1);
			} else {
				// It's an existing page
				$pending = false;
				$existing_page = new Page($page);
				$existing_page = $existing_page->Array;
				$existing_pending_change = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes 
															 WHERE `table` = 'bigtree_pages' AND item_id = ?", $page);
			}
			
			// Save tags separately
			unset($changes["_tags"]);
			
			// Convert to an IPL
			if (!empty($changes["external"])) {
				$changes["external"] = Link::iplEncode($changes["external"]);
			}
			
			// Set trunk flag
			$changes["trunk"] = !empty($changes["trunk"]) ? "on" : "";
			
			// Set the in_nav flag, since it's not in the post if the checkbox became unclicked
			$changes["in_nav"] = !empty($changes["in_nav"]) ? "on" : "";
			
			// Encode fields
			$changes["title"] = Text::htmlEncode($changes["title"]);
			$changes["nav_title"] = Text::htmlEncode($changes["nav_title"]);
			$changes["meta_description"] = Text::htmlEncode($changes["meta_description"]);
			$changes["seo_invisible"] = $changes["seo_invisible"]["seo_invisible"] ? "on" : "";
			$changes["external"] = Text::htmlEncode($changes["external"]);
			
			$tags = array_unique($tags);
			
			// If there's already a change in the queue, update it with this latest info.
			if ($existing_pending_change) {
				// If this is a pending page, just replace all the changes
				if ($pending) {
					$diff = $changes;
				// Otherwise, we need to check what's changed.
				} else {
					// We don't want to indiscriminately put post data in as changes, so we ensure it matches a column in the bigtree_pages table
					$diff = [];
					
					foreach ($changes as $key => $val) {
						if (array_key_exists($key, $existing_page) && $existing_page[$key] != $val) {
							$diff[$key] = $val;
						}
					}
				}
				
				// Update existing draft and track
				SQL::update("bigtree_pending_changes", $existing_pending_change, [
					"changes" => $diff,
					"tags_changes" => $tags,
					"open_graph_changes" => $open_graph,
					"user" => $user
				]);
				
				AuditTrail::track("bigtree_pages", $page, "update", "updated draft");
				
				return $existing_pending_change;
				
			} else {
				// We're submitting a change to a presently published page with no pending changes.
				$diff = [];
				
				foreach ($changes as $key => $val) {
					if (array_key_exists($key, $existing_page) && $val != $existing_page[$key]) {
						$diff[$key] = $val;
					}
				}
				
				// Create draft and track
				AuditTrail::track("bigtree_pages", $page, "update", "saved draft");
				
				return SQL::insert("bigtree_pending_changes", [
					"user" => $user,
					"table" => "bigtree_pages",
					"item_id" => $page,
					"changes" => $diff,
					"tags_changes" => $tags,
					"open_graph_changes" => $open_graph,
					"title" => "Page Change Pending"
				]);
			}
		}
		
		/*
			Function: decodeContent
				Turns the JSON content data into a PHP array of content with links being translated into front-end readable links.
				This function is called by BigTree's router and is generally not a function needed to end users.
			
			Parameters:
				data - JSON encoded callout data.
			
			Returns:
				An array of content.
		*/
		
		public static function decodeContent(array $data): array
		{
			if (!is_array($data)) {
				$data = json_decode($data, true);
			}
			
			if (is_array($data)) {
				foreach ($data as $key => $val) {
					// Already an array, decode the whole thing
					if (is_array($val)) {
						$val = Link::decode($val);
					} else {
						// See if it's a JSON string first, if so decode the array
						$decoded_val = json_decode($val, true);
						
						if (is_array($decoded_val)) {
							$val = Link::decode($decoded_val);
							
							// Otherwise it's a string, just replace the {wwwroot} and ipls.
						} else {
							$val = Link::decode($val);
						}
					}
					
					$data[$key] = $val;
				}
			}
			
			return $data;
		}
		
		/*
			Function: delete
				Deletes the page and all children.
		*/
		
		public function delete(): ?bool
		{
			// Delete the children as well.
			$this->deleteChildren($this->ID);
			
			SQL::delete("bigtree_pages", $this->ID);
			AuditTrail::track("bigtree_pages", $this->ID, "delete", "deleted");
			
			return true;
		}
		
		/*
			Function: deleteChildren
				Deletes the children of the page and recurses downward.

			Parameters:
				recursive_id - The parent ID to delete children for (used for recursing down)
		*/
		
		public function deleteChildren(?int $recursive_id = null): void
		{
			$id = $recursive_id ?: $this->ID;
			$children = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ?", $id);
			
			foreach ($children as $child) {
				// Recurse to this child's children
				$this->deleteChildren($child);
				
				// Delete and track
				SQL::delete("bigtree_pages", $child);
				AuditTrail::track("bigtree_pages", $child, "delete", "inherited deletion from parent");
			}
		}
		
		/*
			Function: deleteDraft
				Deletes the pending draft of the page.
		*/
		
		public function deleteDraft(): void
		{
			// Get the draft copy's ID
			$draft_id = SQL::fetchSingle("SELECT id FROM bigtree_pending_changes 
										  WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $this->ID);
			
			// Delete draft copy
			SQL::delete("bigtree_pending_changes", $draft_id);
			
			// Double track to add specificity to what happend to the page
			AuditTrail::track("bigtree_pages", $this->ID, "update", "deleted draft");
			AuditTrail::track("bigtree_pending_changes", $draft_id, "delete", "deleted");
		}
		
		/*
			Function: deleteRevision
				Deletes one of the page's revisions.

			Parameters:
				id - The page reversion id.
		*/
		
		public function deleteRevision(int $id): void
		{
			// Delete the revision
			SQL::delete("bigtree_page_revisions", $id);
			
			// Double track to add specificity to what happend to the page
			AuditTrail::track("bigtree_pages", $this->ID, "update", "deleted revision");
			AuditTrail::track("bigtree_page_revisions", $id, "delete", "deleted");
		}
		
		/*
			Function: getArchivedChildren
				Returns an alphabetic array of archived child pages.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/
		
		public function getArchivedChildren(bool $return_arrays = false): array
		{
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived = 'on' 
									   ORDER BY nav_title ASC", $this->ID);
			
			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}
			
			return $children;
		}
		
		/*
			Function: getAlertsForUser
				Gets a list of pages with content older than their Max Content Age that a user follows.

			Parameters:
				user - The user id to pull alerts for or a user entry

			Returns:
				An array of arrays containing a page title, path, and id.
		*/
		
		public static function getAlertsForUser($user): array
		{
			$user = new User($user);
			
			// Alerts is empty, nothing to check
			$user->Alerts = array_filter((array) $user->Alerts);
			
			if (!count($user->Alerts)) {
				return [];
			}
			
			// If we care about the whole tree, skip the madness.
			if ($user->Alerts[0] == "on") {
				return SQL::fetchAll("SELECT nav_title, id, path, updated_at,
											 DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age
									  FROM bigtree_pages 
									  WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
									  ORDER BY current_age DESC");
			} else {
				$where = [];
				
				// We're going to generate a list of pages the user cares about first to get their paths.
				foreach ($user->Alerts as $alert => $status) {
					$where[] = "id = '".SQL::escape($alert)."'";
				}
				
				// Now from this we'll build a path query
				$path_query = [];
				$path_strings = SQL::fetchAllSingle("SELECT path FROM bigtree_pages WHERE ".implode(" OR ", $where));
				
				foreach ($path_strings as $path) {
					$path = SQL::escape($path);
					$path_query[] = "path = '$path' OR path LIKE '$path/%'";
				}
				
				// Only run if the pages requested still exist
				if (count($path_query)) {
					// Find all the pages that are old that contain our paths
					return SQL::fetchAll("SELECT nav_title, id, path, updated_at,
												 DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age
										  FROM bigtree_pages 
										  WHERE max_age > 0 AND (".implode(" OR ", $path_query).") AND 
												DATEDIFF('".date("Y-m-d")."',updated_at) > max_age 
										  ORDER BY current_age DESC");
				}
			}
			
			return [];
		}
		
		/*
			Function: getBreadcrumb
				Returns an array of titles, links, and ids for pages above this page.
			
			Parameters:
				ignore_trunk - Ignores trunk settings when returning the breadcrumb
			
			Returns:
				An array of arrays with "title", "link", and "id" of each of the pages above the current (or passed in) page.
			
			See Also:
				<getBreadcrumbByPage>
		*/
		
		public function getBreadcrumb(bool $ignore_trunk = false): array
		{
			return Navigation::getBreadcrumb($this, $ignore_trunk);
		}
		
		/*
			Function: getChangeExists
				Returns whether pending changes exist for the page.

			Returns:
				true or false
		*/
		
		public function getChangeExists(): bool
		{
			return SQL::exists("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $this->ID]);
		}
		
		/*
			Function: getChildren
				Returns an array of non-archived child pages.

			Parameters:
				sort - Sort order (defaults to "nav_title ASC")
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/
		
		public function getChildren(string $sort = "nav_title ASC", bool $return_arrays = false): array
		{
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages
									   WHERE parent = ? AND archived != 'on' ORDER BY $sort", $this->ID);
			
			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}
			
			return $children;
		}

		/*
			Function: getHiddenChildren
				Returns an alphabetic array of hidden child pages.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page entries.
		*/
		
		public function getHiddenChildren(bool $return_arrays = false): array
		{
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND in_nav = '' AND archived != 'on' 
									   ORDER BY nav_title ASC", $this->ID);
			
			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}
			
			return $children;
		}
		
		/*
			Function: getLineage
				Returns all the ids of pages above the provided page ID not including the homepage.
			
			Parameters:
				page - A Page ID
		
			Returns:
				Array of IDs
		*/
		
		public static function getLineage(int $page): array
		{
			$parents = [];
			
			while ($page = SQL::fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $page)) {
				$parents[] = $page;
			}
			
			return $parents;
		}
		
		/*
			Function: getParentPage
				Returns the parent page for the current page (or null if this page is top level)
			
			Returns:
				Page object or null.
		*/
		
		public function getParentPage(): ?Page
		{
			if (empty($this->Parent) || $this->Parent === -1) {
				return null;
			}
			
			return new Page($this->Parent);
		}
		
		/*
			Function: getPendingChange
				Returns a PendingChange object that applies to this page.

			Returns:
				A PendingChange object.
		*/
		
		public function getPendingChange(): ?PendingChange
		{
			$change = SQL::fetch("SELECT * FROM bigtree_pending_changes
								  WHERE `table` = 'bigtree_pages' AND `item_id` = ?", $this->ID);
			
			if (empty($change)) {
				return null;
			}
			
			return new PendingChange($change);
		}
		
		/*
			Function: getPendingChildren
				Returns an array of pending child pages of this page with most recent first.

			Parameters:
				in_nav - true returns pages in navigation, false returns hidden pages

			Returns:
				An array of pending page titles/ids.
		*/
		
		public function getPendingChildren(bool $in_nav = true): array
		{
			$nav = $titles = [];
			$changes = SQL::fetchAll("SELECT * FROM bigtree_pending_changes 
									  WHERE pending_page_parent = ? AND `table` = 'bigtree_pages' AND item_id IS NULL 
									  ORDER BY date DESC", $this->ID);
			
			foreach ($changes as $change) {
				$page = json_decode($change["changes"], true);
				
				// Only get the portion we're asking for
				if (($page["in_nav"] && $in_nav) || (!$page["in_nav"] && !$in_nav)) {
					$page["bigtree_pending"] = true;
					$page["title"] = $page["nav_title"];
					$page["id"] = "p".$change["id"];
					
					$titles[] = $page["nav_title"];
					$nav[] = $page;
				}
			}
			
			// Sort by title
			array_multisort($titles, $nav);
			
			return $nav;
		}
		
		/*
			Function: getPageDraft
				Returns a page along with pending changes applied.

			Parameters:
				id - The ID of the page (or "p" + id for pulling an unpublished page)

			Returns:
				A Page object or false if no page was found.
		*/
		
		public static function getPageDraft($id): ?Page
		{
			if (is_numeric($id)) {
				// Numeric id means the page is live.
				if (!Page::exists($id)) {
					return null;
				}
				
				$page = new Page($id);
				$page->OpenGraph = OpenGraph::getData("bigtree_pages", $id);
				
				// Get pending changes for this page.
				$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes 
									   WHERE `table` = 'bigtree_pages' AND item_id = ?", $page->ID);
				
			} else {
				// If it's prefixed with a "p" then it's a pending entry.
				$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `id` = ?", substr($id, 1));
				
				if (empty($pending)) {
					return null;
				}
				
				$page = new Page;
			}
			
			// No changes, just return
			if (empty($pending)) {
				return $page;
			}
			
			// Decode the tag changes, apply them back.
			$tags_changes = json_decode($pending["tags_changes"], true);
			
			if (is_array($tags_changes)) {
				$page->setTags($tags_changes);
			}
			
			// Regular changes
			$changes = Link::decode(json_decode($pending["changes"], true));
			
			// Protected vars, force an inheritance
			$draft_data = new \stdClass;
			$draft_data->ChangesApplied = true;
			$draft_data->ChangeID = $pending["id"];
			$draft_data->UpdatedAt = $pending["date"];
			$draft_data->LastEditedBy = $pending["user"] ?: $page->LastEditedBy;
			
			if (empty($page->ID)) {
				$draft_data->ID = "p".$pending["id"];
			}
			
			$page->inherit($draft_data);
			
			// Public vars -- things here only exist if there's been a change so we do the weird isset syntax
			$page->Parent = ($pending["pending_page_parent"] !== null) ? $pending["pending_page_parent"] : $page->Parent;
			
			isset($changes["expire_at"]) ? ($page->ExpireAt = $changes["expire_at"] ?: false) : false;
			isset($changes["external"]) ? ($page->External = $changes["external"]) : false;
			isset($changes["in_nav"]) ? ($page->InNav = $changes["in_nav"] ? true : false) : false;
			isset($changes["meta_description"]) ? ($page->MetaDescription = $changes["meta_description"]) : false;
			isset($changes["nav_title"]) ? ($page->NavigationTitle = $changes["nav_title"]) : false;
			isset($changes["new_window"]) ? ($page->NewWindow = $changes["new_window"] ? true : false) : false;
			isset($changes["open_graph"]) ? ($page->OpenGraph = $changes["open_graph"]) : false;
			isset($changes["path"]) ? ($page->Path = $changes["path"]) : false;
			isset($changes["publish_at"]) ? ($page->PublishAt = $changes["publish_at"] ?: false) : false;
			isset($changes["content"]) ? ($page->Content = $changes["content"]) : false;
			isset($changes["route"]) ? ($page->Route = $changes["route"]) : false;
			isset($changes["seo_invisible"]) ? ($page->SEOInvisible = $changes["seo_invisible"] ? true : false) : false;
			isset($changes["template"]) ? ($page->Template = $changes["template"]) : false;
			isset($changes["title"]) ? ($page->Title = $changes["title"]) : false;
			isset($changes["trunk"]) ? ($page->Trunk = $changes["trunk"]) : false;
			
			return $page;
		}
		
		/*
			Function: getRevision
				Returns a revision of the page.

			Parameters:
				id - The id of the page revision to apply.

			Returns:
				A duplicate Page object with changes applied.
		*/
		
		public static function getRevision(int $id): Page
		{
			$revision = SQL::fetch("SELECT * FROM bigtree_page_revisions WHERE id = ?", $id);
			
			// Get original page
			$page = new Page($revision["page"]);
			
			$page->External = Link::decode($revision["external"]);
			$page->MetaDescription = $revision["meta_description"];
			$page->NewWindow = $revision["new_window"] ? true : false;
			$page->Content = array_filter((array) @json_decode($revision["content"], true));
			$page->Revision = new \stdClass;
			$page->Template = $revision["template"];
			$page->Title = $revision["title"];
			
			$page->Revision->Author = $revision["author"];
			$page->Revision->Description = $revision["saved_description"];
			$page->Revision->Saved = $revision["saved"] ? true : false;
			$page->Revision->UpdatedAt = $revision["updated_at"];
			
			return $page;
		}
		
		/*
			Function: getSEORating
				Returns the SEO rating for the page.
		
			Parameters:
				id - A page ID or null for a non-existant page
				template - A template ID, !, or empty
				title - The page title
				meta_description - The page meta description
				content - The page content array
				last_updated - A timestamp the page was last updated

			Returns:
				An array of SEO data.
				"score" reflects a score from 0 to 100 points.
				"recommendations" is an array of recommendations to improve SEO score.
				"color" is a color reflecting the SEO score.

				Score Parameters
				- Having a title - 5 points
				- Having a unique title - 5 points
				- Title does not exceed 72 characters and has at least 4 words - 5 points
				- Having a meta description - 5 points
				- Meta description that is less than 165 characters - 5 points
				- Having an h1 - 10 points
				- Having page content - 5 points
				- Having at least 300 words in your content - 15 points
				- Having links in your content - 5 points
				- Having external links in your content - 5 points
				- Having one link for every 120 words of content - 5 points
				- Readability Score - up to 20 points
				- Fresh content - up to 10 points
		*/
		
		public static function getSEORating(?int $id, ?string $template, ?string $title, ?string $meta_description,
											?array $content, ?string $last_updated  ): array
		{
			if (empty($template) || $template == "!") {
				return ["score" => 100, "recommendations" => [], "color" => "#00CC00"];
			}

			$template = new Template($template);
			$template_fields = [];
			$h1_field = "";
			$body_fields = [];
			
			// Figure out what fields should behave as the SEO body and H1
			if (is_array($template->Fields)) {
				foreach ($template->Fields as $item) {
					if (isset($item["seo_body"]) && $item["seo_body"]) {
						$body_fields[] = $item["id"];
					}
					if (isset($item["seo_h1"]) && $item["seo_h1"]) {
						$h1_field = $item["id"];
					}
					$template_fields[$item["id"]] = $item;
				}
			}
			
			// Default to page_header and page_content
			if (!$h1_field && $template_fields["page_header"]) {
				$h1_field = "page_header";
			}
			
			if (!count($body_fields) && $template_fields["page_content"]) {
				$body_fields[] = "page_content";
			}
			
			$textStats = new \DaveChild\TextStatistics\TextStatistics;
			$recommendations = [];
			
			$score = 0;
			
			// Check if they have a page title.
			if ($title) {
				$score += 5;
				
				// They have a title, let's see if it's unique
				$count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages 
										   WHERE title = ? AND id != ?", $title, $id);
				if (!$count) {
					// They have a unique title
					$score += 5;
				} else {
					$recommendations[0] = $count - 1;
				}
				
				// Check title length / word count
				$words = $textStats->wordCount($title);
				$length = mb_strlen($title);
				
				// Minimum of 4 words, less than 72 characters
				if ($words >= 4 && $length <= 72) {
					$score += 5;
				} else {
					$recommendations[1] = true;
				}
			} else {
				$recommendations[2] = true;
			}
			
			// Check for meta description
			if ($meta_description) {
				$score += 5;
				
				// They have a meta description, let's see if it's no more than 165 characters.
				$meta_length = mb_strlen($meta_description);
				if ($meta_length <= 165) {
					$score += 5;
				} else {
					$recommendations[3] = $meta_length;
				}
			} else {
				$recommendations[4] = true;
			}
			
			if (!is_array($content)) {
				$content = [];
			}
			
			// Check for an H1
			if (!$h1_field || !empty($content[$h1_field])) {
				$score += 10;
			} else {
				$recommendations[5] = true;
			}
			
			// Check the content!
			if (!count($body_fields)) {
				// If this template doesn't for some reason have a seo body resource, give the benefit of the doubt.
				$score += 65;
			} else {
				$regular_text = "";
				$stripped_text = "";
				
				foreach ($body_fields as $field) {
					if (!is_array($content[$field])) {
						$regular_text .= $content[$field]." ";
						$stripped_text .= strip_tags($content[$field])." ";
					}
				}

				// Check to see if there is any content
				if ($stripped_text) {
					$score += 5;
					$words = $textStats->wordCount($stripped_text);
					$readability = $textStats->fleschKincaidReadingEase($stripped_text);
					
					if ($readability < 0) {
						$readability = 0;
					}

					$number_of_links = substr_count($regular_text, "<a ");
					$number_of_external_links = substr_count($regular_text, 'href="http://');
					
					// See if there are at least 300 words.
					if ($words >= 300) {
						$score += 15;
					} else {
						$recommendations[6] = $words;
					}
					
					// See if we have any links
					if ($number_of_links) {
						$score += 5;
						
						// See if we have at least one link per 120 words.
						if (floor($words / 120) <= $number_of_links) {
							$score += 5;
						} else {
							$recommendations[7] = [$number_of_links, floor($words / 120)];
						}

						// See if we have any external links.
						if ($number_of_external_links) {
							$score += 5;
						} else {
							$recommendations[8] = true;
						}
					} else {
						$recommendations[9] = true;
					}
					
					// Check on our readability score.
					if ($readability >= 90) {
						$score += 20;
					} else {
						$read_score = round(($readability / 90), 2);
						$recommendations[10] = $read_score * 100;
						$score += ceil($read_score * 20);
					}
				} else {
					$recommendations[11] = true;
				}
				
				// Check page freshness
				$updated = strtotime($last_updated);
				$age = time() - $updated - (60 * 24 * 60 * 60);
				
				// See how much older it is than 2 months.
				if ($age > 0) {
					$age_score = 10 - floor(2 * ($age / (30 * 24 * 60 * 60)));
					
					if ($age_score < 0) {
						$age_score = 0;
					}

					$score += $age_score;
					$recommendations[12] = ceil(2 + ($age / (30 * 24 * 60 * 60)));
				} else {
					$score += 10;
				}
			}
			
			$color = "#008000";
			
			if ($score <= 50) {
				$color = Utils::colorMesh("#FD9725", "#D32F2F", 100 - (100 * $score / 50));
			} elseif ($score <= 80) {
				$color = Utils::colorMesh("#00A370", "#FD9725", 100 - (100 * ($score - 50) / 30));
			}
			
			return ["score" => $score, "recommendations" => $recommendations, "color" => $color];
		}
		
		/*
			Function: getTags
				Returns an array of tags for this page.

			Parameters:
				return_arrays - Set to true to return arrays rather than objects.
			
			Returns:
				An array of Tag objects.
		*/
		
		public function getTags(bool $return_arrays = false): array
		{
			$tags = SQL::fetchAll("SELECT bigtree_tags.*
								   FROM bigtree_tags JOIN bigtree_tags_rel 
								   ON bigtree_tags.id = bigtree_tags_rel.tag 
								   WHERE bigtree_tags_rel.`table` = 'bigtree_pages' AND bigtree_tags_rel.entry = ?
								   ORDER BY bigtree_tags.tag", $this->ID);
			
			if (!$return_arrays) {
				foreach ($tags as &$tag) {
					$tag = new Tag($tag);
				}
			}
			
			return $tags;
		}
		
		/*
			Function: getTopLevelPageID
				Returns the highest level ancestor's ID for the page.
			
			Parameters:
				trunk_as_top_level - Treat a trunk as top level navigation instead of a new "site" (will return the trunk instead of the first nav item below the trunk if encountered) - defaults to false
			
			Returns:
				The ID of the highest ancestor of the given page.
			
			See Also:
				<getToplevelNavigationId>
			
		*/
		
		public function getTopLevelPageID(bool $trunk_as_top_level = false): ?int
		{
			if ($this->Parent === BIGTREE_SITE_TRUNK) {
				return $this->ID;
			}
			
			if (BIGTREE_SITE_TRUNK === $this->ID) {
				return null;
			}
			
			$paths = [];
			$path = "";
			$parts = explode("/", $this->Path);
			
			foreach ($parts as $part) {
				$path .= "/".$part;
				$path = ltrim($path, "/");
				$paths[] = "path = '".SQL::escape($path)."'";
			}
			
			// Get either the trunk or the top level nav id.
			$page = SQL::fetch("SELECT id, trunk, path FROM bigtree_pages
								WHERE (".implode(" OR ", $paths).") AND (trunk = 'on' OR parent = '".BIGTREE_SITE_TRUNK."')
								ORDER BY LENGTH(path) DESC LIMIT 1");
			
			// If we don't want the trunk, look higher
			if (!$trunk_as_top_level && $page["trunk"] && $page["parent"] && $page["parent"] !== BIGTREE_SITE_TRUNK) {
				// Get the next item in the path.
				$id = SQL::fetchSingle("SELECT id FROM bigtree_pages 
										WHERE (".implode(" OR ", $paths).") AND LENGTH(path) < ".strlen($page["path"])." 
										ORDER BY LENGTH(path) ASC LIMIT 1");
				if ($id) {
					return $id;
				}
			}
			
			return $page["id"];
		}
		
		/*
			Function: getUserAccessLevel
				Returns the permission level for the logged in user to the page

			Parameters:
				user - Optional User object to check permissions for (defaults to logged in user)
			
			Returns:
				A permission level ("p" for publisher, "e" for editor, null for none)
		*/
		
		public function getUserAccessLevel(?User $user = null): ?string
		{
			return Auth::user($user)->getAccessLevel($this);
		}
		
		/*
			Function: getUserCanModifyChildren
				Checks whether the logged in user can modify all child pages.
				Assumes we already know that we're a publisher of the parent.

			Returns:
				true if the user can modify all the page children, otherwise false.
		*/
		
		public function getUserCanModifyChildren(): bool
		{
			// Make sure a user is logged in
			if (is_null(Auth::user()->ID)) {
				trigger_error("Property UserCanModifyChildren not available outside logged-in user context.");
				
				return false;
			}
			
			if (Auth::user()->Level > 0) {
				return true;
			}
			
			$path = SQL::escape($this->Path);
			$descendant_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE path LIKE '$path%'");
			
			// Check all the descendants for an explicit "no" or "editor" permission
			foreach ($descendant_ids as $id) {
				$permission = Auth::user()->Permissions["page"][$id];
				
				if ($permission == "n" || $permission == "e") {
					return false;
				}
			}
			
			return true;
		}
		
		/*
			Function: getVisibleChildren
				Returns a list children of the page that are in navigation.
			
			Parameters:
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page objects.
		*/
		
		public function getVisibleChildren(bool $return_arrays = false): array
		{
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND in_nav = 'on' AND archived != 'on' 
									   ORDER BY position DESC, id ASC", $this->ID);
			
			if (!$return_arrays) {
				foreach ($children as &$child) {
					$child = new Page($child);
				}
			}
			
			return $children;
		}
		
		/*
			Function: regeneratePath
				Calculates the full navigation path for the page, sets $this->Path, and returns the path.

			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/
		
		public function regeneratePath(?int $id = null, array $path = []): string
		{
			if (is_null($id)) {
				$id = $this->ID;
			}
			
			$page_info = SQL::fetch("SELECT route, parent FROM bigtree_pages WHERE id = ?", $id);
			$path[] = $page_info["route"];
			
			// If we have a higher page, keep recursing up
			if ($page_info["parent"] > 0) {
				return $this->regeneratePath($page_info["parent"], $path);
			}
			
			// Reverse since we started with the deepest level but want the inverse
			$this->Path = implode("/", array_reverse($path));
			
			return $this->Path;
		}
		
		/*
			Function: search
				Searches for pages based on the provided fields.

			Parameters:
				query - Query string to search against.
				fields - Fields to search.
				max - Maximum number of results to return.
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of Page objects.
		*/
		
		public static function search(string $query, array $fields = ["nav_title"], int $max = 10,
							   bool $return_arrays = false): array
		{
			// Since we're in JSON we have to do stupid things to the /s for URL searches.
			$query = str_replace('/', '\\\/', $query);
			
			$terms = explode(" ", $query);
			$where_parts = ["archived != 'on'"];
			
			foreach ($terms as $term) {
				$term = SQL::escape($term);
				
				$or_parts = [];
				foreach ($fields as $field) {
					$or_parts[] = "`$field` LIKE '%$term%'";
				}
				
				$where_parts[] = "(".implode(" OR ", $or_parts).")";
			}
			
			$pages = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE ".implode(" AND ", $where_parts)." 
									ORDER BY nav_title LIMIT $max");
			
			if (!$return_arrays) {
				foreach ($pages as &$page) {
					$page = new Page($page);
				}
			}
			
			return $pages;
		}
		
		/*
			Function: unarchive
				Unarchives the page and all its children that inherited archived status.
		*/
		
		public function unarchive(): void
		{
			SQL::update("bigtree_pages", $this->ID, ["archived" => ""]);
			$this->unarchiveChildren();
			
			AuditTrail::track("bigtree_pages", $this->ID, "update", "unarchived");
		}
		
		/*
			Function: unarchiveChildren
				Unarchives the page's children that have the archived_inherited status.
		*/
		
		public function unarchiveChildren(?int $id = null): void
		{
			// Allow for recursion
			if (is_null($id)) {
				$id = $this->ID;
			}
			
			$child_ids = SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE parent = ? AND archived_inherited = 'on'", $id);
			
			foreach ($child_ids as $child_id) {
				AuditTrail::track("bigtree_pages", $child_id, "update", "inherited unarchived state from parent");
				$this->unarchiveChildren($child_id);
			}
			
			// Unarchive this level
			SQL::query("UPDATE bigtree_pages SET archived = '', archived_inherited = '' 
						WHERE parent = ? AND archived_inherited = 'on'", $id);
		}
		
		/*
			Function: uncache
				Removes any static cache copies of this page.
		*/
		
		public function uncache(): void
		{
			FileSystem::deleteFile(md5(json_encode(["bigtree_htaccess_url" => $this->Path])).".page");
			FileSystem::deleteFile(md5(json_encode(["bigtree_htaccess_url" => $this->Path."/"])).".page");
		}
		
		/*
			Function: save
				Saves and validates object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			// Explicitly set to -1 in the constructor for null pages
			if ($this->ID === -1) {
				$new = static::create(
					$this->Trunk,
					$this->Parent,
					$this->InNav,
					$this->NavigationTitle,
					$this->Title,
					$this->Route,
					$this->MetaDescription,
					$this->SEOInvisible,
					$this->Template,
					$this->External,
					$this->NewWindow,
					$this->Content,
					$this->PublishAt,
					$this->ExpireAt,
					$this->MaxAge,
					$this->Tags,
					$this->ChangeID
				);
				$this->inherit($new);
				
				return true;
			}
			
			// Homepage must have no route
			if ($this->ID == 0) {
				$this->Route = "";
				$this->Parent = -1;
			} else {
				// Get a unique route
				$original_route = $route = Link::urlify($this->Route);
				$x = 2;
				
				// Reserved paths.
				if ($this->Parent == 0) {
					while (file_exists(SERVER_ROOT."site/".$route."/")) {
						$route = $original_route."-".$x;
						$x++;
					}
					$reserved_routes = Router::getReservedRoutes();
					while (in_array($route, $reserved_routes)) {
						$route = $original_route."-".$x;
						$x++;
					}
				}
				
				// Make sure route isn't longer than 250
				$route = substr($route, 0, 250);
				
				// Existing pages.
				while (SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages 
										 WHERE `route` = ? AND parent = ? AND id != ?", $route, $this->Parent, $this->ID)) {
					$route = $original_route."-".$x;
					$x++;
				}
				
				$this->Route = $route;
			}
			
			// Update the path in case the parent changed
			$original_path = $this->Path;
			$this->regeneratePath();
			
			// Remove old paths from the redirect list, add a new redirect and update children
			if ($this->Path != $original_path) {
				$this->updateChildrenPaths();
				
				SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $this->Path, $original_path);
				SQL::insert("bigtree_route_history", [
					"old_route" => $original_path,
					"new_route" => $this->Path
				]);
			}
			
			$seo = static::getSEORating($this->ID, $this->Template, $this->Title, $this->MetaDescription,
										$this->Content, date("Y-m-d H:i:s"));
			
			$update = [
				"trunk" => $this->Trunk ? "on" : "",
				"parent" => $this->Parent,
				"in_nav" => $this->InNav ? "on" : "",
				"nav_title" => Text::htmlEncode($this->NavigationTitle),
				"title" => Text::htmlEncode($this->Title),
				"path" => $this->Path,
				"route" => $this->Route,
				"meta_description" => Text::htmlEncode($this->MetaDescription),
				"seo_invisible" => $this->SEOInvisible ? "on" : "",
				"template" => $this->Template,
				"external" => $this->External ? Link::encode($this->External) : "",
				"new_window" => $this->NewWindow ? "on" : "",
				"content" => (array) $this->Content,
				"publish_at" => $this->PublishAt ?: null,
				"expire_at" => $this->ExpireAt ?: null,
				"max_age" => $this->MaxAge ?: 0,
				"seo_score" => $seo["score"],
				"seo_recommendations" => $seo["recommendations"],
				"last_edited_by" => Auth::user()->ID ?: $this->LastEditedBy
			];
			
			// Check if this data is exactly the same as the pending copy -- if it is, attribute it to the change author, not the publisher
			$pending = SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = ?", $this->ID);
			
			if ($pending && $pending["user"] != Auth::user()->ID) {
				$exact = true;
				$changes = Link::decode(json_decode($pending["changes"], true));
				
				foreach ($changes as $key => $value) {
					if ($update[$key] != $value) {
						$exact = false;
					}
				}
				
				if ($exact) {
					$update["last_edited_by"] = $pending["user"];
					AuditTrail::track("bigtree_pages", $this->ID, "update", "updated via publisher", $pending["user"]);
					AuditTrail::track("bigtree_pages", $this->ID, "update", "published");
				} else {
					AuditTrail::track("bigtree_pages", $this->ID, "update", "updated");
				}
			} else {
				AuditTrail::track("bigtree_pages", $this->ID, "update", "updated");
			}
			
			SQL::update("bigtree_pages", $this->ID, $update);
			
			// Remove any pending drafts
			SQL::delete("bigtree_pending_changes", ["table" => "bigtree_pages", "item_id" => $this->ID]);
			
			// Handle tags
			$existing_tags = SQL::fetchAllSingle("SELECT tag FROM bigtree_tags_rel
												  WHERE `table` = 'bigtree_pages' AND `entry` = ?", $this->ID);
			SQL::delete("bigtree_tags_rel", ["table" => "bigtree_pages", "entry" => $this->ID]);
			$this->Tags = array_unique($this->Tags);
			$tag_ids = [];

			foreach ($this->Tags as $tag) {
				SQL::insert("bigtree_tags_rel", [
					"table" => "bigtree_pages",
					"entry" => $this->ID,
					"tag" => $tag->ID
				]);

				$tag_ids[] = $tag->ID;
			}
			
			$update_tags = array_merge($existing_tags, $tag_ids);

			if (count($update_tags)) {
				Tag::updateReferenceCounts($update_tags);
			}
			
			// If this page is a trunk in a multi-site setup, wipe the cache
			foreach (Router::$SiteRoots as $site_path => $site_data) {
				if ($site_data["trunk"] == $this->ID) {
					unlink(SERVER_ROOT."cache/bigtree-multi-site-cache.json");
				}
			}
			
			return true;
		}
		
		/*
			Function: setTags
				Sets the page object's tags property with a new set of tag IDs.

			Parameters:
				tags - An array of tag IDs
		*/
		
		public function setTags(?array $tags)
		{
			$this->Tags = [];
			
			if (is_array($tags)) {
				foreach ($tags as $tag_id) {
					$this->Tags[] = new Tag($tag_id);
				}
			}
		}
		
		/*
			Function: update
				Updates the page's properties and saves changes to the database.
				Creates a new page revision and erases old page revisions.

			Parameters:
				trunk - Trunk status (true or false)
				parent - Parent page ID
				in_nav - In navigation (true or false)
				nav_title - Navigation title
				title - Page title
				route - Page route (leave empty to auto generate)
				meta_description - Page meta description
				seo_invisible - Pass "X-Robots-Tag: noindex" header (true or false)
				template - Page template ID
				external - External link (or empty)
				new_window - Open in new window from nav (true or false)
				content - Array of page data
				publish_at - Publish time (or false for immediate publishing)
				expire_at - Expiration time (or false for no expiration)
				max_age - Content age (in days) allowed before alerts are sent (0 for no max)
				tags - An array of tag IDs to apply to the page (optional)
		*/
		
		public function update(?bool $trunk, ?int $parent, ?bool $in_nav, string $nav_title, string $title,
							   string $route, string $meta_description, ?bool $seo_invisible, string $template,
							   ?string $external, ?bool $new_window, ?array $content, ?string $publish_at,
							   ?string $expire_at, ?int $max_age, ?array $tags = []): void
		{
			// Save a page revision
			PageRevision::create($this);
			
			// Count the page revisions, if we have more than 10, delete any that are more than a month old
			$revision_count = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_page_revisions 
												WHERE page = ? AND saved = ''", $this->ID);
			
			if ($revision_count > 10) {
				SQL::query("DELETE FROM bigtree_page_revisions 
							WHERE page = ? AND updated_at < '".date("Y-m-d", strtotime("-1 month"))."' AND saved = '' 
							ORDER BY updated_at ASC LIMIT ".($revision_count - 10), $this->ID);
			}
			
			// Remove this page from the cache
			$this->uncache();
			
			// We have no idea how this affects the nav, just wipe it all.
			if ($this->NavigationTitle != $nav_title ||
				$this->Route != $route ||
				$this->InNav != $in_nav ||
				$this->Parent != $parent
			) {
				Router::clearCache();
			}
			
			$this->Content = $content;
			$this->ExpireAt = ($expire_at && $expire_at != "NULL") ? date("Y-m-d", strtotime($expire_at)) : null;
			$this->External = $external;
			$this->InNav = $in_nav;
			$this->MaxAge = $max_age;
			$this->MetaDescription = $meta_description;
			$this->NavigationTitle = $nav_title;
			$this->NewWindow = $new_window;
			$this->Parent = $parent;
			$this->PublishAt = ($publish_at && $publish_at != "NULL") ? date("Y-m-d", strtotime($publish_at)) : null;
			$this->Route = $route ?: Link::urlify($nav_title);
			$this->SEOInvisible = $seo_invisible;
			$this->Template = $template;
			$this->Title = $title;
			$this->Trunk = $trunk;
	
			// Converts tag IDs into tag objects
			$this->setTags($tags);
			
			$this->save();
		}
		
		/*
			Function: updateChildrenPaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.
		*/
		
		public function updateChildrenPaths(?int $page = null): void
		{
			// Allow for recursion
			if ($page !== null) {
				$parent_path = SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $page);
			} else {
				$parent_path = $this->Path;
				$page = $this->ID;
			}
			
			$child_pages = SQL::fetchAll("SELECT id, route, path FROM bigtree_pages WHERE parent = ?", $page);
			
			foreach ($child_pages as $child) {
				$new_path = $parent_path."/".$child["route"];
				
				if ($child["path"] != $new_path) {
					// Remove any overlaps
					SQL::query("DELETE FROM bigtree_route_history WHERE old_route = ? OR old_route = ?", $new_path, $child["path"]);
					
					// Add a new redirect
					SQL::insert("bigtree_route_history", [
						"old_route" => $child["path"],
						"new_route" => $new_path
					]);
					
					// Update the primary path
					SQL::update("bigtree_pages", $child["id"], ["path" => $new_path]);
					AuditTrail::track("bigtree_pages", $child["id"], "update", "inherited path change");
					
					// Update all this page's children as well
					$this->updateChildrenPaths($child["id"]);
				}
			}
		}
		
		/*
			Function: updateParent
				Changes the page's parent and adjusts child pages.
				Sets a special audit trail entry for "moved".

			Parameters:
				parent - The new parent page ID.
		*/
		
		public function updateParent(int $parent): void
		{
			$this->Parent = $parent;
			$this->save();
			
			// Track a movement
			AuditTrail::track("bigtree_pages", $this->ID, "update", "moved to another parent");
		}
		
		/*
			Function: updatePosition
				Sets the position of the page without adjusting other columns.

			Parameters:
				position - The position to set.
		*/
		
		public function updatePosition(int $position): void
		{
			$this->Position = $position;
			SQL::update("bigtree_pages", $this->ID, ["position" => $position]);
			AuditTrail::track("bigtree_pages", $this->ID, "update", "changed positioned");
		}
		
	}
	