<?php
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	// Get crop and thumb prefix info
	include BigTree::path("admin/modules/files/process/_crop-setup.php");

	while ($file = readdir($dir)) {
		if ($file == "." || $file == "..") {
			continue;
		}

		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if ($extension == "jpg" || $extension == "jpeg" || $extension == "png" || $extension == "gif") {
			$file_name = SITE_ROOT."files/temporary/".$admin->ID."/".$file;
			$min_height = intval($preset["min_height"]);
			$min_width = intval($preset["min_width"]);

			list($width, $height, $type, $attr) = getimagesize($file_name);

			// Scale up content that doesn't meet minimums
			if ($width < $min_width || $height < $min_height) {
				BigTree::createUpscaledImage($file_name, $file_name, $min_width, $min_height);
			}

			$field = [
				"title" => $file,
				"file_input" => [
					"tmp_name" => $file_name,
					"name" => $file,
					"error" => 0
				],
				"settings" => [
					"directory" => "files/resources/",
					"preset" => "default"
				]
			];

			$output = $admin->processImageUpload($field);

			if ($output) {
				$admin->createResource($bigtree["commands"][0], $output, $file, "image", $crop_prefixes, $thumb_prefixes);
			}
		}
	}

	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
		"return_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
		"crop_key" => $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"])
	];

	if (count($bigtree["errors"])) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p>Some images uploaded encountered <?=count($bigtree["errors"])?> error<?php if (count($bigtree["errors"]) != 1) { ?>s<?php } ?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column field">File</span>
				<span class="view_column error">Error</span>
			</header>
			<ul>
				<?php foreach ($bigtree["errors"] as $error) { ?>
				<li>
					<section class="view_column field"><?=$error["field"]?></section>
					<section class="view_column error"><?=$error["error"]?></section>
				</li>
				<?php } ?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>files/crop/<?=intval($bigtree["commands"][0])?>/" class="button blue">Continue</a>
	</footer>
</div>
<?php
	} else {
		BigTree::redirect(ADMIN_ROOT."files/crop/".intval($bigtree["commands"][0])."/");
	}
