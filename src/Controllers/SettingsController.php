<?php

namespace MultilingualFormsFluentFormsPolylang\Controllers;

use MultilingualFormsFluentFormsPolylang\Services\FormTranslationService;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsController
{
    private $translations;

    public function __construct($app)
    {
        $this->translations = new FormTranslationService();

        new FormSettingsController($app, $this->translations);
        new RuntimeTranslationController($app, $this->translations);
    }
}
