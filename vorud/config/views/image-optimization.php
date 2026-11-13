<kiss-container class="kiss-margin-small" size="medium">

    <ul class="kiss-breadcrumbs">
        <li><a href="<?= $this->route('/system') ?>"><?= t('Settings') ?></a></li>
        <li><span><?= t('Image Optimization') ?></span></li>
    </ul>

    <div class="kiss-margin-large-bottom kiss-flex kiss-flex-middle">
        <div class="kiss-size-4 kiss-flex-1">
            <strong><?= t('Image Optimization & Cache') ?></strong>
        </div>
    </div>

    <vue-view>
        <template>
            <!-- Status & Toggle Card -->
            <kiss-card class="kiss-margin-large-bottom kiss-padding" theme="contrast bordered">
                <div class="kiss-flex kiss-flex-middle">
                    <div class="kiss-margin-small-end">
                        <icon class="kiss-size-2" :class="enabled ? 'kiss-color-success' : 'kiss-color-muted'">photo_size_select_actual</icon>
                    </div>
                    <div class="kiss-flex-1">
                        <div class="kiss-flex kiss-flex-middle kiss-margin-xsmall-bottom">
                            <span class="kiss-text-bold kiss-size-5 kiss-margin-small-end"><?= t('Responsive WebP Pipeline') ?></span>
                            <span class="kiss-badge" :class="enabled ? 'kiss-badge-outline kiss-color-success' : 'kiss-badge-outline kiss-color-danger'">
                                {{ enabled ? '<?= t('Active') ?>' : '<?= t('Disabled') ?>' }}
                            </span>
                        </div>
                        <div class="kiss-size-small kiss-color-muted" v-if="enabled">
                            <?= t('Images are dynamically optimized into WebP format with responsive srcset breakpoints (300–1920px).') ?>
                        </div>
                        <div class="kiss-size-small kiss-color-danger" v-else>
                            <?= t('Optimization is disabled. The site serves raw Cockpit assets directly without WebP conversion or srcset.') ?>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="kiss-button" :class="enabled ? 'kiss-button-danger' : 'kiss-button-success'" :disabled="isRefreshing" @click="toggleOptimization">
                            <icon class="kiss-margin-xsmall-end">{{ enabled ? 'power_settings_new' : 'check_circle' }}</icon>
                            {{ enabled ? '<?= t('Disable') ?>' : '<?= t('Enable') ?>' }}
                        </button>
                    </div>
                </div>
            </kiss-card>

            <!-- Metrics Grid -->
            <kiss-grid cols="2@s 4@m" class="kiss-margin-large-bottom" gap="small">
                <kiss-card class="kiss-padding" theme="contrast bordered">
                    <div class="kiss-text-caption kiss-color-muted kiss-margin-xsmall-bottom"><?= t('Original Images') ?></div>
                    <div class="kiss-size-4 kiss-text-bold">{{ stats.original_count }} <span class="kiss-size-small kiss-text-normal kiss-color-muted"><?= t('files') ?></span></div>
                    <div class="kiss-size-xsmall kiss-color-muted">{{ stats.original_size_human }} &bull; ~{{ stats.avg_original_human }}/img</div>
                </kiss-card>

                <kiss-card class="kiss-padding" theme="contrast bordered">
                    <div class="kiss-text-caption kiss-color-muted kiss-margin-xsmall-bottom"><?= t('Generated Variants') ?></div>
                    <div class="kiss-size-4 kiss-text-bold" :class="stats.variant_count > 0 ? 'kiss-color-primary' : 'kiss-color-muted'">
                        {{ stats.variant_count }} <span class="kiss-size-small kiss-text-normal kiss-color-muted"><?= t('files') ?></span>
                    </div>
                    <div class="kiss-size-xsmall kiss-color-muted">{{ stats.variant_size_human }} &bull; ~{{ stats.avg_variant_human }}/var</div>
                </kiss-card>

                <kiss-card class="kiss-padding" theme="contrast bordered">
                    <div class="kiss-text-caption kiss-color-muted kiss-margin-xsmall-bottom"><?= t('Avg Bandwidth Saved') ?></div>
                    <div class="kiss-size-4 kiss-text-bold kiss-color-success">{{ stats.avg_savings_percent }}%</div>
                    <div class="kiss-size-xsmall kiss-color-muted"><?= t('Per image served to visitor') ?></div>
                </kiss-card>

                <kiss-card class="kiss-padding" theme="contrast bordered">
                    <div class="kiss-text-caption kiss-color-muted kiss-margin-xsmall-bottom"><?= t('Cache Storage') ?></div>
                    <div class="kiss-size-4 kiss-text-bold kiss-color-success">{{ stats.variant_size_human }}</div>
                    <div class="kiss-size-xsmall kiss-color-muted"><?= t('Optimized WebP cache') ?></div>
                </kiss-card>
            </kiss-grid>

            <!-- Cache Refresh / Rebuild Panel -->
            <kiss-card class="kiss-padding" theme="contrast bordered">
                <div class="kiss-margin-bottom">
                    <div class="kiss-size-5 kiss-text-bold kiss-margin-xsmall-bottom">
                        <icon class="kiss-margin-xsmall-end">autorenew</icon><?= t('Cache Refresh & Rebuilding') ?>
                    </div>
                    <div class="kiss-size-small kiss-color-muted">
                        <?= t('Clears cached WebP images and regenerates all responsive variants.') ?>
                    </div>
                </div>

                <!-- Controls when NOT running -->
                <div v-if="!isRefreshing" class="kiss-flex kiss-flex-middle kiss-flex-wrap" style="gap: 10px;">
                    <button type="button" class="kiss-button kiss-button-primary" @click="startRebuild(true)">
                        <icon class="kiss-margin-xsmall-end">refresh</icon><?= t('Clear Cache & Rebuild All') ?>
                    </button>
                    <button type="button" class="kiss-button" @click="startRebuild(false)" v-if="stats.variant_count === 0 || stats.variant_count < stats.original_count">
                        <icon class="kiss-margin-xsmall-end">flash_on</icon><?= t('Warm Missing Variants Only') ?>
                    </button>
                    <button type="button" class="kiss-button kiss-button-danger kiss-button-blank" @click="clearCache">
                        <icon class="kiss-margin-xsmall-end">delete_sweep</icon><?= t('Clear Cache Only') ?>
                    </button>
                </div>

                <!-- Active Progress UI -->
                <div v-else class="kiss-margin-top">
                    <!-- Progress Bar -->
                    <div style="background: rgba(127,127,127,0.2); border-radius: 6px; height: 16px; overflow: hidden; position: relative;" class="kiss-margin-small-bottom">
                        <div style="background: #2563eb; height: 100%; transition: width 0.2s ease;" :style="{ width: progressPercent + '%' }"></div>
                    </div>

                    <div class="kiss-flex kiss-flex-middle kiss-margin-small-bottom">
                        <div class="kiss-flex-1 kiss-size-small">
                            <strong>{{ progressPercent }}%</strong>
                            <span class="kiss-color-muted kiss-margin-small-start">({{ currentIndex }} / {{ queue.length }} assets processed)</span>
                            <span class="kiss-badge kiss-badge-outline kiss-margin-small-start" v-if="isPaused"><?= t('PAUSED') ?></span>
                        </div>
                        <div class="kiss-size-small kiss-color-muted">
                            <icon class="kiss-margin-xsmall-end">schedule</icon>{{ elapsedSec }}s
                        </div>
                    </div>

                    <!-- Current Asset Card -->
                    <div v-if="currentAsset" class="kiss-card kiss-padding-small kiss-margin-small-bottom kiss-flex kiss-flex-middle" style="background: rgba(0,0,0,0.03); border-radius: 6px;">
                        <img v-if="currentAsset.thumbUrl" :src="currentAsset.thumbUrl" width="40" height="40" style="object-fit: cover; border-radius: 4px;" class="kiss-margin-small-end" alt="">
                        <div class="kiss-flex-1 kiss-text-truncate">
                            <div class="kiss-text-bold kiss-size-small">{{ currentAsset.title }}</div>
                            <div class="kiss-size-xsmall kiss-color-muted">{{ currentAsset.width }}x{{ currentAsset.height }}px &bull; {{ currentAsset.widths.join(', ') }}px variants</div>
                        </div>
                        <div class="kiss-size-small kiss-color-muted">
                            <span class="kiss-color-success kiss-margin-small-end">+{{ createdCount }} created</span>
                            <span class="kiss-color-primary">{{ alreadyPresentCount }} existing</span>
                        </div>
                    </div>

                    <!-- Actions while running -->
                    <div class="kiss-flex kiss-flex-middle" style="gap: 8px;">
                        <button type="button" class="kiss-button kiss-button-small" @click="togglePause">
                            <icon class="kiss-margin-xsmall-end">{{ isPaused ? 'play_arrow' : 'pause' }}</icon>
                            {{ isPaused ? '<?= t('Resume') ?>' : '<?= t('Pause') ?>' }}
                        </button>
                        <button type="button" class="kiss-button kiss-button-small kiss-button-danger" @click="cancelRebuild">
                            <icon class="kiss-margin-xsmall-end">stop</icon><?= t('Cancel') ?>
                        </button>
                    </div>

                    <!-- Failure List if any -->
                    <div v-if="errors.length" class="kiss-margin-top kiss-color-danger kiss-size-small">
                        <strong><?= t('Failed items') ?>:</strong>
                        <ul class="kiss-margin-xsmall-top">
                            <li v-for="err in errors" :key="err">{{ err }}</li>
                        </ul>
                    </div>
                </div>
            </kiss-card>
        </template>

        <script type="module">
            export default {
                data() {
                    return {
                        enabled: <?= json_encode((bool) $enabled) ?>,
                        stats: <?= json_encode($stats) ?>,
                        isRefreshing: false,
                        isPaused: false,
                        isCancelled: false,
                        queue: [],
                        currentIndex: 0,
                        createdCount: 0,
                        alreadyPresentCount: 0,
                        failedCount: 0,
                        currentAsset: null,
                        errors: [],
                        elapsedSec: 0,
                        timerInterval: null,
                    }
                },

                computed: {
                    progressPercent() {
                        if (!this.queue.length) return 0;
                        return Math.min(100, Math.round((this.currentIndex / this.queue.length) * 100));
                    }
                },

                methods: {
                    async safeRequest(url, data = {}) {
                        const token = App.csrf || document.documentElement.getAttribute('data-csrf') || '';
                        const payload = Object.assign({ xcsrftoken: token }, data);

                        try {
                            return await this.$request(url, payload);
                        } catch (err) {
                            const is412 = (err && (err.status === 412 || err.error === 'Precondition Failed' || (typeof err === 'string' && err.includes('412'))));
                            if (is412) {
                                try {
                                    const session = await this.$request('/check-session');
                                    if (session && session.status && session.csrf) {
                                        App.csrf = session.csrf;
                                        payload.xcsrftoken = session.csrf;
                                        return await this.$request(url, payload);
                                    }
                                } catch (sessionErr) {}
                            }
                            throw err;
                        }
                    },

                    toggleOptimization() {
                        const target = !this.enabled;
                        App.ui.block('<?= t('Updating setting...') ?>');

                        this.safeRequest('/system/image-optimization/toggle', { enabled: target }).then(res => {
                            this.enabled = res.enabled;
                            App.ui.notify(res.message, res.enabled ? 'success' : 'info');
                        }).catch(err => {
                            App.ui.notify(err.error || 'Failed updating setting', 'error');
                        }).finally(() => {
                            App.ui.unblock();
                        });
                    },

                    clearCache() {
                        App.ui.confirm('<?= t('Are you sure you want to delete all cached WebP variants?') ?>', () => {
                            App.ui.block('<?= t('Clearing cache...') ?>');

                            this.safeRequest('/system/image-optimization/cleanCache').then(res => {
                                this.stats = res.stats;
                                App.ui.notify(`<?= t('Deleted') ?> ${res.deleted_files} <?= t('variant files') ?>.`, 'success');
                            }).catch(err => {
                                App.ui.notify(err.error || 'Failed clearing cache', 'error');
                            }).finally(() => {
                                App.ui.unblock();
                            });
                        });
                    },

                    async startRebuild(flushFirst = true) {
                        if (!this.enabled) {
                            App.ui.notify('<?= t('Please enable image optimization first.') ?>', 'warning');
                            return;
                        }

                        const confirmMsg = flushFirst
                            ? '<?= t('Clear all cached variants and rebuild from scratch?') ?>'
                            : '<?= t('Generate all missing WebP variants?') ?>';

                        App.ui.confirm(confirmMsg, async () => {
                            try {
                                App.ui.block('<?= t('Preparing queue...') ?>');

                                if (flushFirst) {
                                    const cleanRes = await this.safeRequest('/system/image-optimization/cleanCache');
                                    this.stats = cleanRes.stats;
                                }

                                const queueRes = await this.safeRequest('/system/image-optimization/getQueue');
                                this.queue = queueRes.queue || [];

                                if (!this.queue.length) {
                                    App.ui.notify('<?= t('No resizable images found.') ?>', 'info');
                                    App.ui.unblock();
                                    return;
                                }

                                this.currentIndex = 0;
                                this.createdCount = 0;
                                this.alreadyPresentCount = 0;
                                this.failedCount = 0;
                                this.errors = [];
                                this.isPaused = false;
                                this.isCancelled = false;
                                this.isRefreshing = true;
                                this.elapsedSec = 0;

                                clearInterval(this.timerInterval);
                                this.timerInterval = setInterval(() => {
                                    if (!this.isPaused) this.elapsedSec++;
                                }, 1000);

                                App.ui.unblock();
                                await this.processQueue();
                            } catch (e) {
                                App.ui.unblock();
                                App.ui.notify(e.error || e.message || 'Failed initiating rebuild', 'error');
                            }
                        });
                    },

                    async processQueue() {
                        const batchSize = 2;

                        while (this.currentIndex < this.queue.length && !this.isCancelled) {
                            if (this.isPaused) {
                                await new Promise(r => setTimeout(r, 300));
                                continue;
                            }

                            const batch = this.queue.slice(this.currentIndex, this.currentIndex + batchSize);
                            this.currentAsset = batch[0];

                            try {
                                const res = await this.safeRequest('/system/image-optimization/processBatch', {
                                    asset_ids: batch.map(a => a.id)
                                });

                                this.createdCount += (res.variants_created || res.created || 0);
                                this.alreadyPresentCount += (res.already_present || 0);
                                this.failedCount += (res.failed_count || res.failed || 0);

                                if (res.errors && res.errors.length) {
                                    this.errors.push(...res.errors);
                                }
                            } catch (err) {
                                this.errors.push(`Batch error: ${err.error || err.message}`);
                            }

                            this.currentIndex += batch.length;
                            await new Promise(r => setTimeout(r, 100));
                        }

                        this.finishRebuild();
                    },

                    togglePause() {
                        this.isPaused = !this.isPaused;
                    },

                    cancelRebuild() {
                        this.isCancelled = true;
                        this.finishRebuild();
                    },

                    async finishRebuild() {
                        clearInterval(this.timerInterval);
                        this.isRefreshing = false;
                        this.currentAsset = null;

                        try {
                            const res = await this.safeRequest('/system/image-optimization/stats');
                            if (res.stats) this.stats = res.stats;
                        } catch(e) {}

                        if (this.isCancelled) {
                            App.ui.notify('<?= t('Rebuild cancelled.') ?>', 'info');
                        } else {
                            App.ui.notify(`<?= t('Rebuild completed!') ?> ${this.createdCount} <?= t('variants generated.') ?>`, 'success');
                        }
                    }
                }
            }
        </script>
    </vue-view>

</kiss-container>
