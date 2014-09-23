<?php
/*
 * This file is part of the HP IDOL OnDemand Suite for WP.
 *
 * (c) 2014 Kiril Kartunov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace OnDemandSuiteWP\Services
{
    /**
     * Represents class providing useful services when content (post/page) is edited or created.
     */
    final class ContentEditWidget extends \OnDemandSuiteWP\Utils\HTTPClient
    {
        /**
         * @var OnDemandSuiteWP\Services\ContentEditWidget Singleton instance holder.
         */
        private static $instance;

        const SentimentMetaKey = "ODSWP_sentiment";

        /**
         * Getter for the singleton class instance.
         * There should be only one instance of this wodget
         * thus the singleton pattern is used.
         *
         * @return OnDemandSuiteWP\Services\ContentEditWidget The created current instance.
         */
        public static function getInstance()
        {
            if (!isset(self::$instance)) {
                $className = __CLASS__;
                self::$instance = new $className;
            }
            return self::$instance;
        }

        /**
         * Constructor.
         */
        public function __construct()
        {
            // Make sure there is only one instance of this class indeed and return it if __construct was called directly.
            if( isset(self::$instance) )
                return self::$instance;
            else self::$instance = $this;

            // This widget needs IDOL API key.
            // Do not work if such missing.
            if( !APIkeyManager::defKey() )
                return;

            // Setup the API key to use latter.
            parent::__construct( APIkeyManager::defKey() );
            // Admin area.
            if( is_admin() ){
                add_action('add_meta_boxes', array($this, 'add_meta_box'));
                add_action('save_post', array($this, 'save_post'));
                add_action('admin_init', function(){ $this -> admin_init(); });
                // Add custom column to post/pages lists to show representation.
                add_filter('manage_posts_columns', function($columns){ return $this -> addPluginColumn($columns); });
                add_action('manage_posts_custom_column', function($column, $post_id){ $this -> fillPluginColumn($column, $post_id); }, 10, 2);
                add_filter('manage_pages_columns', function($columns){ return $this -> addPluginColumn($columns); });
                add_action('manage_pages_custom_column', function($column, $post_id){ $this -> fillPluginColumn($column, $post_id); }, 10, 2);
            }

            // Setup a backend ajax handler.
            $this -> handleAjax();
        }

        /**
         * Widget's backend access point.
         */
        private function handleAjax()
        {
            add_action('wp_ajax_ODSWP_ContentEditWidget', function(){
                // Make sure the widget indeed is requesting tasks.
                if( !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ODSWP_ContentEditWidget') )
                    exit('Cheating?');

                // Parse request data
                if( isset($_GET['_filepost_']) ){
                    // Process according task but in mind that this is a file post!
                    switch($_GET['task']){
                        case 'OCRImageFile':
                            // OCR Image File
                            // TODO Comming soon
                        break;
                    }
                }else{
                    $request = json_decode(file_get_contents('php://input'), true);
                    // Process according task.
                    switch($request['task']){
                        case 'OCRImage':
                            // OCR Image
                            $OCR_image = $this -> IDOLget(array(
                                'ident' => 'ocrdocument',
                                'qparams' => array(
                                    'mode' => $request['mode'],
                                    strtolower($request['src']) => $request['value']
                                )
                            ), array(
                                'timeout' => 30
                            ));
                            if( !$OCR_image -> isError )
                                $this -> JSONrsp(200, $OCR_image -> body);
                            else
                                $this -> JSONrsp(500, $OCR_image -> error, $OCR_image -> errorMsg);
                        break;
                        case 'SntmText':
                            // Sentiment analyz.
                            $Sntm = $this -> IDOLget(array(
                                'ident' => 'analyzesentiment',
                                'qparams' => array(
                                    'language' => $request['language'],
                                    strtolower($request['src']) => $request['value']
                                )
                            ), array(
                                'timeout' => 30
                            ));
                            if( !$Sntm -> isError )
                                $this -> JSONrsp(200, $Sntm -> body);
                            else
                                $this -> JSONrsp(500, $Sntm -> error, $Sntm -> errorMsg);
                        break;
                        case 'HghLigText':
                            // Highlight text.
                            $HghLig = $this -> IDOLget(array(
                                'ident' => 'highlighttext',
                                'qparams' => array(
                                    strtolower($request['src']) => $request['value'],
                                    'highlight_expression' => $request['highlight_expression'],
                                    'start_tag' => isset($request['start_tag'])? $request['start_tag']:'<span style="background-color: yellow">'
                                )
                            ), array(
                                'timeout' => 30
                            ));
                            if( !$HghLig -> isError )
                                $this -> JSONrsp(200, $HghLig -> body);
                            else
                                $this -> JSONrsp(500, $HghLig -> error, $HghLig -> errorMsg);
                        break;
                    }
                }
            });
        }

        /**
         * Add plugin's metabox in edit post/page GUI.
         *
         * @params string $post_type Gets called by WP with current post type beeing edited
         */
        public function add_meta_box($post_type)
        {
            $show_on_post_types = array(
                'post',
                'page'
            );
            if( in_array($post_type, $show_on_post_types) ){
                add_meta_box(
                    'ContentEditWidget_GUI',
                    __('HP IDOL OnDemand Suite For WP', 'HP_IDOL_OnDemand_ContentEditWidget_Textdomain'),
                    function($post){
                        // Get the post's sentiment key if exists.
                        $SentimentMetaKey = get_post_meta( $post -> ID, self::SentimentMetaKey, true);
                        // Add security nonce to our control box.
                        wp_nonce_field('ContentEditWidget_GUI', 'ContentEditWidget_GUI_meta_box_nonce');
                        include __DIR__ . '/../Partials/ContentEditWidget-HTML.html';
                    },
                    $post_type,
                    'side'
                );
            }
        }

        /**
         * Hook on save post event to update plugin internal data.
         *
         * @params string $post_id Gets called by WP with current post id beeing saved
         */
        public function save_post($post_id)
        {
            // Check if this came from plugin screen because this hook
            // can be triggered at other times.
            if( ! isset($_POST['ContentEditWidget_GUI_meta_box_nonce']) ||
                ! wp_verify_nonce($_POST['ContentEditWidget_GUI_meta_box_nonce'], 'ContentEditWidget_GUI') )
                // It was not, just pass further...
                return;
            // When doing autosave the plugin form won't be submitted either.
            // Thus again we do nothing.
            if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return;
            // If this is just a revision, again pass further.
            if( wp_is_post_revision($post_id) )
                return;

            // When still here let's do some work...
            $post = get_post($post_id);
            $SentimentMetaKey = get_post_meta( $post -> ID, self::SentimentMetaKey, true);
            if( isset($_POST['ODSWP_SentimentTrack']) ){
                // Add sentiment update to the post
                $Sntm = $this -> IDOLget(array(
                    'ident' => 'analyzesentiment',
                    'qparams' => array(
                        'text' => $post -> post_content
                    )
                ), array(
                    'timeout' => 10
                ));
                if( !$Sntm -> isError ){
                    // Only when no errors occured!
                    update_post_meta($post -> ID, self::SentimentMetaKey, $Sntm -> body);
                }
            }else if( $SentimentMetaKey ){
                // Delete the sentiment key
                delete_post_meta( $post -> ID, self::SentimentMetaKey );
            }
        }

        /**
         * Manage plugin styles/scripts under the admin area.
         */
        private function admin_init()
        {
            global $pagenow;
            if( $pagenow == 'post-new.php' || $pagenow == 'post.php' ){
                // Load control GUI assets.
                // JS localized data.
                $localize = array(
                    'error' => 0,
                    'error_txt' => '',
                    'nonce' => wp_create_nonce('ODSWP_ContentEditWidget')
                );

                // Load font-awesome
                wp_register_style('Fontawesome', plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/fontawesome/css/font-awesome.min.css');
                wp_enqueue_style('Fontawesome');
                // ngModal CSS
                wp_register_style('ngModal', plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/ngModal/dist/ng-modal.css');
                wp_enqueue_style('ngModal');
                // Load widget CSS
                wp_register_style(
                    'ODSWP_ContentEditWidgetCSS',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'src/OnDemandSuiteWP/Assets/less/dist/ContentEditWidget.css'
                );
                wp_enqueue_style('ODSWP_ContentEditWidgetCSS');
                // Load lodash
                wp_register_script(
                    'lodash',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/lodash/dist/lodash.compat.min.js'
                );
                wp_enqueue_script('lodash');

                // Load angular-file-upload-shim
                wp_register_script(
                    'angular-file-upload-shim',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/ng-file-upload/angular-file-upload-shim.min.js'
                );
                wp_enqueue_script('angular-file-upload-shim');

                // Load angular
                wp_register_script(
                    'angular',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/angular/angular.min.js',
                    array('angular-file-upload-shim')
                );
                wp_enqueue_script('angular');

                // Load angular-file-upload
                wp_register_script(
                    'angular-file-upload',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/ng-file-upload/angular-file-upload.min.js',
                    array('angular-file-upload-shim', 'angular')
                );
                wp_enqueue_script('angular-file-upload');

                // Load ngModal
                wp_register_script(
                    'ngModal',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/ngModal/dist/ng-modal.min.js',
                    array('angular')
                );
                wp_enqueue_script('ngModal');

                // Load ngAnimate
                wp_register_script(
                    'ngAnimate',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/angular-animate/angular-animate.min.js',
                    array('angular')
                );
                wp_enqueue_script('ngAnimate');

                // Load the widget JS
                wp_register_script(
                    'ODSWP_ContentEditWidgetJS',
                    plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'src/OnDemandSuiteWP/Assets/js/dist/ContentEditWidget.js',
                    array('angular', 'ngModal', 'lodash', 'ngAnimate')
                );
                wp_localize_script(
                    'ODSWP_ContentEditWidgetJS',
                    'ODSWP_ContentEditWidget',
                    $localize
                );
                wp_enqueue_script('ODSWP_ContentEditWidgetJS');
            }
            if( $pagenow == 'edit.php'){
                // Table related CSS
                wp_register_style('Fontawesome', plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/fontawesome/css/font-awesome.min.css');
                wp_enqueue_style('Fontawesome');
                wp_register_style('ContentEditWidgetListings', plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'src/OnDemandSuiteWP/Assets/less/dist/ContentEditWidgetListings.css');
                wp_enqueue_style('ContentEditWidgetListings');
            }
        }

        /**
         * Add custom column to post/pages lists to show representation.
         *
         * @params array $columns Gets called by WP with all columns that will be rendered on the edit.php page
         */
        private function addPluginColumn($columns)
        {
            return
                array_slice($columns, 0, 1, true) +
                array('ODSWP_sentiment' => '') +
                array_slice($columns, 1, count($columns)-1, true);
        }

        /**
         * Fill the custom column added to post/pages lists.
         */
        private function fillPluginColumn($column, $post_id)
        {
            if($column == 'ODSWP_sentiment'){
                $SentimentMetaKey = get_post_meta( $post_id, self::SentimentMetaKey, true);
                if( $SentimentMetaKey )
                    switch($SentimentMetaKey['aggregate']['sentiment']){
                    case 'positive':
                        echo "<i class='fa fa-smile-o fa-lg positive' title='IDOL thinks this content is positive.\nScore: ".round($SentimentMetaKey['aggregate']['score'], 3)."'></i>";
                    break;
                    case 'negative':
                        echo "<i class='fa fa-frown fa-lg negative' title='IDOL thinks this content is negative.\nScore: ".round($SentimentMetaKey['aggregate']['score'], 3)."'></i>";
                    break;
                    case 'neutral':
                        echo "<i class='fa fa-meh-o fa-lg neutral' title='IDOL thinks this content is neutral.\nScore: ".round($SentimentMetaKey['aggregate']['score'], 3)."'></i>";
                    break;
                }
            }
        }
    }
}

// -----------
// Global code.
// Functions and sorcuts for plugins and themes
// made available by this module.
// -----------
namespace
{
    use OnDemandSuiteWP\Services\ContentEditWidget;

    function ODSWP_is_positive(){
        if(!in_the_loop())
            return null;
        $sentiment = get_post_meta(get_the_ID(), ContentEditWidget::SentimentMetaKey, true);
        return $sentiment? ($sentiment['aggregate']['sentiment'] == 'positive'): false;
    }

    function ODSWP_is_negative(){
        if(!in_the_loop())
            return null;
        $sentiment = get_post_meta(get_the_ID(), ContentEditWidget::SentimentMetaKey, true);
        return $sentiment? ($sentiment['aggregate']['sentiment'] == 'negative'): false;
    }

    function ODSWP_is_neutral(){
        if(!in_the_loop())
            return null;
        $sentiment = get_post_meta(get_the_ID(), ContentEditWidget::SentimentMetaKey, true);
        return $sentiment? ($sentiment['aggregate']['sentiment'] == 'neutral'): false;
    }
}
