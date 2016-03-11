<?php
/**
 * Plugin Name: Google Maps for WP
 * Plugin URI: http://optimalplugins.com
 * Description: No setup. No code. Just Google Maps! Usage: [wpmap]1313 Disneyland Dr, Anaheim, CA 92802, United States[/wpmap]
 * Version: 1.0.0
 * Author: OptimalPlugins
 * Author URI: http://www.optimalplugins.com
 * Requires at least: 3.8
 * Tested up to: 4.1
 */

define('GOOGLE_MAPS_SHORTCODE', 'wpmap');

if (!defined('WPINC')) {
    die();
}

class Google_Maps
{
    public static $google_maps_js = '';
    public static $n = 0;
    public static $fullscreenList = Array();
    public static $namespace = 'wpmap';
    private static $instance;

    private function __construct()
    {
        ;
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self;
            self::$instance->actions();
        }

        return self::$instance;
    }

    private function actions()
    {
        if (is_admin) {
            add_filter('plugin_row_meta', array($this, 'plugin_meta_links'), 10, 2);
        }

        if (!is_admin()) {
            add_shortcode(GOOGLE_MAPS_SHORTCODE, array($this, 'shortcode'));
        }

        add_action('wp_footer', array($this, 'footer'), 2);

        if (has_filter('widget_text', 'do_shortcode') === false) {
            add_filter('widget_text', 'do_shortcode');
        }

        return;
    }

    public function plugin_meta_links($links, $file)
    {
        $doc_link = "<a target='_blank' href='http://www.optimalplugins.com/doc/google-maps-for-wp'
							title='View documentation'>Documentation</a>";

        $support_link = "<a target='_blank' href='http://www.optimalplugins.com/support/'
							title='Contact Optimal Plugins'>Support</a>";

        if ($file == plugin_basename(__FILE__)) {
            $links[] = $doc_link;
            $links[] = $support_link;
        }

        return $links;
    }

    public function shortcode($atts, $content = null)
    {
        self::$n++;

        $map_var = self::$namespace . self::$n;
        $map_div_id = self::$namespace . self::$n;

        $atts = shortcode_atts(
            array(
                'width' => '100%',
                'height' => '360px',
                'address' => '',
                'lat' => false,
                'lng' => false,
                'desc' => '',
                'show_desc' => false,
                'zoom' => '15',
                'fullscreen' => false,
                'icon' => 'red',
                'type' => 'ROADMAP',
                'disable_cache' => false,
                'scroll' => true
            ),
            $atts);

        if (!empty($content)) {
            $atts['address'] = trim($content);
        } else {
            $atts['address'] = trim($atts['address']);
        }

        $atts['desc'] = str_replace(array("\n", '"', "'"), array(' ', '\"', "\'"), $atts['desc']);

        if (substr_count($atts['desc'], '|') == 1) {
            $tmp = explode('|', $atts['desc']);
            $atts['desc'] = '<b>' . $tmp[0] . '</b><br />' . $tmp[1];
        }

        $directions = 'http://maps.google.com/?daddr=' . urlencode($atts['address']);

        $atts['desc'] = str_replace('DIRECTIONS', '<a href=\'' . $directions . '\'>directions</a>', $atts['desc']);

        $atts['desc'] = str_replace('DIRECTIONS_LINK', $directions, $atts['desc']);

        $atts['show_desc'] = (bool)$atts['show_desc'];

        $atts['fullscreen'] = (bool)$atts['fullscreen'];

        if ($atts['fullscreen']) {
            self::$fullscreenList[] = $map_div_id;
        }

        $atts['width'] = trim($atts['width']);

        if (empty($atts['width'])) {
            $atts['width'] = '100%';
        }

        if (is_numeric($atts['width'])) {
            $atts['width'] .= 'px';
        }

        $atts['height'] = trim($atts['height']);

        if (empty($atts['height'])) {
            $atts['height'] = '360px';
        }

        if (is_numeric($atts['height'])) {
            $atts['height'] .= 'px';
        }

        $atts['zoom'] = (int)$atts['zoom'];

        if ($atts['zoom'] < 0 || $atts['zoom'] > 20) {
            $atts['zoom'] = 15;
        }

        $icon = strtolower($atts['icon']);

        switch ($icon) {
            case 'red':
            case 'blue':
            case 'yellow':
            case 'green':
            case 'grey':
            case 'black':
            case 'white':
                $atts['icon'] = plugins_url('/images/pin-'. $icon . '.png', __FILE__);
                break;
            case 'default':
                $atts['icon'] = '';
                break;
            default:
        }

        $type = strtolower($atts['type']);

        switch ($type) {
            case 'roadmap':
            case 'satellite':
            case 'hybrid':
            case 'terrain':
                $atts['type'] = strtoupper($type);
                break;
            default:
                $atts['type'] = 'ROADMAP';
        }

        $atts['scroll'] = (int)(bool)$atts['scroll'];

        $atts['disable_cache'] = (bool)$atts['disable_cache'];

        if ($atts['lat'] && $atts['lng']) {
            $coordinates['lat'] = $atts['lat'];
            $coordinates['lng'] = $atts['lng'];
        } else {
            $coordinates = self::get_coordinates($atts['address'], $atts['disable_cache']);
            if (is_string($coordinates)) {
                $err = '<p style="color: red;">' . $coordinates . '</p>';
            }
        }

        if (isset($coordinates['address'])) {
            $atts['title'] = $coordinates['address'];
        } else {
            $atts['title'] = $atts['address'];
        }

        // Generate google map div
        ob_start();
        ?>

        <div class="<?php echo self::$namespace?>-containter" id="<?php echo $map_div_id ?>" style="width:<?php echo $atts['width'] ?>;height:<?php echo $atts['height'] ?>;"></div>

        <?php

        $shortcode_html = ob_get_clean();

        // Generate google map js
        $desc_js = '';

        if ($atts['desc']) {
            ob_start();

            ?>
            var infoWindow = new google.maps.InfoWindow({
                content: '<div class="<?php echo self::$namespace ?>-desc"><?php echo $atts['desc'] ?></div>'
            });

            var isShown = false;

            google.maps.event.addListener(marker, "click", function () {
                if (isShown) {
                    infoWindow.close(<?php echo $map_var ?>, marker);
                    isShown = false;
                } else {
                    infoWindow.open(<?php echo $map_var ?>, marker);
                    isShown = true;
                }
            });

            google.maps.event.addListener(<?php echo $map_var ?>, "tilesloaded", function() {
                isShown = true;
                infoWindow.open(<?php echo $map_var ?>, marker);
             });
            <?php
            $desc_js = ob_get_clean();
        }

        ob_start();

        ?>
        <script type="text/javascript">
            var <?php echo $map_var ?>;

            function <?php echo self::$namespace ?>GoogleMaps_<?php echo self::$n ?>() {

                var myLatlng = new google.maps.LatLng('<?php echo $coordinates['lat'] ?>',
                    '<?php echo $coordinates['lng'] ?>');

                var myOptions = {
                    zoom: <?php echo $atts['zoom'] ?>,
                    center: myLatlng,
                    scrollwheel: '<?php echo $atts['scroll'] ?>',
                    mapTypeId: google.maps.MapTypeId.<?php echo $atts['type'] ?>
                }

                <?php echo $map_var ?> = new google.maps.Map(
                    document.getElementById("<?php echo $map_div_id ?>"), myOptions);

                var marker = new google.maps.Marker({
                    position: myLatlng,
                    map: <?php echo $map_var ?>,
                    icon: '<?php echo $atts['icon'] ?>',
                    title: '<?php echo $atts['title'] ?>'
                });

                <?php echo $desc_js ?>
            }
        </script>
        <?php

        self::$google_maps_js .= ob_get_clean();

        return $shortcode_html;
    }

    public function get_coordinates($address, $force_refresh = false)
    {
        $address_hash = md5($address);

        if ($force_refresh || ($coordinates = get_transient($address_hash)) === false) {
            $url = 'http://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($address) . '&sensor=false';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $xml = curl_exec($ch);
            $ch_info = curl_getinfo($ch);
            curl_close($ch);

            if ($ch_info['http_code'] == 200) {

                $data = new SimpleXMLElement($xml);

                if ($data->status == 'OK') {
                    $cache_value['lat'] = (string)$data->result->geometry->location->lat;
                    $cache_value['lng'] = (string)$data->result->geometry->location->lng;
                    $cache_value['address'] = (string)$data->result->formatted_address;

                    // cache coordinates for 1 months
                    set_transient($address_hash, $cache_value, 3600 * 24 * 30 * 1);
                    $data = $cache_value;
                } elseif (!$data->status) {
                    return 'Unable to parse XML.';
                } else {
                    return 'Unable to parse address. Error code: ' . @$data->status;
                }
            } else {
                return 'Unable to connect to Google Maps API';
            }
        } else {
            $data = get_transient($address_hash);
        }

        return $data;
    }

    public function footer()
    {
        ob_start();
        echo self::$google_maps_js;
        ?>
        <script src="http://maps.google.com/maps/api/js?sensor=false" type="text/javascript"></script>
        <script type="text/javascript">
            <?php

            for ($i = 1; $i <= self::$n; $i++) {

            ?>

            <?php echo self::$namespace ?>GoogleMaps_<?php echo $i ?>();

            <?php

                $map_div_id = self::$namespace . $i;

                if (in_array($map_div_id, self::$fullscreenList)) {

            ?>
            jQuery(function ($) {
                $('#<?php echo $map_div_id ?>').append(
                    "<a href='#' title='Toggle full screen mode' " +

                    "onclick='<?php echo self::$namespace ?>ToggleFullscreen(<?php echo $i ?>)'>" +

                    "<img src='<?php echo plugins_url('/images/fullscreen.png', __FILE__) ?>'" +

                    " style='z-index: 2; border: none; position: absolute; top: 10px; right: 10px;'></a>");
            });
            <?php
                }
            }

            if (sizeof(self::$fullscreenList) > 0) {
            ?>

            var isfullscreen = false;

            function <?php echo self::$namespace ?>ToggleFullscreen(map_id) {
                var e = '#<?php echo self::$namespace?>' + map_id;

                if (!jQuery(e).data('fullscreen')) {
                    jQuery(e).data('fullscreen', jQuery(e).attr('style'));
                }

                if (isfullscreen) {
                    jQuery(e).attr('style', jQuery(e).data('fullscreen'));
                    jQuery('object').show();
                    isfullscreen = false;
                } else {
                    jQuery('object').hide();
                    isfullscreen = true;
                    jQuery(e).data('fullscreen', jQuery(e).attr('style'));
                    jQuery(e).css('position', 'fixed').css('z-index', parseInt(10000 + map_id, 10));
                    jQuery(e).css('width', '100%').css('height', '100%');
                    jQuery(e).css('top', '0').css('left', '0');
                }

                google.maps.event.trigger(eval('<?php echo self::$namespace ?>' + map_id), 'resize');

                return false;
            }
            <?php
            }
            ?>
        </script>
        <?php

        $html = ob_get_clean();
        echo $html;
    }
}

Google_Maps::getInstance();

?>