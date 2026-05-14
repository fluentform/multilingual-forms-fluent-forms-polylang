<?php

namespace MultilingualFormsFluentFormsPolylang\Controllers;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Models\Submission;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsController
{
    const FORM_META_KEY = 'ff_polylang';
    const STRING_GROUP_PREFIX = 'Fluent Forms Form';
    const REQUEST_LANGUAGE_KEY = 'lang';

    protected $app;

    private static $currentDoubleOptinContext = null;
    private static $entryConfirmationLanguageContext = null;
    private static $translatedFormFieldsCache = [];
    private static $formModelCache = [];

    private $runtimeStringKeys = [
        'label',
        'admin_label',
        'admin_field_label',
        'placeholder',
        'help_message',
        'btn_text',
        'text',
        'title',
        'description',
        'note',
        'list_name',
        'tag_name',
        'html_codes',
        'tnc_html',
        'prefix_label',
        'suffix_label',
        'start_text',
        'end_text',
        'button_text',
        'modal_title',
        'message',
        'messageToShow',
        'customPageHtml',
        'successPageTitle',
        'redirectMessage',
        'subject',
        'email_subject',
        'email_body',
        'body',
        'header',
        'footer',
        'confirmation_message',
        'success_message',
        'error_message',
        'approval_message',
        'approved_message',
        'rejected_message',
        'notification_subject',
        'notification_body',
        'payment_instruction',
        'receipt_template',
        'limitReachedMsg',
        'pendingMsg',
        'expiredMsg',
        'requireLoginMsg',
        'drag_drop_text',
        'upload_text',
        'max_file_error',
        'file_type_error',
        'file_size_error',
        'upload_failed_text',
        'upload_error_text',
        'stock_out_message',
        'item_label',
        'price_label',
        'qty_label',
        'line_total_label',
        'sub_total_label',
        'discount_label',
        'total_label',
        'signup_fee_label',
        'trial_label',
        'processing_text',
        'confirming_text',
        'request_failed',
        'payment_failed',
        'no_method_found',
        'copy_button',
        'email_button',
        'email_placeholder',
        'copy_success',
        'please_wait',
        'location_not_determined',
        'address_fetch_failed',
        'geolocation_failed',
        'geolocation_not_supported',
    ];

    private $nonTranslatableKeys = [
        'id',
        'form_id',
        'element',
        'type',
        'name',
        'value',
        'data_name',
        'uniqElKey',
        'container_class',
        'class',
        'hook_name',
        'conditional_operator',
        'field',
        'operator',
        'status',
        'email_field',
        'email_body_type',
    ];

    public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    public function init()
    {
        add_action('init', [$this, 'setupLanguageForAjax'], 5);

        add_filter('fluentform/ajax_url', [$this, 'setAjaxLanguage'], 10, 1);
        add_filter('fluentform/rendering_form', [$this, 'translateRenderingForm'], 10, 1);
        add_filter('fluentform/recaptcha_lang', [$this, 'setCaptchaLanguage'], 10, 1);
        add_filter('fluentform/hcaptcha_lang', [$this, 'setCaptchaLanguage'], 10, 1);
        add_filter('fluentform/turnstile_lang', [$this, 'setCaptchaLanguage'], 10, 1);

        add_filter('fluentform/form_submission_confirmation', [$this, 'translateConfirmationMessage'], 10, 3);
        add_filter('fluentform/entry_limit_reached_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/schedule_form_pending_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/schedule_form_expired_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/form_requires_login_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/deny_empty_submission_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/ip_restriction_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/country_restriction_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/keyword_restriction_message', [$this, 'translateFormMessage'], 10, 2);

        add_filter('fluentform/integration_feed_before_parse', [$this, 'translateFeedValuesBeforeParse'], 10, 4);
        add_filter('fluentform/email_subject', [$this, 'translateEmailSubject'], 10, 4);
        add_filter('fluentform/email_body', [$this, 'translateEmailBody'], 10, 4);
        add_filter('fluentform/submission_message_parse', [$this, 'translateSubmissionMessageParse'], 10, 4);

        add_action('fluentform/before_form_actions_processing', [$this, 'prepareDoubleOptinContext'], 9, 3);
        add_action('fluentform/before_form_actions_processing', [$this, 'clearDoubleOptinContext'], 11, 3);
        add_action('fluentform/entry_confirmation', [$this, 'setSubmissionLanguageForEntryConfirmation'], 0, 1);
        add_action('shutdown', [$this, 'restoreEntryConfirmationLanguage'], 0);
        add_filter('wp_mail', [$this, 'localizeDoubleOptinConfirmationWpMail'], 10, 1);

        add_filter('fluentform/double_optin_messages', [$this, 'translateFormMessagesArray'], 10, 3);
        add_filter('fluentform/admin_approval_messages', [$this, 'translateFormMessagesArray'], 10, 3);
        add_filter('fluentform/admin_approval_confirmation_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/honeypot_spam_message', [$this, 'translateFormIdMessage'], 10, 2);
        add_filter('fluentform/akismet_spam_message', [$this, 'translateFormIdMessage'], 10, 2);
        add_filter('fluentform/too_many_requests', [$this, 'translateFormIdMessage'], 10, 2);
        add_filter('fluentform/recaptcha_failed_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/hcaptcha_failed_message', [$this, 'translateFormMessage'], 10, 2);
        add_filter('fluentform/turnstile_failed_message', [$this, 'translateFormMessage'], 10, 2);

        add_filter('fluentform/validations', [$this, 'translateValidationMessages'], 10, 3);
        add_filter('fluentform/validation_error_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/token_based_validation_error_message', [$this, 'translateFormIdMessage'], 10, 2);
        add_filter('fluentform/file_upload_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/calculation_field_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/inventory_field_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/form_submission_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/payment_handler_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/form_save_progress_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/address_autocomplete_messages', [$this, 'translateFormMessagesArray'], 10, 2);
        add_filter('fluentform/payment_gateway_messages', [$this, 'translateFormMessagesArray'], 10, 2);

        add_filter('fluentform/payment_confirmation_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/payment_pending_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/payment_error_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/quiz_result_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/quiz_result_title', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/subscription_confirmation_message', [$this, 'translateFormMessage'], 10, 3);
        add_filter('fluentform/recurring_payment_message', [$this, 'translateFormMessage'], 10, 3);

        add_filter('fluentform_pdf/check_wpml_active', [$this, 'isPolylangActive'], 10, 1);
        add_filter('fluentform_pdf/get_current_language', [$this, 'getCurrentPolylangLanguage'], 10, 1);
        add_filter('fluentform_pdf/add_language_to_url', [$this, 'addLanguageToUrl'], 10, 1);
        add_filter('fluentform_pdf/handle_language_for_pdf', [$this, 'handleLanguageForPdf'], 10, 1);

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

        $this->registerFormStrings($formId);

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
        if (!$this->canUsePolylangStrings()) {
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
        unset(self::$translatedFormFieldsCache[$formId]);

        if ($this->isPolylangEnabledOnForm($formId)) {
            $this->registerFormStrings($formId, $formFields);
        }

        return $formFields;
    }

    public function removePolylangStrings($formId)
    {
        $formId = absint($formId);

        if (!$formId) {
            return;
        }

        unset(self::$translatedFormFieldsCache[$formId], self::$formModelCache[$formId]);
        Helper::setFormMeta($formId, self::FORM_META_KEY, false);
    }

    public function translateRenderingForm($form)
    {
        if (!is_object($form) || empty($form->id) || !$this->isPolylangEnabledOnForm($form->id)) {
            return $form;
        }

        $language = $this->getCurrentPolylangLanguage();
        $cacheKey = $language ?: 'current';

        if (isset(self::$translatedFormFieldsCache[$form->id][$cacheKey])) {
            $form->fields = self::$translatedFormFieldsCache[$form->id][$cacheKey];
            return $form;
        }

        if (isset($form->fields)) {
            $form->fields = $this->translateKnownStringsRecursive($form->fields, '', $language);
            self::$translatedFormFieldsCache[$form->id][$cacheKey] = $form->fields;
        }

        return $form;
    }

    public function setCaptchaLanguage($language)
    {
        $currentLanguage = $this->getCurrentPolylangLanguage();

        if (!$currentLanguage) {
            return $language;
        }

        return $currentLanguage;
    }

    public function translateConfirmationMessage($confirmation, $formData, $form)
    {
        if (!$this->canTranslateForm($form)) {
            return $confirmation;
        }

        return $this->translateKnownStringsRecursive($confirmation);
    }

    public function translateFormMessage($message, $formOrContext = null, $form = null)
    {
        $form = $this->resolveFormInstance($form ?: $formOrContext);

        if (!$form || !$this->isPolylangEnabledOnForm($form->id)) {
            return $message;
        }

        return $this->translateStringValue($message);
    }

    public function translateFormIdMessage($message, $formId)
    {
        $formId = absint($formId);

        if (!$formId || !$this->isPolylangEnabledOnForm($formId)) {
            return $message;
        }

        return $this->translateStringValue($message);
    }

    public function translateFormMessagesArray($messages, $formDataOrForm, $form = null)
    {
        $form = $this->resolveFormInstance($form ?: $formDataOrForm);

        if (!$form || !$this->isPolylangEnabledOnForm($form->id)) {
            return $messages;
        }

        return $this->translateKnownStringsRecursive($messages);
    }

    public function translateValidationMessages($validations, $form, $formData)
    {
        if (!$this->canTranslateForm($form) || !is_array($validations)) {
            return $validations;
        }

        if (isset($validations[1]) && is_array($validations[1])) {
            $validations[1] = $this->translateKnownStringsRecursive($validations[1]);
        }

        return $validations;
    }

    public function translateFeedValuesBeforeParse($feed, $insertId, $formData, $form)
    {
        $form = $this->resolveFormInstance($form);

        if (!$form || !$this->isPolylangEnabledOnForm($form->id) || !is_array($feed)) {
            return $feed;
        }

        if (isset($feed['settings'])) {
            $feed['settings'] = $this->translateKnownStringsRecursive($feed['settings']);
        }

        return $feed;
    }

    public function translateEmailSubject($emailSubject, $formData, $form, $notification)
    {
        if (!$this->canTranslateForm($form)) {
            return $emailSubject;
        }

        return $this->translateStringValue($emailSubject);
    }

    public function translateEmailBody($emailBody, $formData, $form, $notification)
    {
        if (!$this->canTranslateForm($form)) {
            return $emailBody;
        }

        return $this->translateStringValue($emailBody);
    }

    public function translateSubmissionMessageParse($message, $insertId, $formData, $form)
    {
        if (!$this->canTranslateForm($form)) {
            return $message;
        }

        if ($this->isCurrentDoubleOptinForm($form)) {
            $translated = $this->translateDoubleOptinRuntimeMessage($message, $form);
            if ($translated !== null) {
                return $translated;
            }
        }

        return $this->translateStringValue($message);
    }

    public function prepareDoubleOptinContext($insertId, $formData, $form)
    {
        self::$currentDoubleOptinContext = null;

        if (!$this->canTranslateForm($form)) {
            return;
        }

        if (!class_exists('\\FluentFormPro\\classes\\DoubleOptin')) {
            return;
        }

        $doubleOptin = new \FluentFormPro\classes\DoubleOptin();
        $settings = $doubleOptin->getDoubleOptinSettings($form->id, 'public');

        if (!$settings || ArrayHelper::get($settings, 'status') !== 'yes') {
            return;
        }

        self::$currentDoubleOptinContext = [
            'insert_id' => (int) $insertId,
            'form'      => $form,
            'settings'  => (array) $settings,
            'form_data' => (array) $formData,
        ];
    }

    public function clearDoubleOptinContext($insertId, $formData, $form)
    {
        self::$currentDoubleOptinContext = null;
    }

    public function localizeDoubleOptinConfirmationWpMail($atts)
    {
        $context = self::$currentDoubleOptinContext;

        if (!$context || !is_array($atts)) {
            return $atts;
        }

        $language = $this->extractSubmissionLanguage((array) ArrayHelper::get($context, 'form_data', []));
        $form = ArrayHelper::get($context, 'form');
        $insertId = (int) ArrayHelper::get($context, 'insert_id');

        if (!$language || !is_object($form) || empty($form->id) || !$insertId) {
            return $atts;
        }

        foreach (['subject', 'message'] as $mailKey) {
            if (!empty($atts[$mailKey]) && is_string($atts[$mailKey])) {
                $atts[$mailKey] = $this->translateStringValue($atts[$mailKey], $language);
            }
        }

        $hash = (string) $this->getSubmissionMetaValue($insertId, '_entry_uid_hash');
        if (!$hash || empty($atts['message']) || !is_string($atts['message'])) {
            return $atts;
        }

        $defaultUrl = add_query_arg([
            'ff_landing'         => $form->id,
            'entry_confirmation' => $hash,
        ], home_url());

        $localizedUrl = $this->addLanguageToUrl($defaultUrl, $language);
        if ($localizedUrl && $localizedUrl !== $defaultUrl) {
            $atts['message'] = str_replace([
                $defaultUrl,
                esc_url($defaultUrl),
                htmlspecialchars($defaultUrl, ENT_QUOTES, 'UTF-8'),
            ], [
                $localizedUrl,
                esc_url($localizedUrl),
                htmlspecialchars($localizedUrl, ENT_QUOTES, 'UTF-8'),
            ], $atts['message']);
        }

        return $atts;
    }

    public function setAjaxLanguage($url)
    {
        $language = $this->getCurrentPolylangLanguage();

        if (!$language) {
            return $url;
        }

        return add_query_arg([self::REQUEST_LANGUAGE_KEY => $language], $url);
    }

    public function setupLanguageForAjax()
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX || !$this->isPolylangActive()) {
            return;
        }

        $request = $this->app->request->all();
        $action = isset($request['action']) ? sanitize_text_field((string) $request['action']) : '';

        if (!in_array($action, [
            'fluentform_submit',
            'fluentform_pdf_download',
            'fluentform_pdf_download_public',
            'fluentform_pdf_admin_ajax_actions',
        ], true)) {
            return;
        }

        $language = $this->extractLanguageFromRequest($request);

        if ($language) {
            $this->switchLanguage($language);
        }
    }

    public function setSubmissionLanguageForEntryConfirmation($data)
    {
        self::$entryConfirmationLanguageContext = null;

        $formId = absint(ArrayHelper::get((array) $data, 'ff_landing'));
        $hash = sanitize_text_field((string) ArrayHelper::get((array) $data, 'entry_confirmation'));

        if (!$formId || !$hash || !$this->isPolylangEnabledOnForm($formId) || !function_exists('wpFluent')) {
            return;
        }

        $meta = wpFluent()->table('fluentform_submission_meta')
            ->where('form_id', $formId)
            ->where('meta_key', '_entry_uid_hash')
            ->where('value', $hash)
            ->first();

        if (!$meta || empty($meta->response_id)) {
            return;
        }

        $submission = Submission::find((int) $meta->response_id);
        if (!$submission) {
            return;
        }

        $language = $this->extractSubmissionLanguage((array) json_decode($submission->response, true));
        if (!$language) {
            return;
        }

        self::$entryConfirmationLanguageContext = $this->getCurrentPolylangLanguage();
        $this->switchLanguage($language);
    }

    public function restoreEntryConfirmationLanguage()
    {
        if (self::$entryConfirmationLanguageContext) {
            $this->switchLanguage(self::$entryConfirmationLanguageContext);
            self::$entryConfirmationLanguageContext = null;
        }
    }

    public function isPolylangActive()
    {
        return function_exists('pll_current_language') && function_exists('pll_languages_list');
    }

    public function getCurrentPolylangLanguage()
    {
        if (!function_exists('pll_current_language')) {
            return null;
        }

        $language = pll_current_language('slug');

        return is_string($language) && $language !== '' ? $language : null;
    }

    public function addLanguageToUrl($url, $language = null)
    {
        $language = $language ?: $this->getCurrentPolylangLanguage();

        if (!$language) {
            return $url;
        }

        return add_query_arg([self::REQUEST_LANGUAGE_KEY => $language], $url);
    }

    public function handleLanguageForPdf($requestData)
    {
        $language = $this->extractLanguageFromRequest((array) $requestData);

        if ($language) {
            $this->switchLanguage($language);
        }
    }

    private function registerFormStrings($formId, $rawFormFields = null)
    {
        if (!$this->canUsePolylangStrings()) {
            return;
        }

        $form = $this->getCachedFormModel($formId);
        if (!$form) {
            return;
        }

        $group = $this->getFormStringGroup($form);
        $strings = [];

        if (is_array($rawFormFields)) {
            $decodedFields = $rawFormFields;
        } elseif ($rawFormFields) {
            $decodedFields = json_decode((string) $rawFormFields, true);
        } else {
            $decodedFields = json_decode((string) $form->form_fields, true);
        }

        if (is_array($decodedFields)) {
            $this->extractTranslatableStrings($decodedFields, $strings, 'fields');
        }

        $formSettings = $this->getFormSettings($formId);
        if ($formSettings) {
            $this->extractTranslatableStrings($formSettings, $strings, 'settings');
        }

        foreach ($strings as $name => $value) {
            $this->registerString($name, $value, $group);
        }
    }

    private function getFormSettings($formId)
    {
        if (!class_exists(FormMeta::class)) {
            return [];
        }

        return FormMeta::where('form_id', $formId)
            ->whereNot('meta_key', [
                'step_data_persistency_status',
                'form_save_state_status',
                '_primary_email_field',
                'ffs_default',
                '_ff_form_styles',
                self::FORM_META_KEY,
                '_total_views',
                'revision',
                'template_name',
            ])
            ->get()
            ->reduce(function ($result, $item) {
                $value = $item['value'];
                $decodedValue = json_decode($value, true);
                $metaValue = (json_last_error() === JSON_ERROR_NONE) ? $decodedValue : $value;
                $result[$item['meta_key']][$item['id']] = $metaValue;

                return $result;
            }, []);
    }

    private function extractTranslatableStrings($value, array &$strings, $path)
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $key = (string) $key;
                $nextPath = $path === '' ? $key : $path . '.' . $key;

                if (in_array($key, $this->nonTranslatableKeys, true)) {
                    continue;
                }

                $this->extractTranslatableStrings($item, $strings, $nextPath);
            }

            return;
        }

        if (!is_string($value) || $value === '' || !$this->looksTranslatablePath($path)) {
            return;
        }

        $name = sanitize_key(str_replace(['.', '->', '[', ']'], '_', $path));
        if ($name === '') {
            $name = 'string_' . md5($path . $value);
        }

        $strings[$name] = $value;
    }

    private function translateKnownStringsRecursive($value, $path = '', $language = null)
    {
        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $nextPath = $path === '' ? $key : $path . '.' . $key;

                if (in_array((string) $key, $this->nonTranslatableKeys, true)) {
                    continue;
                }

                $value->{$key} = $this->translateKnownStringsRecursive($item, $nextPath, $language);
            }

            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $nextPath = $path === '' ? $key : $path . '.' . $key;

                if (in_array((string) $key, $this->nonTranslatableKeys, true)) {
                    continue;
                }

                $value[$key] = $this->translateKnownStringsRecursive($item, $nextPath, $language);
            }

            return $value;
        }

        if (!is_string($value) || $value === '' || !$this->looksTranslatablePath($path)) {
            return $value;
        }

        return $this->translateStringValue($value, $language);
    }

    private function looksTranslatablePath($path)
    {
        $segments = explode('.', (string) $path);
        $last = end($segments);

        if (in_array($last, $this->runtimeStringKeys, true)) {
            return true;
        }

        foreach ($segments as $segment) {
            if (in_array($segment, ['advanced_options', 'pricing_options', 'subscription_options', 'grid_columns', 'grid_rows', 'step_titles', 'messages'], true)) {
                return true;
            }
        }

        return strpos((string) $path, 'message') !== false
            || strpos((string) $path, 'label') !== false
            || strpos((string) $path, 'title') !== false
            || strpos((string) $path, 'text') !== false
            || strpos((string) $path, 'body') !== false
            || strpos((string) $path, 'subject') !== false;
    }

    private function registerString($name, $value, $group)
    {
        if (!$this->canUsePolylangStrings() || !is_string($value) || $value === '') {
            return;
        }

        pll_register_string($name, $value, $group, $this->isMultiline($value));
    }

    private function translateStringValue($value, $language = null)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        if ($language && function_exists('pll_translate_string')) {
            return pll_translate_string($value, $language);
        }

        if (function_exists('pll__')) {
            return pll__($value);
        }

        return $value;
    }

    private function isMultiline($value)
    {
        return strpos($value, "\n") !== false
            || strpos($value, '<') !== false
            || strpos($value, '{') !== false
            || strpos($value, '#') !== false;
    }

    private function isCurrentDoubleOptinForm($form)
    {
        if (!self::$currentDoubleOptinContext || !is_object($form) || !isset($form->id)) {
            return false;
        }

        $contextForm = ArrayHelper::get(self::$currentDoubleOptinContext, 'form');

        return is_object($contextForm)
            && isset($contextForm->id)
            && (int) $contextForm->id === (int) $form->id;
    }

    private function translateDoubleOptinRuntimeMessage($message, $form)
    {
        $context = self::$currentDoubleOptinContext;
        if (!$context) {
            return null;
        }

        $settings = (array) ArrayHelper::get($context, 'settings', []);

        foreach (['confirmation_message', 'email_subject', 'email_body'] as $settingKey) {
            if ($message === ArrayHelper::get($settings, $settingKey)) {
                return $this->translateStringValue($message, $this->extractSubmissionLanguage((array) ArrayHelper::get($context, 'form_data', [])));
            }
        }

        return null;
    }

    private function extractLanguageFromRequest(array $request)
    {
        if (!empty($request[self::REQUEST_LANGUAGE_KEY])) {
            return $this->sanitizeValidLanguage($request[self::REQUEST_LANGUAGE_KEY]);
        }

        if (!empty($request['data'])) {
            parse_str((string) $request['data'], $parsedData);

            if (!empty($parsedData[self::REQUEST_LANGUAGE_KEY])) {
                return $this->sanitizeValidLanguage($parsedData[self::REQUEST_LANGUAGE_KEY]);
            }

            if (!empty($parsedData['_wp_http_referer'])) {
                return $this->extractValidLanguageFromUrl($parsedData['_wp_http_referer']);
            }
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            return $this->extractValidLanguageFromUrl(esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])));
        }

        return null;
    }

    private function extractSubmissionLanguage($formData)
    {
        if (!is_array($formData)) {
            return null;
        }

        if (!empty($formData[self::REQUEST_LANGUAGE_KEY])) {
            return $this->sanitizeValidLanguage($formData[self::REQUEST_LANGUAGE_KEY]);
        }

        $referer = ArrayHelper::get($formData, '_wp_http_referer');
        if (is_string($referer) && $referer !== '') {
            return $this->extractValidLanguageFromUrl($referer);
        }

        return null;
    }

    private function extractValidLanguageFromUrl($url)
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        $parsed = wp_parse_url($url);

        if (!empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);

            if (!empty($query[self::REQUEST_LANGUAGE_KEY])) {
                return $this->sanitizeValidLanguage($query[self::REQUEST_LANGUAGE_KEY]);
            }
        }

        if (!empty($parsed['path'])) {
            $segments = explode('/', trim((string) $parsed['path'], '/'));

            foreach ($segments as $segment) {
                $language = $this->sanitizeValidLanguage($segment);
                if ($language) {
                    return $language;
                }
            }
        }

        return null;
    }

    private function sanitizeValidLanguage($language)
    {
        $language = sanitize_text_field((string) $language);

        if ($language === '' || !function_exists('pll_languages_list')) {
            return null;
        }

        $languages = pll_languages_list(['fields' => 'slug']);

        return in_array($language, (array) $languages, true) ? $language : null;
    }

    private function switchLanguage($language)
    {
        $language = $this->sanitizeValidLanguage($language);

        if (!$language) {
            return;
        }

        if (function_exists('pll_switch_language')) {
            pll_switch_language($language);
            return;
        }

        if (function_exists('PLL')) {
            $polylang = PLL();
            if (is_object($polylang) && isset($polylang->curlang) && isset($polylang->model) && method_exists($polylang->model, 'get_language')) {
                $languageObject = $polylang->model->get_language($language);
                if ($languageObject) {
                    $polylang->curlang = $languageObject;
                }
            }
        }
    }

    private function getSubmissionMetaValue($submissionId, $metaKey, $default = false)
    {
        if (method_exists(Helper::class, 'getSubmissionMeta')) {
            return Helper::getSubmissionMeta($submissionId, $metaKey, $default);
        }

        if (!$submissionId || !$metaKey || !function_exists('wpFluent')) {
            return $default;
        }

        $value = wpFluent()
            ->table('fluentform_submission_meta')
            ->where('response_id', $submissionId)
            ->where('meta_key', $metaKey)
            ->value('value');

        return $value !== null ? $value : $default;
    }

    private function canTranslateForm($form)
    {
        return is_object($form)
            && isset($form->id)
            && $this->isPolylangEnabledOnForm($form->id);
    }

    private function resolveFormInstance($form)
    {
        if (is_object($form) && isset($form->id)) {
            return $form;
        }

        if (is_array($form) && isset($form['id'])) {
            return (object) $form;
        }

        $formId = absint($form);

        return $formId ? $this->getCachedFormModel($formId) : null;
    }

    private function getCachedFormModel($formId)
    {
        $formId = absint($formId);

        if (!$formId || !class_exists(Form::class)) {
            return null;
        }

        if (!array_key_exists($formId, self::$formModelCache)) {
            self::$formModelCache[$formId] = Form::find($formId);
        }

        return self::$formModelCache[$formId];
    }

    private function getFormFields($form, $asArray = false)
    {
        if (class_exists(FormFieldsParser::class) && method_exists(FormFieldsParser::class, 'getFields')) {
            return FormFieldsParser::getFields($form, $asArray);
        }

        if (class_exists('\FluentForm\App\Services\Parser\Form')) {
            $parser = new \FluentForm\App\Services\Parser\Form($form);

            if (method_exists($parser, 'getFields')) {
                return $parser->getFields($asArray);
            }
        }

        return [];
    }

    private function getFormStringGroup($form)
    {
        $title = isset($form->title) && $form->title !== '' ? $form->title : (string) $form->id;

        return self::STRING_GROUP_PREFIX . ' ' . absint($form->id) . ': ' . $title;
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
