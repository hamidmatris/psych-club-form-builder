<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PCFB_Form {
    public function __construct() {
        add_shortcode( 'pcfb_form', [ $this, 'render_form' ] );
    }

    public function render_form() {
        ob_start();
        ?>
        <form class="pcfb-public-form">
            <label>نام:</label>
            <input type="text" name="name" />
            <label>ایمیل:</label>
            <input type="email" name="email" />
            <button type="submit">ارسال</button>
        </form>
        <?php
        return ob_get_clean();
    }
}
