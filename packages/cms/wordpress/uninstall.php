<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('shield_captcha_forms');
