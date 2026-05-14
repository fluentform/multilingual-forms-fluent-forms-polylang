<?php

namespace MultilingualFormsFluentFormsPolylang\Services;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Models\Submission;

if (!defined('ABSPATH')) {
    exit;
}

class FormTranslationService
{
    const FORM_META_KEY = 'ff_polylang';
    const STRING_GROUP_PREFIX = 'Fluent Forms Form';
    const REQUEST_LANGUAGE_KEY = 'lang';

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

    public function setFormEnabled($formId, $isEnabled)
    {
        $formId = absint($formId);

        if (!$formId) {
            return;
        }

        $this->clearFormCache($formId);
        Helper::setFormMeta($formId, self::FORM_META_KEY, (bool) $isEnabled);
    }

    public function isFormEnabled($formId)
    {
        if (!$formId) {
            return false;
        }

        return Helper::getFormMeta($formId, self::FORM_META_KEY) === '1'
            || Helper::getFormMeta($formId, self::FORM_META_KEY) === true;
    }

    public function clearFormCache($formId)
    {
        $formId = absint($formId);

        if (!$formId) {
            return;
        }

        unset(self::$translatedFormFieldsCache[$formId], self::$formModelCache[$formId]);
    }

    public function removeFormStrings($formId)
    {
        $formId = absint($formId);

        if (!$formId) {
            return;
        }

        $this->clearFormCache($formId);
        Helper::setFormMeta($formId, self::FORM_META_KEY, false);
    }

    public function registerFormStrings($formId, $rawFormFields = null)
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

    public function registerEnabledFormStrings()
    {
        if (!$this->canUsePolylangStrings() || !class_exists(FormMeta::class)) {
            return;
        }

        $enabledForms = FormMeta::where('meta_key', self::FORM_META_KEY)
            ->where('value', '1')
            ->get();

        foreach ($enabledForms as $enabledForm) {
            $formId = absint(isset($enabledForm['form_id']) ? $enabledForm['form_id'] : null);

            if ($formId) {
                $this->registerFormStrings($formId);
            }
        }
    }

    public function translateRenderingForm($form)
    {
        if (!is_object($form) || empty($form->id) || !$this->isFormEnabled($form->id)) {
            return $form;
        }

        $language = $this->getRuntimeLanguageFromRequest();
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

    public function translateKnownStringsRecursive($value, $path = '', $language = null)
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

    public function translateStringValue($value, $language = null)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $language = $language ?: $this->getCurrentPolylangLanguage();

        if ($language && function_exists('pll_translate_string')) {
            $this->loadStringTranslations($language);

            return pll_translate_string($value, $language);
        }

        if (function_exists('pll__')) {
            return pll__($value);
        }

        return $value;
    }

    public function getRuntimeLanguageFromRequest()
    {
        return $this->extractLanguageFromRequest($_REQUEST) ?: $this->getCurrentPolylangLanguage();
    }

    public function loadStringTranslations($language)
    {
        if (!$language || !function_exists('PLL')) {
            return;
        }

        $polylang = PLL();

        if (is_object($polylang) && method_exists($polylang, 'load_strings_translations')) {
            $polylang->load_strings_translations($language);
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

    public function extractLanguageFromRequest(array $request)
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

    public function extractSubmissionLanguage($formData)
    {
        if (!is_array($formData)) {
            return null;
        }

        if (!empty($formData[self::REQUEST_LANGUAGE_KEY])) {
            return $this->sanitizeValidLanguage($formData[self::REQUEST_LANGUAGE_KEY]);
        }

        $referer = isset($formData['_wp_http_referer']) ? $formData['_wp_http_referer'] : null;
        if (is_string($referer) && $referer !== '') {
            return $this->extractValidLanguageFromUrl($referer);
        }

        return null;
    }

    public function switchLanguage($language)
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
            if (is_object($polylang) && isset($polylang->model)) {
                $languageObject = $polylang->model->get_language($language);
                if ($languageObject) {
                    $polylang->curlang = $languageObject;
                }
            }
        }
    }

    public function getSubmissionMetaValue($submissionId, $metaKey, $default = false)
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

    public function getSubmissionLanguageFromConfirmationData($data)
    {
        $formId = absint(isset($data['ff_landing']) ? $data['ff_landing'] : null);
        $hash = sanitize_text_field((string) (isset($data['entry_confirmation']) ? $data['entry_confirmation'] : ''));

        if (!$formId || !$hash || !$this->isFormEnabled($formId) || !function_exists('wpFluent')) {
            return null;
        }

        $meta = wpFluent()->table('fluentform_submission_meta')
            ->where('form_id', $formId)
            ->where('meta_key', '_entry_uid_hash')
            ->where('value', $hash)
            ->first();

        if (!$meta || empty($meta->response_id)) {
            return null;
        }

        $submission = Submission::find((int) $meta->response_id);
        if (!$submission) {
            return null;
        }

        return $this->extractSubmissionLanguage((array) json_decode($submission->response, true));
    }

    public function canTranslateForm($form)
    {
        return is_object($form)
            && isset($form->id)
            && $this->isFormEnabled($form->id);
    }

    public function resolveFormInstance($form)
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

    public function canUsePolylangStrings()
    {
        return function_exists('pll_register_string') && function_exists('pll__');
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

    private function isMultiline($value)
    {
        return strpos($value, "\n") !== false
            || strpos($value, '<') !== false
            || strpos($value, '{') !== false
            || strpos($value, '#') !== false;
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

    private function getFormStringGroup($form)
    {
        $title = isset($form->title) && $form->title !== '' ? $form->title : (string) $form->id;

        return self::STRING_GROUP_PREFIX . ' ' . absint($form->id) . ': ' . $title;
    }
}
