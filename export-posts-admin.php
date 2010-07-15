<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($_POST["submit"] == 'Clear Old Zip Files') {
        clear_old_zips();
        print "<p>Old zip files deleted.</p>";
        exit(0);
    }
    $in =  $_POST['selected_values'];

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
        $dir = $upload_dir['path'];
	    $filename = $dir.'/'.$f;
	    #print 'filename:' . $filename;
	    if ($upload_dir['error']) { 
	        print '<p>' . $upload_dir['error'] . '</p>';
	        exit(0);
	    }
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
                You can download your zip file <a href="<?php echo $url; ?>">here</a>.
            	</p>

            </div>

            <?php 

	    } else {
	        print "Could not create zip file";
	    }
	endif;
	
} else {

$dumprows = get_post_list();
?>

<div id="content" class="narrowcolumn">
	
	<p>
		<form id="export_posts" method="post" action="">
		
    	<?php
    		if ($dumprows) :
    	?>  
    	    All Posts:<br/>
            <select id="export_post_entries" multiple="multiple" size="12" style="height: auto; width: 750px;">
    	    <?php
    			foreach ($dumprows as $dump) :
    		?>
                <option value="<?php echo $dump->ID; ?>" class="export_post_entry">
                    <?php echo $dump->post_title; ?>
                    (<?php echo $dump->user_nicename; ?> - <?php echo $dump->words; ?> words)
                </option>
    		<?php
    			endforeach;
    		echo "</select>";
    		endif;
    	?>		
        <p style="text-align: center; width: 750px;">
        <input type="button" id="add_selected" value="Add Selected Posts"/>
        <input type="button" id="remove_selected" value="Remove Selected Posts"/> 
        <input type="button" id="add_all" value="Add All Posts"/> 
        <input type="button" id="remove_all" value="Remove All Posts"/> 
        </p>
    	<p>
    	Selected Posts:<br/>
        <select id="selected" multiple="multiple" size="12" style="height: auto; width: 750px;">
    	</select>
		</p>

        <p style="text-align: center; width: 750px;">
            <input type="hidden" id="selected_values" name="selected_values" value="0"/>
		    <input type="submit" name="submit" value="Generate Zip File" id="zip"/>
		</p>
		</form>
	
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        
        jQuery('#export_posts').submit(function() {
            var ids = '';
            jQuery("#selected option").each(function(index, elem) {
                ids += $(elem).val() + ',';
            });
            if (ids.length == 0) { 
                alert("Please select posts to export.");
                return false; 
            }
            ids = ids.slice(0, -1);
            jQuery('#selected_values').val(ids);
        });
        
        jQuery("#remove_all").click(function() {
            jQuery("#selected option").each(function(index, elem) {
                var selectElem = $(elem);
                if (selectElem.val()) {
                    jQuery('#selected option[value=' + selectElem.val() + ']').remove();
                    jQuery('#export_post_entries').append('<option value="'+selectElem.val()+'">' + 
                    selectElem.text() + '</option>');
                }
            });
        });

        jQuery("#remove_selected").click(function() {
            jQuery("#selected :selected").each(function(index, elem) {
                var selectElem = $(elem);
                if (selectElem.val()) {
                    jQuery('#selected option[value=' + selectElem.val() + ']').remove();
                    jQuery('#export_post_entries').append('<option value="'+selectElem.val()+'">' + 
                    selectElem.text() + '</option>');
                }
            });
        });

        jQuery("#add_selected").click(function() {
            jQuery("#export_post_entries :selected").each(function(index, elem) {
                var selectElem = $(elem);
                if (selectElem.val()) {
                    jQuery('#export_post_entries option[value=' + selectElem.val() + ']').remove();
                    jQuery('#selected').append('<option value="'+selectElem.val()+'">' + 
                    selectElem.text() + '</option>');
                }
            });
        });

        jQuery("#add_all").click(function() {
            jQuery("#export_post_entries option").each(function(index, elem) {
                var selectElem = $(elem);
                if (selectElem.val()) {
                    jQuery('#export_post_entries option[value=' + selectElem.val() + ']').remove();
                    jQuery('#selected').append('<option value="'+selectElem.val()+'">' + 
                    selectElem.text() + '</option>');
                }
            });
        });

    });
</script>
<?php 
#	get_footer(); 
}

function clear_old_zips() {
    $files = get_option('upload_path') . '/stories*.zip';
    $t = exec("rm $files", $r);
}

function get_post_list() {
    global $wpdb;
    
    $sql =  "SELECT p.ID, u.user_nicename, p.post_title, ";
    $sql .= "SUM(LENGTH(p.post_content) - LENGTH(REPLACE(p.post_content, ' ', ''))+1) as words ";
    $sql .= "FROM " . $wpdb->posts . " p, " . $wpdb->users . " u ";
    $sql .= "WHERE p.post_author = u.ID and ";
    $sql .= "p.post_type='post' and ";
    $sql .= "p.post_status='publish'";
    $sql .= "GROUP BY p.ID ORDER BY p.post_date DESC";
    
    $rows = $wpdb->get_results($sql);
    
    return $rows;
}

?>

    