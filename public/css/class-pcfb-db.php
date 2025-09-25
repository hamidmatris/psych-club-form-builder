<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_DB {
    private static $table = 'pcfb_forms';

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_name varchar(200) NOT NULL,
            form_json longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function insert_form($name, $json) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        $wpdb->insert($table_name, [
            'form_name' => $name,
            'form_json' => $json
        ]);
        return $wpdb->insert_id;
    }

    public static function get_forms() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
    }

    public static function get_form($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
}
