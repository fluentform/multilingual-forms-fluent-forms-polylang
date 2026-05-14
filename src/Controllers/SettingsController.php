<?php

namespace MultilingualFormsFluentFormsPolylang\Controllers;

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsController
{
    const FORM_META_KEY = 'ff_polylang';
    const STRING_GROUP_PREFIX = 'Fluent Forms Form';

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    public function init()
    {
        $this->handleAdmin();
    }

    public function handleAdmin()
    {
        if (!is_admin()) {
            return;
        }

        $this->app->addAdminAjaxAction('fluentform_get_polylang_settings', [$this, 'getPolylangSettings']);
        $this->app->addAdminAjaxAction('fluentform_store_polylang_settings', [$this, 'storePolylangSettings']);
        $this->app->addAdminAjaxAction('fluentform_delete_polylang_settings', [$this, 'removePolylangSettings']);

        add_action('fluentform/form_settings_menu', [$this, 'pushSettings'], 10, 2);
    }

    public function getPolylangSettings()
    {
        if (!$this->canManageFluentForms()) {
            wp_send_json_error(__('You do not have permission to manage this form.', 'multilingual-forms-fluent-forms-polylang'), 403);
        }

        $request = $this->app->request->get();
        $formId = absint(ArrayHelper::get($request, 'form_id'));

        wp_send_json_success($this->isPolylangEnabledOnForm($formId));
    }

    public function storePolylangSettings()
    {
        if (!$this->canManageFluentForms()) {
            wp_send_json_error(__('You do not have permission to manage this form.', 'multilingual-forms-fluent-forms-polylang'), 403);
        }

        $request = $this->app->request->get();
        $formId = absint(ArrayHelper::get($request, 'form_id'));
        $isEnabled = ArrayHelper::get($request, 'is_ff_polylang_enabled', false) === 'true';

        if (!$formId) {
            wp_send_json_error(__('Invalid form ID.', 'multilingual-forms-fluent-forms-polylang'), 400);
        }

        Helper::setFormMeta($formId, self::FORM_META_KEY, $isEnabled);

        if (!$isEnabled) {
            wp_send_json_success(__('Translation is disabled for this form.', 'multilingual-forms-fluent-forms-polylang'));
        }

        wp_send_json_success(__('Translation is enabled for this form.', 'multilingual-forms-fluent-forms-polylang'));
    }

    public function removePolylangSettings()
    {
        if (!$this->canManageFluentForms()) {
            wp_send_json_error(__('You do not have permission to manage this form.', 'multilingual-forms-fluent-forms-polylang'), 403);
        }

        $request = $this->app->request->get();
        $formId = absint(ArrayHelper::get($request, 'form_id'));

        if (!$formId) {
            wp_send_json_error(__('Invalid form ID.', 'multilingual-forms-fluent-forms-polylang'), 400);
        }

        Helper::setFormMeta($formId, self::FORM_META_KEY, false);

        wp_send_json_success(__('Translations are disabled for this form.', 'multilingual-forms-fluent-forms-polylang'));
    }

    public function pushSettings($settingsMenus, $formId)
    {
        if (!$this->canUsePolylangStrings()) {
            return $settingsMenus;
        }

        $settingsMenus['ff_polylang'] = [
            'title' => __('Polylang Translations', 'multilingual-forms-fluent-forms-polylang'),
            'slug'  => 'ff_polylang',
            'hash'  => 'ff_polylang',
        ];

        return $settingsMenus;
    }

    private function isPolylangEnabledOnForm($formId)
    {
        if (!$formId) {
            return false;
        }

        return Helper::getFormMeta($formId, self::FORM_META_KEY) === '1'
            || Helper::getFormMeta($formId, self::FORM_META_KEY) === true;
    }

    private function canManageFluentForms()
    {
        return current_user_can('manage_options') || current_user_can('fluentform_full_access');
    }

    private function canUsePolylangStrings()
    {
        return function_exists('pll_register_string') && function_exists('pll__');
    }
}
