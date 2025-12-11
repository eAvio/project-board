<template>
    <div class="flex flex-wrap gap-6 mb-6 px-1" v-if="(card.assignees && card.assignees.length) || (card.labels && card.labels.length) || card.due_date">
        <div v-if="card.assignees && card.assignees.length">
            <h3 class="text-xs font-bold text-gray-500 uppercase mb-2">Members</h3>
            <div class="flex flex-wrap gap-2">
                <div v-for="user in card.assignees" :key="user.id" class="relative group">
                    <img 
                            :src="user.avatar_url || `https://ui-avatars.com/api/?name=${user.name}`" 
                            :title="user.name"
                            class="w-8 h-8 rounded-full cursor-pointer hover:opacity-80"
                    />
                </div>
                <div class="relative">
                    <button @click.stop="toggleMembersPopup" class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            <Icon name="plus" class="w-4 h-4" />
                    </button>
                    <!-- Inline Members Popup -->
                    <div 
                            v-if="showMembersPopup" 
                            v-click-outside="closePopups"
                            class="popup-container absolute left-0 top-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 overflow-hidden"
                            style="width: 18rem;"
                            @click.stop
                    >
                            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                                <div></div>
                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">Members</span>
                                <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <Icon name="x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="p-3 max-h-64 overflow-y-auto">
                                <div 
                                    v-for="user in users" 
                                    :key="user.id"
                                    @click="toggleMember(user)"
                                    class="flex items-center px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded"
                                >
                                    <img :src="user.avatar_url" class="w-6 h-6 rounded-full mr-2" />
                                    <span class="text-sm text-gray-700 dark:text-gray-200 flex-1 truncate">{{ user.name }}</span>
                                    <Icon v-if="isMemberSelected(user)" name="check" class="w-4 h-4 text-green-500" />
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div v-if="card.labels && card.labels.length">
            <h3 class="text-xs font-bold text-gray-500 uppercase mb-2">Labels</h3>
            <div class="flex flex-wrap gap-2">
                <div 
                    v-for="label in card.labels" 
                    :key="label.id"
                    class="h-8 px-3 rounded font-bold text-white cursor-pointer hover:opacity-80 transition-opacity"
                    :style="{ 
                        backgroundColor: label.color || '#3b82f6',
                        minWidth: '60px',
                        maxWidth: '200px',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        lineHeight: '2rem'
                    }"
                    :title="label.name"
                    @click="toggleLabelsPopup"
                >{{ label.name }}</div>
                <div class="relative">
                    <button @click.stop="toggleLabelsPopup" class="w-8 h-8 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            <Icon name="plus" class="w-4 h-4" />
                    </button>
                    <!-- Inline Labels Popup -->
                    <div 
                            v-if="showLabelsPopup" 
                            v-click-outside="closePopups"
                            class="popup-container absolute left-0 top-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 flex flex-col overflow-hidden"
                            style="width: 18rem;"
                            @click.stop
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <button v-if="isCreatingLabel" @click="isCreatingLabel = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="chevron-left" class="w-4 h-4" />
                            </button>
                            <div v-else></div>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">{{ isCreatingLabel ? 'Create Label' : 'Labels' }}</span>
                            <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="x-mark" class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Labels List -->
                        <div v-if="!isCreatingLabel" class="p-3">
                            <input 
                                type="text" 
                                v-model="labelSearch"
                                placeholder="Search labels..." 
                                class="w-full form-control form-input form-control-bordered text-sm mb-3 h-9"
                            />
                            
                            <div class="space-y-1 max-h-64 overflow-y-auto mb-3">
                                <div 
                                    v-for="label in filteredLabels" 
                                    :key="label.id"
                                    class="flex items-center group"
                                >
                                    <div 
                                        @click="toggleLabel(label)"
                                        class="flex-1 h-8 rounded px-3 flex items-center gap-2 cursor-pointer hover:opacity-80 transition-all relative min-w-0"
                                        :style="{ backgroundColor: label.color || '#e5e7eb' }"
                                        :title="label.name"
                                    >
                                        <span 
                                            class="font-bold text-white text-sm drop-shadow-sm relative z-10 flex-1"
                                            style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        >{{ label.name }}</span>
                                        <Icon v-if="isLabelSelected(label)" name="check" class="w-4 h-4 text-white drop-shadow-sm relative z-10 flex-shrink-0" />
                                    </div>
                                    <button class="ml-1.5 p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded flex-shrink-0" @click="notImplemented('Edit label')">
                                        <Icon name="pencil" class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                            
                            <button 
                                @click="isCreatingLabel = true" 
                                class="w-full py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 text-sm font-medium rounded transition-colors"
                            >
                                Create a new label
                            </button>
                        </div>

                        <!-- Create Label Form -->
                        <div v-else class="p-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                            <input 
                                v-model="newLabelName" 
                                class="w-full form-control form-input form-control-bordered text-sm mb-3"
                                @keydown.enter="createLabel"
                            />
                            
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select a color</label>
                            <div class="grid grid-cols-5 gap-2 mb-4" style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));">
                                <div 
                                    v-for="color in trelloColors" 
                                    :key="color"
                                    @click="newLabelColor = color"
                                    class="h-8 rounded cursor-pointer transition-all duration-200 flex items-center justify-center transform hover:scale-110 hover:shadow-md"
                                    :class="{ 'ring-2 ring-offset-2 ring-gray-300 dark:ring-gray-600 scale-110 shadow-sm': newLabelColor === color }"
                                    :style="{ backgroundColor: color }"
                                >
                                    <Icon v-if="newLabelColor === color" name="check" class="w-4 h-4 text-white drop-shadow-md" />
                                </div>
                                <div 
                                    @click="newLabelColor = null"
                                    class="h-8 rounded bg-gray-200 dark:bg-gray-600 cursor-pointer flex items-center justify-center hover:bg-gray-300 dark:hover:bg-gray-500"
                                    :class="{ 'ring-2 ring-offset-2 ring-gray-300 dark:ring-gray-600': !newLabelColor }"
                                >
                                    <Icon v-if="!newLabelColor" name="check" class="w-4 h-4 text-gray-500" />
                                </div>
                            </div>
                            
                            <div v-if="!newLabelColor" class="mb-4 text-xs text-gray-500">
                                No color. This won't show up on the front of cards.
                            </div>

                            <div class="flex items-center justify-between">
                                <button @click="createLabel" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded font-bold text-sm transition-colors" :disabled="!newLabelName">Create</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="card.due_date">
            <h3 class="text-xs font-bold text-gray-500 uppercase mb-2">Due Date</h3>
            <div class="flex items-center">
                <div 
                    class="flex items-center px-2 py-1.5 rounded text-sm font-medium cursor-pointer transition-colors"
                    :class="dueDateClasses"
                    @click="toggleComplete"
                >
                    <div 
                        class="w-4 h-4 border-2 rounded mr-2 flex items-center justify-center transition-colors"
                        :class="card.completed_at ? 'border-white bg-transparent' : 'border-gray-400 dark:border-gray-500 bg-white dark:bg-gray-800'"
                    >
                        <Icon v-if="card.completed_at" name="check" class="w-3 h-3 text-white" />
                    </div>
                    <span>{{ formattedDueDate }}</span>
                    <span v-if="dueDateStatusText" class="ml-2 px-1.5 py-0.5 text-[10px] uppercase rounded bg-black/10 dark:bg-white/20">
                        {{ dueDateStatusText }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Icon from '../UI/Icon.vue'
import { format, isPast, isToday, parseISO } from 'date-fns'

export default {
    components: { Icon },
    props: {
        card: Object,
        users: Array,
        availableLabels: Array,
    },
    emits: ['update'],
    data() {
        return {
            showMembersPopup: false,
            showLabelsPopup: false,
            isCreatingLabel: false,
            newLabelName: '',
            newLabelColor: '#3b82f6',
            labelSearch: '',
            trelloColors: [
                '#61bd4f', '#f2d600', '#ff9f1a', '#eb5a46', '#c377e0', '#0079bf', '#00c2e0', '#51e898', '#ff78cb', '#344563',
            ]
        }
    },
    computed: {
        filteredLabels() {
            if (!this.labelSearch) return this.availableLabels;
            return this.availableLabels.filter(l => l.name.toLowerCase().includes(this.labelSearch.toLowerCase()));
        },
        formattedDueDate() {
            if (!this.card.due_date) return '';
            const date = parseISO(this.card.due_date);
            return format(date, 'MMM d'); // e.g. Nov 24
        },
        isOverdue() {
            if (!this.card.due_date || this.card.completed_at) return false;
            const date = parseISO(this.card.due_date);
            return isPast(date) && !isToday(date);
        },
        dueDateStatusText() {
            if (this.card.completed_at) return 'Complete';
            if (this.isOverdue) return 'Overdue';
            // Could add 'Due soon' logic here
            return '';
        },
        dueDateClasses() {
            if (this.card.completed_at) {
                return 'bg-green-500 text-white hover:bg-green-600';
            }
            if (this.isOverdue) {
                return 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50';
            }
            return 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600';
        }
    },
    directives: {
        'click-outside': {
            mounted(el, binding) {
                el.clickOutsideEvent = function(event) {
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
        async toggleComplete() {
            const newStatus = this.card.completed_at ? null : new Date().toISOString();
            // Optimistic
            this.card.completed_at = newStatus;

            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}`, {
                    completed_at: newStatus
                });
                this.$emit('update');
                if (newStatus) {
                    Nova.success('Card marked as complete');
                } else {
                    Nova.success('Card marked as incomplete');
                }
            } catch (e) {
                Nova.error('Failed to update status');
                // Revert
                this.card.completed_at = !newStatus; 
            }
        },
        notImplemented(feature) {
            Nova.warning(`${feature} is not implemented yet.`);
        },
        closePopups() {
            this.showMembersPopup = false;
            this.showLabelsPopup = false;
            this.isCreatingLabel = false;
            this.labelSearch = '';
        },
        toggleMembersPopup() {
            this.showMembersPopup = !this.showMembersPopup;
            if (this.showMembersPopup) {
                this.showLabelsPopup = false;
            }
        },
        toggleLabelsPopup() {
            this.showLabelsPopup = !this.showLabelsPopup;
            if (this.showLabelsPopup) {
                this.showMembersPopup = false;
            }
        },
        isMemberSelected(user) {
            return this.card.assignees?.some(u => u.id === user.id);
        },
        async toggleMember(user) {
            let assignees = this.card.assignees ? [...this.card.assignees] : [];
            const index = assignees.findIndex(u => u.id === user.id);
            
            if (index >= 0) {
                assignees.splice(index, 1);
            } else {
                assignees.push(user);
            }

            this.card.assignees = assignees;
            
            try {
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/assignees`, {
                    users: assignees.map(u => u.id)
                });
                this.$emit('update');
                Nova.success('Members updated');
            } catch (e) {
                Nova.error('Failed to update members');
            }
        },
        isLabelSelected(label) {
            return this.card.labels?.some(l => l.id === label.id);
        },
        async toggleLabel(label) {
            let labels = this.card.labels ? [...this.card.labels] : [];
            const index = labels.findIndex(l => l.id === label.id);
            
            if (index >= 0) {
                labels.splice(index, 1);
            } else {
                labels.push(label);
            }
            
            this.card.labels = labels;

            try {
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/labels`, {
                    labels: labels.map(l => l.id)
                });
                this.$emit('update');
                Nova.success('Labels updated');
            } catch (e) {
                Nova.error('Failed to update labels');
            }
        },
        async createLabel() {
            if (!this.newLabelName) return;
            try {
                const { data } = await Nova.request().post('/nova-vendor/project-board/labels', {
                    name: this.newLabelName,
                    color: this.newLabelColor
                });
                this.$emit('update');
                Nova.success('Label created');
                this.newLabelName = '';
                this.isCreatingLabel = false;
                this.toggleLabel(data);
            } catch (e) {
                Nova.error('Failed to create label');
            }
        },
    }
}
</script>
