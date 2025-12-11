<template>
    <div v-if="show" class="absolute z-50 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 overflow-hidden" :style="positionStyle" v-click-outside="close">
        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
            <div></div>
            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">{{ title }}</span>
            <button @click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <Icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
        <div class="p-3 space-y-4">
            <div v-if="mode === 'copy'">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Title</label>
                <input 
                    type="text"
                    v-model="copyTitle" 
                    class="w-full form-control form-input form-control-bordered text-sm mb-3"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Destination</label>
                
                <!-- Board Select -->
                <div class="mb-2">
                    <label class="text-xs text-gray-500 mb-1 block">Board</label>
                    <select v-model="selectedBoardId" class="w-full form-control form-select form-control-bordered text-sm">
                        <option v-for="b in boards" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="text-xs text-gray-500 mb-1 block">Column</label>
                    <select v-model="selectedColumnId" class="w-full form-control form-select form-control-bordered text-sm">
                        <option v-for="col in columns" :key="col.id" :value="col.id">
                            {{ col.name }} {{ col.id === currentColumnId ? '(Current)' : '' }}
                        </option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="text-xs text-gray-500 mb-1 block">Position</label>
                    <select v-model="selectedPosition" class="w-full form-control form-select form-control-bordered text-sm">
                        <option v-for="(pos, index) in availablePositions" :key="index" :value="pos.value">
                            {{ pos.label }}
                        </option>
                    </select>
                </div>
            </div>

            <button 
                @click="submit" 
                class="w-full bg-primary-500 hover:bg-primary-400 text-white font-bold text-sm py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading"
            >
                {{ mode === 'copy' ? 'Create Card' : 'Move' }}
            </button>
        </div>
    </div>
</template>

<script>
import Icon from '../UI/Icon.vue'

export default {
    components: { Icon },
    props: {
        show: Boolean,
        mode: {
            type: String,
            default: 'move' // 'move' or 'copy'
        },
        card: Object,
        boards: {
            type: Array,
            default: () => []
        },
        currentBoardId: {
            type: [Number, String],
            default: null
        },
        triggerRect: {
            type: Object,
            default: null,
        },
    },
    emits: ['close', 'move', 'copy'],
    data() {
        return {
            copyTitle: '',
            selectedBoardId: null,
            selectedColumnId: null,
            selectedPosition: 'bottom',
            loading: false,
        }
    },
    computed: {
        currentColumnId() {
            return this.card?.column?.id || this.card?.board_column_id;
        },
        currentBoard() {
            const id = this.selectedBoardId || this.currentBoardId;
            return this.boards.find(b => b.id === id) || this.boards[0] || null;
        },
        columns() {
            return this.currentBoard && this.currentBoard.columns ? this.currentBoard.columns : [];
        },
        availablePositions() {
            // Simplified position logic: Top or Bottom, or specifically index if we had the full list of cards per column here.
            // Since we only have list of columns, we'll just offer Top/Bottom + existing position if same column.
            // For simplicity: Top (1) and Bottom (max)
            return [
                { label: 'Bottom (Default)', value: 'bottom' },
                { label: 'Top', value: 'top' },
            ];
        },
        positionStyle() {
            // Fixed popup width ~18rem (~288px) to match other side panel popups.
            // Vertically align near the triggering button inside the same sidebar/container.
            let top = 0;
            let right = 0;

            try {
                if (this.triggerRect && this.$el && this.$el.parentElement) {
                    const parentRect = this.$el.parentElement.getBoundingClientRect();
                    // Position just below the trigger button within the parent container
                    top = this.triggerRect.bottom - parentRect.top + 4; // +4px spacing
                    right = 0;
                }
            } catch (e) {
                // Fallback: keep defaults
            }

            return {
                width: '18rem',
                top: `${top}px`,
                right: `${right}px`,
            };
        },
        title() {
            return this.mode === 'copy' ? 'Copy Card' : 'Move Card';
        }
    },
    watch: {
        show(val) {
            if (val) {
                this.copyTitle = this.card.title;
                // Default to current board if known
                this.selectedBoardId = this.currentBoardId || (this.card.column && this.card.column.board && this.card.column.board.id) || (this.boards[0] && this.boards[0].id) || null;
                this.selectedColumnId = this.currentColumnId;
                this.selectedPosition = 'bottom';
            }
        }
    },
    directives: {
        'click-outside': {
            mounted(el, binding) {
                el.clickOutsideEvent = function(event) {
                    // Check if click was on the trigger button (which is outside this component)
                    // We assume the parent handles strict clicking, but good to have
                     if (!(el === event.target || el.contains(event.target))) {
                        if (typeof binding.value === 'function') {
                            binding.value(event, el);
                        }
                    }
                };
                document.body.addEventListener('click', el.clickOutsideEvent);
            },
            unmounted(el) {
                document.body.removeEventListener('click', el.clickOutsideEvent);
            },
        },
    },
    methods: {
        close() {
            this.$emit('close');
        },
        submit() {
            this.loading = true;
            const payload = {
                board_column_id: this.selectedColumnId,
                position: this.selectedPosition,
                title: this.copyTitle
            };
            
            if (this.mode === 'copy') {
                this.$emit('copy', payload);
            } else {
                this.$emit('move', payload);
            }
            this.loading = false;
        }
    }
}
</script>
