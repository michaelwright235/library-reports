<?php

/*
Plugin Name: Library Reports
Plugin URI: https://github.com/michaelwright235/library-reports
Description: Ежедневная отчетность для библиотек
Version: 1.2
Author: Michael Wright
Author URI: https://github.com/michaelwright235
License: MIT license
Text Domain: library-reports
*/

require_once( __DIR__ . '/library-reports-frontend.php' );
require_once( __DIR__ . '/library-reports-export.php' );
require_once( __DIR__ . '/library-reports-add-event.php' );
require_once( __DIR__ . '/library-reports-settings.php' );
require_once( __DIR__ . '/library-reports-db.php' );
require_once( __DIR__ . '/library-reports-common.php' );


// Создание базы данных при активации плагина
register_activation_hook( __FILE__, array('LibraryReportsDb', 'create_reports_db') );
