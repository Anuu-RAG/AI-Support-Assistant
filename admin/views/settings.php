<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div class="wrap">

    <h1>AI Support Settings</h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">

        <?php
        settings_fields( AI_Support_Assistant_Settings::OPTION_GROUP );
        do_settings_sections( 'ai-support-settings' );
        submit_button( 'Save Changes' );
        ?>

    </form>

</div>
