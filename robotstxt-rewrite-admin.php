<?php
/**
 * @package Robots.txt rewriter
 * @version 1.6
 */
/*
Plugin Name: Robots.txt rewrite
Plugin URI: http://wordpress.org/plugins/robotstxt-rewrite/
Description: Manage your robots.txt form admin side. Plugin provide to help search engines to indexing site correctly. A simple plugin to manage your robots.txt. Plugin donn't create the file or edit it. This plugin edit WordPress output of robots.txt content. And get you a easy and usable interface to manage it.
Author: Eugen Bobrowski
Version: 1.1
Author URI: http://atf.li/
Text Domain: robotstxt-rewrite
*/


class RobotsTxtRewrite_Admin
{

    protected static $instance;

    private function __construct()
    {
        add_action('admin_menu', array($this, 'menu_item'));
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    public function load_plugin_textdomain()
    {
        load_plugin_textdomain('robotstxt-rewrite', FALSE, basename(dirname(__FILE__)) . '/languages/');
    }

    public function menu_item()
    {
        $hook_suffix = add_options_page(
            'Robots.txt Options',
            'Robots.txt Options',
            'manage_options',
            'robots-txt-options',
            array($this, 'options_page_callback')
        );
        global $plugin_page;

        if (strpos($hook_suffix, $plugin_page)) {
            include_once plugin_dir_path(__FILE__) . 'atf-fields/htmlhelper.php';

            add_action('admin_enqueue_scripts', array($this, 'assets'));
            $this->save_options();
        }

    }

    public function save_options()
    {
        if (
            isset($_POST['robots_options']) &&
            (!isset($_POST['robots_txt_rewrite_options_nonce_field'])
                || !wp_verify_nonce($_POST['robots_txt_rewrite_options_nonce_field'], 'save_options_robots_txt_rewrite'))
        ) {
            print 'Sorry, your nonce did not verify.';
            exit;
        }

        if (isset($_POST['blog_public'])) {

            update_option('blog_public', sanitize_option('blog_public', $_POST['blog_public']));
        }
        if (isset($_POST['robots_options'])) {
            
            $to_save = array();
            foreach ($_POST['robots_options']['allows'] as $allows) {

                if (!isset($allows['path'])) continue;
                if (!isset($allows['allowed'])) $allows['allowed'] = false;

                $to_save['allows'][] = array(
                    'path' => sanitize_text_field($allows['path']),
                    'allowed' => intval($allows['allowed']),
                    'bots' => !empty($allows['bots']) ? array_map('sanitize_text_field', $allows['bots']) : array() ,
                );
            }

            update_option('robots_options', $to_save);
        }


    }

    public function assets()
    {
        AtfHtmlHelper::assets();
    }

    public function options_page_callback()
    {


        ?>
        <div class="wrap atf-fields">

            <h2><?php echo esc_html(get_admin_page_title()); ?>
                <a href="<?php echo site_url('/robots.txt') ?>"
                   target="_blank"
                   onclick="window.open('<?php echo site_url('/robots.txt') ?>', 'popupwindow', 'resizable=1,scrollbars=1,width=500,height=600');return false;"
                   class="page-title-action">robots.txt</a>
            </h2>
            <?php
            $options = $this->get_options();
            ?>

            <form method="post">
                <?php wp_nonce_field('save_options_robots_txt_rewrite', 'robots_txt_rewrite_options_nonce_field'); ?>

                <table class="form-table">
                    <tr class="form-field form-required">
                        <th scope="row"><label><?php _e('Search Engine Visibility'); ?></label></th>
                        <td><?php AtfHtmlHelper::tumbler(array(
                                'id' => 'blog_public',
                                'value' => $options['blog_public'],

                            )); ?></td>
                    </tr>
                    <tr class="form-required">
                        <td colspan="2">
                            <?php AtfHtmlHelper::group(array(
                                    'name' => 'robots_options[allows]',
                                    'items' => array(

                                        'path' => array(
                                            'title' => __('Path', 'robotstxt-rewrite'),
                                            'type' => 'text',
                                            'desc' => __('Relative path of WordPress installation directory', 'robotstxt-rewrite'),

                                        ),
//                                        'bots' => array(
//                                            'title' => __('Robots names', 'robotstxt-rewrite'),
//                                            'type' => 'multiselect',
//                                            'class' => '',
//                                            'vertical' => false,
//                                            'options' => array(
//                                                '*' => __('All', 'robotstxt-rewrite'),
//                                                'googlebot' => 'Google',
//                                                'googlebot-mobile' => 'Google Mobile',
//                                                'googlebot-image' => 'Google Images',
//                                                'Yandex' => 'Yandex',
//                                            ),
//                                            'value' => $options['bots'],
//                                            'desc' => __('Select the user agents for which this setting is avelliable', 'robotstxt-rewrite'),
//
//                                        ),
                                        'allowed' => array(
                                            'title' => __('Allow', 'robotstxt-rewrite'),
                                            'type' => 'tumbler',
                                            'options' => array('plain' => 'Text', 'html' => 'HTML'),
                                            'desc' => __('Allow / Disallow', 'robotstxt-rewrite'),
                                            'cell_style' => 'text-align: center;',
                                        ),

                                    ),
                                    'value' => $options['allows'],
                                )
                            );
                            ?>
                        </td>
                    </tr>
                </table>

                <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
                                         value="Submit"></p>

            </form>
        </div>
        <?php
    }

    public function get_options()
    {
        $site_url = site_url();

        if (is_admin() && (strpos(content_url(), $site_url) === false)) {
            $message = __('Your content directory is located at another domain. You can use this page to set robots options only for current domain .', 'robotstxt-rewrite');
            echo "<div class='notice notice-warning'><p>" . $message . "</p></div>";
        }

        if (file_exists(ABSPATH . '/robots.txt')) {
            $message = __('You have an existing file robots.txt in the root of your site. Please delete it or rename to this options will be fully applied.', 'robotstxt-rewrite');
            echo "<div class='notice notice-warning'><p>" . $message . "</p></div>";
        }


        $defaults = array(
            'blog_public' => get_option('blog_public'),
            //default demo paths
            'allows' => array(
                array(
                    'allowed' => 1,
                    'path' => '/',
                )),
            'bots' => '',
            );


        if (strpos(admin_url(), $site_url) !== false)
            $defaults['allows'][] = array(
                'allowed' => 0,
                'path' => str_replace($site_url, '', admin_url()),
            );

        if (strpos(includes_url(), $site_url) !== false)
            $defaults['allows'][] = array(
                'allowed' => 0,
                'path' => str_replace($site_url, '', includes_url()),
            );
        if (strpos(plugins_url(), $site_url) !== false)
            $defaults['allows'][] = array(
                'allowed' => 0,
                'path' => str_replace($site_url, '', plugins_url('/')),
            );
        if (strpos(content_url(), $site_url) !== false)
            $defaults['allows'][] = array(
                'allowed' => 0,
                'path' => str_replace($site_url, '', content_url('cache/')),
            );
        if (strpos(get_theme_root_uri(), $site_url) !== false)
            $defaults['allows'][] = array(
                'allowed' => 0,
                'path' => str_replace($site_url, '', get_theme_root_uri()) . '/',
            );
        if (strpos(admin_url(), $site_url) !== false)
            $defaults['allows'][] = array(
                'allowed' => 1,
                'path' => str_replace($site_url, '', admin_url('admin-ajax.php')),
            );


        $options = wp_parse_args(get_option('robots_options'), $defaults);
        return $options;
    }

}

RobotsTxtRewrite_Admin::get_instance();
