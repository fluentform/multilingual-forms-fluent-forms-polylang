<?php

namespace MultilingualFormsFluentFormsPolylang\Controllers;

use FluentForm\Framework\Helpers\ArrayHelper;
use MultilingualFormsFluentFormsPolylang\Services\FormTranslationService;

if (!defined('ABSPATH')) {
    exit;
}

class FormSettingsController
{
    private $app;
    private $translations;

    public function __construct($app, FormTranslationService $translations)
    {
        $this->app = $app;
        $this->translations = $translations;
        $this->init();
    }

    public function init()
    {
        if (!is_admin()) {
            return;
        }

        $this->app->addAdminAjaxAction('fluentform_get_polylang_settings', [$this, 'getPolylangSettings']);
        $this->app->addAdminAjaxAction('fluentform_store_polylang_settings', [$this, 'storePolylangSettings']);
        $this->app->addAdminAjaxAction('fluentform_delete_polylang_settings', [$this, 'removePolylangSettings']);

        add_action('fluentform/form_settings_menu', [$this, 'pushSettings'], 10, 2);
        add_filter('fluentform/form_fields_update', [$this, 'handleFormFieldUpdate'], 10, 2);
        add_action('fluentform/after_form_delete', [$this, 'removePolylangStrings'], 10, 1);
    }

    public function getPolylangSettings()
    {
        if (!$this->canManageFluentForms()) {
            wp_send_json_error(__('You do not have permission to manage this form.', 'multilingual-forms-fluent-forms-polylang'), 403);
        }

        $request = $this->app->request->get();
        $formId = absint(ArrayHelper::get($request, 'form_id'));

        wp_send_json_success($this->translations->isFormEnabled($formId));
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

        $this->translations->setFormEnabled($formId, $isEnabled);

        if (!$isEnabled) {
            wp_send_json_success(__('Translation is disabled for this form.', 'multilingual-forms-fluent-forms-polylang'));
        }

        $this->translations->registerFormStrings($formId);

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

        $this->removePolylangStrings($formId);

        wp_send_json_success(__('Translations are disabled for this form.', 'multilingual-forms-fluent-forms-polylang'));
    }

    public function pushSettings($settingsMenus, $formId)
    {
        if (!$this->translations->canUsePolylangStrings()) {
            return $settingsMenus;
        }

        $settingsMenus['ff_polylang'] = [
            'title' => __('Polylang Translations', 'multilingual-forms-fluent-forms-polylang'),
            'slug'  => 'ff_polylang',
            'hash'  => 'ff_polylang',
            'route' => '/ff-polylang',
        ];

        return $settingsMenus;
    }

    public function handleFormFieldUpdate($formFields, $formId)
    {
        $this->translations->clearFormCache($formId);

        if ($this->translations->isFormEnabled($formId)) {
            $this->translations->registerFormStrings($formId, $formFields);
        }

        return $formFields;
    }

    public function removePolylangStrings($formId)
    {
        $this->translations->removeFormStrings($formId);
    }

    private function canManageFluentForms()
    {
        return current_user_can('manage_options') || current_user_can('fluentform_full_access');
    }
}
