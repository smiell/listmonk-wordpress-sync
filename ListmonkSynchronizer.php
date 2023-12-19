<?php
/*
* Plugin Name: Listmonk Synchronizer 
* Description: Wysyłaj nowych klientów do bazy subskrybentów newslettera listmonk.
* Version: 1.0.0
* Author: ZHN GRUPA
* Author URI: https://zhngrupa.net
*/

//Load Plugin Classes
require_once plugin_dir_path( __FILE__ ) . 'class/Listmonk_Synchronizer_Settings.php';
require_once plugin_dir_path( __FILE__ ) . 'class/Listmonk_Synchronizer_Api_Caller.php';

//Run Classes
new Listmonk_Synchronizer_Settings();
new Listmonk_Synchronizer();

