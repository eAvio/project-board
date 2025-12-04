<template>
<Modal
        :show="show"
        @close="$emit('close')"
        role="dialog"
        class="card-detail-modal !z-[100]"
        size="7xl"
    > 
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-visible w-full max-w-5xl mx-auto">

            <!-- Modal content  max-w-[1500px] -->
            <div 
                v-if="card" 
                class="flex flex-col relative min-h-[600px]"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleGlobalDrop"
            >
                <!-- Drag Overlay -->
                <div v-if="isDragging" class="absolute inset-0 bg-primary-500/10 z-50 border-2 border-primary-500 border-dashed rounded-lg flex items-center justify-center pointer-events-none">
                    <div class="bg-white dark:bg-gray-800 p-4 rounded shadow-lg flex items-center space-x-2">
                        <Icon name="cloud-arrow-up" class="w-6 h-6 text-primary-500" />
                        <span class="font-bold text-gray-700 dark:text-gray-200">Drop files to upload</span>
                    </div>
                </div>

                <!-- Dummy focusable element to prevent focus-trap error -->
                <button type="button" class="opacity-0 absolute top-0 left-0 w-0 h-0 overflow-hidden"></button>

                <!-- Cover Image (Modal) -->
                <div v-if="editingCardImage" class="w-full relative bg-gray-100 dark:bg-gray-700" style="height: 200px;">
                    <img :src="editingCardImage" class="w-full h-full block" style="object-fit: cover;" alt="Cover Image" />
                </div>

                <ModalHeader class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-3">
                    <div class="flex items-center space-x-2 flex-1">
                        <Icon name="credit-card" class="w-6 h-6 text-gray-500 flex-shrink-0" />
                        <h2 
                            v-if="!isEditingTitle" 
                            @click="startEditingTitle"
                            class="text-lg font-bold text-gray-900 dark:text-gray-100 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 px-2 py-1 rounded w-full"
                        >
                            {{ localCard.title }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">- In <span class="font-bold">{{ column.name }}</span></span>

                        </h2>
                        <input 
                            v-else
                            ref="cardTitleInput"
                            v-model="localCard.title" 
                            @blur="updateCardTitle"
                            @keydown.enter="updateCardTitle"
                            class="form-control form-input form-control-bordered text-lg font-bold text-gray-900 dark:text-gray-100 w-full"
                        />
                    </div>
                    <button @click="$emit('close')" class="ml-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                        <Icon name="x-mark" class="w-6 h-6" />
                    </button>
                </ModalHeader>

                <div class="p-8 flex-1 flex flex-col md:flex-row gap-16">
                    <!-- Left Column: Main Content -->
                    <div class="md:w-3/4 space-y-10 mr-4">
                        <!-- Title Edit (if needed to rename inside modal) -->
                    <!--    <div>
                            <div class="flex items-center space-x-2 mb-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400">in list <span class="font-bold text-gray-800 dark:text-gray-200">{{ column.name }}</span></span>
                            </div>
                        </div>
                        -->

                        <!-- Estimates Summary (Click to Edit) -->
                        <div v-if="hasTimeOrCost" class="flex items-center justify-between text-sm mb-4 p-3 rounded-lg" :class="estimatesBannerClass">
                            <div class="flex items-center space-x-4">
                                <!-- Hours -->
                                <div class="flex items-center" v-if="localCard.estimated_hours > 0 || localCard.actual_hours > 0">
                                    <template v-if="editingField === 'actualHours'">
                                        <input 
                                            ref="inlineActualHoursInput"
                                            type="number" 
                                            v-model="inlineActualHours" 
                                            @blur="saveActualHours" 
                                            @keydown.enter="saveActualHours"
                                            step="0.5" 
                                            min="0" 
                                            class="w-10 text-center bg-transparent border-b border-gray-400 focus:border-primary-500 outline-none font-bold"
                                        />
                                    </template>
                                    <template v-else>
                                        <span :class="hoursActualClass" class="cursor-pointer hover:underline" @click="startEdit('actualHours')">{{ formatNum(localCard.actual_hours) }}</span>
                                    </template><span class="mx-1">/</span><template v-if="editingField === 'estHours'">
                                        <input 
                                            ref="inlineEstHoursInput"
                                            type="number" 
                                            v-model="inlineEstHours" 
                                            @blur="saveEstHours" 
                                            @keydown.enter="saveEstHours"
                                            step="0.5" 
                                            min="0" 
                                            class="w-10 text-center bg-transparent border-b border-gray-400 focus:border-primary-500 outline-none"
                                        />
                                    </template>
                                    <template v-else>
                                        <span class="cursor-pointer hover:underline" @click="startEdit('estHours')">{{ formatNum(localCard.estimated_hours) }}</span>
                                    </template>&nbsp;h
                                </div>
                                <!-- Cost -->
                                <div class="flex items-center" v-if="localCard.estimated_cost > 0 || localCard.actual_cost > 0">
                                    <template v-if="editingField === 'actualCost'">
                                        <input 
                                            ref="inlineActualCostInput"
                                            type="number" 
                                            v-model="inlineActualCost" 
                                            @blur="saveActualCost" 
                                            @keydown.enter="saveActualCost"
                                            step="10" 
                                            min="0" 
                                            class="w-14 text-center bg-transparent border-b border-gray-400 focus:border-primary-500 outline-none font-bold"
                                        />
                                    </template>
                                    <template v-else>
                                        <span :class="costActualClass" class="cursor-pointer hover:underline" @click="startEdit('actualCost')">{{ formatNum(localCard.actual_cost) }}</span>
                                    </template><span class="mx-1">/</span><template v-if="editingField === 'estCost'">
                                        <input 
                                            ref="inlineEstCostInput"
                                            type="number" 
                                            v-model="inlineEstCost" 
                                            @blur="saveEstCost" 
                                            @keydown.enter="saveEstCost"
                                            step="10" 
                                            min="0" 
                                            class="w-14 text-center bg-transparent border-b border-gray-400 focus:border-primary-500 outline-none"
                                        />
                                    </template>
                                    <template v-else>
                                        <span class="cursor-pointer hover:underline" @click="startEdit('estCost')">{{ formatNum(localCard.estimated_cost) }}</span>
                                    </template>&nbsp;€
                                </div>
                            </div>
                            <!-- Clear estimates button -->
                            <button @click="clearAllEstimates" class="text-gray-400 hover:text-red-500 ml-2" title="Clear all estimates">
                                <Icon name="x-mark" type="micro" />
                            </button>
                        </div>

                        <!-- Members & Labels (Header Meta) -->
                        <CardHeaderMeta 
                            :card="localCard" 
                            :users="users" 
                            :available-labels="availableLabels"
                            @update="handleCardUpdate"
                        />
                        
                        <!-- Description -->
                        <CardDescription 
                            :card="localCard"
                            @update="handleCardUpdate"
                        />

                        <!-- Checklists -->
                        <CardChecklist 
                            ref="cardChecklist"
                            :card="localCard"
                            @update="handleCardUpdate"
                        />

                        <!-- Attachments -->
                        <CardAttachments 
                            :card="localCard"
                            @update="handleCardUpdate"
                        />

                        <!-- Activity / Comments -->
                        <CardActivity 
                            :card="localCard" 
                            :current-user="currentUser"
                            :users="users"
                            @update="handleCardUpdate"
                        />
                    </div> <!-- End of Left Column -->

                    <!-- Right Column: Sidebar -->
                    <CardSidebar 
                        :card="localCard"
                        :users="users"
                        :available-labels="availableLabels"
                        :all-columns="allColumns"
                        :boards="boards"
                        :current-board-id="currentBoardId"
                        @update="handleCardUpdate"
                        @delete-card="$emit('delete-card')"
                        @add-checklist="addChecklistFromSidebar"
                    />
                </div>
            </div>
        </div>
    </Modal>
</template>

<script>
import { Icon } from 'laravel-nova-ui'
import CardHeaderMeta from './card-detail/CardHeaderMeta.vue'
import CardDescription from './card-detail/CardDescription.vue'
import CardChecklist from './card-detail/CardChecklist.vue'
import CardAttachments from './card-detail/CardAttachments.vue'
import CardActivity from './card-detail/CardActivity.vue'
import CardSidebar from './card-detail/CardSidebar.vue'

export default {
    components: { 
        Icon,
        CardHeaderMeta,
        CardDescription,
        CardChecklist,
        CardAttachments,
        CardActivity,
        CardSidebar
    },
    props: {
        show: Boolean,
        card: Object,
        column: Object,
        allColumns: Array,
        currentUser: Object,
        users: Array,
        availableLabels: Array,
        boards: {
            type: Array,
            default: () => []
        },
        currentBoardId: {
            type: [Number, String],
            default: null
        },
    },
    emits: ['close', 'update', 'delete-card'],
    data() {
        return {
            localCard: null,
            isEditingTitle: false,
            isDragging: false,
            inlineActualHours: 0,
            inlineActualCost: 0,
            inlineEstHours: 0,
            inlineEstCost: 0,
            editingField: null,
        }
    },
    computed: {
        editingCardImage() {
            if (!this.localCard || !this.localCard.media) return null;
            // Only show image from 'featured_image' collection as the cover
            const coverImage = this.localCard.media.find(m => m.collection_name === 'featured_image');
            return coverImage ? this.getMediaUrl(coverImage) : null;
        },
        hasTimeOrCost() {
            if (!this.localCard) return false;
            return (this.localCard.estimated_hours > 0 || this.localCard.actual_hours > 0 || this.localCard.estimated_cost > 0 || this.localCard.actual_cost > 0);
        },
        hoursActualClass() {
            if (!this.localCard) return 'font-bold';
            const actual = parseFloat(this.localCard.actual_hours) || 0;
            const estimated = parseFloat(this.localCard.estimated_hours) || 0;
            if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
            return 'font-bold';
        },
        costActualClass() {
            if (!this.localCard) return 'font-bold';
            const actual = parseFloat(this.localCard.actual_cost) || 0;
            const estimated = parseFloat(this.localCard.estimated_cost) || 0;
            if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
            return 'font-bold';
        },
        isOverBudget() {
            if (!this.localCard) return false;
            const actualHours = parseFloat(this.localCard.actual_hours) || 0;
            const estHours = parseFloat(this.localCard.estimated_hours) || 0;
            const actualCost = parseFloat(this.localCard.actual_cost) || 0;
            const estCost = parseFloat(this.localCard.estimated_cost) || 0;
            return (estHours > 0 && actualHours > estHours) || (estCost > 0 && actualCost > estCost);
        },
        estimatesBannerClass() {
            if (this.isOverBudget) {
                return 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400';
            }
            return 'bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400';
        },
    },
    watch: {
        localCard: {
            immediate: true,
            handler(card) {
                if (card) {
                    this.inlineActualHours = card.actual_hours || 0;
                    this.inlineActualCost = card.actual_cost || 0;
                    this.inlineEstHours = card.estimated_hours || 0;
                    this.inlineEstCost = card.estimated_cost || 0;
                }
            }
        },
        card: {
            immediate: true,
            handler(newCard) {
                if (newCard) {
                    this.localCard = JSON.parse(JSON.stringify(newCard));
                }
            }
        }
    },
    mounted() {
        document.addEventListener('keydown', this.handleKeydown);
    },
    unmounted() {
        document.removeEventListener('keydown', this.handleKeydown);
    },
    methods: {
        formatNum(val) {
            const num = parseFloat(val) || 0;
            return num % 1 === 0 ? num.toFixed(0) : num.toFixed(1);
        },
        startEdit(field) {
            this.editingField = field;
            this.$nextTick(() => {
                const refName = `inline${field.charAt(0).toUpperCase() + field.slice(1)}Input`;
                if (this.$refs[refName]) {
                    this.$refs[refName].focus();
                    this.$refs[refName].select();
                }
            });
        },
        async saveActualHours() {
            this.editingField = null;
            const newVal = parseFloat(this.inlineActualHours) || 0;
            const oldVal = parseFloat(this.localCard.actual_hours) || 0;
            if (newVal === oldVal) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.localCard.id}`, { actual_hours: newVal || null });
                this.localCard.actual_hours = newVal;
                this.$emit('update');
            } catch (e) { Nova.error('Failed to save'); }
        },
        async saveActualCost() {
            this.editingField = null;
            const newVal = parseFloat(this.inlineActualCost) || 0;
            const oldVal = parseFloat(this.localCard.actual_cost) || 0;
            if (newVal === oldVal) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.localCard.id}`, { actual_cost: newVal || null });
                this.localCard.actual_cost = newVal;
                this.$emit('update');
            } catch (e) { Nova.error('Failed to save'); }
        },
        async saveEstHours() {
            this.editingField = null;
            const newVal = parseFloat(this.inlineEstHours) || 0;
            const oldVal = parseFloat(this.localCard.estimated_hours) || 0;
            if (newVal === oldVal) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.localCard.id}`, { estimated_hours: newVal || null });
                this.localCard.estimated_hours = newVal;
                this.$emit('update');
            } catch (e) { Nova.error('Failed to save'); }
        },
        async saveEstCost() {
            this.editingField = null;
            const newVal = parseFloat(this.inlineEstCost) || 0;
            const oldVal = parseFloat(this.localCard.estimated_cost) || 0;
            if (newVal === oldVal) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.localCard.id}`, { estimated_cost: newVal || null });
                this.localCard.estimated_cost = newVal;
                this.$emit('update');
            } catch (e) { Nova.error('Failed to save'); }
        },
        async clearAllEstimates() {
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.localCard.id}`, {
                    estimated_hours: null,
                    estimated_cost: null,
                    actual_hours: null,
                    actual_cost: null,
                });
                this.localCard.estimated_hours = 0;
                this.localCard.estimated_cost = 0;
                this.localCard.actual_hours = 0;
                this.localCard.actual_cost = 0;
                this.inlineEstHours = 0;
                this.inlineEstCost = 0;
                this.inlineActualHours = 0;
                this.inlineActualCost = 0;
                this.$emit('update');
                Nova.success('Estimates cleared');
            } catch (e) { Nova.error('Failed to clear estimates'); }
        },
        handleKeydown(e) {
            if (e.key === 'Escape') {
                this.$emit('close');
            }
        },
        getMediaUrl(media) {
            if (!media || !media.original_url) return '';
            try {
                const parsed = new URL(media.original_url);
                return parsed.pathname;
            } catch(e) {
                return media.original_url;
            }
        },
        startEditingTitle() {
            this.isEditingTitle = true;
            this.$nextTick(() => {
                if (this.$refs.cardTitleInput) {
                    this.$refs.cardTitleInput.focus();
                }
            });
        },
        async updateCardTitle() {
            if (!this.localCard.title.trim()) return;
            this.isEditingTitle = false;
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.localCard.id}`, {
                    title: this.localCard.title
                });
                this.$emit('update');
                Nova.success('Card updated');
            } catch (e) {
                Nova.error('Failed to update card');
            }
        },
        addChecklistFromSidebar() {
            if (this.$refs.cardChecklist) {
                this.$refs.cardChecklist.createChecklist();
            }
        },
        async handleGlobalDrop(e) {
            this.isDragging = false;
            const files = e.dataTransfer.files;
            if (!files.length) return;

            for (let i = 0; i < files.length; i++) {
                await this.uploadFile(files[i]);
            }
        },
        async fetchCard() {
            try {
                const { data } = await Nova.request().get(`/nova-vendor/project-board/cards/${this.localCard.id}`);
                this.localCard = data;
            } catch (e) {
                console.error('Failed to refresh card');
            }
        },
        async uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);

            try {
                Nova.success(`Uploading ${file.name}...`);
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.localCard.id}/attachments`, formData);
                this.handleCardUpdate();
                Nova.success(`Uploaded ${file.name}`);
            } catch (e) {
                const msg = e.response?.data?.message || e.message || 'Unknown error';
                Nova.error(`Failed to upload ${file.name}: ${msg}`);
            }
        },
        handleCardUpdate() {
            this.fetchCard();
            this.$emit('update');
        }
    }
}
</script>
