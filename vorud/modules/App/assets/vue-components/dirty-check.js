export function useDirtyCheck(propName) {

    return {

        data() {
            return {
                savedItemState: null,
                settled: false
            }
        },

        created() {
            this.savedItemState = JSON.stringify(this[propName]);
        },

        computed: {
            isModified() {
                return this.settled && JSON.stringify(this[propName]) !== this.savedItemState;
            }
        },

        mounted() {

            let settleTimer = setTimeout(() => settle(), 500);

            const settle = () => {
                this.savedItemState = JSON.stringify(this[propName]);
                this.settled = true;
                if (this._settleWatcher) {
                    this._settleWatcher();
                    this._settleWatcher = null;
                }
            };

            this._settleWatcher = this.$watch(propName, () => {
                clearTimeout(settleTimer);
                settleTimer = setTimeout(() => settle(), 500);
            }, { deep: true });

            this._beforeUnloadHandler = (e) => {
                if (this.isModified) {
                    e.preventDefault();
                    e.returnValue = this.t('You have unsaved data! Are you sure you want to leave?');
                }
            };

            window.addEventListener('beforeunload', this._beforeUnloadHandler);
        },

        beforeUnmount() {
            window.removeEventListener('beforeunload', this._beforeUnloadHandler);
        },

        methods: {
            resetDirtyState() {
                this.$nextTick(() => {
                    this.savedItemState = JSON.stringify(this[propName]);
                });
            }
        }
    };
}
