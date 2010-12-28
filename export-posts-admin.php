<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($_POST["submit"] == 'Clear Old Zip Files') {
        clear_old_zips();
        print "<p>Old zip files deleted.</p>";
        exit(0);
    }
    $in =  $_POST['selected_values'];

	$sql = "SELECT p.ID, u.ID as user_id, u.display_name, p.post_title, p.post_content, p.post_date, p.guid ";
	$sql .= "FROM " . $wpdb->prefix . "posts as p, ". $wpdb->prefix ."users as u ";
	$sql .= "WHERE p.ID in (". rtrim($in, ",") . ") AND p.post_type = 'post' AND u.ID = p.post_author ";
	$sql .= "GROUP BY p.ID ";
	$sql .= "ORDER BY p.post_date desc";

	$rows = $wpdb->get_results($sql);
   # print "<pre>" . $sql . "</pre>";
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
            $export_posts_tag_id = 0;

    		foreach ($rows as $row) {
                if ($export_posts_tag) {
    		        # update the status to printed
                    $tag = get_or_create_tag($export_posts_tag);
                    $wpdb->insert($wpdb->term_relationships, 
                        array('object_id' => $row->ID, 'term_taxonomy_id' => $tag['term_taxonomy_id'], 'term_order' => 0),
                        array('%d', '%s', '%d'));
                    
                    $wpdb->query($sql);
                }

                $feature_image = get_featured_image($row->ID);   

                if ($feature_image) {
                    $image_xml = "\t\t<featured_image>\n";
                    if ($_POST['output'] == 'html') {
                        $image_text = "<br/><br/>Featured Image: ";
                    } else {
                        $image_text = "\n\nFeatured Image: ";
                    }
                    $image_text .= $feature_image;
                    $image_xml .= $feature_image;
                    $image_xml .= "\t\t</featured_image>\n";
                }
                
                $story = '';
                $xml = "<export-posts>\n";
                $xml .= "\t<post>\n";
                if ($_POST['title']) {
    		        $story = $row->post_title . "\n";
    		        $xml .= "\t\t<title>". $row->post_title . "</title>\n";
    		    }
    		    if ($_POST['author']) {
    		        $user_title = get_user_meta($row->user_id, 'user_title', True);
    		        $story .= $row->display_name . "\n";
    		        $xml .= "\t\t<author>". $row->display_name . "</author>\n";
    		        if ($user_title) {
    		            $story .= $user_title . "\n";
    		            $xml .= "\t\t<author-title>" . $user_title . "</author-title\n";
    		        }
    		    }
    		    if ($_POST['date']) {
    		        $story .= $row->post_date . "\n";
    		        $xml .= "\t\t<date>". $row->post_date . "</date>\n";
    		    }
    		    if ($_POST['content']) {
    		        $html_story = $story . "\n" . $row->post_content;
    		        $story .= "\n" . replace_empty_lines(strip_tags($row->post_content));
    		        $xml .= "\t\t<content>". strip_tags($row->post_content) . "\t\t</content>\n";
    		    }
    		
                if (($_POST['photo']) && ($feature_image)) {
                    $story .= $image_text;         
                    $xml .= $image_xml;   
                }
                
                    
    		    $xml .= "\t</post>\n</export-posts>\n";
    		    $extension = ".txt";
    		    if ($_POST['output'] != 'html') {
                    $story = strip_tags($story);
                } else {
                    $html_head = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\"\n";
                    $html_head .= "\t\"http://www.w3.org/TR/html4/strict.dtd\">\n";
                    $html_head .= "<head>\n\t<title>" . $row->post_title . "</title>\n";
                    $html_head .= "\t<meta http-equiv=\"Content-type\" content=\"text/html; charset=utf-8\">\n";
                    $html_head .= "</head>\n<body>\n";
                    
                    $html_tail = "\n</body>\n</html>\n";
                    $story = nl2br($html_story);
                    $story = $html_head . $story . $html_tail;
                }
                
                if ($_POST['output'] == 'html') { $extension = ".html"; }
                if ($_POST['output'] == 'xml') { $extension = ".xml"; }


               $story = iconv("UTF-8", "ascii//TRANSLIT", $story);

    		    $zip_name = "stories/" . $row->post_title . $extension;
    		    if ($_POST['output'] == 'xml') {
    		        $zip->addFromString($zip_name, $xml);
    		    } else {
    		        $zip->addFromString($zip_name, $story);
    		    }
    		}
    		$zip->close();  

            # update tag_count
            if ($export_posts_tag) {
		        # update the status to printed
                $tag = get_or_create_tag($export_posts_tag);
                $update_tag_sql = "UPDATE " . $wpdb->term_taxonomy . " t SET count=". 
                $update_tag_sql .= "(SELECT count(l.object_id) FROM " . $wpdb->term_relationships . " l ";
                $update_tag_sql .= "WHERE l.term_taxonomy_id = " . $tag['term_taxonomy_id'] . ") ";
                $update_tag_sql .= "WHERE t.term_taxonomy_id = ". $tag['term_taxonomy_id'];
                $wpdb->query($update_tag_sql);
            }
            ?>

            <div id="content" class="narrowcolumn">

            	<p>
                You can download your zip file <a id="zip_url" href="<?php echo $url; ?>">here</a>.
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
            	$export_tag = get_option('export_posts_tag');
    	 	    foreach ($tags as $tag):
    	 	    if ($tag->name == $export_tag) { continue; }
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
    	 	    if (!$_GET['status']) { $_GET['status'] = 'publish'; }
    	 	    $status = get_status_list();
    	 	    foreach ($status as $stat):
    	 	    if ($stat->post_status == 'auto-draft') { continue; }
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
            jQuery.post(window.location, jQuery("#export_posts").serialize(), function(data){
                window.location.replace(jQuery(data).find("#zip_url").attr("href"));
            });
            return false;
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
        $sql .= "p.ID in (" . $catsql . ") and ";
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
    
    if (get_option('export_posts_tag')) {
        $exclude_tag = get_or_create_tag(get_option('export_posts_tag'));
        
        $exclude_tag_sql = "SELECT l.object_id FROM " . $wpdb->term_relationships . " l, ";
        $exclude_tag_sql .= $wpdb->term_taxonomy . " t WHERE t.term_taxonomy_id = l.term_taxonomy_id ";
        $exclude_tag_sql .= " and t.term_id = " . $exclude_tag['term_id'];
        
        $sql .= "p.ID not in (" . $exclude_tag_sql . ") and ";
    }
    
    $sql .= "p.post_type='post' and p.post_status <> 'auto-draft' ";
    
    $sql .= "GROUP BY p.ID ORDER BY p.post_date DESC";
    #print  '<p>' . $sql . '</p>';
    $rows = $wpdb->get_results($sql);

    return $rows;
}
/**
 * Modifies a string to remove al non ASCII characters and spaces.
 */
function slugify($text)
{
    // replace non letter or digits by -
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
 
    // trim
    $text = trim($text, '-');
 
    // transliterate
    if (function_exists('iconv'))
    {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }
 
    // lowercase
    $text = strtolower($text);
 
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
 
    if (empty($text))
    {
        return 'n-a';
    }
 
    return $text;
}

function get_or_create_tag($tag) {
    # this function will create the tag if it doesn't exist
    global $wpdb;
    
    $sql = "SELECT l.term_id, t.term_taxonomy_id FROM " . $wpdb->terms . " l, " . $wpdb->term_taxonomy . " t ";
    $sql .= "WHERE l.term_id=t.term_id and t.taxonomy = 'post_tag' and ";
    $sql .= "l.name = '" .$tag . "'";
    
    $row = $wpdb->get_row($sql);
    
    if ($row) {
        $term_id = $row->term_id;
        $term_taxonomy_id = $row->term_taxonomy_id;
    } else {
        # create the tag
        $wpdb->insert($wpdb->terms, 
            array('name' => $tag, 'slug' => slugify($tag), 'term_group' => 0), 
            array('%s', '%s', '%d'));
        $term_id = $wpdb->insert_id;
        
        # create taxonomy
        $wpdb->insert($wpdb->term_taxonomy, 
            array('term_id' => $term_id, 'taxonomy' => 'post_tag', 'description' => '', 
                'parent' => 0, 'count' => 0),
            array('%d', '%s', '%s', '%d', '%d'));
        $term_taxonomy_id = $wpdb->insert_id;
    }    
    
    return array('term_id' => $term_id, 'term_taxonomy_id' => $term_taxonomy_id);
}

function get_featured_image($post_id) {
    $feature_image = get_the_post_thumbnail($post_id, 'full');
    $feature_image = get_the_post_thumbnail($post_id, 'full');
    
    $images = array();
    if ($feature_image) {
        $doc=new DOMDocument();
        $doc->loadHTML($feature_image);
        $xml=simplexml_import_dom($doc);
        $images=$xml->xpath('//img');
        $feature_image = basename($images[0]['src']) . " ('" . $images[0]['alt'] . "')";
    }
    
    return $feature_image;    
}

function replace_empty_lines($string)
{
    $string = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string);
    return str_replace("\\n\\n", "\\n", $string);
}

?>

