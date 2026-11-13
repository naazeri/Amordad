/**
 * Cockpit CMS Addon: AiTranslate
 * Vue 3 Components for Amordad AI Translation
 */
(function() {
    'use strict';

    function initAiTranslate() {
        if (!window.VueView) {
            setTimeout(initAiTranslate, 50);
            return;
        }

        // 1. Single Project Translation Component
        VueView.component('ai-translate-project-widget', {
            props: {
                item: {
                    type: Object,
                    default: () => ({})
                }
            },
            data() {
                return {
                    loading: false,
                    confirmed: false
                };
            },
            methods: {
                translateProject() {
                    if (this.loading) return;

                    let item = this.item;
                    if (!item) {
                        App.ui.notify('خطا: اطلاعات پروژه در دسترس نیست.', 'error', { timeout: 12000 });
                        return;
                    }

                    if (!item._id) {
                        App.ui.notify('لطفاً ابتدا پروژه را یک‌بار ذخیره نمایید تا شناسه پروژه ثبت شود.', 'error', { timeout: 12000 });
                        return;
                    }

                    let titleFa = (item.title_fa || '').trim();
                    let infoFa = (item.projectInformation_fa || '').trim();
                    let descFa = (item.description_fa || '').trim();
                    let contentFa = (item.content_fa || '').trim();

                    if (!titleFa && !infoFa && !descFa && !contentFa) {
                        App.ui.notify('هیچ متنی در فیلدهای فارسی این پروژه وارد نشده است.', 'error', { timeout: 12000 });
                        return;
                    }

                    // Cockpit's own dialog (native confirm() can be blocked by the browser)
                    if (!this.confirmed) {
                        App.ui.confirm('متن‌های انگلیسی و ترکی این پروژه با ترجمه‌ی هوش مصنوعی جایگزین و ذخیره می‌شوند. ادامه می‌دهید؟', () => {
                            this.confirmed = true;
                            this.translateProject();
                        });
                        return;
                    }
                    this.confirmed = false;

                    this.loading = true;

                    let unloadHandler = (e) => {
                        let msg = 'ترجمه هوشمند در حال انجام است. لطفاً صفحه را نبندید.';
                        e.returnValue = msg;
                        return msg;
                    };
                    window.addEventListener('beforeunload', unloadHandler);

                    let payload = {
                        id: item._id,
                        override: {
                            title_fa: item.title_fa || '',
                            projectInformation_fa: item.projectInformation_fa || '',
                            description_fa: item.description_fa || '',
                            content_fa: item.content_fa || ''
                        }
                    };

                    App.request('/ai-translate/project', payload).then(res => {
                        window.removeEventListener('beforeunload', unloadHandler);
                        this.loading = false;

                        if (!res || !res.success) {
                            let errMsg = (res && res.error) ? res.error : 'خطا در دریافت پاسخ ترجمه';
                            App.ui.notify(errMsg, 'error', { timeout: 12000 });
                            return;
                        }

                        // Apply only the translated EN/TR fields, so other unsaved
                        // edits in the form are kept.
                        if (res.translations) {
                            Object.keys(res.translations).forEach(key => {
                                this.item[key] = res.translations[key];
                            });
                        }
                        if (res.modified) {
                            this.item._modified = res.modified;
                        }

                        let modelInfo = res.modelUsed ? ` (مدل: ${res.modelUsed})` : '';
                        let partial = Array.isArray(res.errors) && res.errors.length;
                        App.ui.notify((res.message || 'ترجمه هوشمند پروژه با موفقیت انجام و ذخیره شد.') + modelInfo, partial ? 'warning' : 'success');

                        if (partial) {
                            App.ui.notify('بخشی که ترجمه نشد:\n' + res.errors.join('\n'), 'error', { timeout: 12000 });
                        }

                        if (res.usedFallbackKey) {
                            App.ui.notify('سهمیه کلید اصلی تمام شده بود؛ با کلید پشتیبان با موفقیت کامل شد.', 'info');
                        }
                    }).catch(err => {
                        window.removeEventListener('beforeunload', unloadHandler);
                        this.loading = false;
                        let msg = (err && (err.error || err.message)) ? (err.error || err.message) : 'خطا در ارتباط با سرور';
                        App.ui.notify(msg, 'error', { timeout: 12000 });
                    });
                }
            },
            template: `
                <div class="kiss-margin" id="ai-translate-project-wrapper">
                    <div class="kiss-text-caption kiss-size-xsmall kiss-text-bold">هوش مصنوعی / AI Translation</div>
                    <kiss-card class="kiss-margin-small kiss-padding-small kiss-bgcolor-contrast">
                        <div class="kiss-margin-xsmall kiss-color-muted kiss-size-xsmall">
                            ترجمه هوشمند متون فارسی به زبان‌های انگلیسی و ترکی و ذخیره خودکار
                        </div>
                        <button type="button" 
                                class="kiss-button kiss-button-primary kiss-width-1-1 kiss-margin-small-top" 
                                :disabled="loading"
                                @click="translateProject()">
                            <icon class="kiss-margin-xsmall-end" :class="{'animated spin': loading}">{{ loading ? 'sync' : 'auto_awesome' }}</icon>
                            <span>{{ loading ? 'در حال ترجمه هوشمند...' : 'ترجمه هوشمند با AI' }}</span>
                        </button>
                    </kiss-card>
                </div>
            `
        });

        // 2. Bulk Site Content Translation Component
        VueView.component('ai-translate-site-widget', {
            props: {
                item: {
                    type: Object,
                    default: () => ({})
                }
            },
            data() {
                return {
                    loading: false,
                    confirmed: false
                };
            },
            methods: {
                translateSite() {
                    if (this.loading) return;

                    // Cockpit's own dialog (native confirm() can be blocked by the browser)
                    if (!this.confirmed) {
                        App.ui.confirm('آیا مایلید تمامی دسته‌بندی‌ها، اسلایدرها و متون عمومی سایت (به‌جز نام برند) بر اساس زبان فارسی ترجمه و ذخیره شوند؟', () => {
                            this.confirmed = true;
                            this.translateSite();
                        });
                        return;
                    }
                    this.confirmed = false;

                    this.loading = true;

                    let unloadHandler = (e) => {
                        let msg = 'ترجمه کلی متون سایت در حال انجام است. لطفاً صفحه را نبندید.';
                        e.returnValue = msg;
                        return msg;
                    };
                    window.addEventListener('beforeunload', unloadHandler);

                    App.request('/ai-translate/site-content', {}).then(res => {
                        window.removeEventListener('beforeunload', unloadHandler);
                        this.loading = false;

                        if (!res || !res.success) {
                            let errMsg = (res && res.error) ? res.error : 'خطا در ترجمه متون سایت';
                            App.ui.notify(errMsg, 'error', { timeout: 12000 });
                            return;
                        }

                        let modelInfo = res.modelUsed ? ` (مدل: ${res.modelUsed})` : '';
                        App.ui.notify((res.message || 'ترجمه کلیه متون سایت با موفقیت انجام و ذخیره شد.') + modelInfo, 'success');

                        if (Array.isArray(res.errors) && res.errors.length) {
                            App.ui.notify('بخشی از ترجمه انجام نشد:\n' + res.errors.join('\n'), 'error', { timeout: 12000 });
                        }

                        if (res.usedFallbackKey) {
                            App.ui.notify('سهمیه کلید اصلی تمام شده بود؛ با کلید پشتیبان با موفقیت انجام شد.', 'info');
                        }

                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }).catch(err => {
                        window.removeEventListener('beforeunload', unloadHandler);
                        this.loading = false;
                        let msg = (err && (err.error || err.message)) ? (err.error || err.message) : 'خطا در ارتباط با سرور';
                        App.ui.notify(msg, 'error', { timeout: 12000 });
                    });
                }
            },
            template: `
                <div class="kiss-margin" id="ai-translate-site-wrapper">
                    <div class="kiss-text-caption kiss-size-xsmall kiss-text-bold">ترجمه هوشمند کل سایت</div>
                    <kiss-card class="kiss-margin-small kiss-padding-small kiss-bgcolor-contrast">
                        <div class="kiss-margin-xsmall kiss-color-muted kiss-size-xsmall">
                            ترجمه نام دسته‌بندی‌ها، فوتر، سئو و اسلایدرها (به‌جز پروژه‌ها و نام برند)
                        </div>
                        <button type="button" 
                                class="kiss-button kiss-button-primary kiss-width-1-1 kiss-margin-small-top" 
                                :disabled="loading"
                                @click="translateSite()">
                            <icon class="kiss-margin-xsmall-end" :class="{'animated spin': loading}">{{ loading ? 'sync' : 'translate' }}</icon>
                            <span>{{ loading ? 'در حال ترجمه هوشمند کل سایت...' : 'ترجمه مابقی متون سایت' }}</span>
                        </button>
                    </kiss-card>
                </div>
            `
        });

        // 3. Secret (password-style) text field, used for API keys in Site Settings
        VueView.component('field-secret', {
            _meta: {
                label: 'Secret',
                info: 'Masked text input for API keys and other secrets'
            },
            props: {
                modelValue: {
                    type: String,
                    default: ''
                }
            },
            data() {
                return {
                    visible: false
                };
            },
            template: `
                <div class="kiss-flex kiss-flex-middle" style="gap: 8px;">
                    <input :type="visible ? 'text' : 'password'"
                           class="kiss-input kiss-flex-1"
                           autocomplete="off" spellcheck="false" dir="ltr"
                           :value="modelValue"
                           @input="$emit('update:modelValue', $event.target.value)">
                    <button type="button" class="kiss-button kiss-button-small" @click="visible = !visible">
                        {{ visible ? 'پنهان' : 'نمایش' }}
                    </button>
                </div>
            `
        });

        // 4. OpenRouter model selector (Site Settings). The model list is fetched
        //    live from OpenRouter (through the admin route) on every page load.
        VueView.component('field-openrouter-model', {
            _meta: {
                label: 'OpenRouter Model',
                info: 'Select the OpenRouter model used for AI translation'
            },
            props: {
                modelValue: {
                    type: String,
                    default: ''
                }
            },
            data() {
                return {
                    free: [],
                    paid: [],
                    defaultModel: 'openrouter/free',
                    freeTier: null,
                    loading: true,
                    error: null
                };
            },
            computed: {
                selected() {
                    return this.modelValue || this.defaultModel;
                },
                selectedModel() {
                    return this.free.concat(this.paid).find(m => m.id === this.selected) || null;
                },
                isPaidSelected() {
                    return this.paid.some(m => m.id === this.selected);
                },
                isMissing() {
                    return !this.loading && !this.error && !this.selectedModel;
                }
            },
            mounted() {
                this.load();
            },
            methods: {
                load() {
                    this.loading = true;
                    this.error = null;
                    App.request('/ai-translate/openrouter-models').then(res => {
                        this.loading = false;
                        if (!res || !Array.isArray(res.free)) {
                            this.error = (res && res.error) || 'خطا در دریافت فهرست مدل‌ها';
                            return;
                        }
                        this.free = res.free;
                        this.paid = res.paid || [];
                        this.defaultModel = res.defaultModel || this.defaultModel;
                        this.freeTier = res.freeTier;
                    }).catch(err => {
                        this.loading = false;
                        this.error = (err && (err.error || err.message)) || 'خطا در دریافت فهرست مدل‌ها از OpenRouter';
                    });
                },
                select(value) {
                    this.$emit('update:modelValue', value === this.defaultModel ? '' : value);
                },
                price(m) {
                    if (m.prompt === null || m.output === null) return 'قیمت متغیر';
                    return `$${m.prompt} / $${m.output} در هر میلیون توکن`;
                },
                label(m) {
                    let text = m.name + ' — ' + m.id;
                    if (m.id === this.defaultModel) text = 'خودکار (پیش‌فرض) — ' + text;
                    return text;
                }
            },
            template: `
                <div>
                    <div class="kiss-flex kiss-flex-middle" style="gap: 8px;">
                        <select class="kiss-input kiss-select kiss-flex-1" dir="ltr"
                                :value="selected" :disabled="loading"
                                @change="select($event.target.value)">
                            <option v-if="loading" :value="selected">در حال دریافت فهرست مدل‌ها...</option>
                            <option v-if="isMissing || error" :value="selected">{{ selected }}{{ isMissing ? ' (دیگر در OpenRouter وجود ندارد)' : '' }}</option>
                            <optgroup :label="'رایگان (' + free.length + ')'" v-if="free.length">
                                <option v-for="m in free" :key="m.id" :value="m.id">{{ label(m) }}</option>
                            </optgroup>
                            <option disabled v-if="paid.length">──────────  مدل‌های پولی  ──────────</option>
                            <optgroup :label="'💲 پولی — نیاز به اعتبار در حساب OpenRouter (' + paid.length + ')'" v-if="paid.length">
                                <option v-for="m in paid" :key="m.id" :value="m.id">💲 {{ m.name }} — {{ m.id }} ({{ price(m) }})</option>
                            </optgroup>
                        </select>
                        <button type="button" class="kiss-button kiss-button-small" :disabled="loading" @click="load()">به‌روزرسانی</button>
                    </div>
                    <div class="kiss-size-xsmall kiss-margin-xsmall-top kiss-color-danger" v-if="error">{{ error }}</div>
                    <div class="kiss-size-xsmall kiss-margin-xsmall-top kiss-color-danger" v-if="isMissing">
                        این مدل دیگر در OpenRouter نیست؛ هنگام ترجمه خودکار از openrouter/free استفاده می‌شود.
                    </div>
                    <div class="kiss-size-xsmall kiss-margin-xsmall-top kiss-color-warning" v-if="isPaidSelected && freeTier">
                        این مدل پولی است و کلید فعلی OpenRouter اعتبار ندارد؛ ترجمه با این مدل انجام نمی‌شود و به Gemini می‌رود.
                    </div>
                    <div class="kiss-size-xsmall kiss-margin-xsmall-top kiss-color-muted" v-if="selectedModel && !isPaidSelected && selected !== defaultModel">
                        مدل رایگان مشخص؛ اگر از دسترس خارج شود، خودکار از openrouter/free استفاده می‌شود.
                    </div>
                </div>
            `
        });

        // 5. Categories Checkboxes Selector Component
        VueView.component('field-categories', {
            _meta: {
                label: 'Categories',
                info: 'Architecture Categories Checkbox Selector'
            },
            props: {
                modelValue: {
                    default: () => []
                }
            },
            data() {
                return {
                    categories: [],
                    loading: true,
                    error: null
                };
            },
            computed: {
                selectedIds() {
                    let val = this.modelValue;
                    if (!val) return [];
                    if (!Array.isArray(val)) val = [val];
                    return val.map(item => {
                        if (typeof item === 'object' && item !== null && item._id) return item._id;
                        return String(item);
                    });
                }
            },
            mounted() {
                App.request('/ai-translate/categories').then(res => {
                    this.loading = false;
                    if (Array.isArray(res)) {
                        this.categories = res;
                    } else {
                        this.error = (res && res.error) || 'خطا در دریافت فهرست دسته‌بندی‌ها';
                    }
                }).catch(err => {
                    this.loading = false;
                    this.error = (err && (err.error || err.message)) || 'خطا در دریافت فهرست دسته‌بندی‌ها';
                });
            },
            methods: {
                isChecked(id) {
                    return this.selectedIds.indexOf(id) !== -1;
                },
                toggle(id) {
                    let current = [...this.selectedIds];
                    let idx = current.indexOf(id);
                    if (idx > -1) {
                        current.splice(idx, 1);
                    } else {
                        current.push(id);
                    }
                    let payload = current.map(catId => ({
                        _model: 'categories',
                        _id: catId
                    }));
                    this.$emit('update:modelValue', payload);
                }
            },
            template: `
                <div class="kiss-margin-small">
                    <div class="kiss-color-muted kiss-size-small" v-if="loading">در حال بارگیری دسته‌بندی‌ها...</div>
                    <div class="kiss-color-danger kiss-size-small" v-if="error">{{ error }}</div>
                    <div v-if="!loading && !error && !categories.length" class="kiss-color-muted kiss-size-small">هیچ دسته‌بندی‌ای تعریف نشده است.</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px;">
                        <div v-for="cat in categories" 
                             :key="cat.id" 
                             class="kiss-card kiss-padding-small kiss-flex kiss-flex-middle kiss-cursor-pointer kiss-position-relative"
                             :style="isChecked(cat.id) ? 'border: 1.5px solid var(--kiss-color-primary, #6366f1); background: rgba(99, 102, 241, 0.12); border-radius: 8px;' : 'border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.02); border-radius: 8px;'"
                             @click="toggle(cat.id)">
                            
                            <input type="checkbox" 
                                   :checked="isChecked(cat.id)" 
                                   @click.stop="toggle(cat.id)" 
                                   style="accent-color: #6366f1; width: 18px; height: 18px; cursor: pointer; margin: 0;" />
                                   
                            <span class="kiss-size-small kiss-margin-small-start kiss-text-bold" 
                                  :style="isChecked(cat.id) ? 'color: var(--kiss-color-primary, #a5b4fc);' : ''"
                                  style="user-select: none;">
                                {{ cat.name }}
                            </span>
                        </div>
                    </div>
                    <div class="kiss-color-muted kiss-size-xsmall kiss-margin-small-top">
                        دسته‌بندی‌های این پروژه را تیک بزنید (امکان انتخاب هم‌زمان چند دسته وجود دارد).
                    </div>
                </div>
            `
        });
    }

    initAiTranslate();
})();
