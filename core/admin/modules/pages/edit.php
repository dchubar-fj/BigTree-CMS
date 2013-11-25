<?
	$bigtree["current_page"] = $page;
	$bigtree["resources"] = $page["resources"];
	
	// Show the properties section
	include BigTree::path("admin/modules/pages/_properties.php");
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	$admin->lockCheck("bigtree_pages",$page["id"],"admin/modules/pages/_locked.php",$force);
	
	// Grab template information
	$template_data = $cms->getTemplate($page["template"]);

	// Audit Trail link
	$bigtree["developer_nav_links"][] = array("url" => ADMIN_ROOT."developer/audit/search/?table=bigtree_pages&entry=".$page["id"],"class" => "icon_trail","title" => "Audit Trail");		
	
	$bigtree["form_action"] = "update";
	include BigTree::path("admin/modules/pages/_form.php");
?>