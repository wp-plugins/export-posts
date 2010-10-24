<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($_POST["submit"] == 'Clear Old Zip Files') {
        clear_old_zips();
        print "<p>Old zip files deleted.</p>";
        exit(0);
    }
    $in =  $_POST['selected_values'];

	$sql = "SELECT p.ID, u.display_name, p.post_title, p.post_content, p.post_date, p.guid ";
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

        $url = $upload_dir['url'] . '/'. $f;
	    $zip = new ZipArchive;
	    $res = $zip->open($filename, ZipArchive::CREATE) or die('Could not create file.');
	    if ($res == TRUE) {
	        $zip->addEmptyDir('stories');
            $export_posts_tag = get_option('export_posts_tag');
            if ($export_posts_tag) {
                $sql = "SELECT t.term_taxonomy_id FROM " . $wpdb->term_taxonomy . " t WHERE t.term_id = " . $export_posts_tag;
                $tax_id = $wpdb->get_row($sql);        
            }
    		foreach ($rows as $row) {
                if ($export_posts_tag) {
    		        # update the status to printed
    		        $sql = "INSERT INTO " . $wpdb->term_relationships . " (object_id, term_taxonomy_id, term_order) VALUES ";
    		        $sql .= "(". $row->ID . ", ". $tax_id->term_taxonomy_id . ", 0)";
                    $wpdb->query($sql);
                }
                $images =& get_children('post_type=attachment&post_mime_type=image&post_parent=1');
                
                if ($images) {
                    $image_xml = "\t\t<images>\n";
                    if ($_POST['output'] == 'html') {
                        $image_text = "<br/>Attached Images:<br/>";
                    } else {
                        $image_text = "\nAttached Images:\n";
                    }
                    foreach ($images as $image) {
                        $image_text .= "\t" . $image->guid;
                        if ($_POST['output'] == 'html') {
                            $image_text .= "<br/";
                        } 
                        $image_text .= "\n";
                        $image_xml .= "\t\t\t<image>" . $image->guid . "</image>\n";
                    }
                    $image_xml .= "\t\t</images>\n";
                }
                
                $story = '';
                $xml = "<export-posts>\n";
                $xml .= "\t<post>\n";
                if ($_POST['title']) {
    		        $story = $row->post_title . "\n";
    		        $xml .= "\t\t<title>". $row->post_title . "</title>\n";
    		    }
    		    if ($_POST['author']) {
    		        $story .= $row->display_name . "\n";
    		        $xml .= "\t\t<author>". $row->display_name . "</author>\n";
    		    }
    		    if ($_POST['date']) {
    		        $story .= $row->post_date . "\n";
    		        $xml .= "\t\t<date>". $row->post_date . "</date>\n";
    		    }
    		    if ($_POST['content']) {
    		        $story .= "\n" . $row->post_content;
    		        $xml .= "\t\t<content>". strip_tags($row->post_content) . "\t\t</content>\n";
    		    }
    		
                if ($_POST['photo']) {
                    $story .= $image_text;         
                    $xml .= $image_xml;   
                }
                
                    
    		    $xml .= "\t</post>\n</export-posts>\n";
    		    $extension = ".txt";
    		    if ($_POST['output'] != 'html') {
                    $story = strip_tags($story);
                }
                
                if ($_POST['output'] == 'html') { $extension = ".html"; }
                if ($_POST['output'] == 'xml') { $extension = ".xml"; }


                $story = iconv("UTF-8", "ascii//IGNORE", $story);
                $story = preg_replace("/&amp;/", "&", $story);

    		    $zip_name = "stories/" . $row->post_title . $extension;
    		    if ($_POST['output'] == 'xml') {
    		        $zip->addFromString($zip_name, $xml);
    		    } else {
    		        $zip->addFromString($zip_name, $story);
    		    }
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
$dumprows = get_post_list($_GET['category'], $_GET['status'], $_GET['keyword'], $_GET['tag']);
?>

<div id="content" class="narrowcolumn">

	<p>
    	    <p >
            <form name="filter" action="" method="GET" style="text-align: center; width: 800px;">
            <input type="hidden" name="page" value="Export-Posts"/>
    	    <?php $categories = get_categories(); ?>
    	    Category: <select name="category">
    	    <option value="all">All Categories</option>
 	       <?php
    	        foreach ($categories as $category) :
    	    ?>
    	    <option value="<?php echo $category->slug; ?>" <?php if ($_GET['category'] == $category->slug) { echo " selected"; }?>>
    	    <?php echo $category->cat_name; ?>
    	    </option>
    	    <?php 
    	        endforeach;
    	    ?>
    	 	    </select>
    	 	Tag: <select name="tag">
    	 	<option value="all">All Tags</option>
    	 	<?php
                $sql = "SELECT t.term_id, t.name from " . $wpdb->terms . " t, " . $wpdb->term_taxonomy;
                $sql .= " l where t.term_id=l.term_id and l.taxonomy='post_tag' order by t.name";
            	$tags = $wpdb->get_results($sql);
    	 	    foreach ($tags as $tag):
    	 	?>
    	 	<option value="<?php echo $tag->term_id; ?>" <?php if ($_GET['tag'] == $tag->term_id) { echo " selected"; }?>>
    	 	<?php echo $tag->name; ?>
    	 	</option>
    	 	<?php
    	 	    endforeach;
    	 	?>
    	 	</select>
    	 	Status: <select name="status">
    	 	<option value="all">All Statuses</option>
    	 	<?php
    	 	    $status = get_status_list();
    	 	    foreach ($status as $stat):
    	    ?>
                <option<?php if ($_GET['status'] == $stat->post_status) { echo " selected"; }?>><?php echo $stat->post_status;?></option>
    	    <?php
    	        endforeach;
    	    ?>
    	    </select>
    	    Keyword: <input type="text" name="keyword" value="<?php echo $_GET['keyword'] ?>" size="8"/>
    	    <input type="submit" name="submit" value="Filter"/>
            </form>
            </p>
         	<?php
        		if ($dumprows) :
        	?>
        	    <?php
        	    if ((($_GET['category']) && ($_GET['category'] != 'all')) ||
        	        (($_GET['status']) && ($_GET['status'] != 'all'))) {
        	            echo 'Filtered Posts:<br/>';
        	    } else {
        	            echo 'All Posts:<br/>';
        	    }
        	    ?>
        	<p></p>
    		<form id="export_posts" method="post" action="">
            <select id="export_post_entries" multiple="multiple" size="12" style="height: auto; width: 800px;">
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

    	?>		
        <p style="text-align: center; width: 800px;">
        <input type="button" id="add_selected" value="Add Selected Posts"/>
        <input type="button" id="remove_selected" value="Remove Selected Posts"/> 
        <input type="button" id="add_all" value="Add All Posts"/> 
        <input type="button" id="remove_all" value="Remove All Posts"/> 
        </p>
    	<p>
    	Selected Posts:<br/>
        <select id="selected" multiple="multiple" size="12" style="height: auto; width: 800px;">
    	</select>
		</p>

        <p>
            <b>Output Format</b><br/>
            <input type="radio" name="output" value="text" checked="true"/> Text<br/>
            <input type="radio" name="output" value="xml"/> XML<br/>
            <input type="radio" name="output" value="html"/> HTML
        </p>

        <p>
            <b>Fields to include:</b><br/>
            <input type="checkbox" name="title" value="1" checked="checked"/> Title
            <input type="checkbox" name="author" value="1" checked="checked"/> Author
            <input type="checkbox" name="date" value="1"/> Date
            <input type="checkbox" name="content" value="1" checked="checked"/> Content
            <input type="checkbox" name="photo" value="1"/> Feature Photo
        </p>
        
        <p style="text-align: center; width: 750px;">
            <input type="hidden" id="selected_values" name="selected_values" value="0"/>
		    <input type="submit" name="submit" value="Generate Zip File" id="zip"/>
		</p>
		</form>
	    <?php
	    else:
	    ?>
	    <p style="text-align: center; width: 800px;">
	    No posts match the selected filter.
	    </p>
	    <?php
		endif;
	    ?>
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

function get_status_list() {
    global $wpdb;
    $sql = "SELECT DISTINCT p.post_status FROM ".$wpdb->posts." p WHERE p.post_type='post'";
    $rows = $wpdb->get_results($sql);
    return $rows;
}

function get_post_list($category, $status, $keyword, $tag) {
    global $wpdb;
    
    if (($category) && ($category != 'all')) {

        $cat_sql = "SELECT term_id FROM " . $wpdb->terms . " ";
        $cat_sql .= "WHERE slug = '" . $category . "'";
        $cat_row = $wpdb->get_row($cat_sql);

    }
    
    if (($tag) && ($tag != 'all')) {
        $tag_sql = "SELECT l.object_id FROM " . $wpdb->term_relationships . " l, ";
        $tag_sql .= $wpdb->term_taxonomy . " t where t.term_taxonomy_id = l.term_taxonomy_id ";
        $tag_sql .= " and t.term_id = " . $tag; 
    }
    
    $sql =  "SELECT p.ID, u.user_nicename, p.post_title, ";
    $sql .= "SUM(LENGTH(p.post_content) - LENGTH(REPLACE(p.post_content, ' ', ''))+1) as words ";
    $sql .= "FROM " . $wpdb->posts . " p, " . $wpdb->users . " u ";
    $sql .= "WHERE p.post_author = u.ID and ";
    if (($category) && ($category != 'all')) {
        $catsql = "SELECT r.object_id ";
        $catsql .= "FROM ". $wpdb->terms . " t, " . $wpdb->term_taxonomy . " x, ";
        $catsql .= $wpdb->term_relationships . " r ";
        $catsql .= "WHERE t.slug = '". $category ."' and t.term_id = x.term_id ";
        $catsql .= "and x.term_taxonomy_id = r.term_taxonomy_id";
        $sql .= "p.ID in (" . $catsql . ") ";
    }

    if (($tag) && ($tag != 'all')) {
        $sql .= "p.ID in (" . $tag_sql . ") and ";
    }

    if (($status) && ($status != 'all')) {
        $sql .= "p.post_status='" .$status."' and ";
    }
    if ($keyword) {
        $sql .= "p.post_title like '%" . $keyword ."%' and ";
    }
    $sql .= "p.post_type='post' ";
    
    $sql .= "GROUP BY p.ID ORDER BY p.post_date DESC";
    #print $sql;
    $rows = $wpdb->get_results($sql);

    return $rows;
}

?>

    