<?php

/*
Plugin Name: FeedBurner Awareness
Plugin URI: http://www.insanum.com/wordpress/feedburner/wordpress-plugin-feedburner-awareness/
Description: Access your feed statistics using the FeedBurner Awareness API.
Author: Eric Davis
Version: 1.1
Author URI: http://www.insanum.com
*/

/*
TODO
 1. Add some meaningful graphs using the GD APIs
 2. Cache feed stats in the database (IMPORTANT)
 3. Things other people come up with
*/

/*
Thanks to the folks at FeedBurner for giving me free access to the
Total Stats PRO services while I developed this plugin... way cool.
*/

/*
LICENSE

This source is submitted to the public domain.  Feel free to use and modify it.
Please provide a comment in your modified source attributing credit for my
original work would be appreciated.  And buy me a beer...

THIS SOFTWARE IS PROVIDED AS IS AND WITHOUT ANY WARRANTY OF ANY KIND.  USE AT
YOUR OWN RISK!
*/

require_once(ABSPATH . 'wp-includes/class-snoopy.php');

$FB_options = array( 'feeduri' => '' );

$FB_uri = 'http://api.feedburner.com/awareness/1.0/';

$FB_data = array();
$FB_cur_entry = '';
$FB_cur_item = '';
$FB_error = FALSE;
$FB_error_msg = '';


function FB_Start()
{
    global $FB_options;

    /*
     * If the options already exist in the database then use them,
     * else initialize the database by storing the defaults.
     */
    $options = get_option('FB_Awareness');
    if ($options == FALSE)
    {
        add_option('FB_Awareness', $FB_options);
    }
    else
    {
        $FB_options = $options;
    }
}

add_action('init', 'FB_Start');


function FB_Fetch($url)
{
    $http = new Snoopy();
    $http->agent = 'Wordpress (FeedBurner Awareness Plugin)';
    $http->read_timeout = 2;

    @$http->fetch($url);

    if (($http->status >= 200) && ($http->status < 300))
    {
        return $http->results;
    }
    else
    {
        return FALSE;
    }
}


function FB_XmlStartElementHandler($xml, $name, $attribs)
{
    global $FB_data;
    global $FB_cur_entry;
    global $FB_cur_item;
    global $FB_error;
    global $FB_error_msg;

    //error_log("start: $name\n");

    if (sizeof($attribs))
    {
        if ($name == 'ENTRY')
        {
            $FB_data[$attribs['DATE']]['CIRCULATION'] = $attribs['CIRCULATION'];
            $FB_data[$attribs['DATE']]['HITS'] = $attribs['HITS'];

            $FB_cur_entry = $attribs['DATE'];
        }
        else if ($name == 'ITEM')
        {
            if ($FB_cur_entry != '')
            {
                $FB_data[$FB_cur_entry]['ITEMS'][$attribs['TITLE']]['URL'] = $attribs['URL'];
                $FB_data[$FB_cur_entry]['ITEMS'][$attribs['TITLE']]['ITEMVIEWS'] = $attribs['ITEMVIEWS'];
                $FB_data[$FB_cur_entry]['ITEMS'][$attribs['TITLE']]['CLICKTHROUGHS'] = $attribs['CLICKTHROUGHS'];

                $FB_cur_item = $attribs['TITLE'];
            }
        }
        else if ($name == 'REFERRER')
        {
            if (($FB_cur_entry != '') && ($FB_cur_item != ''))
            {
                $FB_data[$FB_cur_entry]['ITEMS'][$FB_cur_item]['REFERRERS'][] = $attribs;
            }
        }
        else if ($name == 'ERROR')
        {
            $FB_error = TRUE;
            $FB_error_msg = $attribs['MESSAGE'];
        }
    }
}


function FB_XmlEndElementHandler($xml, $name)
{
    global $FB_cur_entry;
    global $FB_cur_item;

    //error_log("end: $name\n");

    if ($name == 'ENTRY')
    {
        $FB_cur_entry = '';
    }
    else if ($name == 'ITEM')
    {
        $FB_cur_item = '';
    }
}


function FB_XmlDefaultHandler($xml, $data)
{
    //error_log("default: $data\n");
}


function FB_XmlParse($data)
{
    $xml = xml_parser_create();

    xml_set_element_handler($xml, "FB_XmlStartElementHandler", "FB_XmlEndElementHandler");
    xml_set_default_handler($xml, "FB_XmlDefaultHandler");

    $lines = preg_split("/\n/", $data, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($lines as $line)
    {
        if (!xml_parse($xml, $line))
        {
            die(sprintf("XML ERROR: %s\n", xml_error_string(xml_get_error_code($xml))));
        }
    }

    xml_parser_free($xml);
}


function FB_ParseDates($dates)
{
    if (preg_match("/today/i", $dates))
    {
        return date('Y-m-d');
    }
    else if (preg_match("/yesterday/i", $dates))
    {
        return date('Y-m-d', strtotime("1 day ago"));
    }
    else if (preg_match("/last.week/i", $dates))
    {
        return date('Y-m-d', strtotime("1 week ago")) . ',' . date('Y-m-d');
    }
    else if (preg_match("/last.month/i", $dates))
    {
        return date('Y-m-d', strtotime("1 month ago")) . ',' . date('Y-m-d');
    }
    else if (preg_match("/last.year/i", $dates))
    {
        return date('Y-m-d', strtotime("1 year ago")) . ',' . date('Y-m-d');
    }
    else
    {
        return $dates;
    }
}


function FB_GetStats($func, $feeduri, $itemurl, $dates)
{
    global $FB_data;
    global $FB_uri;

    $FB_data = array(); /* XXX clear old data */

    $url = $FB_uri . $func . '?uri=' . $feeduri;

    if ($dates)
    {
        $url = $url . '&dates=' . FB_ParseDates($dates);
    }

    if ($itemurl)
    {
        $url = $url . '&itemurl=' . $itemurl;
    }

    $data = FB_Fetch($url);

    if ($data)
    {
        FB_XmlParse($data);
    }
    else
    {
        return FALSE;
    }
}


function FB_DisplayBasicStats($feeduri)
{
    global $FB_data;

    $min_circ = 0;
    $max_circ = 0;
    $hits = 0;

    foreach ($FB_data as $entry)
    {
        if (!$min_circ && !$max_circ)
        {
            $min_circ = $max_circ = $entry['CIRCULATION'];
        }
        else if ($min_circ > $entry['CIRCULATION'])
        {
            $min_circ = $entry['CIRCULATION'];
        }
        else if ($max_circ < $entry['CIRCULATION'])
        {
            $max_circ = $entry['CIRCULATION'];
        }

        $hits += $entry['HITS'];
    }

    if (count($FB_data) == 1)
    {
        echo "<h3>$feeduri: $min_circ Circulation with $hits Hits</h3>\n";
    }
    else
    {
        echo "<h3>$feeduri: Min $min_circ Max $max_circ Circulation with $hits Hits</h3>\n";
    }
}


function FB_DisplayFeedData($feeduri)
{
    global $FB_data;

    FB_DisplayBasicStats($feeduri);

    echo "<table style=\"margin: 0px auto;\" cellspacing=\"5\" cellpadding=\"5\">\n";

    echo "<tr style=\"background: #bbb;\">\n";
    echo "<th scope=\"col\">Date</th>\n";
    echo "<th scope=\"col\">Circulation</th>\n";
    echo "<th scope=\"col\">Hits</th>\n";
    echo "</tr>\n";

    foreach ($FB_data as $date=>$entry)
    {
        echo "<tr style=\"background: #eee;\">\n";
        echo "<th scope=\"row\">$date</th>\n";
        echo "<td>" . $entry['CIRCULATION'] . "</td>\n";
        echo "<td>" . $entry['HITS'] . "</td>\n";
        echo "</tr>\n";
    }

    echo "</table>\n";
}


function FB_DisplayReferrers($item)
{
    if (count($item['REFERRERS']))
    {
        echo "<tr><td colspan=\"3\">\n";
        echo "<table style=\"background: #efe; margin: 0px auto; font-size: 8pt;\" cellspacing=\"4\" cellpadding=\"4\">\n";

        echo "<tr style=\"font-size: 8pt;\">\n";
        echo "<th scope=\"col\">Referrer</th>\n";
        echo "<th scope=\"col\">Itemviews</th>\n";
        echo "<th scope=\"col\">Clickthroughs</th>\n";
        echo "</tr>\n";

        foreach ($item['REFERRERS'] as $ref)
        {
            echo "<tr style=\"font-size: 8pt;\">\n";
            echo "<td style=\"font-size: 8pt;\">";
            if (preg_match("/^http:\/\//", $ref['URL']))
            {
                echo "<a href=\"" . $ref['URL'] . "\">";
                if (strlen($ref['URL']) > 30)
                {
                    echo substr($ref['URL'], 0, 29);
                    echo "...";
                }
                else
                {
                    echo $ref['URL'];
                }
                echo "</a>";
            }
            else
            {
                echo $ref['URL'];
            }
            echo "</td>\n";
            echo "<td>" . $ref['ITEMVIEWS'] . "</td>\n";
            echo "<td>" . $ref['CLICKTHROUGHS'] . "</td>\n";
            echo "</tr>\n";
        }

        echo "</table>\n";
        echo "</td></tr>\n";
    }
}


function FB_DisplayItemData($feeduri, $sortbydate)
{
    global $FB_data;

    FB_DisplayBasicStats($feeduri);

    if ($sortbydate)
    {
        echo "<table style=\"margin: 0px auto;\" cellspacing=\"5\" cellpadding=\"5\">\n";

        echo "<tr>\n";
        echo "<th scope=\"col\">Date / Circulation / Hits</th>\n";
        echo "</tr>\n";

        foreach ($FB_data as $date=>$entry)
        {
            echo "<tr style=\"background: #bbb;\">\n";
            echo "<th scope=\"row\">$date / " . $entry['CIRCULATION'] . " / " . $entry['HITS'] . "</th>\n";
            echo "</tr>\n";

            echo "<tr><td>\n";
            echo "<table style=\"margin: 0px auto;\" cellspacing=\"4\" cellpadding=\"4\">\n";

            if (count($entry['ITEMS']))
            {
                echo "<tr style=\"background: #eee;\">\n";
                echo "<th scope=\"col\">Item</th>\n";
                echo "<th scope=\"col\">Itemviews</th>\n";
                echo "<th scope=\"col\">Clickthroughs</th>\n";
                echo "</tr>\n";

                foreach ($entry['ITEMS'] as $title=>$item)
                {
                    echo "<tr style=\"background: #eee;\">\n";
                    echo "<td><a href=\"" . $item['URL'] . "\">$title</a></td>\n";
                    echo "<td>" . $item['ITEMVIEWS'] . "</td>\n";
                    echo "<td>" . $item['CLICKTHROUGHS'] . "</td>\n";
                    echo "</tr>\n";

                    FB_DisplayReferrers($item);
                }
            }
            else
            {
                echo "<tr>\n";
                echo "<th scope=\"col\">No Items</th>\n";
                echo "</tr>\n";
            }

            echo "</table>\n";
            echo "</td></tr>\n";
        }

        echo "</table>\n";
    }
    else
    {
        $item_data = array();

        foreach ($FB_data as $date=>$entry)
        {
            foreach ($entry['ITEMS'] as $title=>$item)
            {
                $item_data[$title]['URL'] = $item['URL'];
                $item_data[$title]['ITEMVIEWS'] += $item['ITEMVIEWS'];
                $item_data[$title]['CLICKTHROUGHS'] += $item['CLICKTHROUGHS'];
            }
        }

        echo "<table style=\"margin: 0px auto;\" cellspacing=\"5\" cellpadding=\"5\">\n";

        echo "<tr>\n";
        echo "<th scope=\"col\">Item / Itemviews / Clickthroughs</th>\n";
        echo "</tr>\n";

        foreach ($item_data as $title=>$item)
        {
            echo "<tr style=\"background: #bbb;\">\n";
            echo "<th scope=\"row\"><a href=\"" . $item['URL'] . "\">$title</a> / " . $item['ITEMVIEWS'] . " / " . $item['CLICKTHROUGHS'] . "</th>\n";
            echo "</tr>\n";

            echo "<tr><td>\n";
            echo "<table style=\"margin: 0px auto;\" cellspacing=\"4\" cellpadding=\"4\">\n";

            $th_out = FALSE;

            foreach ($FB_data as $date=>$entry)
            {
                if ($entry['ITEMS'][$title])
                {
                    if (!$th_out)
                    {
                        echo "<tr style=\"background: #eee;\">\n";
                        echo "<th scope=\"col\">Date</th>\n";
                        echo "<th scope=\"col\">Itemviews</th>\n";
                        echo "<th scope=\"col\">Clickthroughs</th>\n";
                        echo "</tr>\n";

                        $th_out = TRUE;
                    }

                    echo "<tr style=\"background: #eee;\">\n";
                    echo "<td>$date</td>\n";
                    echo "<td>" . $entry['ITEMS'][$title]['ITEMVIEWS'] . "</td>\n";
                    echo "<td>" . $entry['ITEMS'][$title]['CLICKTHROUGHS'] . "</td>\n";
                    echo "</tr>\n";

                    FB_DisplayReferrers($entry['ITEMS'][$title]);
                }
            }

            echo "</table>\n";
            echo "</td></tr>\n";
        }

        echo "</table>\n";
    }
}


function FB_DisplayError()
{
    global $FB_error_msg;

    echo "<h3>ERROR: $FB_error_msg</h3>\n";
}



function FB_AddOptionsPage()
{
    if (function_exists('add_options_page'))
    {
        add_options_page('FeedBurner Awareness', 'FeedBurner Awareness', 8,
                         basename(__FILE__), 'FB_OptionsPanel');
    }
}

add_action('admin_menu', 'FB_AddOptionsPage');


function FB_OptionsForm()
{
    global $FB_options;
    global $FB_data;
    global $FB_error;

?>
<div class="wrap">
  <h2>FeedBurner Awareness Plugin</h2>
  <fieldset class="options">
    <legend><b>Plugin Usage</b></legend>
    <p>
    This plugin simplifies access to the statistics of your burned
    <a href="http://www.feedburner.com">FeedBurner</a> feed
    using the
    <a href="http://www.feedburner.com/fb/a/api/awareness">FeedBurner Awareness API</a>.
    </p>
    <p>
    You can use the following template tags within your theme files:
    </p>
    <ul>
      <p>
      <code>&lt;?php FB_GetCirculation($dates = 'last_month'); ?&gt;</code><br/>
      This function echos the maximum circulation value for your feed for the
      given date specification.
      </p>
      <p>
      <code>&lt;?php FB_GetHits($dates = 'last_month'); ?&gt;</code><br/>
      This function echos the total number of hits for your feed for the given
      date specification.
      </p>
      <p>
      <code>&lt;?php FB_GetPost_Itemviews($dates = 'last_month'); ?&gt;</code><br/>
      This function echos the total number of itemviews for the current post
      for the given date specification.  This function can only be used
      within The Loop.
      </p>
      <p>
      <code>&lt;?php FB_GetPost_Clickthroughs($dates = 'last_month'); ?&gt;</code><br/>
      This function echos the total number of clickthroughs for the current
      post for the given date specification.  This function can only be used
      within The Loop.
      </p>
    </ul>
    <p>
    The <code>$dates</code> parameter defaults to the last months' worth of
    data.  This plugin translates <code>'today'</code>, <code>'yesterday'</code>,
    <code>'last_week'</code>, <code>'last_month'</code>, and <code>'last_year'</code>
    into the proper formatted date string.  Documentation for the date string
    format can be found
    <a href="http://www.feedburner.com/fb/a/api/awareness#dates">here</a>.
    For example, you can specify a single date as <code>'YYYY-MM-DD'</code>
    or a range of dates as <code>'YYYY-MM-DD,YYYY-MM-DD'</code>.
    </p>
    <p>
    Note that the <code>FB_GetPost_Itemviews</code> and
    <code>FB_GetPost_Clickthroughs</code> functions will only work properly for
    <a href="http://www.feedburner.com/fb/a/pro-totalstats">Total Stats PRO</a>
    <img src="http://www.feedburner.com/fb/images/badges/pro.gif" alt="[PRO]" />
    subscribers.
    </p>
    <p>
    Advanced Usage:
    </p>
    <ul>
      <code>&lt;?php FB_GetStats($func, $feeduri, $itemurl, $dates); ?&gt;</code><br/>
      This function fetches the specified statistical data and puts it into the
      global multi-dimensional array <code>$FB_data</code> which can be easily
      parsed.  You should be familiar with the
      <a href="http://www.feedburner.com/fb/a/api/awareness">FeedBurner Awareness API</a>
      before using this function.  The <code>$func</code> parameter must be one
      of <code>'GetFeedData'</code>, <code>'GetItemData'</code>, or
      <code>'GetResyndicationData'</code>.
    </ul>
    <p>
    Have fun!
    </p>
  </fieldset>
  <fieldset class="options">
    <legend><b>Plugin Options</b></legend>
    <p>
    The Feed URI is the ID you registered with FeedBurner to identify your feed:
    <ul>
      http://feeds.feedburner.com/&lt;<i>feeduri</i>&gt;
    </ul>
    Note that the Feed URI is not the entire FeedBurner URL string.
    </p>
    <form method="post">
      <table width="100%" cellspacing="2" cellpadding="5" class="editform" style="padding-top: 10px;">
        <tr>
          <th width="20%" valign="top" scope="row">Feed URI: </th>
          <td>
            <input name="feeduri" type="text" size="30" value="<?php echo $FB_options['feeduri']; ?>" />
          </td>
        </tr>
      </table>
      <div class="submit" style="text-align: left;">
        <input type="submit" name="fb_update" value="Update" />
      </div>
    </form>
  </fieldset>
  <fieldset class="options">
    <legend><b>Feed Statistics</b></legend>
    <p>
    Here you can view the various statistics for your FeedBurner feed.  Note
    that all the statistics you can view here can also be viewed from your
    account on the
    <a href="http://www.feedburner.com">FeedBurner</a> website.
    </p>
    <p>
    The <b>Feed URI</b> is the ID you registered with FeedBurner to identify
    your feed.  This will be the default Feed URI you configured above but
    you can override it here to view stats for other burned feeds.
    </p>
    <p>
    The <b>Item URL</b> is a full URL of one of your posts.  This URL must be
    the same as that specified within your feed that is used by FeedBurner.  By
    default when this field is empty, data for all items (posts) are returned.
    This field is only used by the <b>Item Stats</b> and <b>Resyndication
    Stats</b> statistical <b>Query Types</b>.
    </p>
    <p>
    The <b>Dates</b> field can be used to gather statistics for a specific set
    of dates.  This plugin translates <code>'today'</code>, <code>'yesterday'</code>,
    <code>'last_week'</code>, <code>'last_month'</code>, and <code>'last_year'</code>
    into the proper formatted date string.  Documentation for the date string
    format can be found
    <a href="http://www.feedburner.com/fb/a/api/awareness#dates">here</a>.
    For example, you can specify a single date as <code>'YYYY-MM-DD'</code>
    or a range of dates as <code>'YYYY-MM-DD,YYYY-MM-DD'</code>.  By default
    when this field is empty, the most recent day is used.
    </p>
    <p>
    The <b>Query Type</b> specifies what kind of statistical data you want to
    view.  <b>Feed Stats</b> returns the <i>Circulation</i> and <i>Hits</i>
    information for your entire feed.  <b>Item Stats</b> returns the
    <i>Circulation</i> and <i>Hits</i> for your entire feed and the
    <i>Itemviews</i> and <i>Clickthroughs</i> for each item (post).
    <b>Resyndication Stats</b> returns the <i>Circulation</i> and <i>Hits</i>
    for your entire feed and the <i>Itemviews</i>, <i>Clickthroughs</i>, and
    <i>Referrers</i> for each item (post).
    </p>
    <p>
    Note that the <b>Item Stats</b> and <b>Resyndication Stats</b> will only
    work properly for
    <a href="http://www.feedburner.com/fb/a/pro-totalstats">Total Stats PRO</a>
    <img src="http://www.feedburner.com/fb/images/badges/pro.gif" alt="[PRO]" />
    subscribers.
    </p>
    <form method="post">
      <table width="100%" cellspacing="2" cellpadding="5" class="editform" style="padding-top: 10px;">
        <tr>
          <th width="20%" valign="top" scope="row">Feed URI: </th>
          <td>
            <?php
            echo "<input name=\"feeduri\" type=\"text\" size=\"30\" value=\"";
            if ($_POST['feeduri'])
            {
                echo $_POST['feeduri'];
            }
            else
            {
                echo $FB_options['feeduri'];
            }
            echo "\" />\n";
            ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Item URL: </th>
          <td>
            <?php
            echo "<input name=\"itemurl\" type=\"text\" size=\"50\" value=\"";
            if ($_POST['itemurl'])
            {
                echo $_POST['itemurl'];
            }
            echo "\" />\n";
            ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Dates: </th>
          <td>
            <?php
            echo "<input name=\"dates\" type=\"text\" size=\"50\" value=\"";
            if ($_POST['dates'])
            {
                echo $_POST['dates'];
            }
            echo "\" />\n";
            ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Query Type: </th>
          <td>
            <?php
            $querytype = (isset($_POST['querytype']) && ($_POST['querytype'] != '')) ? $_POST['querytype'] : FALSE;
            ?>
            <label for="getfeed">
              <input name="querytype" id="getfeed" type="radio" value="feed" <?php if (!$querytype || ($querytype == 'feed')) echo "checked=\"checked\""; ?> /> Feed Stats
            </label><br/>
            <label for="getitem_date">
              <input name="querytype" id="getitem_date" type="radio" value="item_date" <?php if ($querytype == 'item_date') echo "checked=\"checked\""; ?> /> Item Stats sorted by Date
              <img src="http://www.feedburner.com/fb/images/badges/pro.gif" alt="[PRO]" />
            </label><br/>
            <label for="getitem_item">
              <input name="querytype" id="getitem_item" type="radio" value="item_item" <?php if ($querytype == 'item_item') echo "checked=\"checked\""; ?> /> Item Stats sorted by Item
              <img src="http://www.feedburner.com/fb/images/badges/pro.gif" alt="[PRO]" />
            </label><br/>
            <label for="getresyn_date">
              <input name="querytype" id="getresyn_date" type="radio" value="resyn_date" <?php if ($querytype == 'resyn_date') echo "checked=\"checked\""; ?> /> Resyndication Stats sorted by Date
              <img src="http://www.feedburner.com/fb/images/badges/pro.gif" alt="[PRO]" />
            </label><br/>
            <label for="getresyn_item">
              <input name="querytype" id="getresyn_item" type="radio" value="resyn_item" <?php if ($querytype == 'resyn_item') echo "checked=\"checked\""; ?> /> Resyndication Stats sorted by Item
              <img src="http://www.feedburner.com/fb/images/badges/pro.gif" alt="[PRO]" />
            </label>
          </td>
        </tr>
      </table>
      <div class="submit" style="text-align: left;">
        <input type="submit" name="fb_stats" value="Submit" />
      </div>
    </form>
    <?php
    //echo nl2br(eregi_replace(" ", "&nbsp;", print_r($_POST, TRUE)));
    if (isset($_POST['fb_stats']))
    {
        echo "<hr/>\n";
        $error = FALSE;

        $feeduri = (isset($_POST['feeduri']) && ($_POST['feeduri'] != '')) ?
            $_POST['feeduri'] : $FB_options['feeduri'];

        if ($feeduri == '')
        {
            $error = TRUE;
            echo "ERROR: Must specify the Feed URI.<br/>\n";
        }

        $itemurl = (isset($_POST['itemurl']) && ($_POST['itemurl'] != '')) ?
            $_POST['itemurl'] : FALSE;

        $dates = (isset($_POST['dates']) && ($_POST['dates'] != '')) ?
            $_POST['dates'] : FALSE;

        if (!$querytype)
        {
            $error = TRUE;
            echo "ERROR: Must specify the Query Type.<br/>\n";
        }

        if (!$error)
        {
            if ($querytype == 'feed')
            {
                FB_GetStats('GetFeedData', $feeduri, FALSE, $dates);
                if ($FB_error)
                {
                    FB_DisplayError();
                }
                else
                {
                    FB_DisplayFeedData($feeduri);
                }
            }
            else if (($querytype == 'item_date') || ($querytype == 'item_item'))
            {
                FB_GetStats('GetItemData', $feeduri, $itemurl, $dates);
                if ($FB_error)
                {
                    FB_DisplayError();
                }
                else
                {
                    if ($querytype == 'item_date')
                    {
                        FB_DisplayItemData($feeduri, TRUE);
                    }
                    else
                    {
                        FB_DisplayItemData($feeduri, FALSE);
                    }
                }
            }
            else if (($querytype == 'resyn_date') || ($querytype == 'resyn_item'))
            {
                FB_GetStats('GetResyndicationData', $feeduri, $itemurl, $dates);
                if ($FB_error)
                {
                    FB_DisplayError();
                }
                else
                {
                    if ($querytype == 'resyn_date')
                    {
                        FB_DisplayItemData($feeduri, TRUE);
                    }
                    else
                    {
                        FB_DisplayItemData($feeduri, FALSE);
                    }
                }
            }
            else
            {
                $error = TRUE;
                echo "ERROR: Unknown Query Type \"$querytype\".<br/>\n";
            }
        }

        /*
        if (!$error)
        {
            echo "<hr/>\n";
            echo nl2br(eregi_replace(" ", "&nbsp;", print_r($FB_data, TRUE)));
        }
        */
    }
    ?>
  </fieldset>
</div>
<?php

//FB_GetStats('GetFeedData', 'insanum', FALSE, FALSE);
//FB_GetStats('GetFeedData', 'insanum', '2005-05-01,2005-05-03', FALSE);

//FB_GetStats('GetItemData', 'insanum', FALSE, FALSE);
//FB_GetStats('GetItemData', 'insanum', 'http://www.insanum.com/cars/does-auto-insurance-cover-this/', FALSE);
//FB_GetStats('GetItemData', 'insanum', FALSE, '2005-07-10,2005-07-17');
//FB_GetStats('GetItemData', 'insanum', 'http://www.insanum.com/cars/does-auto-insurance-cover-this/', '2005-07-16,2005-07-17');

//FB_GetStats('GetResyndicationData', 'insanum', FALSE, FALSE);
//FB_GetStats('GetResyndicationData', 'insanum', 'http://www.insanum.com/cars/does-auto-insurance-cover-this/', FALSE);
//FB_GetStats('GetResyndicationData', 'insanum', FALSE, '2005-07-10,2005-07-17');
//FB_GetStats('GetResyndicationData', 'insanum', 'http://www.insanum.com/cars/does-auto-insurance-cover-this/', '2005-07-15,2005-07-18');

//echo nl2br(eregi_replace(" ", "&nbsp;", print_r($FB_data, TRUE)));

}


function FB_OptionsPanel()
{
    global $FB_options;

    if (isset($_POST['fb_update']))
    {
    ?>
<div class="updated">
  <p>
    <strong>
    <?php
        //echo nl2br(eregi_replace(" ", "&nbsp;", print_r($_POST, TRUE)));
        $changed = FALSE;

        if (isset($_POST['feeduri']))
        {
            $tmp = $_POST['feeduri'];

            if ($tmp !== $FB_options['feeduri'])
            {
                $FB_options['feeduri'] = $tmp;
                $changed = TRUE;

                echo "The feeduri has been set to \"$tmp\".<br/>\n";
            }
        }

        if ($changed == FALSE)
        {
            echo "Nothing changed.<br/>\n";
        }
    ?>
    </strong>
  </p>
</div>
    <?php

        update_option('FB_Awareness', $FB_options);
    }

    FB_OptionsForm();
}


function FB_GetCirculation($dates = 'last_month')
{
    global $FB_options;
    global $FB_data;
    global $FB_error;

    FB_GetStats('GetFeedData', $FB_options['feeduri'], FALSE, $dates);

    if ($FB_error)
    {
        echo "n/a";
        return;
    }

    $circ = 0;

    foreach ($FB_data as $entry)
    {
        if ($circ < $entry['CIRCULATION'])
        {
            $circ = $entry['CIRCULATION'];
        }
    }

    echo $circ;
}


function FB_GetHits($dates = 'last_month')
{
    global $FB_options;
    global $FB_data;
    global $FB_error;

    FB_GetStats('GetFeedData', $FB_options['feeduri'], FALSE, $dates);

    if ($FB_error)
    {
        echo "n/a";
        return;
    }

    $hits = 0;

    foreach ($FB_data as $entry)
    {
        $hits += $entry['HITS'];
    }

    echo $hits;
}


function FB_GetPost_Itemviews($dates = 'last_month')
{
    global $FB_options;
    global $FB_data;
    global $FB_error;

    $itemurl = get_permalink();

    FB_GetStats('GetItemData', $FB_options['feeduri'], $itemurl, $dates);

    if ($FB_error)
    {
        echo "n/a";
        return;
    }

    $views = 0;

    foreach ($FB_data as $entry)
    {
        foreach ($entry['ITEMS'] as $item)
        {
            $views += $item['ITEMVIEWS'];
        }
    }

    echo $views;
}


function FB_GetPost_Clickthroughs($dates = 'last_month')
{
    global $FB_options;
    global $FB_data;
    global $FB_error;

    $itemurl = get_permalink();

    FB_GetStats('GetItemData', $FB_options['feeduri'], $itemurl, $dates);

    if ($FB_error)
    {
        echo "n/a";
        return;
    }

    $clicks = 0;

    foreach ($FB_data as $entry)
    {
        foreach ($entry['ITEMS'] as $item)
        {
            $clicks += $item['CLICKTHROUGHS'];
        }
    }

    echo $clicks;
}

?>
