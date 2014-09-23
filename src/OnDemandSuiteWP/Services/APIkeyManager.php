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
 * This class organizes the storage and management of the API keys used
 * to access the IDOL API. It does so by providing a settings page in the admin settings menu
 * and some helper methods publicly available for plugins and themes.
 */
final class APIkeyManager extends \OnDemandSuiteWP\Utils\HTTPClient
{
    /**
     * @var OnDemandSuiteWP\Services\APIkeyManager Singleton instance holder.
     */
    private static $instance;

    /**
     * @var array Module settings.
     */
    private $settings;

    const OPTIONS_STORAGE_KEY = 'OnDemandSuiteWP_APIkeyManager';

    /**
     * Getter for the singleton class instance.
     * There should be only one instance handling the API keys managment
     * thus the singleton pattern is used.
     *
     * @return OnDemandSuiteWP\Services\APIkeyManager The created current instance
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

        // Load the saved settings or init them for first time.
        $this -> settings = get_option(self::OPTIONS_STORAGE_KEY);
        if( $this -> settings === FALSE ){
            $def = array(
                'default' => NULL,
                'keys' => array()
            );
            update_option(self::OPTIONS_STORAGE_KEY, $def);
            $this -> settings = $def;
        }

        // If there is a default API key already saved use it.
        if($this -> settings['default']!==NULL )
            parent::__construct($this -> settings['keys'][$this -> settings['default']]);

        // For admin area only.
        if( is_admin() ){
            // Register settings page.
            add_action('admin_menu', function(){
                add_options_page(
                    __('HP IDOL OnDemand - API Keys', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain'),
                    __('IDOL API Keys', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain'),
                    'manage_options',
                    'OnDemandSuiteWP_APIkeyManager',
                    function(){
                        include __DIR__ . '/../Partials/APIkeyManager-SettingsPage.html';
                    }
                );
            });
            // Init the settings page.
            add_action('admin_init', function(){
                $this -> init_settings_page();
            });
        }
    }

    /**
     * Initializes the fields on the settings page.
     */
    private function init_settings_page()
    {
        add_settings_section(
            self::OPTIONS_STORAGE_KEY,
            null,
            null,
            'OnDemandSuiteWP_APIkeyManager'
        );

        // Loop keys to render controlls.
        foreach($this -> settings['keys']?$this -> settings['keys']:array() as $key => $val){
            add_settings_field(
                'key_'.$key,
                null,
                function() use($key, $val){
                    echo '<input type="text" id="key_'.$key.'" class="regular-text" name="'.self::OPTIONS_STORAGE_KEY.'[keys]['.$key.']" value="'.$val.'" spellcheck="false" readonly/>',
                         '<br>',
                         '<input type="radio" id="l_key_'.$key.'" name="'.self::OPTIONS_STORAGE_KEY.'[default]" value="'.$key.'" '.checked($this->settings['default'], $key, false).'/>',
                         '<label for="l_key_'.$key.'">'.__('Default', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain').'</label>',
                         '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" id="del_key_'.$key.'" name="'.self::OPTIONS_STORAGE_KEY.'[del_'.$key.']"/>',
                         '<label for="del_key_'.$key.'">'.__('Delete', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain').'</label>';
                },
                'OnDemandSuiteWP_APIkeyManager',
                self::OPTIONS_STORAGE_KEY
            );
        }

        // Always add new key filed.
        add_settings_field(
            'new_api_key',
            __('New API key', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain'),
            function(){
                echo '<input type="text" id="new_api_key" class="regular-text" name="'.self::OPTIONS_STORAGE_KEY.'[new_api_key]" spellcheck="false"/>',
                     '<br>',
                     '<input type="radio" id="new_api_key_as_default" name="'.self::OPTIONS_STORAGE_KEY.'[default]" value="new_api_key_as_default" '.checked(count($this->settings['keys']), 0, false).'/>',
                     '<label for="new_api_key_as_default">'.__('Default', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain').'</label>';
            },
            'OnDemandSuiteWP_APIkeyManager',
            self::OPTIONS_STORAGE_KEY
        );

        // Finally register the settings.
        register_setting(
            self::OPTIONS_STORAGE_KEY,
            self::OPTIONS_STORAGE_KEY,
            // Settings update validator(sanitize).
            function($input){
                // This is what will be returned by this validator function
                // after its modifications.
                $res = $this -> settings;

                // Handle default key changes.
                if( $input['default']!='new_api_key_as_default' && $input['default']!=$res['default'] )
                    $res['default'] = intval($input['default']);

                // Handle deletes.
                // To delete chekboxes are named `del_<index>` so extract them all and do delete if any.
                // Update the default key index as needed.
                $to_del = array_filter(array_keys($input), function($key){
                    return substr($key, 0, 4) == 'del_';
                });
                foreach($to_del as $ind){
                    $i = substr($ind, 4);
                    unset( $res['keys'][$i] );
                    $res['keys'] = array_values( $res['keys'] );
                    if( $res['default'] == $i )
                        $res['default'] = NULL;
                    else if($res['default'] > $i)
                        $res['default'] = $res['default'] - 1;
                }
                if( !empty($to_del) && $res['default'] == NULL && count($res['keys'])>=1 )
                    $res['default'] = 0;


                // New Key add.
                if( $input['new_api_key'] && !in_array($input['new_api_key'], $res['keys']) ){
                    // Test the key.
                    if( $this -> isValidAPIkey($input['new_api_key']) ){
                        $indx = array_push($res['keys'], $input['new_api_key']);
                        if( $input['default']=='new_api_key_as_default' )
                            $res['default'] = $indx-1;
                    }else{
                        add_settings_error(
                            'OnDemandSuiteWP_APIkeyManager_Add_Key_Error',
                            esc_attr('settings_updated'),
                            __('Request to the IDOL OnDemand API with the provided key has failed!', 'HP_IDOL_OnDemand_APIkey_Manager_Textdomain'),
                            'error'
                        );
                    }
                }

                // Return the data which should be stored.
                return $res;
            }
        );
    }

    /**
     * IDOL API Key validator.
     * Probably the best way to validate a key is to make a dummy request to the API
     * and see if 401 rsp error code with this key occurs.
     *
     * @param string $key_to_test The key string which sould be validated
     *
     * @return boolean Accepted or Not
     */
    public function isValidAPIkey($key_to_test)
    {
        return !$this -> IDOLget(array(
            'ident' => 'listindexes',
            'qparams' => array(
                'apikey' => $key_to_test
            )
        )) -> isError;
    }

    /**
     * Default API key public getter.
     * Handy method to allow plugins/themes to obtain the current API key, managed by this class for whatever purpose they need it.
     * Implemented as static function so one could easily use APIkeyManager::defKey()
     * instead of APIkeyManager::getInstance() -> getKey() to obtain the current used key.
     * It just looks nicer but of course the opposite is posible too.
     *
     * @return string|null The current default IDOL OnDemand API key
     */
    public static function defKey()
    {
        if( isset(self::$instance) )
            return self::$instance -> getKey();
        else{
            $keys = get_option(self::OPTIONS_STORAGE_KEY);
            if($keys && $keys['default'] !== NULL)
                return $keys['keys'][$keys['default']];
        }
        return NULL;
    }

    /**
     * Default API key public getter.
     * Look at `defKey()` for more info.
     */
    public function getKey()
    {
        if( $this -> settings['default'] !== NULL )
            return $this -> settings['keys'][$this -> settings['default']];
        return NULL;
    }
}
