<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_DB {
    private static $table = 'pcfb_forms';
    private static $submissions_table = 'pcfb_submissions';

    public static function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول فرم‌ها
        $forms_table = $wpdb->prefix . self::$table;
        $forms_sql = "CREATE TABLE $forms_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_name varchar(200) NOT NULL,
            form_json longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            status tinyint(1) DEFAULT 1 NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        // جدول ارسال‌ها
        $submissions_table = $wpdb->prefix . self::$submissions_table;
        $submissions_sql = "CREATE TABLE $submissions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            form_data longtext NOT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $forms_sql );
        dbDelta( $submissions_sql );
    }

    public static function insert_form($name, $json) {
        global $wpdb;
        
        $name = sanitize_text_field( $name );
        if ( empty( $name ) ) {
            return new WP_Error( 'invalid_name', 'نام فرم نمی‌تواند خالی باشد.' );
        }
        
        if ( ! self::is_valid_json( $json ) ) {
            return new WP_Error( 'invalid_json', 'داده‌های فرم معتبر نیستند.' );
        }
        
        $table_name = $wpdb->prefix . self::$table;
        
        $result = $wpdb->insert(
            $table_name,
            [
                'form_name' => $name,
                'form_json' => $json
            ],
            [ '%s', '%s' ]
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        
        return $wpdb->insert_id;
    }

    public static function update_form($id, $name, $json) {
        global $wpdb;
        
        $id = absint( $id );
        $name = sanitize_text_field( $name );
        
        if ( empty( $name ) ) {
            return new WP_Error( 'invalid_name', 'نام فرم نمی‌تواند خالی باشد.' );
        }
        
        if ( ! self::is_valid_json( $json ) ) {
            return new WP_Error( 'invalid_json', 'داده‌های فرم معتبر نیستند.' );
        }
        
        $table_name = $wpdb->prefix . self::$table;
        
        $result = $wpdb->update(
            $table_name,
            [
                'form_name' => $name,
                'form_json' => $json
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        
        return $result;
    }

    public static function delete_form($id) {
        global $wpdb;
        
        $id = absint( $id );
        $table_name = $wpdb->prefix . self::$table;
        
        // حذف نرم (soft delete)
        $result = $wpdb->update(
            $table_name,
            [ 'status' => 0 ],
            [ 'id' => $id ],
            [ '%d' ],
            [ '%d' ]
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        
        return $result;
    }

    public static function get_forms($active_only = true) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        
        $where = $active_only ? " WHERE status = 1" : "";
        $query = "SELECT * FROM $table_name{$where} ORDER BY id DESC";
        
        return $wpdb->get_results( $query );
    }

    public static function get_form($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table;
        
        return $wpdb->get_row( 
            $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) 
        );
    }

    public static function insert_submission($form_id, $form_data) {
        global $wpdb;
        
        $form_id = absint( $form_id );
        $table_name = $wpdb->prefix . self::$submissions_table;
        
        $result = $wpdb->insert(
            $table_name,
            [
                'form_id' => $form_id,
                'form_data' => wp_json_encode( $form_data ),
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : ''
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        
        return $wpdb->insert_id;
    }

    public static function get_submissions($form_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$submissions_table;
        
        $where = '';
        $prepare_args = [];
        
        if ( $form_id ) {
            $where = " WHERE form_id = %d";
            $prepare_args[] = $form_id;
        }
        
        $query = "SELECT * FROM $table_name{$where} ORDER BY created_at DESC";
        
        if ( ! empty( $prepare_args ) ) {
            $query = $wpdb->prepare( $query, $prepare_args );
        }
        
        return $wpdb->get_results( $query );
    }

    private static function is_valid_json($data) {
        if ( is_string( $data ) ) {
            json_decode( $data );
            return ( json_last_error() === JSON_ERROR_NONE );
        }
        
        return true;
    }

    private static function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return sanitize_text_field( $ip );
    }
}