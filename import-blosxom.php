<?php
define('BLOSXOM_RSSFILE', '/home/edavis/index.rss20');
// Example:
// define('BLOSXOM_RSSFILE', '/home/foobar/rss.xml');
// or if it's in the same directory as import-blosxom.php
// define('BLOSXOM_RSSFILE', 'rss.xml');

$post_author       = 1; // Author to import posts as author ID
$post_status       = 'publish'; // Status for imported posts: 'publish', 'draft', 'private'
$comment_status    = 'open'; // Allow comments for imported posts: 'open', 'closed'
$ping_status       = 'open'; // Allow pings for imported posts: 'open', 'closed'
$timezone_offset   = 0; // GMT offset of posts your importing
$import_writebacks = 1; // import writebacks (1 = yes, 0 = no)

function unhtmlentities($string) // From php.net for < 4.3 compat
{
   $trans_tbl = get_html_translation_table(HTML_ENTITIES);
   $trans_tbl = array_flip($trans_tbl);
   return strtr($string, $trans_tbl);
}

$add_hours = intval($timezone_offset);
$add_minutes = intval(60 * ($timezone_offset - $add_hours));

if (!file_exists('../wp-config.php'))
{
    die("There doesn't seem to be a wp-config.php file. You must install WordPress before you import any entries.");
}

require('../wp-config.php');

$step = $_GET['step'];
if (!$step) $step = 0;
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<title>WordPress &rsaquo; Import from RSS</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style media="screen" type="text/css">
  body {
    font-family: Georgia, "Times New Roman", Times, serif;
    margin-left: 20%;
    margin-right: 20%;
  }
  #logo {
    margin: 0;
    padding: 0;
    background-image: url(http://wordpress.org/images/logo.png);
    background-repeat: no-repeat;
    height: 60px;
    border-bottom: 4px solid #333;
  }
  #logo a {
    display: block;
    text-decoration: none;
    text-indent: -100em;
    height: 60px;
  }
  p {
    line-height: 140%;
  }
</style>
</head>
<body>

<h1 id="logo"><a href="http://wordpress.org/">WordPress</a></h1>

<?php
switch($step)
{
    case 0:
?>

<p>
Howdy! This importer allows you to extract posts from a Blosxom generated RSS 2.0
feed file into your blog.  This tool also has the ability to import writebacks from
the Blosxom <code>writeback</code> or <code>writeback_plus</code> plugins.
</p>
<p>
The feed should be generated using the following Blosxom <code>.rss20</code> theme.
If you don't use themes then simply break the theme up into the necessary individual
flavour files.
</p>
<p>
Blosxom RSS 2.0 Wordpress Import theme file:
</p>
<p><pre>
  &lt;!-- blosxom theme .rss20 --&gt;
  
  &lt;!-- blosxom content_type text/xml --&gt;
  
  &lt;!-- blosxom head --&gt;
  &lt;?xml version="1.0"?&gt;
  &lt;rss version="2.0"&gt;
    &lt;channel&gt;
      &lt;title&gt;$blog_title&lt;/title&gt;
      &lt;link&gt;$url&lt;/link&gt;
      &lt;description&gt;$blog_description&lt;/description&gt;
      &lt;language&gt;$blog_language&lt;/language&gt;
      &lt;copyright&gt;Get Lost!&lt;/copyright&gt;
      &lt;generator&gt;Blosxom&lt;/generator&gt;
      &lt;ttl&gt;180&lt;/ttl&gt;
  
  &lt;!-- blosxom date --&gt;
  
  &lt;!-- blosxom story --&gt;
  
      &lt;item&gt;
        &lt;title&gt;$title&lt;/title&gt;
        &lt;link&gt;$url$path/$fn.html&lt;/link&gt;
        &lt;description&gt;&lt;![CDATA[$body]]&gt;&lt;/description&gt;
        &lt;pubDate&gt;$dw, $da $mo $yr $hr:$min:00 PST&lt;/pubDate&gt;
        &lt;category&gt;&lt;@filesystem.path_basename path="$path" output="yes" /&gt;&lt;/category&gt;
        &lt;guid isPermaLink="true"&gt;$url$path/$fn.html&lt;/guid&gt;
        &lt;author&gt;Administrator&lt;/author&gt;
        $writeback::writebacks
      &lt;/item&gt;
  
  &lt;!-- blosxom writeback --&gt;
  
        &lt;wb&gt;
          &lt;wb_name&gt;$writeback::name&lt;/wb_name&gt;
          &lt;wb_url&gt;$writeback::url&lt;/wb_url&gt;
          &lt;wb_date&gt;$writeback::date&lt;/wb_date&gt;
          &lt;wb_ip&gt;$writeback::ip&lt;/wb_ip&gt;
          &lt;wb_title&gt;$writeback::title&lt;/wb_title&gt;
          &lt;wb_comment&gt;&lt;![CDATA[$writeback::comment]]&gt;&lt;/wb_comment&gt;
        &lt;/wb&gt;
  
  &lt;!-- blosxom foot --&gt;
  
    &lt;/channel&gt;
  &lt;/rss&gt;
</pre></p>
<p>
If you look carefully at the above theme you will see the <i>category</i> field is filled
in using the <code>interpolate_fancy</code> plugin which calls the <code>path_basename</code>
function within the <code>filesystem</code> plugin.  This code results in the basename of
the file path of the post to be used as the category name.  For example, if a post exists
at <code>/software/blosxom/hacks.txt</code>, the path is <code>/software/blosxom</code>
and the resulting category will be <i>blosxom</i>.  This might not be what you want and
you have a couple options.  First is to remove the <i>category</i> field from the
theme resulting in every post being posted in the <i>Uncategorized</i> category.   Then
you'll need to recategorize your posts manually using the Wordpress admin pages.  Second
is to write, or politely ask someone else to write, a Blosxom plugin that will break apart
the post path and create multiple <i>category </i> fields for the post.  This will
essentially cross pollinate the post into multiple categories.  Third... any ideas?
</p>
<p>
The <code>interpolate_fancy</code> plugin can be downloaded
<a href="http://www.blosxom.com/plugins/interpolate/interpolate_fancy.htm">here</a> and
the <code>filesystem</code> plugin can be downloaded
<a href="http://www.insanum.com/software">here</a>.
</p>
<p>
If your Blosxom blog does not have comments or you don't use the <code>writeback</code> or
<code>writeback_plus</code> plugins then remove the <code>$writeback::writebacks</code> line
from the above theme.
</p>
<p>
To get started you must modify your Blosxom blog by installing the above theme and
changing your <code>$num_entries</code> configuration item to a very large number (i.e.
more than the number of posts in your blog).  Then visit your blog using the following
url: <code>http://&lt;your_site&gt;/index.rss20</code>.  Save this output to a file.  This
is your Blosxom generated RSS 2.0 Wordpress Import file.
<p>
Now edit the following line in this file (<code>import-blosxom.php</code>):
</p>
<p>
<code>define('BLOSXOM_RSSFILE', '');</code>
</p>
<p>
You want to define where the RSS file you saved above is.  For example:
</p>
<p>
<code>define('BLOSXOM_RSSFILE', '/home/foobar/rss.xml');</code>
</p>
<p>
You have to do this manually for security reasons.  When you're done
<a href="import-blosxom.php?step=1">reload this page</a> and we'll take you to the
next step.
</p>
<?php if ('' != BLOSXOM_RSSFILE) : ?>
<h2 style="text-align: right;"><a href="import-blosxom.php?step=1">Begin Blosxom RSS Import &raquo;</a></h2>
<?php endif; ?>

<?php
    break;

    case 1:

// Bring in the data
set_magic_quotes_runtime(0);
$datalines = file(BLOSXOM_RSSFILE); // Read the file into an array
$importdata = implode('', $datalines); // squish it
$importdata = str_replace(array("\r\n", "\r"), "\n", $importdata);

preg_match_all('|<item>(.*?)</item>|is', $importdata, $posts);
$posts = $posts[1];

echo "<ol>";

foreach ($posts as $post)
{
    $title = $date = $categories = $content = $post_id = '';

    echo "<li>Importing post... ";

    preg_match('|<title>(.*?)</title>|is', $post, $title);
    $title = addslashes(trim($title[1]));
    $post_name = sanitize_title($title);

    preg_match('|<pubDate>(.*?)</pubDate>|is', $post, $date);
    $date = strtotime($date[1]);
    $post_date = gmdate('Y-m-d H:i:s', $date);

    preg_match_all('|<category>(.*?)</category>|is', $post, $categories);
    $categories = $categories[1];

    preg_match('|<guid.+?>(.*?)</guid>|is', $post, $guid);
    $guid = addslashes(trim($guid[1]));

    preg_match('|<description>(.*?)</description>|is', $post, $content);
    $content = str_replace(array('<![CDATA[', ']]>'), '', addslashes(trim($content[1])));
    $content = unhtmlentities($content);

    // Clean up content
    $content = preg_replace('|<(/?[A-Z]+)|e', "'<' . strtolower('$1')", $content);
    $content = str_replace('<br>', '<br />', $content);
    $content = str_replace('<hr>', '<hr />', $content);

    // Check for a duplicate
    $duplicate = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE
                                 post_title = '$title' AND post_date = '$post_date'");
    if ($duplicate)
    {
        echo "Post already imported</li>";
        continue;
    }

    // Insert the post into the database
    $wpdb->query("INSERT INTO $wpdb->posts
                  (post_author, post_date,
                   post_date_gmt, post_content,
                   post_title, post_status,
                   comment_status, ping_status,
                   post_name, guid)
                  VALUES
                  ('$post_author', '$post_date',
                  DATE_ADD('$post_date', INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE),
                  '$content', '$title',
                  '$post_status', '$comment_status',
                  '$ping_status', '$post_name', '$guid')");

    $post_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE
                               post_title = '$title' AND post_date = '$post_date'");
    if (!$post_id)
    {
        die("Couldn't get post ID");
    }

    // Insert and associate the categories with the post
    if (count($categories) != 0)
    {
        foreach ($categories as $post_category)
        {
            $post_category = unhtmlentities($post_category);

            // See if the category exists yet
            $cat_id = $wpdb->get_var("SELECT cat_ID from $wpdb->categories WHERE
                                      cat_name = '$post_category'");

            if (!$cat_id && (trim($post_category) != ''))
            {
                $cat_nicename = sanitize_title($post_category);

                $wpdb->query("INSERT INTO $wpdb->categories (cat_name, category_nicename)
                              VALUES ('$post_category', '$cat_nicename')");

                $cat_id = $wpdb->get_var("SELECT cat_ID from $wpdb->categories WHERE
                                          cat_name = '$post_category'");
            }

            if (trim($post_category) == '')
            {
                $cat_id = 1;
            }

            // Double check it's not there already
            $exists = $wpdb->get_row("SELECT * FROM $wpdb->post2cat WHERE
                                      post_id = $post_id AND category_id = $cat_id");

            if (!$exists)
            {
                $wpdb->query("INSERT INTO $wpdb->post2cat (post_id, category_id)
                              VALUES ($post_id, $cat_id)");
            }
        }
    }
    else
    {
        $exists = $wpdb->get_row("SELECT * FROM $wpdb->post2cat WHERE
                                  post_id = $post_id AND category_id = 1");
        if (!$exists)
        {
            $wpdb->query("INSERT INTO $wpdb->post2cat (post_id, category_id)
                          VALUES ($post_id, 1)");
        }
    }

    // Insert the writebacks for the post
    $wbs = '';
    preg_match_all('|<wb>(.*?)</wb>|is', $post, $wbs);
    $wbs = $wbs[1];

    if (!$import_writebacks || (count($wbs) == 0))
    {
        echo "Done!</li>";
        continue;
    }

    foreach ($wbs as $post_wb)
    {
        $wb_name = $wb_url = $wb_email = $wb_date = '';
        $wb_title = $wb_comment = $wb_ip = '';

        preg_match('|<wb_name>(.*?)</wb_name>|is', $post_wb, $wb_name);
        if ($wb_name)
        {
            $wb_name = addslashes(trim($wb_name[1]));
        }

        preg_match('|<wb_url>(.*?)</wb_url>|is', $post_wb, $wb_url);
        if ($wb_url)
        {
            $wb_url = trim($wb_url[1]);

            if (preg_match('|mailto|is', $wb_url) || preg_match('|.+@.+|is', $wb_url))
            {
                $wb_url = '';
                $wb_email = addslashes($wb_url);
            }
            else
            {
                $wb_url = addslashes($wb_url);
                $wb_email = '';
            }
        }
        else
        {
            $wb_url = '';
            $wb_email = '';
        }

        preg_match('|<wb_date>(.*?)</wb_date>|is', $post_wb, $wb_date);
        if ($wb_date)
        {
            $wb_date = trim($wb_date[1]);
			$wb_date = date('Y-m-d H:i:s', strtotime($wb_date));
        }

        preg_match('|<wb_ip>(.*?)</wb_ip>|is', $post_wb, $wb_ip);
        if ($wb_ip)
        {
            $wb_ip = trim($wb_ip[1]);
        }

        preg_match('|<wb_title>(.*?)</wb_title>|is', $post_wb, $wb_title);
        if ($wb_title)
        {
            $wb_title = addslashes(trim($wb_title[1]));
        }

        preg_match('|<wb_comment>(.*?)</wb_comment>|is', $post_wb, $wb_comment);
        if ($wb_comment)
        {
            $wb_comment = str_replace(array('<![CDATA[', ']]>'), '', addslashes(trim($wb_comment[1])));
        }

        if ($wb_title)
        {
            $wb_comment = $wb_title . "<br/>" . $wb_comment;
        }

        $wb_comment = unhtmlentities($wb_comment);

        // Check if it's already there
        if (!$wpdb->get_row("SELECT * FROM $wpdb->comments WHERE
                             comment_date = '$comment_date' AND
                             comment_content = '$comment_content'"))
        {
            $wpdb->query("INSERT INTO $wpdb->comments
                          (comment_post_ID, comment_author,
                           comment_author_email, comment_author_url,
                           comment_author_IP, comment_date,
                           comment_content, comment_approved)
                          VALUES
                          ($post_id, '$wb_name',
                           '$wb_email', '$wb_url',
                           '$wb_ip', '$wb_date',
                           '$wb_comment', '1')");
        }
    }

    echo "Done!</li>";
}
?>

</ol>

<h3>All done. <a href="../">Have fun!</a></h3>

<?php
    break;
}
?>

</body>
</html>

