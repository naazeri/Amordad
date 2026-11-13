
const JsonNode = {
    name: 'json-node',
    props: {
        data: [Object, Array, String, Number, Boolean],
        name: [String, Number],
        isLast: Boolean,
        depth: { type: Number, default: 0 }
    },
    data() {
        return {
            expanded: this.depth < 2
        }
    },
    computed: {
        type() {
            if (this.data === null) return 'null';
            return Array.isArray(this.data) ? 'array' : typeof this.data;
        },
        displayValue() {
            if (this.type === 'string') return `"${this.data}"`;
            if (this.type === 'null') return 'null';
            return this.data;
        },
        isPrimitive() {
            return this.type !== 'object' && this.type !== 'array';
        },
        hasChildren() {
            return !this.isPrimitive && Object.keys(this.data || {}).length > 0;
        },
        keys() {
            return this.isPrimitive ? [] : Object.keys(this.data || {});
        }
    },
    methods: {
        toggle() {
            this.expanded = !this.expanded;
        }
    },
    template: /*html*/`
        <div class="json-node" :class="'depth-'+depth" style="line-height:1.5em; font-family: monospace;">
            
            <!-- Primitive Value -->
            <div v-if="isPrimitive">
                <span class="kiss-color-muted" v-if="name !== undefined">
                    {{ name }}: 
                </span>
                <span :class="{'kiss-color-primary': type==='string', 'kiss-color-danger': type==='boolean', 'kiss-color-success': type==='number', 'kiss-color-muted': type==='null'}">{{ displayValue }}</span><span v-if="!isLast">,</span>
            </div>

            <!-- Object / Array -->
            <div v-else>
                <div class="kiss-flex kiss-flex-middle">
                    <a class="kiss-margin-small-right kiss-color-muted" @click="toggle" v-if="hasChildren" style="cursor: pointer; user-select:none;">
                        <icon>{{ expanded ? 'arrow_drop_down' : 'arrow_right' }}</icon>
                    </a>
                    <span class="kiss-margin-small-right kiss-color-muted" v-else>&nbsp;&nbsp;&nbsp;</span>

                    <span class="kiss-color-muted" v-if="name !== undefined">
                        {{ name }}: 
                    </span>
                    
                    <span class="kiss-color-muted" @click="toggle" style="cursor: pointer;">
                        {{ type === 'array' ? '[' : '{' }}
                        
                        <span v-if="!expanded && hasChildren" class="kiss-size-small kiss-color-muted">...</span>
                        
                        <span v-if="!hasChildren || !expanded">
                            {{ type === 'array' ? ']' : '}' }}<span v-if="!isLast">,</span>
                            <span class="kiss-size-xsmall kiss-color-muted kiss-margin-small-left" v-if="hasChildren && !expanded">
                                {{ keys.length }} {{ keys.length === 1 ? 'item' : 'items' }}
                            </span>
                        </span>
                    </span>
                </div>

                <div v-if="expanded && hasChildren" class="kiss-margin-left">
                    <json-node 
                        v-for="(key, index) in keys" 
                        :key="key" 
                        :name="type === 'array' ? undefined : key" 
                        :data="data[key]"
                        :is-last="index === keys.length - 1"
                        :depth="depth + 1">
                    </json-node>
                </div>
                
                <div v-if="expanded && hasChildren" class="kiss-color-muted">
                    {{ type === 'array' ? ']' : '}' }}<span v-if="!isLast">,</span>
                </div>

            </div>
        </div>
    `
};

export default {

    components: {
        JsonNode
    },

    _meta: {flip: true, size: 'large'},

    data() {
        return {
            json: null,
            filter: ''
        }
    },

    props: {
        data: {
            type: Object,
            default: () => ({})
        },
        caption: {
            type: String
        }
    },

    computed: {
        searchResults() {
            
            if (!this.filter) return [];

            let results = [];
            let term = this.filter.toLowerCase();

            const traverse = (data, path) => {
                
                if (data && typeof data === 'object') {
                    Object.keys(data).forEach(key => {
                        traverse(data[key], path ? `${path}.${key}` : key);
                    });
                    return;
                }

                let val = String(data);
                
                if (path.toLowerCase().includes(term) || val.toLowerCase().includes(term)) {
                    results.push({path, value: val});
                }
            };

            traverse(this.data, '');

            return results;
        }
    },

    template: /*html*/`

        <div class="app-offcanvas-container">
            <div class="kiss-padding kiss-text-bold kiss-flex kiss-flex-middle">
                <span class="kiss-flex-1">{{ caption || t('JSON Viewer') }}</span>
            </div>
            <div class="kiss-padding kiss-bgcolor-contrast">
                <input type="text" class="kiss-input kiss-input-small" :placeholder="t('Search...')" v-model="filter">
            </div>
            <div class="app-offcanvas-content kiss-padding kiss-bgcolor-contrast kiss-flex-1 kiss-size-small">
                
                <div v-if="filter">
                    
                    <div v-if="!searchResults.length" class="kiss-color-muted kiss-align-center kiss-padding-large">
                        {{ t('No results found') }}
                    </div>

                    <div v-else class="kiss-text-monospace">
                        <div v-for="result in searchResults" class="kiss-margin-small-bottom">
                            <span class="kiss-text-bold" v-html="highlight(result.path)"></span>: 
                            <span class="kiss-color-muted" v-html="highlight(result.value)"></span>
                        </div>
                    </div>
                </div>

                <json-node v-else :data="data" :is-last="true"></json-node>

            </div>
            <div class="kiss-padding kiss-bgcolor-contrast">
                <div class="kiss-button-group kiss-flex kiss-child-width-1-2">
                    <button class="kiss-button" kiss-offcanvas-close>{{ t('Close') }}</button>
                    <button class="kiss-button kiss-button-primary" @click="copy()">{{ t('Copy') }}</button>
                </div>
            </div>
        </div>
    `,

    methods: {

        copy() {
            App.utils.copyText(JSON.stringify(this.data, undefined, 2), () =>  App.ui.notify('JSON copied!'));
        },

        highlight(text) {
            text = String(text);
            if (!this.filter) return this.escape(text);

            const regex = new RegExp(`(${this.filter.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');

            return text.split(regex).map((part, i) => {
                if (i % 2 === 1) {
                    return `<mark class="kiss-color-warning kiss-text-bold">${this.escape(part)}</mark>`;
                }
                return this.escape(part);
            }).join('');
        },

        escape(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }
}
