<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST["clear"])) {
        $files = get_option('upload_path') . '/stories*.zip';
        $t = exec("rm $files", $r);
        print "<p>Old zip files deleted.</p>";
        exit(0);
    }
	$in = "";
	foreach ($_POST as $val):
		$in .= $val . ",";
	endforeach;

//PREPARE SQL
	$sql = "SELECT p.ID, u.user_nicename, p.post_title, p.post_content, p.guid ";
	$sql .= "FROM " . $wpdb->prefix . "posts as p, ". $wpdb->prefix ."users as u ";
	$sql .= "WHERE p.ID in (". rtrim($in, ",") . ") AND p.post_type = 'post' AND p.post_status = 'publish' AND u.ID = p.post_author ";
	$sql .= "GROUP BY p.ID ";
	$sql .= "ORDER BY p.post_date desc";

	$rows = $wpdb->get_results($sql);
	if ($rows) :
	    # create zip file
        $f = 'stories-'.time().'.zip';
        $upload_dir = wp_upload_dir();
        print_r($upload_dir);
        $dir = $upload_dir['path'];
	    $filename = $dir.'/'.$f;
	    #print 'filename:' . $filename;
        if (! is_dir($dir)) {
            print '<p>Upload directory: ' . $dir .'<br/>';
            print 'ERROR: Directory does not exist.<br/></p>';
            exit(0);
        }
        if (! is_writable($dir)) {
            print '<p>Upload directory: ' . $dir .'<br/>';
            print 'ERROR: Directory is not writable.<br/></p>';
            exit(0);        
        }
        #$path = str_replace(WP_CONTENT_DIR, '', $dir);
        $url = $upload_dir['url'] . '/'. $f;
	    $zip = new ZipArchive;
	    $res = $zip->open($filename, ZipArchive::CREATE) or die('Could not create file.');
	    if ($res == TRUE) {
	        $zip->addEmptyDir('stories');
    		foreach ($rows as $row) {
    		    $story = $row->post_title . "\n";
    		    $story .= $row->user_nicename . "\n\n";
    		    $story .= $row->post_content;
                $story = strip_tags($story);
                $story = iconv("UTF-8", "ascii//IGNORE", $story);
                $story = preg_replace("/&amp;/", "&", $story);

    		    $zip_name = "stories/" . $row->post_title . ".txt";
    		    $zip->addFromString($zip_name, $story);
    		}
    		$zip->close();  

            ?>

            <div id="content" class="narrowcolumn">

            	<p>
                You can download your zip file <a href="<?= $url ?>">here</a>.
            	</p>

            </div>

            <?php 

	    } else {
	        print "Could not create zip file";
	    }
	endif;
	
} else {

$sql = "SELECT p.ID, u.user_nicename, p.post_title, SUM(LENGTH(p.post_content) - LENGTH(REPLACE(p.post_content, ' ', ''))+1) wordcount, p.guid ";
$sql .= "FROM " .$wpdb->prefix . "posts as p, ". $wpdb->prefix ."users as u ";
$sql .= "WHERE u.ID = p.post_author AND p.post_type = 'post' and p.post_status = 'publish' ";
$sql .= "GROUP BY p.ID ";
$sql .= "ORDER BY p.post_date desc";
$dumprows = $wpdb->get_results($sql);
#get_header();
?>

<div id="content" class="narrowcolumn">
	
	<p>
		<form method="post" action="">
	<?php
		if ($dumprows) :
			foreach ($dumprows as $dump) :
		?>
			<input type="checkbox" name="post_<?= $dump->ID ?>" value="<?= $dump->ID ?>"/><a href="<?= $dump->guid ?>"><?= $dump->post_title ?></a>  - <?= $dump->user_nicename ?> (<?= $dump->wordcount ?> words)<br/>
		<?php
			endforeach;
		endif;
	?>
		<p><input type="submit" value="Generate Zip File"/></p>
		</form>
	</p>
	
	<p>
	<form method="post" action="">
	<input type="hidden" name="clear" value="1"/>
	<input type="submit" value="Clear Old Zip Files"/>
	</form>
	</p>
</div>

<?php 
#	get_footer(); 
}
?>
