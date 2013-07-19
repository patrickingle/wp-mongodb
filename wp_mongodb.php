<?php
/*
Plugin Name: WP-MongoDB
Plugin URI: https://github.com/patrickingle/wp-mongodb
Description: A wordpress database object that gives wordpress access to a MongoDB NoSQL data store
Author: Patrick Ingle
Version: 1.0
Author URI: http://github.com/patrickingle/
*/

function wp_mongodb_activate() {
    global $wpdb;
    
    if (class_exists('Mongo')) {
        if (!defined('WP_MONGODB_IP')) {
            _e("WP_MONGODB_IP is not defined! Required to define the IP of the MongoDB server");
            exit;
        }
        
        // Clone MySQL to MongolDB
        $conn = new Mongo(WP_MONGODB_IP);
        $db = $conn->{DB_NAME};
        $tables = $wpdb->get_results('SHOW TABLES');

        foreach($tables as $table) {
            $db->createCollection($table->Tables_in_wordpress);
            $results = $wpdb->get_results('SELECT * FROM '.$table->Tables_in_wordpress);
            foreach($results as $result) {
                $ref = &$result; // IMPORTANT! to assign a reference to the $result variable
                $collection = $table->Tables_in_wordpress;
                call_user_func_array(array($db->{$collection},'insert'), array($ref));
            }
        } 

        
        if (@copy(WP_PLUGIN_DIR.'/wp-mongodb/db.php',WP_CONTENT_DIR.'/db.php')) {
            _e("Could not copy db.php");
            exit;
        }
    } else {
        
    }    
}

function wp_mongodb_deactivate() {
    if (file_exists(WP_CONTENT_DIR.'/db.php')) {
        @unlink(WP_CONTENT_DIR.'/db.php');
    }
}

function wp_mongodb_test() {
    global $wpdb;
    
    $conn = new Mongo(WP_MONGODB_IP);
    $db = $conn->{DB_NAME};

    $tables = $wpdb->get_results('SHOW TABLES');
    foreach($tables as $table) {
        $results = $wpdb->get_results('SELECT * FROM '.$table->Tables_in_wordpress);
        foreach($results as $result) {
            $ref = &$result;
            $collection = $table->Tables_in_wordpress;
            call_user_func_array(array($db->{$collection},'insert'), array($ref));
        }
    }

    /*
    $table = 'wp_options';
    $operation = 'insert';
    $collection = $db.'->'.$table.'->'.$operation;

    $data = array('user' => 'myuser','pass' => md5('568fh'));
    $ref = &$data;
    call_user_func_array(array($db->{$table},$operation), array($ref));
    */
}

function wp_mongodb_get_options() {
    $conn = new Mongo(WP_MONGODB_IP);
    $db = $conn->selectDB(DB_NAME);
    $collection = new MongoCollection($db,'wp_options');
    

    $data = array('autoload' => 'yes');
    $output = $collection->find();
    
    //$ref = &$data;
    //$output = $db->wp_options->find($data);
    echo '<pre>'; print_r($output); echo '</pre>';
}

add_action( 'admin_menu', 'wp_mongodb_menu' );

function wp_mongodb_menu() {
    add_options_page( 'WP-MongoDB Options', 'WP-MongoDB', 'manage_options', 'wp-mongodb-main', 'wp_mongodb_options' );
}

function wp_mongodb_admin_tabs($tabs, $current=NULL){
    if(is_null($current)){
        if(isset($_GET['tab'])){
            $current = $_GET['tab'];
        } else {
            $current = 'wp-mongodb-overview';
        }
    }
    $content = '';
    $content .= '<h2 class="nav-tab-wrapper">';
    foreach($tabs as $location => $tabname){
        if($current == $location){
            $class = ' nav-tab-active';
        } else{
            $class = '';    
        }
        $content .= '<a class="nav-tab'.$class.'" href="?page=wp-mongodb-main&tab='.$location.'">'.$tabname.'</a>';
    }
    $content .= '</h2>';
        return $content;
}

function wp_mongodb_options() {
    echo '<div class="wrap">';
    screen_icon();
    echo '<h1>WP-MongoDB</h1>';

    $wp_mongodb_plugin_tabs = array(
        'wp-mongodb-overview' => 'Overview',
        'wp-mongodb-phpmoadmin' => 'PhpMoAdmin'
    );

    echo wp_mongodb_admin_tabs($wp_mongodb_plugin_tabs);
    
    if (isset($_GET['tab'])) {
        switch($_GET['tab']) {
            case 'wp-mongodb-overview':
                wp_mongodb_overview();
                break;
            case 'wp-mongodb-phpmoadmin':
                wp_mongodb_phpmoadmin();
                break;
        }
    } else {
        wp_mongodb_overview();
    }

}

function wp_mongodb_overview() {
    echo '<h2>Overview</h2>';
}

function wp_mongodb_phpmoadmin() {
    require(dirname(__FILE__).'/moadmin.php');

    $db = (isset($_GET['db']) ? $_GET['db'] : (isset($_POST['db']) ? $_POST['db'] : 'admin')); //admin is in every Mongo DB
    $dbUrl = urlencode($db);

    //if (!isset($_REQUEST['db'])) {
    //    $_REQUEST['db'] = moadminModel::$dbName;
    //} else if (strpos($_REQUEST['db'], '.') !== false) {
    //    $_REQUEST['db'] = $_REQUEST['newdb'];
    //}
    try {
        moadminComponent::$model = new moadminModel($db);
    } catch(Exception $e) {
        echo '<pre>';
        print_r($e);
       echo '</pre>';
        exit(0);
    }
    
    $mo = new moadminComponent;
    
    echo '<h2>Database: '.$db.'</h2>';
    
    //echo '<pre>';  print_r($mo); echo '</pre>';
    //echo $db.'<br>';

    echo '<form method="post">';
    echo '<select name="db">';
    foreach($mo->mongo['dbs'] as $key => $value) {
        echo '<option value="'.$key.'">'.$value.'</option>';
    }
    echo '</select>';
    echo '<input type="submit" name="submit" value="Change Database">';
    echo '</form>';
    
    $i=1;
    echo '<table>';
    foreach($mo->mongo['listCollections'] as $key => $value) {
        echo '<tr>';
        echo '<td>'.$i++.'.</td><td>[x]</td><td><a href="?page=wp-mongodb-main&tab=wp-mongodb-phpmoadmin&db='.$db.'&action=listRows&collection='.$key.'">'.$key.'</a></td><td>('.$value.')</td>';
        echo '</tr>';
    }
    echo '</table>';

    if (isset($mo->mongo['listRows'])) {
        if (isset($mo->mongo['listIndexes'])) {
            
        }
    }    
}

register_activation_hook( __FILE__, 'wp_mongodb_activate' );
register_deactivation_hook( __FILE__, 'wp_mongodb_deactivate' );
?>
