<?php

namespace MultilingualFormsFluentFormsPolylang\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

class GlobalSettingsController
{
    const GLOBAL_STRINGS_SCHEMA_VERSION = '1';
    const STRING_GROUP = 'Fluent Forms Global Settings';

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        add_action('init', [$this, 'maybeRegisterGlobalStrings'], 20);
        add_action('added_option', [$this, 'markGlobalStringsDirty'], 10, 2);
        add_action('updated_option', [$this, 'markGlobalStringsDirty'], 10, 3);

        add_filter('option__fluentform_global_form_settings', [$this, 'translateGlobalFormSettings']);
        add_filter('option__fluentform_double_optin_settings', [$this, 'translateGlobalDoubleOptinSettings']);
        add_filter('option___fluentform_payment_module_settings', [$this, 'translateGlobalPaymentModuleSettings']);
        add_filter('option_fluentform_payment_settings_test', [$this, 'translateOfflinePaymentSettings']);
        add_filter('option_ff_admin_approval', [$this, 'translateGlobalAdminApprovalSettings']);
        add_filter('fluentform/double_optin_invalid_confirmation_url_message', [$this, 'translateDoubleOptinInvalidConfirmationUrlMessage']);
    }

    public function maybeRegisterGlobalStrings()
    {
        if (!is_admin() || !$this->canUsePolylangStrings() || !$this->shouldRegisterGlobalStrings()) {
            return;
        }

        $this->registerOptionStrings(
            (array) get_option('_fluentform_global_form_settings', []),
            $this->extractGlobalFormSettingStrings()
        );

        $this->registerOptionStrings(
            (array) get_option('_fluentform_double_optin_settings', []),
            $this->extractGlobalDoubleOptinStrings()
        );

        $this->registerOptionStrings(
            (array) get_option('__fluentform_payment_module_settings', []),
            $this->extractGlobalPaymentModuleStrings()
        );

        $this->registerOptionStrings(
            (array) get_option('fluentform_payment_settings_test', []),
            $this->extractOfflinePaymentStrings()
        );

        $this->registerOptionStrings(
            (array) get_option('ff_admin_approval', []),
            $this->extractGlobalAdminApprovalStrings()
        );

        update_option($this->getGlobalSyncVersionOptionName(), self::GLOBAL_STRINGS_SCHEMA_VERSION, false);
        update_option($this->getGlobalDirtyOptionName(), '0', false);
    }

    public function markGlobalStringsDirty($option)
    {
        if ($this->isTrackedOption($option)) {
            update_option($this->getGlobalDirtyOptionName(), '1', false);
        }
    }

    public function translateGlobalFormSettings($settings)
    {
        return $this->translateOptionStrings($settings, $this->extractGlobalFormSettingStrings());
    }

    public function translateGlobalDoubleOptinSettings($settings)
    {
        return $this->translateOptionStrings($settings, $this->extractGlobalDoubleOptinStrings());
    }

    public function translateOfflinePaymentSettings($settings)
    {
        return $this->translateOptionStrings($settings, $this->extractOfflinePaymentStrings());
    }

    public function translateGlobalPaymentModuleSettings($settings)
    {
        return $this->translateOptionStrings($settings, $this->extractGlobalPaymentModuleStrings());
    }

    public function translateGlobalAdminApprovalSettings($settings)
    {
        return $this->translateOptionStrings($settings, $this->extractGlobalAdminApprovalStrings());
    }

    public function translateDoubleOptinInvalidConfirmationUrlMessage($message)
    {
        if (!is_string($message) || $message === '') {
            return $message;
        }

        $this->registerString('global_double_optin_invalid_confirmation_url_message', $message);

        return $this->translateString($message);
    }

    private function translateOptionStrings($settings, array $pathMap)
    {
        if (!is_array($settings)) {
            return $settings;
        }

        foreach ($pathMap as $path => $translationKey) {
            $value = $this->arrayGet($settings, $path);

            if (!is_string($value) || $value === '') {
                continue;
            }

            $this->arraySet($settings, $path, $this->translateString($value));
        }

        return $settings;
    }

    private function registerOptionStrings(array $settings, array $pathMap)
    {
        foreach ($pathMap as $path => $translationKey) {
            $value = $this->arrayGet($settings, $path);

            if (is_string($value) && $value !== '') {
                $this->registerString($translationKey, $value);
            }
        }
    }

    private function registerString($name, $value)
    {
        if (!$this->canUsePolylangStrings()) {
            return;
        }

        pll_register_string($name, $value, self::STRING_GROUP, $this->isMultiline($value));
    }

    private function translateString($value)
    {
        if (!function_exists('pll__')) {
            return $value;
        }

        return pll__($value);
    }

    private function canUsePolylangStrings()
    {
        return function_exists('pll_register_string');
    }

    private function isMultiline($value)
    {
        return strpos($value, "\n") !== false
            || strpos($value, '<') !== false
            || strpos($value, '{') !== false
            || strpos($value, '#') !== false;
    }

    private function shouldRegisterGlobalStrings()
    {
        if (get_option($this->getGlobalSyncVersionOptionName()) !== self::GLOBAL_STRINGS_SCHEMA_VERSION) {
            return true;
        }

        return get_option($this->getGlobalDirtyOptionName(), '1') === '1';
    }

    private function isTrackedOption($option)
    {
        return in_array($option, [
            '_fluentform_global_form_settings',
            '_fluentform_double_optin_settings',
            '__fluentform_payment_module_settings',
            'fluentform_payment_settings_test',
            'ff_admin_approval',
        ], true);
    }

    private function getGlobalSyncVersionOptionName()
    {
        return '_mfffpll_global_strings_schema_version';
    }

    private function getGlobalDirtyOptionName()
    {
        return '_mfffpll_global_strings_dirty';
    }

    private function extractGlobalFormSettingStrings()
    {
        return [
            'default_messages.required' => 'global_default_message_required',
            'default_messages.email' => 'global_default_message_email',
            'default_messages.numeric' => 'global_default_message_numeric',
            'default_messages.min' => 'global_default_message_min',
            'default_messages.max' => 'global_default_message_max',
            'default_messages.digits' => 'global_default_message_digits',
            'default_messages.url' => 'global_default_message_url',
            'default_messages.allowed_image_types' => 'global_default_message_allowed_image_types',
            'default_messages.allowed_file_types' => 'global_default_message_allowed_file_types',
            'default_messages.max_file_size' => 'global_default_message_max_file_size',
            'default_messages.max_file_count' => 'global_default_message_max_file_count',
            'default_messages.valid_phone_number' => 'global_default_message_valid_phone_number',
        ];
    }

    private function extractGlobalDoubleOptinStrings()
    {
        return [
            'email_subject' => 'global_double_optin_email_subject',
            'email_body' => 'global_double_optin_email_body',
        ];
    }

    private function extractOfflinePaymentStrings()
    {
        return [
            'payment_instruction' => 'global_payment_settings_test_instruction',
        ];
    }

    private function extractGlobalPaymentModuleStrings()
    {
        return [
            'business_name' => 'global_payment_module_business_name',
            'business_address' => 'global_payment_module_business_address',
        ];
    }

    private function extractGlobalAdminApprovalStrings()
    {
        return [
            'email_subject' => 'global_admin_approval_email_subject',
            'email_body' => 'global_admin_approval_email_body',
        ];
    }

    private function arrayGet(array $array, $path)
    {
        $segments = explode('.', $path);
        $value = $array;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function arraySet(array &$array, $path, $newValue)
    {
        $segments = explode('.', $path);
        $target = &$array;

        foreach ($segments as $segment) {
            if (!is_array($target)) {
                $target = [];
            }

            if (!array_key_exists($segment, $target)) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $newValue;
    }
}
