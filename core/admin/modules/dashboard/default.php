<?php
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."developer/upgrade/database/");
	}

	// Get pane settings
	$extension_settings = $cms->getSetting("bigtree-internal-extension-settings");
	$settings = $extension_settings["dashboard"];

	// Sort the panes
	foreach (BigTree\Dashboard::$CoreOptions as $id => $name) {
		$panes[] = array(
			"id" => $id,
			"name" => $name,
			"disabled" => isset($settings[$id]["disabled"]) ? $settings[$id]["disabled"] : ""
		);
		$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
	}
	foreach (BigTree\Dashboard::$Plugins as $extension => $set) {
		foreach ($set as $id => $name) {
			$id = $extension."*".$id;
			$panes[] = array(
				"id" => $id,
				"name" => $name,
				"disabled" => isset($settings[$id]["disabled"]) ? $settings[$id]["disabled"] : ""
			);
			$positions[] = isset($settings[$id]["position"]) ? $settings[$id]["position"] : 0;
		}
	}
	array_multisort($positions,SORT_DESC,$panes);

	// Draw the panes
	foreach ($panes as $pane) {
		if (!$pane["disabled"]) {
			echo '<div class="dashboard_pane">';
			// Core pane
			if (strpos($pane["id"],"*") === false) {
				include BigTree::path("admin/modules/dashboard/panes/".$pane["id"].".php");
			// Extension pane
			} else {
				list($extension,$id) = explode("*",$pane["id"]);
				include SERVER_ROOT."extensions/$extension/plugins/dashboard/$id.php";
			}
			echo '</div>';
		}
	}