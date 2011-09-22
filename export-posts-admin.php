<?php
require('export-posts-class.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($_POST["submit"] == 'Clear Old Zip Files') {
        clear_old_zips();
        print "<p>Old zip files deleted.</p>";
        exit(0);
    }
    $in =  $_POST['selected_values'];

	$sql = "SELECT p.ID, u.ID as user_id, u.display_name, p.post_title, p.post_content, p.post_date, p.guid, ";
    $sql .= "SUM(LENGTH(p.post_content) - LENGTH(REPLACE(p.post_content, ' ', ''))+1) as words ";
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
    		    $xml = "";
                if ($export_posts_tag) {
    		        # update the status to printed
                    $tag = get_or_create_tag($export_posts_tag);
                    $wpdb->insert($wpdb->term_relationships, 
                        array('object_id' => $row->ID, 'term_taxonomy_id' => $tag['term_taxonomy_id'], 'term_order' => 0),
                        array('%d', '%s', '%d'));
                    
                    $wpdb->query($sql);
                    # add export-posts-date meta
                    update_post_meta($row->ID, 'export-posts-date', date('m/d/Y H:i:s'));
                }

                $feature_image = get_featured_image($row->ID);   

                if ($feature_image) {
                    $image_xml = "<featured_image>";
                    if ($_POST['output'] == 'html') {
                        $image_text = "<br/><br/>Featured Image: ";
                    } else {
                        $image_text = "\n\nFeatured Image: ";
                    }
                    $image_text .= $feature_image;
                    $image_xml .= $feature_image;
                    $image_xml .= "</featured_image>\n";
                }
                
                $story = '';
				$xml_title = htmlspecialchars($row->post_title);
                $xml .= "<". strtolower(strip_to_alpha_only($row->post_title)) .">";
                if ($_POST['title']) {
    		        $story = $row->post_title . "\n";
    		        $xml .= "<title>". $xml_title . "</title>\n";
    		    }
    		    if ($_POST['author']) {
    		        $user_title = get_user_meta($row->user_id, 'user_title', True);
    		        $story .= $row->display_name . "\n";
    		        $xml .= "<author>". $row->display_name . "</author>\n";
    		        if ($user_title) {
    		            $story .= $user_title . "\n";
    		            $xml .= "<author-title>" . $user_title . "</author-title>\n";
    		        }
    		    }
    		    if ($_POST['date']) {
    		        $story .= $row->post_date . "\n";
    		        $xml .= "<date>". $row->post_date . "</date>\n";
    		    }
    		    if ($_POST['content']) {
    		        $html_story = $story . "\n" . $row->post_content;
    		        $story .= "\n" . replace_empty_lines(strip_tags($row->post_content));
    		        $xml .= "<content>". strip_tags($row->post_content, '<b><i><strong><em>') . "\t\t</content>\n";
    		    }
    		
                if (($_POST['photo']) && ($feature_image)) {
                    $story .= $image_text;         
                    $xml .= $image_xml;   
                }
                
                if ($_POST['comment_count']) {
                    $comment_count = wp_count_comments($row->ID);
                    $xml .= "<comments>" . $comment_count->total_comments . "</comments>\n";
                }
                
                if ($_POST['short_link']) {
                    $xml .= "<shortlink>" . wp_get_shortlink($row->ID) . "</shortlink>\n";
                }
                
                if ($_POST['e_section_quote']) {
                    $quote = get_post_meta($row->ID, 'e-sec-quote', true);
                    $xml .= "<e-sec-quote>" . $quote . "</e-sec-quote>\n";
                }
                
               # $story .= "\nWords: " . $row->words . ", Inches: " . export_posts_inches('inches', $row->ID);
                
                    
    		    $xml .= "</". strtolower(strip_to_alpha_only($row->post_title)) .">\n";
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
    		        $xml = preg_replace("/\r\n\r\n+/s","\r\n",$xml);        
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
$dumprows = get_post_list($_GET['category'], $_GET['status'], $_GET['keyword'], $_GET['tag'], $_GET['post_category']);
?>

<div id="content" class="narrowcolumn">

	<p>
    	    <p >
            <form id="filter" name="filter" action="" method="GET" style="text-align: center; width: 650px;">
            <input type="hidden" name="page" value="Export-Posts"/>
            <?php
                if (array_key_exists('print_tag', $_GET)) { $print_tag = $_GET['print_tag']; }
                else { $print_tag = "0"; }
                $ptags = get_print_tags_drop_down($print_tag);
                if ($ptags) { echo "E-Section: "; echo $ptags; }
            ?>
    	    Keyword: <input type="text" name="keyword" value="<?php echo $_GET['keyword'] ?>" size="8"/>
    	    <input type="submit" name="submit" value="Filter"/>
            </form>
            </p>
            <div id="cat-filter">
            <form id="cat-filter-form" name="cat-filter" action="" method="GET">
            
            <ul>
            <?php 
            $args = array(
                'hide_empty' => 1,
                'hierarchical' => true,
                'popular_cats' => array(),
                'selected_cats' => array(),
                'title_li' => '<h3>Categories</h3>',
                'walker' => new Export_Posts_Walker_Category_Checklist
            );
            wp_list_categories($args); ?>
            </ul>
            <input type="submit" value="Filter"/>
            </form>
            </div>
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
            <select id="export_post_entries" multiple="multiple" size="12" style="height: auto; width: 650px;">
    	    <?php
    			foreach ($dumprows as $dump) :
    			    $options = get_option( COLUMN_INCHES_OPTION );
        		    $word_inches = $options['words_inch'];
        		    $value = export_posts_words_to_inches_value($dump->ID, $dump->words, $word_inches);
    		?>
                <option value='<?php echo $dump->ID; ?>' id='<?php echo $value; ?>' class="export_post_entry">
                    <?php echo $dump->post_title; ?>
                    (<?php echo $dump->user_nicename; ?> - <?php echo export_posts_words_to_inches($dump->words, $word_inches); ?> inches)
                </option>
    		<?php
    			endforeach;
    		echo "</select>"; 
    		

    	?>		
        <p style="text-align: center; width: 650px;">
        <input type="button" id="add_selected" value="Add Selected Posts"/>
        <input type="button" id="remove_selected" value="Remove Selected Posts"/> 
        <input type="button" id="add_all" value="Add All Posts"/> 
        <input type="button" id="remove_all" value="Remove All Posts"/> 
        </p>
    	<p>
    	<span class="inches">Total Inches:
    	<?php for ($i = 0; $i < count($word_inches); $i++) { 
            echo "<span id='inches_" . $i . "'>0</span>";
            if ($i != (count($word_inches) -1)) { echo " / "; }
    	} ?>
    	</span>
    	Selected Posts:<br/>
        <select id="selected" multiple="multiple" size="12" style="height: auto; width: 650px;">
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
        <p class="xml_only" style="display: none;">
            <input type="checkbox" name="comment_count" value="1"/> Comment Count
            <input type="checkbox" name="e_section_quote" value="1"/> E-Section Quote
            <input type="checkbox" name="short_link" value="1"/> Short Link
            
        </p>
        <p style="text-align: center; width: 750px;">
            <input type="hidden" id="selected_values" name="selected_values" value="0"/>
		    <input type="submit" name="submit" value="Generate Zip File" id="zip"/>
		</p>
		</form>
	    <?php
	    #print '<em style="color: #ddd;">posts: ' . count($dumprows) . '</em>';
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
            jQuery.post("<?php echo $_SERVER['PHP_SELF'] ?>?page=Export-Posts", jQuery("#export_posts").serialize(), function(data){
                window.location.replace(jQuery(data).find("#zip_url").attr("href"));
            });
            return false;

        });
        
        jQuery("#remove_all").click(function() {
            jQuery("#selected option").each(function(index, elem) {
                var selectElem = $(elem);
                var obj = jQuery.parseJSON(selectElem.attr('id'));
                <?php 	for ($i = 0; $i < count($word_inches); $i++) { ?>
                    var curInches = parseInt(jQuery("#inches_<?php echo $i; ?>").html());
                    jQuery("#inches_<?php echo $i;?>").html((curInches-parseInt(obj.inches_<?php echo $i; ?>)));
                <?php } ?>
                if (selectElem.val()) {
                    jQuery('#selected option[value=' + selectElem.val() + ']').remove();
                    jQuery('#export_post_entries').append('<option value=\''+selectElem.val()+'\' id=\''+selectElem.attr('id')+'\'>' + 
                    selectElem.text() + '</option>');
                }
            });
        });

        jQuery("#remove_selected").click(function() {
            jQuery("#selected :selected").each(function(index, elem) {
                var selectElem = $(elem);
                var obj = jQuery.parseJSON(selectElem.attr('id'));
                <?php 	for ($i = 0; $i < count($word_inches); $i++) { ?>
                    var curInches = parseInt(jQuery("#inches_<?php echo $i; ?>").html());
                    jQuery("#inches_<?php echo $i;?>").html((curInches-parseInt(obj.inches_<?php echo $i; ?>)));
                <?php } ?>
                if (selectElem.val()) {
                    jQuery('#selected option[value=' + selectElem.val() + ']').remove();
                    jQuery('#export_post_entries').append('<option value="'+selectElem.val()+'" id=\''+selectElem.attr('id')+'\'>' + 
                    selectElem.text() + '</option>');
                }
            });
        });

        jQuery("#add_selected").click(function() {
            jQuery("#export_post_entries :selected").each(function(index, elem) {
                var selectElem = $(elem);
                var obj = jQuery.parseJSON(selectElem.attr('id'));
                <?php 	for ($i = 0; $i < count($word_inches); $i++) { ?>
                    var curInches = parseInt(jQuery("#inches_<?php echo $i; ?>").html());
                    jQuery("#inches_<?php echo $i;?>").html((curInches+parseInt(obj.inches_<?php echo $i; ?>)));
                <?php } ?>
                if (selectElem.val()) {
                    jQuery('#export_post_entries option[value=' + selectElem.val() + ']').remove();
                    jQuery('#selected').append('<option value="'+selectElem.val()+'" id=\''+selectElem.attr('id')+'\'>' + 
                    selectElem.text() + '</option>');
                }
            });
        });

        jQuery("#add_all").click(function() {
            jQuery("#export_post_entries option").each(function(index, elem) {
                var selectElem = $(elem);
                var obj = jQuery.parseJSON(selectElem.attr('id'));
                <?php 	for ($i = 0; $i < count($word_inches); $i++) { ?>
                    var curInches = parseInt(jQuery("#inches_<?php echo $i; ?>").html());
                    jQuery("#inches_<?php echo $i;?>").html((curInches+parseInt(obj.inches_<?php echo $i; ?>)));
                <?php } ?>
                if (selectElem.val()) {
                    jQuery('#export_post_entries option[value=' + selectElem.val() + ']').remove();
                    jQuery('#selected').append('<option value="'+selectElem.val()+'" id=\''+selectElem.attr('id')+'\'>' + 
                    selectElem.text() + '</option>');
                }
            });
        });
        
        jQuery("#cat-filter-form input:submit").click(function() {
            var params = jQuery("#filter").serialize()
            if (jQuery("#cat-filter form").serialize()) {
                params = params + '&' + jQuery("#cat-filter form").serialize();
            }
            window.location.search = params;
            return false; 
        });

        jQuery("#filter input:submit").click(function() {
            var params = jQuery("#filter").serialize()
            if (jQuery("#cat-filter form").serialize()) {
                params = params + '&' + jQuery("#cat-filter form").serialize();
            }
            window.location.search = params;
            return false; 
        });
        
        jQuery("input[name='output']").change(function() {
            if (jQuery("input[name='output']:checked").val() == 'xml') {
                jQuery('.xml_only').show();
            } else {
                jQuery('.xml_only').hide();
                jQuery("input[name='comment_count']").removeAttr("checked");
                jQuery("input[name='e_section_quote']").removeAttr("checked");
                jQuery("input[name='short_link']").removeAttr("checked");
                
            }
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
    $sql = "SELECT DISTINCT p.post_status FROM ".$wpdb->posts." p WHERE p.post_type='post' AND p.post_status <> 'trash'";
    $rows = $wpdb->get_results($sql);
    return $rows;
}

function get_post_list($category, $status="publish", $keyword, $tag, $categories) {
    global $wpdb;
    $cat_ids = "";
    if (($category) && ($category != 'all')) {

        $cat_sql = "SELECT term_id FROM " . $wpdb->terms . " ";
        $cat_sql .= "WHERE slug = '" . $category . "'";

        $cat_row = $wpdb->get_row($cat_sql);
        $cat_ids = "(". $cat_row->term_id . ")";
    }
    
    if (($tag) && ($tag != 'all')) {
        $tag_sql = "SELECT l.object_id FROM " . $wpdb->term_relationships . " l, ";
        $tag_sql .= $wpdb->term_taxonomy . " t where t.term_taxonomy_id = l.term_taxonomy_id ";
        $tag_sql .= " and t.term_id = " . $tag; 
    }
    
    $sql =  "SELECT p.ID, u.user_nicename, p.post_title, ";
    $sql .= "SUM(LENGTH(p.post_content) - LENGTH(REPLACE(p.post_content, ' ', ''))+1) as words ";
    $sql .= "\nFROM " . $wpdb->terms . " t";
    
    $sql .= "\nINNER JOIN " . $wpdb->term_taxonomy . " tt ON t.term_id = tt.term_id";
    $sql .= "\nINNER JOIN " . $wpdb->term_relationships . " wpr ON wpr.term_taxonomy_id = tt.term_taxonomy_id";
    $sql .= "\nINNER JOIN " . $wpdb->posts . " p ON p.ID = wpr.object_id";
    $sql .= "\nINNER JOIN " . $wpdb->users . " u ON p.post_author = u.ID";
    $sql .= "\nWHERE 1=1 AND ";
    
    if (($category) && ($category != 'all') || $categories) {
        if ($categories) {
            $cat_ids = $cat_row->term_id . ",";
            foreach ($categories as $cat) {
                $cat_ids .= $cat . ",";
            }
            $cat_ids = trim($cat_ids, ",");
            $cat_ids = "(" . $cat_ids . ")";
        } 
        $catsql = "SELECT DISTINCT r.object_id ";
        $catsql .= "FROM ". $wpdb->terms . " t, " . $wpdb->term_taxonomy . " x, ";
        $catsql .= $wpdb->term_relationships . " r ";
        $catsql .= "WHERE t.term_id IN ". $cat_ids ." and t.term_id = x.term_id ";
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
    
	$limit = get_option('export_posts_no_posts');
	if ($limit == "") { $limit = 150; }
    $print_tag_options = get_option('print_tag_options');
    $print_tags = array();
    for ($i=1; $i<=$print_tag_options['number_of_sections']; $i++) {
        for ($j=1; $j<=$print_tag_options['page_count_' . $i]; $j++) {
            $tag = $print_tag_options['section_name_' . $i] . $j;
            array_push($print_tags, "'" . $tag . "'");
        }
    }
    #print "<pre>" . implode(",", $print_tags); print "</pre>";
    
    if (count($print_tags)) {
        $sql .= "AND t.name IN (". implode(",", $print_tags) . ") ";
    }
    
     if (array_key_exists('print_tag', $_GET)) {
         $sql .= "AND t.name='". $_GET['print_tag'] . "' ";
     }

    $sql .= "GROUP BY p.ID ORDER BY p.post_date DESC";
	$sql .= " LIMIT " . $limit;

   # print  '<p><pre>' . $sql . '</pre></p>';
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

function strip_to_alpha_only($string)
{
     $pattern = '/[^a-zA-Z]/';
     return preg_replace($pattern, '', $string);
}


function export_posts_inches($column_name, $post_id) {
    $rv = "";
	if ($column_name === 'inches') {
        $post = get_post($post_id);
		
		// Remove HTML tags
		$post_plaintext = strip_tags( $post->post_content );
		
		// Get column inches from options table
		$options = get_option( COLUMN_INCHES_OPTION );
		$column_inches = $options['words_inch'];
		$words = str_word_count( $post_plaintext );
		$num_counts = count($column_inches);
		
		// Display column inches
		for ($i = 0; $i < $num_counts; $i++) {
			$column_inch = $column_inches[$i];
			$name = $column_inch['name'];
			$inches = ceil( $words / $column_inch['count'] );
			$rv .= "<span title='$name: $inches column inch" . ($inches != 1 ? "es" : "") . "' style='border-bottom: 1px dotted #666; cursor: help;'>$inches</span>";
			if ($num_counts  > 1 && $i < $num_counts - 1)
				$rv .= ' / ';
		}		
	}
	return $rv;
}

function export_posts_words_to_inches($words, $words_inches) {
    $rv = "";
    $num_counts = count($words_inches);
	
	// Display column inches
	for ($i = 0; $i < $num_counts; $i++) {
		$column_inch = $words_inches[$i];
		$name = $column_inch['name'];
		$inches = ceil( $words / $column_inch['count'] );
		$rv .= "<span title='$name: $inches column inch" . ($inches != 1 ? "es" : "") . "' style='border-bottom: 1px dotted #666; cursor: help;'>$inches</span>";
		if ($num_counts  > 1 && $i < $num_counts - 1)
			$rv .= ' / ';
	}
	return $rv;
}

function export_posts_words_to_inches_value($id, $words, $words_inches) {
    $rv = array('id'=>$id);
    $num_counts = count($words_inches);
	
	// Display column inches
	for ($i = 0; $i < $num_counts; $i++) {
		$column_inch = $words_inches[$i];
		$name = $column_inch['name'];
		$inches = ceil( $words / $column_inch['count'] );
		$rv['inches_' . $i] = $inches;
	}
	return json_encode($rv);
}

function get_print_tags_drop_down($print_tag) {
    $print_tag_options = get_option('print_tag_options');
    $print_tags = '<select name="print_tag">';
    $print_tags .= '<option value="0">--- select e_section ---</option>';
    for ($i=1; $i<=$print_tag_options['number_of_sections']; $i++) {
        for ($j=1; $j<=$print_tag_options['page_count_' . $i]; $j++) {
            $tag = $print_tag_options['section_name_' . $i] . $j;
            $selected = "";
            if ($tag == $print_tag) { $selected = ' selected="selected"'; }
            $print_tags .= '<option'. $selected .'>' . $tag . '</option>';
         }
    }
    $print_tags .= '</select>';   
    return $print_tags;    
}
?>

