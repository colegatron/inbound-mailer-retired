<?php

/**
 *    Shows Preview of Inbound Email
 */
class Inbound_Email_Preview {
    static $smartbar_enable;
    static $smartbar_content;
    static $smartbar_background_color;
    static $smartbar_font_color;
    static $smartbar_padding;
    static $smartbar_js;

    /**
     *    Initializes class
     */
    function __construct() {
        self::load_hooks();
    }

    /**
     *    Loads hooks and filters
     */
    public function load_hooks() {
        add_action('plugins_loaded', array(__CLASS__, 'load_acf_definitions'), 11);
        add_action('inbound-mailer/email/header', array(__CLASS__, 'load_header_scripts'), 11);
        add_action('inbound-mailer/email/footer', array(__CLASS__, 'load_footer_scripts'), 11);
        add_filter('single_template', array(__CLASS__, 'load_email'), 11);
    }

    /**
     * Load ACF definitions:smartbar
     */
    public static function load_acf_definitions() {

        include_once( INBOUND_EMAIL_PATH . 'assets/acf/smartbar.php');
    }
    /**
     * loads jquery for web version of email
     */
    public static function load_header_scripts() {
        global $post;

        self::$smartbar_enable = get_field("smartbar_enable", $post->ID);

        if (!self::$smartbar_enable) {
            return;
        }

        self::$smartbar_content = get_field("smartbar_content", $post->ID );
        self::$smartbar_background_color = get_field("smartbar_background_color", $post->ID );
        self::$smartbar_font_color = get_field("smartbar_font_color", $post->ID );
        self::$smartbar_padding = get_field("smartbar_padding", $post->ID );
        self::$smartbar_js = get_field("smartbar_js", $post->ID );

        ?>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.2/jquery.min.js"></script>
        <style type="text/css">
            nav {
                transition: all .1s ease-in-out .1s;
                background-color: #3FB7E4;
                color: #fff;
                padding: 0;
                width: 100%;
                position: fixed;
                display:inline-flex;
            }

            body {
                padding:0px;
                margin:0px;
            }

        </style>
        <?php
    }

    /**
     * loads subscribe call to action for web version of email
     */
    public static function load_footer_scripts() {

        if (!self::$smartbar_enable) {
            return;
        }
echo json_encode(self::$smartbar_content );exit;
        ?>
        <script type="text/javascript">
            var Subscribe = (function () {
                var smartbar_content,
                    smartbar_font_color,
                    smartbar_background_color,
                    smartbar_padding;

                var methods = {

                    /**
                     *  Initialize Script
                     */
                    init: function () {
                        Subscribe.setupVars();
                        Subscribe.addListeners();
                        Subscribe.createNav();
                    },
                    /**
                     * Setup Variables
                     */
                    setupVars: function () {
                        Subscribe.smartbar_content = <?php echo json_encode(array('html'=>self::$smartbar_content) ); ?>;
                        Subscribe.smartbar_font_color = <?php echo json_encode(self::$smartbar_font_color ); ?>;
                        Subscribe.smartbar_background_color = <?php echo json_encode(self::$smartbar_background_color ); ?>;

                    },
                    /**
                     * Add Listeners
                     */
                    addListeners: function () {

                    },

                    /**
                     * Create Navigation Elements
                     */
                    createNav: function () {
                        var nav = jQuery("<nav></nav>").attr('class', 'subscribe-container').text(JSON.parse(Subscribe.smartbar_content));
                        var prompt = jQuery("<div></div>").attr('class', 'subscribe-prompt');
                        var content = jQuery("<div></div>").attr('class', 'subscribe-content');

                        nav.prepend(prompt);
                        nav.prepend(content);
                        nav.prepend(content);

                        jQuery('body').prepend(nav);

                        Subscribe.stickNav();

                    },
                    stickNav: function () {
                        var lastScrollTop = 0,
                            header = jQuery('nav'),
                            headerHeight = header.height();

                        header.css( 'margin-bottom'  ,headerHeight+'px' );

                        jQuery(window).scroll(function () {
                            var scrollTop = jQuery(window).scrollTop()
                            jQuery('.scrollTop').html(scrollTop);

                            if (scrollTop > lastScrollTop) {
                                header.css('top','-'+headerHeight+'px')
                                //header.animate({top:'-'+headerHeight+'px'}, 200)
                            } else {
                                header.css('top','0px')
                                //header.animate({top:'0px'}, 200)
                            }

                            lastScrollTop = scrollTop;

                        });
                    },
                    expandNav: function () {

                    },
                    collapseNav: function () {

                    }
                }

                return methods;
            })();
            Subscribe.init();
        </script>
        <?php
    }

    /**
     *    Detects request to view inbound-email post type and loads correct email template
     */
    public static function load_email($template) {

        global $wp_query, $post, $query_string, $Inbound_Mailer_Variations;

        if ($post->post_type != "inbound-email") {
            return $template;
        }

        /* Load email templates */
        Inbound_Mailer_Load_Templates();

        $vid = $Inbound_Mailer_Variations->get_current_variation_id();
        $template = $Inbound_Mailer_Variations->get_current_template($post->ID, $vid);

        if (!isset($template)) {
            return;
        }


        if (file_exists(INBOUND_EMAIL_PATH . 'templates/' . $template . '/index.php')) {
            return INBOUND_EMAIL_PATH . 'templates/' . $template . '/index.php';
        } else if (file_exists(INBOUND_EMAIL_UPLOADS_PATH . $template . '/index.php')) {
            return INBOUND_EMAIL_UPLOADS_PATH . $template . '/index.php';
        } else if (file_exists(INBOUND_EMAIL_THEME_TEMPLATES_PATH . $template . '/index.php')) {
            return INBOUND_EMAIL_THEME_TEMPLATES_PATH . $template . '/index.php';
        }


        return $single;
    }
}

$Inbound_Email_Preview = new Inbound_Email_Preview();