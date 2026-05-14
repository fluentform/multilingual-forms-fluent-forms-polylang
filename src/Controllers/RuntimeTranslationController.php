<?php

namespace MultilingualFormsFluentFormsPolylang\Controllers;

use FluentForm\Framework\Helpers\ArrayHelper;
use MultilingualFormsFluentFormsPolylang\Services\FormTranslationService;

if (!defined('ABSPATH')) {
    exit;
}

class RuntimeTranslationController
{
    private static $currentDoubleOptinContext = null;
    private static $entryConfirmationLanguageContext = null;

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
        add_action('init', [$this, 'setupLanguageForAjax'], 5);
        add_action('wp', [$this, 'setupLanguageForFrontendRequest'], 0);

        add_filter('fluentform/ajax_url', [$this, 'setAjaxLanguage'], 10, 1);
        add_filter('fluentform/rendering_form', [$this, 'translateRenderingForm'], 10, 1);
        add_filter('fluentform/before_render_item', [$this, 'translateRenderingField'], 10, 2);
        $this->registerFieldDataTranslationFilters();
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
    }

    public function translateRenderingForm($form)
    {
        return $this->translations->translateRenderingForm($form);
    }

    public function translateRenderingField($item, $form)
    {
        if (!$this->translations->canTranslateForm($form)) {
            return $item;
        }

        return $this->translations->translateKnownStringsRecursive($item, '', $this->translations->getRuntimeLanguageFromRequest());
    }

    public function translateRenderingFieldData($data, $form)
    {
        return $this->translateRenderingField($data, $form);
    }

    public function setCaptchaLanguage($language)
    {
        $currentLanguage = $this->translations->getCurrentPolylangLanguage();

        if (!$currentLanguage) {
            return $language;
        }

        return $currentLanguage;
    }

    public function translateConfirmationMessage($confirmation, $formData, $form)
    {
        if (!$this->translations->canTranslateForm($form)) {
            return $confirmation;
        }

        return $this->translations->translateKnownStringsRecursive($confirmation);
    }

    public function translateFormMessage($message, $formOrContext = null, $form = null)
    {
        $form = $this->translations->resolveFormInstance($form ?: $formOrContext);

        if (!$form || !$this->translations->isFormEnabled($form->id)) {
            return $message;
        }

        return $this->translations->translateStringValue($message);
    }

    public function translateFormIdMessage($message, $formId)
    {
        $formId = absint($formId);

        if (!$formId || !$this->translations->isFormEnabled($formId)) {
            return $message;
        }

        return $this->translations->translateStringValue($message);
    }

    public function translateFormMessagesArray($messages, $formDataOrForm, $form = null)
    {
        $form = $this->translations->resolveFormInstance($form ?: $formDataOrForm);

        if (!$form || !$this->translations->isFormEnabled($form->id)) {
            return $messages;
        }

        return $this->translations->translateKnownStringsRecursive($messages);
    }

    public function translateValidationMessages($validations, $form, $formData)
    {
        if (!$this->translations->canTranslateForm($form) || !is_array($validations)) {
            return $validations;
        }

        if (isset($validations[1]) && is_array($validations[1])) {
            $validations[1] = $this->translations->translateKnownStringsRecursive($validations[1]);
        }

        return $validations;
    }

    public function translateFeedValuesBeforeParse($feed, $insertId, $formData, $form)
    {
        $form = $this->translations->resolveFormInstance($form);

        if (!$form || !$this->translations->isFormEnabled($form->id) || !is_array($feed)) {
            return $feed;
        }

        if (isset($feed['settings'])) {
            $feed['settings'] = $this->translations->translateKnownStringsRecursive($feed['settings']);
        }

        return $feed;
    }

    public function translateEmailSubject($emailSubject, $formData, $form, $notification)
    {
        if (!$this->translations->canTranslateForm($form)) {
            return $emailSubject;
        }

        return $this->translations->translateStringValue($emailSubject);
    }

    public function translateEmailBody($emailBody, $formData, $form, $notification)
    {
        if (!$this->translations->canTranslateForm($form)) {
            return $emailBody;
        }

        return $this->translations->translateStringValue($emailBody);
    }

    public function translateSubmissionMessageParse($message, $insertId, $formData, $form)
    {
        if (!$this->translations->canTranslateForm($form)) {
            return $message;
        }

        if ($this->isCurrentDoubleOptinForm($form)) {
            $translated = $this->translateDoubleOptinRuntimeMessage($message);
            if ($translated !== null) {
                return $translated;
            }
        }

        return $this->translations->translateStringValue($message);
    }

    public function prepareDoubleOptinContext($insertId, $formData, $form)
    {
        self::$currentDoubleOptinContext = null;

        if (!$this->translations->canTranslateForm($form)) {
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

        $language = $this->translations->extractSubmissionLanguage((array) ArrayHelper::get($context, 'form_data', []));
        $form = ArrayHelper::get($context, 'form');
        $insertId = (int) ArrayHelper::get($context, 'insert_id');

        if (!$language || !is_object($form) || empty($form->id) || !$insertId) {
            return $atts;
        }

        foreach (['subject', 'message'] as $mailKey) {
            if (!empty($atts[$mailKey]) && is_string($atts[$mailKey])) {
                $atts[$mailKey] = $this->translations->translateStringValue($atts[$mailKey], $language);
            }
        }

        $hash = (string) $this->translations->getSubmissionMetaValue($insertId, '_entry_uid_hash');
        if (!$hash || empty($atts['message']) || !is_string($atts['message'])) {
            return $atts;
        }

        $defaultUrl = add_query_arg([
            'ff_landing'         => $form->id,
            'entry_confirmation' => $hash,
        ], home_url());

        $localizedUrl = $this->translations->addLanguageToUrl($defaultUrl, $language);
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
        $language = $this->translations->getCurrentPolylangLanguage();

        if (!$language) {
            return $url;
        }

        return add_query_arg([FormTranslationService::REQUEST_LANGUAGE_KEY => $language], $url);
    }

    public function setupLanguageForAjax()
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX || !$this->translations->isPolylangActive()) {
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

        $language = $this->translations->extractLanguageFromRequest($request);

        if ($language) {
            $this->translations->switchLanguage($language);
            $this->translations->loadStringTranslations($language);
        }
    }

    public function setupLanguageForFrontendRequest()
    {
        if (is_admin() || !$this->translations->isPolylangActive()) {
            return;
        }

        $request = $this->app->request->all();
        $language = $this->translations->extractLanguageFromRequest((array) $request);

        if ($language) {
            $this->translations->switchLanguage($language);
            $this->translations->loadStringTranslations($language);
        }
    }

    public function setSubmissionLanguageForEntryConfirmation($data)
    {
        self::$entryConfirmationLanguageContext = null;

        $language = $this->translations->getSubmissionLanguageFromConfirmationData((array) $data);
        if (!$language) {
            return;
        }

        self::$entryConfirmationLanguageContext = $this->translations->getCurrentPolylangLanguage();
        $this->translations->switchLanguage($language);
    }

    public function restoreEntryConfirmationLanguage()
    {
        if (self::$entryConfirmationLanguageContext) {
            $this->translations->switchLanguage(self::$entryConfirmationLanguageContext);
            self::$entryConfirmationLanguageContext = null;
        }
    }

    public function isPolylangActive()
    {
        return $this->translations->isPolylangActive();
    }

    public function getCurrentPolylangLanguage()
    {
        return $this->translations->getCurrentPolylangLanguage();
    }

    public function addLanguageToUrl($url, $language = null)
    {
        return $this->translations->addLanguageToUrl($url, $language);
    }

    public function handleLanguageForPdf($requestData)
    {
        $this->translations->handleLanguageForPdf($requestData);
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

    private function translateDoubleOptinRuntimeMessage($message)
    {
        $context = self::$currentDoubleOptinContext;
        if (!$context) {
            return null;
        }

        $settings = (array) ArrayHelper::get($context, 'settings', []);

        foreach (['confirmation_message', 'email_subject', 'email_body'] as $settingKey) {
            if ($message === ArrayHelper::get($settings, $settingKey)) {
                return $this->translations->translateStringValue(
                    $message,
                    $this->translations->extractSubmissionLanguage((array) ArrayHelper::get($context, 'form_data', []))
                );
            }
        }

        return null;
    }

    private function registerFieldDataTranslationFilters()
    {
        $fieldElements = [
            'input_text',
            'input_email',
            'input_name',
            'input_textarea',
            'input_number',
            'input_url',
            'input_date',
            'input_file',
            'input_image',
            'input_hidden',
            'select',
            'multi_select',
            'input_radio',
            'input_checkbox',
            'address',
            'section_break',
            'custom_html',
            'terms_and_condition',
            'ratings',
            'net_promoter_score',
            'tabular_grid',
            'repeater_field',
            'rangeslider',
            'gdpr_agreement',
            'phone',
            'country_list',
            'payment_item',
            'custom_payment_component',
            'item_quantity_component',
            'payment_method',
            'coupon',
            'subscription_payment_component',
            'chained_select',
            'form_step',
            'recaptcha',
            'hcaptcha',
            'turnstile',
        ];

        foreach ($fieldElements as $fieldElement) {
            add_filter('fluentform/rendering_field_data_' . $fieldElement, [$this, 'translateRenderingFieldData'], 10, 2);
        }
    }
}
