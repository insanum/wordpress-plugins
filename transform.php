<?php

/*
Plugin Name: Transform
Plugin URI: http://www.insanum.com/software
Description: This plugin is arguably the lamest, least useful, most idiotic, extremely demented, and coolest plugin there is. You'll either scratch your head and say "What the...", or smile and say "That's awesome...". The Transform plugin is a filter that is used to transform your posts into hexadecimal, octal, decimal, binary, the wicked memory dump form, or using an external application (i.e. jive, swedish chef, h@x0r, etc).  You can see it in action <a href="http://www.insanum.com?transform=dmp">here</a>.
Author: Eric Davis
Version: 1.2
Author URI: http://www.insanum.com
*/ 

/*
LICENSE

This source is submitted to the public domain.  Feel free to use and modify it.
Please provide a comment in your modified source attributing credit for my
original work would be appreciated.  And buy me a beer...

THIS SOFTWARE IS PROVIDED AS IS AND WITHOUT ANY WARRANTY OF ANY KIND.  USE AT
YOUR OWN RISK!
*/


$Transform_Options =
  array(
    'eight_bits' => TRUE,
    'dump_width' => 12,
    'robots'     => '<meta name="robots" content="noindex, nofollow">',
    'ext_dir'    => '/tmp',
    'transforms' => array(
                      'nrm' => array(
                                 'label' => 'normal',
                                 'use'   => TRUE),
                      'dmp' => array(
                                 'label' => 'dump',
                                 'use'   => TRUE),
                      'hex' => array(
                                 'label' => 'hex-16',
                                 'use'   => TRUE),
                      'oct' => array(
                                 'label' => 'oct-8',
                                 'use'   => TRUE),
                      'dec' => array(
                                 'label' => 'dec-10',
                                 'use'   => TRUE),
                      'bin' => array(
                                 'label' => 'bin-2',
                                 'use'   => TRUE)));


$Transform_TransformModeDefault = 'nrm';
$Transform_TransformMode = $Transform_TransformModeDefault;
$Transform_TransformLinks = '';


function Transform_unhtmlentities($string)
{
   $trans_tbl = get_html_translation_table(HTML_ENTITIES);
   $trans_tbl = array_flip($trans_tbl);
   return strtr($string, $trans_tbl);
}


function Transform_Links()
{
    global $Transform_TransformLinks;
    echo "$Transform_TransformLinks";
}


function Transform_Start()
{
    global $Transform_Options;
    global $Transform_TransformModeDefault;
    global $Transform_TransformMode;
    global $Transform_TransformLinks;

    /*
     * If the options already exist in the database then use them,
     * else initialize the database by storing the defaults.
     */
    $options = get_option('Transform');
    if ($options == FALSE)
    {
        add_option('Transform', $Transform_Options);
    }
    else
    {
        $Transform_Options = $options;
    }

    /* check the CGI parameters for a transform setting */
    $g = $_GET['transform'];

    if ($g)
    {
        $Transform_TransformMode = $g;
    }

    /* create the transform links for the current URL */

    $Transform_TransformLinks .= "<ul class=\"transform_links\">\n";

    $active = "<li class=\"transform_links_active_item\">";
    $item  = "<li class=\"transform_links_item\">";

    $anchor = "<a href=\"http";
    if ($_SERVER['HTTPS'] == 'on') { $anchor .= 's'; }
    $anchor .= "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    $end = "</a></li>\n";

    foreach ($Transform_Options['transforms'] as $k => $v)
    {
        if (!$v['use'])
        {
            continue;
        }

        $Transform_TransformLinks .= ($Transform_TransformMode == $k) ? $active : $item;

        $query = '';

        if (strstr($anchor, '?')) /* if query string exists */
        {
            //$anchor = preg_replace("/\/$/", "", $anchor); /* remove trailing '/' */
            $anchor = preg_replace("/(.*)transform=[0-9a-zA-Z]{1,}&?(.*)$/", "$1$2", $anchor); /* remove any transform var */

            if (preg_match("/\?$/", $anchor))
            {
                $anchor = preg_replace("/\?$/", "", $anchor);
            }

            $Transform_TransformLinks .=
                preg_match("/^$Transform_TransformModeDefault$/", $k)
                    ? "$anchor\">" . $v['label'] . "$end"
                    : "$anchor&transform=$k\">" . $v['label'] . "$end";
        }
        else
        {
            $Transform_TransformLinks .=
                preg_match("/^$Transform_TransformModeDefault$/", $k)
                    ? "$anchor\">" . $v['label'] . "$end"
                    : "$anchor?transform=$k\">" . $v['label'] . "$end";
        }
    }

    $Transform_TransformLinks .= "</ul>\n";
}

add_action('init', 'Transform_Start');


function Transform_Robots()
{
    global $Transform_Options;
    global $Transform_TransformMode;
    global $Transform_TransformModeDefault;

    if ($Transform_TransformMode != $Transform_TransformModeDefault)
    {
        echo $Transform_Options['robots'] . "\n";
    }
}


function Transform_DumpCharacter(&$count, &$cnt_col, &$hex_col, &$txt_col, &$line_data, &$ch)
{
    global $Transform_Options;

    if (($count % $Transform_Options['dump_width']) == 0)
    {
        /* dump_width bytes have been encoded on a line - start a new line */
        if ($line_data == '')
        {
            $cnt_col .= sprintf("%08x:&nbsp;<br />\n", $count);
        }
        else
        {
            $hex_col .= "&nbsp;&nbsp;<br />\n";
            $txt_col .= sprintf("%s<br />\n", $line_data);
            $cnt_col .= sprintf("%08x:&nbsp;<br />\n", $count);
            $line_data = "";
        }
    }
    else if (($count % 2) == 0)
    {
        /* still building an encoded dump_width byte line */
        $hex_col .= "&nbsp;";
    }

    $count += 1;

    /* add character to ascii data */

    if      ($ch == '<') { $line_data .= "&lt;"; }
    else if ($ch == '>') { $line_data .= "&gt;"; }
    else if ($ch == '&') { $line_data .= "&amp;"; }
    else if ($ch == '"') { $line_data .= "&quot;"; }
    else
    {
        $line_data .=
            preg_match("/[a-zA-Z0-9 ~`!@#\$%^\*()-_=\+\[{\]}\\|;:',\.\/?]/",
                       $ch)
                ? $ch : ".";
    }

    /* transform this character */

    $hex_col .= sprintf("%02x", ord($ch));
}


function Transform_Dump($content)
{
    global $Transform_Options;

    $in_tag = 0;
    $count = 0;
    $line_data = '';
    $tag = '';
    $tmp_data = '';
    $new_data = "<div class=\"transform_dump\"><table><tr>\n";

    $cnt_col = '';
    $hex_col = '';
    $txt_col = '';

    $lines = preg_split("/\n/", $content, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($lines as $line)
    {
        /* remove all beginning whitespace */
        $line = preg_replace("/^\s+/", "", $line);

        /* remove all trailing whitespace */
        $line = preg_replace("/\s+$/", "", $line);

        /* tack the newline back on so html source looks decent */
        $line .= "\n";

        $tmp_data .= $line;
    }

    $chars = preg_split("//", $tmp_data, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char)
    {
        /* html tag - all characters between a '<' and '>' (inclusive) */
        if ($char == '<')
        {
            $in_tag = 1;
            $tag = $char;
            continue;
        }
        else if ($in_tag)
        {
            $tag .= $char;

            if ($char == '>')
            {
                $in_tag = 0;

                /*
                 * anchor and end anchor tags are left as is so links are still
                 * available in the dump, all other html tags are transform'ed.
                 */
                if (preg_match("/^<\s*[aA]\s*.*>$/", $tag) ||
                    preg_match("/^<\s*\/\s*[aA]\s*>$/", $tag))
                {
                    $hex_col .= $tag;
                    $line_data .= $tag;
                }
                else
                {
                    /* transform the html tag */
                    $tmp = preg_split("//", $tag, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($tmp as $tmp_char)
                    {
                        Transform_DumpCharacter($count, $cnt_col, $hex_col,
                                                  $txt_col, $line_data, $tmp_char);
                    }
                }
            }

            continue;
        }

        Transform_DumpCharacter($count, $cnt_col, $hex_col, $txt_col,
                                  $line_data, $char);
    }

    /* pad with zeros to dump_width byte alignment */
    if (($count % $Transform_Options['dump_width']) != 0)
    {
        $pad = ($Transform_Options['dump_width'] -
                ($count % $Transform_Options['dump_width']));

        for ($i == 1; $i < $pad; $i++)
        {
            if (($count % 2) == 0)
            {
                $hex_col .= "&nbsp;";
            }

            $count += 1;
            $line_data .= ".";

            $hex_col .= "00";
        }
    }

    $hex_col .= "&nbsp;&nbsp;<br />\n";
    $txt_col .= "$line_data<br />\n";
    $line_data = "";

    $new_data .= "<td>\n$cnt_col</td>\n";
    $new_data .= "<td>\n$hex_col</td>\n";
    $new_data .= "<td>\n$txt_col</td>\n";
    $new_data .= "</tr></table></div>\n";

    return $new_data;
}


function Transform_ExternalApp($app, $content, $ext_dir)
{
    $output_file = $ext_dir . "/transform_" . getmypid();
    $new_data = '';
    $in_tag = 0;
    $html_tag = '';
    $html_tag_count = 0;
    $html_tag_data = array();

    #
    # This code is a little tricky:
    #   . open write pipe to convert app
    #   . parse all data char by char
    #   . all html tags are removed and put in an array
    #     (replaced with special reference token)
    #   . reference token and non html tag chars sent on pipe
    #   . wait for convert app to finish
    #   . read in file with converted data
    #   . replace all reference tokens in converted data back
    #     to the original html tag
    #   . All done!
    #

    $pipe = popen("$app > $output_file", 'w');
    if ($pipe == FALSE)
    {
        return $content;
    }

    $chars = preg_split("//", $content, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char)
    {
        # don't transform html tags
        # all character between a '<' and '>' (inclusive)
        if ($char == '<')
        {
            $in_tag = 1;
        }

        if ($in_tag)
        {
            $html_tag .= $char;

            if ($char == '>')
            {
                $in_tag = 0;
                $html_tag_data[] = $html_tag;
                fwrite($pipe, "<--$html_tag_count-->");
                $html_tag_count++;
                $html_tag = '';
            }

            continue;
        }

        fwrite($pipe, $char);
    }

    pclose($pipe);

    $fd = fopen("$output_file", 'r');
    if ($fd == FALSE)
    {
        return $content;
    }

    while (!feof($fd))
    {
        $new_data .= fread($fd, 4096);
    }

    fclose($fd);

    for ($i = 0; $i <= ($html_tag_count - 1); $i++)
    {
        $new_data = preg_replace("/<--$i-->/", "$html_tag_data[$i]", $new_data);
    }

    return $new_data;
}


function Transform_Transform($transformmode, $content)
{
    global $Transform_Options;

    $in_tag = 0;
    $new_data = "<span class=\"transform_dump\">\n";

    $lines = preg_split("/\n/", $content, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($lines as $line)
    {
        /* remove all beginning whitespace */
        $line = preg_replace("/^\s+/", "", $line);

        /* remove all trailing whitespace */
        $line = preg_replace("/\s+$/", "", $line);

        /* tack the newline back on so html source looks decent */
        $line .= "\n";

        $chars = preg_split("//", $line, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char)
        {
            /* don't transform newlines */
            if (preg_match("/[\r\n]/", $char))
            {
                $new_data .= $char;
                continue;
            }

            /*
             * don't transform html tags
             * all character between a '<' and '>' (inclusive)
             */
            if ($char == '<')
            {
                $in_tag = 1;
            }

            if ($in_tag)
            {
                $new_data .= $char;

                if ($char == '>')
                {
                    $in_tag = 0;
                }

                continue;
            }

            /* transform this character */

            if ($transformmode == 'hex')
            {
                $new_data .= sprintf("%02x", ord($char));
            }
            else if ($transformmode == 'oct')
            {
                $new_data .= sprintf("%03o", ord($char));
            }
            else if ($transformmode == 'dec')
            {
                $new_data .= sprintf("%03d", ord($char));
            }
            else if ($transformmode == 'bin')
            {
                $tmp = ord($char);

                if ($Transform_Options['eight_bits'])
                {
                    for ($i = 0; $i <= 7; $i++)
                    {
                        $new_data .= (($tmp << $i) & 0x80) ? "1" : "0";
                    }
                }
                else
                {
                    for ($i = 0; $i <= 6; $i++)
                    {
                        $new_data .= (($tmp << $i) & 0x40) ? "1" : "0";
                    }
                }
            }

            /*
             * add a space after every transform'ed character
             * this looks a lot better expecially during window resizes
             */
            $new_data .= " ";
        }
    }

    $new_data .= "</span>\n";

    return $new_data;
}


function Transform_TheContent($content = '')
{
    global $Transform_Options;
    global $Transform_TransformMode;

    if ($Transform_TransformMode == $Transform_TransformModeDefault)
    {
	    return $content;
    }

    if ($Transform_TransformMode == 'dmp')
    {
        return Transform_Dump($content);
    }
    else if (preg_match("/hex|oct|dec|bin/", $Transform_TransformMode))
    {
        return Transform_Transform($Transform_TransformMode, $content);
    }
	else /* external convert application */
    {
        $app = $Transform_Options['transforms'][$Transform_TransformMode]['app'];

        if (!$app)
        {
            return $content;
        }

        return Transform_ExternalApp($app, $content,
                                       $Transform_Options['ext_dir']);
    }

    return $content;
}

/* prio 1 (before other filters) */
add_filter('the_content', 'Transform_TheContent', 1);


function Transform_AddOptionsPage()
{
    if (function_exists('add_options_page'))
    {
        add_options_page('Transform', 'Transform', 8,
                         basename(__FILE__), 'Transform_OptionsPanel');
    }
}

add_action('admin_menu', 'Transform_AddOptionsPage');


function Transform_OptionsBuiltinForm($mode)
{
    global $Transform_Options;

    if ($Transform_Options['transforms'][$mode]['use'] == TRUE)
    {
        echo "<label for=\"${mode}_on\">";
        echo "<input name=\"${mode}_use\" id=\"${mode}_on\" type=\"radio\" ";
        echo "value=\"1\" checked=\"checked\" /> On ";
        echo "</label>\n";
        echo "<label for=\"${mode}_off\">";
        echo "<input name=\"${mode}_use\" id=\"${mode}_off\" type=\"radio\" ";
        echo "value=\"0\" /> Off ";
        echo "</label>\n";
    }
    else
    {
        echo "<label for=\"${mode}_on\">";
        echo "<input name=\"${mode}_use\" id=\"${mode}_on\" type=\"radio\" ";
        echo "value=\"1\" /> On ";
        echo "</label>\n";
        echo "<label for=\"${mode}_off\">";
        echo "<input name=\"${mode}_use\" id=\"${mode}_off\" type=\"radio\" ";
        echo "value=\"0\" checked=\"checked\" /> Off ";
        echo "</label>\n";
    }

    echo "&nbsp;&nbsp;&nbsp;Label: <input name=\"${mode}_label\" type=\"text\" size=\"20\" value=\"";
    echo htmlspecialchars($Transform_Options['transforms'][$mode]['label']);
    echo "\" />\n";
}


function Transform_OptionsForm()
{
    global $Transform_Options;
?>
<div class="wrap">
  <form method="post">
    <h2>Transform Plugin Options</h2>
    <fieldset class="options">
      <legend><b>Plugin Usage</b></legend>
      <p>
      This plugin is arguably the lamest, least useful, most idiotic, extremely
      demented, and coolest plugin there is. You'll either scratch your head
      and say "What the...", or smile and say "That's awesome...". The Transform
      plugin is a filter that is used to transform your posts into hexadecimal,
      octal, decimal, binary, the wicked memory dump form, or using an
      external application (i.e. jive, swedish chef, h@x0r, etc).
      You can see it in action <a href="http://www.insanum.com?transform=dmp">here</a>.
      </p>
      <p>
      To use this plugin, simply add the following line of code somewhere in
      your theme template:
      </p>
      <p style="font-family: monospace;">
        &lt;?php Transform_Links(); ?&gt;
      </p>
      <p>
      The above function produces an unordered list that contains links to the
      various enabled Transform transforms of the currently displayed URL.
      </p>
      <p>
      The following class identifiers are used for CSS control of this plugin's
      output:
      <p style="font-family: monospace;">
        transform_links: the unordered transform links list
      </p>
      <p style="font-family: monospace;">
        transform_links_item: an item in the transform links list
      </p>
      <p style="font-family: monospace;">
        transform_links_active_item: an item in the transform links list
        (this respresents the current transform being displayed)
      </p>
      <p style="font-family: monospace;">
        transform_dump: span wrapper around the transformed post
      </p>
      <p>
      Have fun!
      </p>
    </fieldset>
    <fieldset class="options">
      <legend><b>8 Bit Binary Output</b></legend>
      ASCII data is encoded using only 7 bits.  Showing 8 bits of data in
      the binary output will result in every character transform starting
      with a zero.
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr>
          <th width="20%" valign="top" scope="row">Show 8 Bits: </th>
          <td>
            <select name="transform_binary_output">
              <?php
              if ($Transform_Options['eight_bits'] == TRUE)
              {
                  echo "<option value=\"1\" selected>TRUE</option>";
                  echo "<option value=\"0\">FALSE</option>";
              }
              else
              {
                  echo "<option value=\"1\">TRUE</option>";
                  echo "<option value=\"0\" selected>FALSE</option>";
              }
              ?>
            </select>
          </td>
        </tr>
      </table>
    </fieldset>
    <fieldset class="options">
      <legend><b>Dump Character Width</b></legend>
      The ultra-cool dump output can get really screwed up if your theme
      template uses a narrow space for displaying posts.  Adjust this
      setting as needed to fill in your theme template post width.  This value
      specifies the number of characters to print per line and must be a
      multiple of 2.
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr>
          <th width="20%" valign="top" scope="row">Character Width: </th>
          <td>
            <input name="transform_dump_width" type="text" size="5" value="<?php
              echo $Transform_Options['dump_width']; ?>" />
          </td>
        </tr>
      </table>
    </fieldset>
    <fieldset class="options">
      <legend><b>Meta Robots Tag</b></legend>
      To prevent robots from attempting to index your Transform'ed pages, you
      can specify a meta tag that will be included when a transformation
      is performed.  You most likely will want to use the following tag:
      <p style="font-family: monospace;">
        &lt;meta name="robots" content="noindex, nofollow"&gt;
      </p>
      To use this meta tag, simply add the following line of code to your
      theme header template (i.e. within your &lt;head&gt;&lt;/head&gt; block):
      <p style="font-family: monospace;">
        &lt;?php Transform_Robots(); ?&gt;
      </p>
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr>
          <th width="20%" valign="top" scope="row">Meta Robots Header: </th>
          <td>
            <input name="transform_meta_robots" type="text" size="70" value="<?php
              echo htmlspecialchars($Transform_Options['robots']); ?>" />
          </td>
        </tr>
      </table>
    </fieldset>
    <fieldset class="options">
      <legend><b>Builtin Transforms</b></legend>
      This plugin has some builtin transforms.  Specifically: Dump,
      Hexadecimal, Octal, Decimal, and Binary.  Here you can turn on/off each
      of these transforms and change the link name labels.
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <tr>
          <th width="20%" valign="top" scope="row">Normal: </th>
          <td>
            <?php Transform_OptionsBuiltinForm('nrm'); ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Dump: </th>
          <td>
            <?php Transform_OptionsBuiltinForm('dmp'); ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Hexadecimal: </th>
          <td>
            <?php Transform_OptionsBuiltinForm('hex'); ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Octal: </th>
          <td>
            <?php Transform_OptionsBuiltinForm('oct'); ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Decimal: </th>
          <td>
            <?php Transform_OptionsBuiltinForm('dec'); ?>
          </td>
        </tr>
        <tr>
          <th width="20%" valign="top" scope="row">Binary: </th>
          <td>
            <?php Transform_OptionsBuiltinForm('bin'); ?>
          </td>
        </tr>
      </table>
    </fieldset>
    <fieldset class="options">
      <legend><b>External Application Transforms</b></legend>
      This plugin also has the ability to execute an external application which can
      perform a transform.  An external application must read data from standard
      input and write transformed data to standard output.  The
      <a href="http://ftp.gnu.org/non-gnu/talkfilters">GNU Talkfilters</a> package
      contains a bunch of hilarious English text tranformation applications.
      Here you can turn on/off each of these transforms, change the link name labels,
      and add/delete new transforms.  Additionaly, when using an external application
      a directory that is writable by the web server must be configured for
      writing temporary cache files.
      <table width="100%" cellspacing="2" cellpadding="5" class="editform">
        <?php
        foreach ($Transform_Options['transforms'] as $k => $v)
        {
            if (!preg_match("/^ex/", $k))
            {
                continue;
            }

            echo "<tr>\n";
            echo "  <th width=\"15%\" valign=\"top\" scope=\"row\">" . $v['label'] . ": </th>\n";
            echo "  <td>\n";

            if ($v['use'] == TRUE)
            {
                echo "<label for=\"${k}_on\">";
                echo "<input name=\"${k}_use\" id=\"${k}_on\" type=\"radio\" ";
                echo "value=\"1\" checked=\"checked\" /> On ";
                echo "</label>\n";
                echo "<label for=\"${k}_off\">";
                echo "<input name=\"${k}_use\" id=\"${k}_off\" type=\"radio\" ";
                echo "value=\"0\" /> Off ";
                echo "</label>\n";
            }
            else
            {
                echo "<label for=\"${k}_on\">";
                echo "<input name=\"${k}_use\" id=\"${k}_on\" type=\"radio\" ";
                echo "value=\"1\" /> On ";
                echo "</label>\n";
                echo "<label for=\"${k}_off\">";
                echo "<input name=\"${k}_use\" id=\"${k}_off\" type=\"radio\" ";
                echo "value=\"0\" checked=\"checked\" /> Off ";
                echo "</label>\n";
            }

            echo "&nbsp;&nbsp;&nbsp;Label: <input name=\"${k}_label\" type=\"text\" ";
            echo "size=\"20\" value=\"" . htmlspecialchars($v['label']) . "\" />\n";
            echo "&nbsp;&nbsp;&nbsp;App: <input name=\"${k}_app\" type=\"text\" ";
            echo "size=\"25\" value=\"" . htmlspecialchars($v['app']) . "\" />";
            echo "&nbsp;&nbsp;&nbsp;<label for=\"${k}_del\">\n";
            echo "Delete <input name=\"${k}_del\" id=\"${k}_del\" type=\"checkbox\" value=\"1\" />";
            echo "</label>\n";

            echo "  </td>\n";
            echo "</tr>\n";
        }
        ?>
        <tr>
          <th width="15%" valign="top" scope="row">Writable Directory: </th>
          <td>
            <input name="ext_dir" type="text" size="30"
             value="<?php echo $Transform_Options['ext_dir']; ?>" />
          </td>
        </tr>
        <tr>
          <th width="15%" valign="top" scope="row">New External Application: </th>
          <td>
            Label: <input name="new_label" type="text" size="20" value="" />
            &nbsp;&nbsp;&nbsp;App: <input name="new_app" type="text" size="30" value="" />
          </td>
        </tr>
        </tr>
      </table>
    </fieldset>
    <div class="submit">
      <input type="submit" name="transform_update" value="Update Options" />
    </div>
  </form>
</div>
<?php
}


function Transform_OptionsBuiltinFormProcess($mode, $text, &$changed)
{
    global $Transform_Options;

    if (isset($_POST["${mode}_use"]))
    {
        $tmp = $_POST["${mode}_use"];
        $tmp = ($tmp) ? TRUE : FALSE;

        if ($tmp !== $Transform_Options['transforms'][$mode]['use'])
        {
            $Transform_Options['transforms'][$mode]['use'] = $tmp;
            $changed = TRUE;

            echo "The $text transform has been ";
            echo (($tmp) ? "enabled" : "disabled") . ".<br/>\n";
        }
    }

    if (isset($_POST["${mode}_label"]))
    {
        $tmp = $_POST["${mode}_label"];

        if ($tmp !== $Transform_Options['transforms'][$mode]['label'])
        {
            $Transform_Options['transforms'][$mode]['label'] = $tmp;
            $changed = TRUE;

            echo "The $text label has been changed to $tmp.<br/>\n";
        }
    }
}


function Transform_GetUniqueTransformIndex()
{
    global $Transform_Options;

    while (TRUE)
    {
        $tmp = "ex" . rand(1, 99);

        if (isset($Transform_Options['transforms'][$tmp]))
        {
            continue;
        }

        return $tmp;
    }
}


function Transform_OptionsExternalAppFormProcess(&$changed)
{
    global $Transform_Options;

    foreach ($_POST as $k => $v)
    {
        if (!preg_match("/^ex/", $k))
        {
            continue;
        }

        $index  = preg_replace("/^(ex[0-9]*)_.*$/", "$1", $k);
        $action = preg_replace("/^ex[0-9]*_(.*)$/", "$1", $k);

        if (!isset($Transform_Options['transforms'][$index]))
        {
            continue;
        }

        if ($action == 'del')
        {
            $tmp = $Transform_Options['transforms'][$index]['label'];
            unset($Transform_Options['transforms'][$index]);
            $changed = TRUE;

            echo "The $tmp transform has been deleted.<br/>\n";
        }
        else if ($action == 'use')
        {
            $v = ($v) ? TRUE : FALSE;

            if ($v !== $Transform_Options['transforms'][$index]['use'])
            {
                $Transform_Options['transforms'][$index]['use'] = $v;
                $changed = TRUE;

                echo "The ";
                echo $Transform_Options['transforms'][$index]['label'];
                echo " transform has been ";
                echo (($v) ? "enabled" : "disabled") . ".<br/>\n";
            }
        }
        else if ($action == 'label')
        {
            if ($v !== $Transform_Options['transforms'][$index]['label'])
            {
                $tmp = $Transform_Options['transforms'][$index]['label'];
                $Transform_Options['transforms'][$index]['label'] = $v;
                $changed = TRUE;

                echo "The $tmp transform label has been changed to $v.<br/>\n";
            }
        }
        else if ($action == 'app')
        {
            if ($v !== $Transform_Options['transforms'][$index]['app'])
            {
                $Transform_Options['transforms'][$index]['app'] = $v;
                $changed = TRUE;

                echo "The ";
                echo $Transform_Options['transforms'][$index]['label'];
                echo " transform app has been changed to $v.<br/>\n";
            }
        }
    }

    $ext_dir = $_POST['ext_dir'];

    if (($ext_dir != '') &&
        ($ext_dir !== $Transform_Options['ext_dir']))
    {
        $Transform_Options['ext_dir'] = $ext_dir;
        $changed = TRUE;

        echo "The write directory has been changed to $ext_dir.<br/>\n";
    }

    $new_label = $_POST['new_label'];
    $new_app = $_POST['new_app'];

    if (($new_label != '') && ($new_app != ''))
    {
        $index = Transform_GetUniqueTransformIndex();

        $Transform_Options['transforms'][$index]['use'] = TRUE;
        $Transform_Options['transforms'][$index]['label'] = $new_label;
        $Transform_Options['transforms'][$index]['app'] = $new_app;
        $changed = TRUE;

        echo "The new transform $new_label has been added.<br/>\n";
    }
    else if (($new_label != '') || ($new_app != ''))
    {
        echo "ERROR: Must specify both the 'Label' and 'App' when ";
        echo "adding a new transform.<br/>\n";
    }
}


function Transform_OptionsPanel()
{
    global $Transform_Options;

    if (isset($_POST['transform_update']))
    {
    ?>
<div class="updated">
  <p>
    <strong>
    <?php
        //echo nl2br(eregi_replace(" ", "&nbsp;", print_r($_POST, TRUE)));   
        $changed = FALSE;

        if (isset($_POST['transform_binary_output']))
        {
            $tmp = $_POST['transform_binary_output'];
            $tmp = ($tmp) ? TRUE : FALSE;

            if ($tmp !== $Transform_Options['eight_bits'])
            {
                $Transform_Options['eight_bits'] = $tmp;
                $changed = TRUE;

                if ($tmp)
                {
                    echo "Binary output changed to 8 bits.<br/>\n";
                }
                else
                {
                    echo "Binary output changed to 7 bits.<br/>\n";
                }
            }
        }

        if (isset($_POST['transform_dump_width']))
        {
            $tmp = $_POST['transform_dump_width'];

            if (($tmp == 0) || (($tmp % 2) != 0))
            {
                echo "ERROR: Dump Width must be nonzero and a multiple of 2.<br/>\n";
            }
            else if ($tmp !== $Transform_Options['dump_width'])
            {
                $Transform_Options['dump_width'] = $tmp;
                $changed = TRUE;

                echo "Dump Width changed to $tmp characters.<br/>\n";
            }
        }

        if (isset($_POST['transform_meta_robots']))
        {
            $tmp = $_POST['transform_meta_robots'];
            $tmp = stripslashes($tmp);

            if ($tmp !== $Transform_Options['robots'])
            {
                $Transform_Options['robots'] = $tmp;
                $changed = TRUE;

                if ($tmp != '')
                {
                    echo "Changed to meta robots tag to: ";
                    echo htmlspecialchars($tmp) . "<br/>\n";
                }
                else
                {
                    echo "Deleted the meta robots tag.<br/>\n";
                }
            }
        }

        Transform_OptionsBuiltinFormProcess('nrm', "Normal", $changed);
        Transform_OptionsBuiltinFormProcess('dmp', "Dump", $changed);
        Transform_OptionsBuiltinFormProcess('hex', "Hexadecimal", $changed);
        Transform_OptionsBuiltinFormProcess('oct', "Octal", $changed);
        Transform_OptionsBuiltinFormProcess('dec', "Decimal", $changed);
        Transform_OptionsBuiltinFormProcess('bin', "Binary", $changed);

        Transform_OptionsExternalAppFormProcess($changed);

        if ($changed == FALSE)
        {
            echo "Nothing changed.<br/>\n";
        }
    ?>
    </strong>
  </p>
</div>
    <?php

        update_option('Transform', $Transform_Options);
    }

    Transform_OptionsForm();
}

?>
