(function () {
    'use strict';

    var settings = window.MFFFPLLSettings || {};
    var i18n = settings.i18n || {};

    function text(key, fallback) {
        return i18n[key] || fallback;
    }

    window.fluentformCustomComponents = window.fluentformCustomComponents || {};

    window.fluentformCustomComponents.ff_polylang = {
        name: 'FFPolylangSettings',
        props: ['form'],
        data: function () {
            return {
                isEnabled: false,
                loading: false,
                saving: false,
                deleting: false
            };
        },
        methods: {
            formId: function () {
                return this.form && this.form.id ? this.form.id : window.FluentFormApp && window.FluentFormApp.form_id;
            },
            getSettings: function () {
                var vm = this;

                vm.loading = true;

                return FluentFormsGlobal.$get({
                    action: settings.getAction || 'fluentform_get_polylang_settings',
                    form_id: vm.formId()
                })
                    .then(function (response) {
                        vm.isEnabled = !!response.data;
                    })
                    .fail(function (error) {
                        if (vm.$fail) {
                            vm.$fail(error.responseJSON || error);
                        }
                    })
                    .always(function () {
                        vm.loading = false;
                    });
            },
            saveSettings: function () {
                var vm = this;

                vm.saving = true;

                return FluentFormsGlobal.$post({
                    action: settings.storeAction || 'fluentform_store_polylang_settings',
                    form_id: vm.formId(),
                    is_ff_polylang_enabled: vm.isEnabled
                })
                    .then(function (response) {
                        if (vm.$success) {
                            vm.$success(response.data);
                        }
                    })
                    .fail(function (error) {
                        if (vm.$fail) {
                            vm.$fail(error.responseJSON || error);
                        }
                    })
                    .always(function () {
                        vm.saving = false;
                    });
            },
            deleteSettings: function () {
                var vm = this;
                var reset = function () {
                    vm.deleting = true;

                    return FluentFormsGlobal.$post({
                        action: settings.deleteAction || 'fluentform_delete_polylang_settings',
                        form_id: vm.formId()
                    })
                        .then(function (response) {
                            vm.isEnabled = false;

                            if (vm.$success) {
                                vm.$success(response.data);
                            }
                        })
                        .fail(function (error) {
                            if (vm.$fail) {
                                vm.$fail(error.responseJSON || error);
                            }
                        })
                        .always(function () {
                            vm.deleting = false;
                        });
                };

                if (!vm.$confirm) {
                    return reset();
                }

                return vm.$confirm(
                    text('confirm', 'This will disable Polylang translation for this form. Continue?'),
                    text('warning', 'Warning'),
                    {
                        confirmButtonText: text('reset', 'Reset Polylang Translation'),
                        cancelButtonText: text('cancel', 'Cancel'),
                        confirmButtonClass: 'el-button--soft el-button--danger',
                        cancelButtonClass: 'el-button--soft el-button--success',
                        type: 'warning'
                    }
                ).then(reset).catch(function () {
                    vm.deleting = false;
                });
            }
        },
        mounted: function () {
            this.getSettings();

            if (window.jQuery) {
                window.jQuery('head title').text(text('title', 'Polylang Translations') + ' - Fluent Forms');
            }
        },
        render: function (h) {
            var vm = this;

            return h('div', {class: 'ff-polylang-settings'}, [
                h('div', {class: 'ff_card'}, [
                    h('div', {class: 'ff_card_head'}, [
                        h('h5', {class: 'title'}, [text('title', 'Translations using Polylang')]),
                        h('p', {class: 'text'}, [text('description', 'Enable native Polylang string translations for this Fluent Form.')])
                    ]),
                    h('div', {class: 'ff_card_body'}, [
                        h('div', {class: 'el-form-item ff-form-item ff-form-item-flex'}, [
                            h('label', {class: 'el-form-item__label'}, [text('enable', 'Enable translation for this form')]),
                            h('div', {class: 'el-form-item__content'}, [
                                h('el-switch', {
                                    class: 'el-switch-lg',
                                    props: {
                                        value: vm.isEnabled,
                                        disabled: vm.loading || vm.saving || vm.deleting
                                    },
                                    on: {
                                        input: function (value) {
                                            vm.isEnabled = value;
                                        }
                                    }
                                })
                            ])
                        ]),
                        h('div', {class: 'mt-4'}, [
                            h('el-button', {
                                props: {
                                    loading: vm.saving,
                                    type: 'primary',
                                    icon: 'el-icon-success'
                                },
                                on: {
                                    click: vm.saveSettings
                                }
                            }, [vm.saving ? text('saving', 'Saving Settings') : text('save', 'Save Settings')]),
                            vm.isEnabled ? h('el-button', {
                                props: {
                                    loading: vm.deleting,
                                    type: 'danger',
                                    icon: 'el-icon-delete'
                                },
                                on: {
                                    click: vm.deleteSettings
                                }
                            }, [vm.deleting ? text('resetting', 'Resetting Polylang Translation') : text('reset', 'Reset Polylang Translation')]) : null
                        ])
                    ])
                ])
            ]);
        }
    };
}());
