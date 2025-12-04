<template>
    <Modal :show="show" @close="$emit('close')" role="dialog" size="2xl" class="!z-[100]">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <ModalHeader class="flex items-center justify-between px-8 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Archived Items</h2>
                <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                    <Icon name="x-mark" class="w-6 h-6" />
                </button>
            </ModalHeader>

            <div class="p-4">
                <!-- Tabs -->
                <div class="flex space-x-4 mb-4 border-b border-gray-200 dark:border-gray-700">
                    <button 
                        @click="activeTab = 'cards'" 
                        :class="['pb-2 px-2 font-bold text-sm border-b-2 transition-colors', activeTab === 'cards' ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >
                        Cards
                    </button>
                    <button 
                        @click="activeTab = 'columns'" 
                        :class="['pb-2 px-2 font-bold text-sm border-b-2 transition-colors', activeTab === 'columns' ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >
                        Columns
                    </button>
                </div>

                <!-- Content -->
                <div v-if="loading" class="flex justify-center py-8">
                    <Loader class="text-gray-500" />
                </div>
                <div v-else class="h-96 overflow-y-auto pr-2">
                    <!-- Cards Tab -->
                    <div v-if="activeTab === 'cards'">
                        <div v-if="archivedCards.length === 0" class="text-center text-gray-500 py-8">
                            No archived cards.
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="card in archivedCards" :key="card.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600">
                                <div>
                                    <h4 class="font-bold text-gray-800 dark:text-gray-200">{{ card.title }}</h4>
                                    <p class="text-xs text-gray-500">Originally in {{ card.column?.name || 'Unknown Column' }}</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button @click="restoreCard(card)" class="text-sm font-medium text-primary-500 hover:text-primary-600 hover:underline">Send to Board</button>
                                    <span class="text-gray-300">|</span>
                                    <button @click="deleteCard(card)" class="text-sm font-medium text-red-500 hover:text-red-600 hover:underline">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columns Tab -->
                    <div v-if="activeTab === 'columns'">
                        <div v-if="archivedColumns.length === 0" class="text-center text-gray-500 py-8">
                            No archived columns.
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="column in archivedColumns" :key="column.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600">
                                <div>
                                    <h4 class="font-bold text-gray-800 dark:text-gray-200">{{ column.name }}</h4>
                                    <p class="text-xs text-gray-500">{{ column.cards_count }} cards</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button @click="restoreColumn(column)" class="text-sm font-medium text-primary-500 hover:text-primary-600 hover:underline">Send to Board</button>
                                    <span class="text-gray-300">|</span>
                                    <button @click="deleteColumn(column)" class="text-sm font-medium text-red-500 hover:text-red-600 hover:underline">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script>
import { Icon, Button } from 'laravel-nova-ui'

export default {
    components: { Icon },
    props: {
        show: Boolean,
        boardId: Number
    },
    emits: ['close', 'refresh'],
    data() {
        return {
            activeTab: 'cards',
            loading: false,
            archivedCards: [],
            archivedColumns: [],
            showDeleteArchivedCardModal: false,
            cardToDelete: null,
            showDeleteArchivedColumnModal: false,
            columnToDelete: null,
        }
    },
    watch: {
        show(val) {
            if (val) {
                this.fetchArchived();
            }
        }
    },
    methods: {
        async fetchArchived() {
            if (!this.boardId) return;
            this.loading = true;
            try {
                const { data } = await Nova.request().get(`/nova-vendor/project-board/boards/${this.boardId}/archived`);
                this.archivedCards = data.cards;
                this.archivedColumns = data.columns;
            } catch (e) {
                Nova.error('Failed to load archived items');
            } finally {
                this.loading = false;
            }
        },
        async restoreCard(card) {
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${card.id}/restore`);
                this.archivedCards = this.archivedCards.filter(c => c.id !== card.id);
                Nova.success('Card sent to board');
                this.$emit('refresh');
            } catch (e) {
                Nova.error('Failed to restore card');
            }
        },
        async deleteCard(card) {
            this.cardToDelete = card;
            this.showDeleteArchivedCardModal = true;
        },
        async confirmDeleteArchivedCard() {
            if (!this.cardToDelete) {
                this.showDeleteArchivedCardModal = false;
                return;
            }
            try {
                await Nova.request().delete(`/nova-vendor/project-board/cards/${this.cardToDelete.id}/force`);
                this.archivedCards = this.archivedCards.filter(c => c.id !== this.cardToDelete.id);
                Nova.success('Card deleted permanently');
            } catch (e) {
                Nova.error('Failed to delete card');
            } finally {
                this.cardToDelete = null;
                this.showDeleteArchivedCardModal = false;
            }
        },
        async restoreColumn(column) {
            try {
                await Nova.request().put(`/nova-vendor/project-board/columns/${column.id}/restore`);
                this.archivedColumns = this.archivedColumns.filter(c => c.id !== column.id);
                Nova.success('Column restored');
                this.$emit('refresh');
            } catch (e) {
                Nova.error('Failed to restore column');
            }
        },
        async deleteColumn(column) {
            this.columnToDelete = column;
            this.showDeleteArchivedColumnModal = true;
        },
        async confirmDeleteArchivedColumn() {
            if (!this.columnToDelete) {
                this.showDeleteArchivedColumnModal = false;
                return;
            }
            try {
                await Nova.request().delete(`/nova-vendor/project-board/columns/${this.columnToDelete.id}/force`);
                this.archivedColumns = this.archivedColumns.filter(c => c.id !== this.columnToDelete.id);
                Nova.success('Column deleted permanently');
            } catch (e) {
                Nova.error('Failed to delete column');
            } finally {
                this.columnToDelete = null;
                this.showDeleteArchivedColumnModal = false;
            }
        }
    }
}
</script>
