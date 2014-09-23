<?php
/*
Plugin Name: HP IDOL OnDemand Suite For WP
Description: HP IDOL OnDemand Suite For WP is a plugin for working with the IDOL OnDemand API
Version: 0.1.0
Author: Kiril Kartunov
Author URI: mailto:kiri4a@gmail.com?Subject=HP_IDOL_OnDemand_Suite_For_WP
Author Email: kiri4a@gmail.com
License:
                    GNU GENERAL PUBLIC LICENSE
                       Version 2, June 1991

 Copyright (C) 1989, 1991 Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 Everyone is permitted to copy and distribute verbatim copies
 of this license document, but changing it is not allowed.
*/


/*
 * This file is the main(entry) file loaded by WP
 * for the HP IDOL OnDemand Suite for WP plugin family.
 *
 * (c) 2014 Kiril Kartunov
 */


// Plugins should be loaded only by WP, not directly! Thus prevent direct access.
if( !defined('ABSPATH') ) exit;

// --------
// This suite is modular by design.
// This plugin is one of the possible ways to use and demonstrate the sub modules in action.
// --------

use OnDemandSuiteWP\Services\APIkeyManager;
use OnDemandSuiteWP\Services\ContentEditWidget;
use OnDemandSuiteWP\Services\DashboardWidget;

// This suite is using composer.
// Its autoloader utility to load source files and as dependancy manager too.
require 'vendor/autoload.php';

// Define this ref. to easily build plugin paths and urls
define('OnDemandSuiteWP_BASE_FILE', __FILE__);

// Make use of the APIkey manager class and rely upon it to manage the API keys used by this plugin.
new APIkeyManager;

// Dashboard widget.
new DashboardWidget;

// Content Edit widget.
new ContentEditWidget;
