<?php
/*
 * This file is part of the HP IDOL OnDemand Suite for WP.
 *
 * (c) 2014 Kiril Kartunov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace OnDemandSuiteWP\Services;


/**
 * Represnts widget on the admin dashboard.
 */
final class DashboardWidget extends \OnDemandSuiteWP\Utils\HTTPClient
{
    /**
     * @var OnDemandSuiteWP\Services\DashboardWidget Singleton instance holder.
     */
    private static $instance;

    /**
     * Getter for the singleton class instance.
     * There should be only one instance providing this dashboard widget
     * thus the singleton pattern is used.
     *
     * @return HP_IDOL_OnDemand_Dashboard_Widget The created current instance
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

        // This widget will need to make authorized requests to the IDOL API thus it needs API key.
        // Do its thing only when such presented via a APIkeyManager instance and user is viewing the admin area.
        if( is_admin() ){
            // Setup the API key to use latter.
            parent::__construct( APIkeyManager::defKey() );
            // Show the widget only to the users capable of manage options.
            // Because at this point it is too early we wait until all plugins are loaded.
            add_action('plugins_loaded', function(){
                if( current_user_can('manage_options') ){
                    // Setup the widget.
                    add_action('wp_dashboard_setup', function(){
                        $this -> add_dashboard_widget();
                    });

                    // Prepare widget assets and data.
                    add_action('admin_init', function(){
                        $this -> prepareAssets();
                    });
                }
            });
        }

        // Setup a backend ajax handler.
        $this -> handleAjax();

    }

    /**
     * Widget's backend access point.
     */
    private function handleAjax()
    {
        add_action('wp_ajax_ODSWP_DashboardWidget', function(){
            // Make sure the widget indeed is requesting tasks.
            if( !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ODSWP_DashboardWidget') )
                exit('Cheating?');

            // Parse request data
            $request = json_decode(file_get_contents('php://input'), true);

            // Process according task.
            switch($request['task']){
                // Request uindex status.
                case 'status':
                    $status = $this -> IDOLget(array(
                        'ident' => 'indexstatus',
                        'qparams' => array(
                            'index' => $request['uindex']
                        )
                    ));
                    if( !$status -> isError )
                        $this -> JSONrsp(200, $status -> body);
                    else
                        $this -> JSONrsp(500, NULL, $status -> errorMsg);
                break;
                case 'uindex_create':
                    $uindex_create = $this -> IDOLget(array(
                        'ident' => 'createtextindex',
                        'qparams' => array(
                            'index' => $request['uindex'],
                            'flavor' => $request['flavor'],
                            'description' => isset($request['desc'])? $request['desc']:''
                        )
                    ),array(
                        'timeout' => 30
                    ));
                    if( !$uindex_create -> isError )
                        $this -> JSONrsp(200, $uindex_create -> body);
                    else
                        $this -> JSONrsp(500, $uindex_create -> error, $uindex_create -> errorMsg);
                break;
                case 'uindex_drop':
                    // Request index deletion.
                    $uindex_delete = $this -> IDOLget(array(
                        'ident' => 'deletetextindex',
                        'qparams' => array(
                            'index' => $request['uindex']
                        )
                    ),array(
                        'timeout' => 30
                    ));
                    if( !$uindex_delete -> isError )
                        $this -> JSONrsp(200, $uindex_delete -> body);
                    else
                        $this -> JSONrsp(500, $uindex_delete -> error, $uindex_delete -> errorMsg);
                break;
                case 'uindex_drop_confirm':
                    // Confirm index deletion.
                    $uindex_delete_confirm = $this -> IDOLget(array(
                        'ident' => 'deletetextindex',
                        'qparams' => array(
                            'index' => $request['uindex'],
                            'confirm' => $request['confirm']
                        )
                    ),array(
                        'timeout' => 30
                    ));
                    if( !$uindex_delete_confirm -> isError )
                        $this -> JSONrsp(200, $uindex_delete_confirm -> body);
                    else
                        $this -> JSONrsp(500, $uindex_delete_confirm -> error, $uindex_delete_confirm -> errorMsg);
                break;
            }
        });
    }

    /**
     * Helper adding the widget to the dashboard.
     */
    private function add_dashboard_widget()
    {
        wp_add_dashboard_widget(
            'ODSWP_DashboardWidget',
            __('HP IDOL OnDemand Suite For WP', 'HP_IDOL_OnDemand_Dashboard_Widget_Textdomain'),
            function(){
                include __DIR__ . '/../Partials/DashboardWidget-Content.html';
            }
        );
    }

    /**
     * Helper adding assets to the dashboard page on load.
     */
    private function prepareAssets()
    {
        global $pagenow;
        // Only for the index(dashboards home)
        if( $pagenow == 'index.php' ){
            // JS localized data.
            $localize = array(
                'error' => 0,
                'error_txt' => '',
                'nonce' => wp_create_nonce('ODSWP_DashboardWidget')
            );

            // Get the key indexes from the API.
            $key_indexes = $this -> IDOLget(array(
                'ident' => 'listindexes'
            ));
            if( !$key_indexes -> isError )
                $localize['data'] = $key_indexes -> body;
            else{
                $localize['error'] = true;
                $localize['error_txt'] = $key_indexes -> errorMsg;
            }

            // Load font-awesome
            wp_register_style('Fontawesome', plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/fontawesome/css/font-awesome.min.css');
            wp_enqueue_style('Fontawesome');
            // ngModal CSS
            wp_register_style('ngModal', plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/ngModal/dist/ng-modal.css');
            wp_enqueue_style('ngModal');
            // Load widget CSS
            wp_register_style(
                'ODSWP_DashboardWidgetCSS',
                plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'src/OnDemandSuiteWP/Assets/less/dist/DashboardWidget.css'
            );
            wp_enqueue_style('ODSWP_DashboardWidgetCSS');
            // Load lodash
            wp_register_script(
                'lodash',
                plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/lodash/dist/lodash.compat.min.js'
            );
            wp_enqueue_script('lodash');
            // Load angular
            wp_register_script(
                'angular',
                plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/angular/angular.min.js'
            );
            wp_enqueue_script('angular');
            // Load ngModal
            wp_register_script(
                'ngModal',
                plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'vendor/ngModal/dist/ng-modal.js',
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
                'ODSWP_DashboardWidgetJS',
                plugin_dir_url(OnDemandSuiteWP_BASE_FILE).'src/OnDemandSuiteWP/Assets/js/dist/DashboardWidget.js',
                array('angular', 'ngModal', 'lodash', 'ngAnimate')
            );
            wp_localize_script(
                'ODSWP_DashboardWidgetJS',
                'ODSWP_DashboardWidget',
                $localize
            );
            wp_enqueue_script('ODSWP_DashboardWidgetJS');
        }
    }
}
