<?php
/**
 * Plugin Name: Multilingual Forms for Fluent Forms with Polylang
 * Description: Add multilingual form support for Fluent Forms using Polylang.
 * Author: dhrupo, pyrobd
 * Plugin URI: https://github.com/dhrupo/fluent-forms-polylang
 * Author URI: https://github.com/dhrupo/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: multilingual-forms-fluent-forms-polylang
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to PHP: 8.3
 * Requires Plugins: fluentform, polylang
 */

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright 2026 WPManageNinja LLC. All rights reserved.
 */

defined('ABSPATH') || exit;
define('MFFFPLL_DIR', plugin_dir_path(__FILE__));
define('MFFFPLL_URL', plugins_url('', __FILE__));
defined('MFFFPLL_VERSION') or define('MFFFPLL_VERSION', '1.0.0');

class MultilingualFormsFluentFormsPolylang
{
    const FLUENTFORM_BASENAME    = 'fluentform/fluentform.php';
    const POLYLANG_BASENAME      = 'polylang/polylang.php';
    const POLYLANG_PRO_BASENAME  = 'polylang-pro/polylang.php';

    private static $activePlugins;

    public function boot()
    {
        if (!defined('FLUENTFORM')) {
            return $this->injectFluentFormDependency();
        }

        if (!self::isPolylangActive()) {
            return $this->injectPolylangDependency();
        }

        $this->includeFiles();

        if (function_exists('wpFluentForm')) {
            return $this->registerHooks(wpFluentForm());
        }
    }

    protected function includeFiles()
    {
        include_once MFFFPLL_DIR . 'src/Controllers/GlobalSettingsController.php';
        include_once MFFFPLL_DIR . 'src/Services/FormTranslationService.php';
        include_once MFFFPLL_DIR . 'src/Controllers/FormSettingsController.php';
        include_once MFFFPLL_DIR . 'src/Controllers/RuntimeTranslationController.php';
        include_once MFFFPLL_DIR . 'src/Controllers/SettingsController.php';
    }

    protected function registerHooks($fluentForm)
    {
        new MultilingualFormsFluentFormsPolylang\Controllers\GlobalSettingsController();
        new MultilingualFormsFluentFormsPolylang\Controllers\SettingsController($fluentForm);
    }

    /**
     * Polylang is considered "active" when either the free or Pro bootstrap is
     * loaded *and* the public string-translation API is available. We require
     * pll_register_string so the per-form translation flow has somewhere to
     * register strings.
     */
    public static function isPolylangActive()
    {
        if (!defined('POLYLANG_VERSION')) {
            return false;
        }

        if (!function_exists('pll_register_string') || !function_exists('pll__')) {
            return false;
        }

        if (!isset(self::$activePlugins)) {
            self::setActivePlugins();
        }

        return self::isPluginInActiveList(self::POLYLANG_BASENAME)
            || self::isPluginInActiveList(self::POLYLANG_PRO_BASENAME);
    }

    private static function isPluginInActiveList($basename)
    {
        return in_array($basename, self::$activePlugins, true)
            || array_key_exists($basename, self::$activePlugins);
    }

    private static function setActivePlugins()
    {
        self::$activePlugins = (array) get_option('active_plugins', array());

        if (is_multisite()) {
            self::$activePlugins = array_merge(
                self::$activePlugins,
                get_site_option('active_sitewide_plugins', array())
            );
        }
    }

    /**
     * Tell the user the Fluent Forms plugin is missing and link to install/activate it.
     */
    protected function injectFluentFormDependency()
    {
        add_action('admin_notices', function () {
            $info = $this->getPluginInstallationDetails(self::FLUENTFORM_BASENAME, 'fluentform');
            $this->renderDependencyNotice('Fluent Forms', $info);
        });
    }

    /**
     * Tell the user Polylang is missing. Polylang Pro counts as satisfying the dependency,
     * but the install/activate link always points at the free plugin (the canonical .org slug).
     */
    protected function injectPolylangDependency()
    {
        add_action('admin_notices', function () {
            $info = $this->getPluginInstallationDetails(self::POLYLANG_BASENAME, 'polylang');
            $this->renderDependencyNotice('Polylang', $info);
        });
    }

    protected function renderDependencyNotice($pluginLabel, $info)
    {
        $linkText = $info->action === 'activate'
            ? 'Click Here to Activate the Plugin'
            : 'Click Here to Install the Plugin';

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr('notice notice-error'),
            wp_kses_post(sprintf(
                'Fluent Forms Polylang Add-On Requires %s, <b><a href="%s">%s</a></b>',
                esc_html($pluginLabel),
                esc_url($info->url),
                esc_html($linkText)
            ))
        );
    }

    protected function getPluginInstallationDetails($basename, $slug)
    {
        $activation = (object) [
            'action' => 'install',
            'url'    => '',
        ];

        $allPlugins = get_plugins();

        if (isset($allPlugins[$basename])) {
            $activation->action = 'activate';
            $activation->url    = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=' . $basename),
                'activate-plugin_' . $basename
            );
        } else {
            $activation->url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $slug),
                'install-plugin_' . $slug
            );
        }

        return $activation;
    }
}

add_action('fluentform/loaded', function () {
    (new MultilingualFormsFluentFormsPolylang())->boot();
});
