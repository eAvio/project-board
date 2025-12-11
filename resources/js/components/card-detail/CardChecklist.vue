<template>
    <div class="flex items-start space-x-4 mb-8" v-if="checklists && checklists.length">
        <Icon name="check-circle" class="w-6 h-6 mt-1 text-gray-500" />
        <div class="flex-1 space-y-6">
            <div v-for="checklist in checklists" :key="checklist.id">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ checklist.name }}</h3>
                    <button class="text-xs text-red-500 hover:text-red-600 hover:underline" @click="deleteChecklist(checklist)">Delete</button>
                </div>
                
                <!-- Progress Bar -->
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-xs text-gray-500 w-8">{{ Math.round(checklistProgress(checklist)) }}%</span>
                    <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500 transition-all duration-300" :style="{ width: `${checklistProgress(checklist)}%` }"></div>
                    </div>
                </div>
                
                <div class="space-y-2 pl-4">
                    <div v-for="item in checklist.items" :key="item.id" class="flex items-center group py-1">
                        <div class="flex items-center h-5 mr-3">
                            <div 
                               @click="toggleChecklistItem(item)"
                               class="flex-shrink-0 w-5 h-5 rounded border flex items-center justify-center cursor-pointer transition-colors duration-200"
                               :class="item.is_completed ? 'bg-green-500 border-green-500' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:border-green-500'"
                            >
                               <Icon v-if="item.is_completed" name="check" class="w-3.5 h-3.5 text-white" />
                            </div>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <input 
                                v-if="editingItemId === item.id"
                                v-model="editingItemContent"
                                @blur="updateItemContent(item)"
                                @keydown.enter="updateItemContent(item)"
                                ref="editItemInput"
                                class="w-full text-sm form-control form-input form-control-bordered h-7 py-0 px-2"
                            />
                            <span 
                                v-else
                                :class="{ 'line-through text-gray-400': item.is_completed, 'text-gray-700 dark:text-gray-200': !item.is_completed }"
                                class="text-sm block truncate cursor-pointer hover:text-primary-500 transition-colors"
                                @click="startEditingItem(item)"
                            >{{ item.content }}</span>
                        </div>
                        
                        <button class="text-gray-400 hover:text-red-500 p-1 ml-2 transition-colors" @click="deleteChecklistItem(item)" title="Delete item">
                            <Icon name="trash" class="w-4 h-4" />
                        </button>
                    </div>
                    
                    <!-- Add Item Input -->
                    <div v-if="addingItemToChecklist === checklist.id" class="mt-2 flex items-center gap-2">
                        <input 
                            v-model="newItemContent" 
                            placeholder="Add an item"
                            class="flex-1 form-control form-input form-control-bordered text-sm h-8"
                            @keydown.enter="addChecklistItem(checklist)"
                            ref="newItemInput"
                        />
                        <button @click="addChecklistItem(checklist)" class="bg-primary-500 text-white text-xs font-bold px-3 h-8 rounded hover:bg-primary-600 flex-shrink-0">Add</button>
                        <button @click="addingItemToChecklist = null" class="text-gray-500 hover:text-gray-700 text-xs h-8 px-2 flex-shrink-0">Cancel</button>
                    </div>
                    <button v-else @click="startAddingItem(checklist)" class="bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-900 px-3 py-1.5 rounded text-sm font-medium text-gray-600 dark:text-gray-200 hover:bg-gray-200 mt-2 ml-7">Add an item</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Checklist Modal (Teleported with high z-index) -->
    <Teleport to="body">
        <template v-if="showDeleteChecklistModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 100;" @click="showDeleteChecklistModal = false"></div>
            <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                        <ModalHeader>Delete Checklist</ModalHeader>
                        <div class="p-6">
                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                Are you sure you want to delete the checklist
                                <span class="font-semibold text-gray-800 dark:text-gray-200">"{{ checklistToDelete ? checklistToDelete.name : '' }}"</span>?
                            </p>
                        </div>
                        <ModalFooter>
                            <div class="flex items-center ml-auto">
                                <Button variant="link" state="mellow" @click.prevent="showDeleteChecklistModal = false" class="mr-3">Cancel</Button>
                                <Button state="danger" @click="confirmDeleteChecklist">Delete</Button>
                            </div>
                        </ModalFooter>
                    </div>
                </div>
            </div>
        </template>
    </Teleport>

    <!-- Delete Checklist Item Modal (Teleported with high z-index) -->
    <Teleport to="body">
        <template v-if="showDeleteItemModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 100;" @click="showDeleteItemModal = false"></div>
            <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                        <ModalHeader>Delete Item</ModalHeader>
                        <div class="p-6">
                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                Are you sure you want to delete this checklist item?
                            </p>
                        </div>
                        <ModalFooter>
                            <div class="flex items-center ml-auto">
                                <Button variant="link" state="mellow" @click.prevent="showDeleteItemModal = false" class="mr-3">Cancel</Button>
                                <Button state="danger" @click="confirmDeleteChecklistItem">Delete</Button>
                            </div>
                        </ModalFooter>
                    </div>
                </div>
            </div>
        </template>
    </Teleport>
</template>

<script>
import Icon from '../UI/Icon.vue'
import Button from '../UI/Button.vue'

export default {
    components: { Icon, Button },
    props: {
        card: Object
    },
    data() {
        return {
            checklists: [],
            addingItemToChecklist: null,
            newItemContent: '',
            editingItemId: null,
            editingItemContent: '',
            showDeleteChecklistModal: false,
            checklistToDelete: null,
            showDeleteItemModal: false,
            itemToDelete: null,
        }
    },
    watch: {
        card: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    this.fetchChecklists();
                }
            }
        }
    },
    methods: {
        async fetchChecklists() {
            if (!this.card) return;
            try {
                const { data } = await Nova.request().get(`/nova-vendor/project-board/cards/${this.card.id}/checklists`);
                this.checklists = data;
            } catch (e) {
                console.error('Failed to fetch checklists');
            }
        },
        checklistProgress(checklist) {
            if (!checklist.items || checklist.items.length === 0) return 0;
            const completed = checklist.items.filter(i => i.is_completed).length;
            return (completed / checklist.items.length) * 100;
        },
        async deleteChecklist(checklist) {
            this.checklistToDelete = checklist;
            this.showDeleteChecklistModal = true;
        },
        async startAddingItem(checklist) {
            this.addingItemToChecklist = checklist.id;
            this.newItemContent = '';
            this.$nextTick(() => {
                if(this.$refs.newItemInput && this.$refs.newItemInput.length) {
                    this.$refs.newItemInput[0].focus(); // If v-for refs, it's array
                } else if (this.$refs.newItemInput) {
                    this.$refs.newItemInput.focus();
                }
            });
        },
        async addChecklistItem(checklist) {
            if (!this.newItemContent.trim()) return;
            try {
                const { data } = await Nova.request().post(`/nova-vendor/project-board/checklists/${checklist.id}/items`, {
                    content: this.newItemContent
                });
                checklist.items.push(data);
                this.newItemContent = '';
                // Keep input open
                this.$nextTick(() => {
                     if(this.$refs.newItemInput && this.$refs.newItemInput.length) {
                        this.$refs.newItemInput[0].focus();
                    } else if (this.$refs.newItemInput) {
                        this.$refs.newItemInput.focus();
                    }
                });
            } catch (e) {
                Nova.error('Failed to add item');
            }
        },
        async toggleChecklistItem(item) {
            const originalState = item.is_completed;
            item.is_completed = !item.is_completed;
            try {
                await Nova.request().put(`/nova-vendor/project-board/checklist-items/${item.id}`, {
                    is_completed: item.is_completed
                });
            } catch (e) {
                item.is_completed = originalState;
                Nova.error('Failed to update item');
            }
        },
        async deleteChecklistItem(item) {
            this.itemToDelete = item;
            this.showDeleteItemModal = true;
        },
        async confirmDeleteChecklist() {
            if (!this.checklistToDelete) {
                this.showDeleteChecklistModal = false;
                return;
            }
            try {
                await Nova.request().delete(`/nova-vendor/project-board/checklists/${this.checklistToDelete.id}`);
                this.checklists = this.checklists.filter(c => c.id !== this.checklistToDelete.id);
                Nova.success('Checklist deleted');
            } catch (e) {
                Nova.error('Failed to delete checklist');
            } finally {
                this.checklistToDelete = null;
                this.showDeleteChecklistModal = false;
            }
        },
        async confirmDeleteChecklistItem() {
            if (!this.itemToDelete) {
                this.showDeleteItemModal = false;
                return;
            }
            try {
                await Nova.request().delete(`/nova-vendor/project-board/checklist-items/${this.itemToDelete.id}`);
                for (let list of this.checklists) {
                    const idx = list.items.findIndex(i => i.id === this.itemToDelete.id);
                    if (idx !== -1) {
                        list.items.splice(idx, 1);
                        break;
                    }
                }
            } catch (e) {
                Nova.error('Failed to delete item');
            } finally {
                this.itemToDelete = null;
                this.showDeleteItemModal = false;
            }
        },
        startEditingItem(item) {
            this.editingItemId = item.id;
            this.editingItemContent = item.content;
            this.$nextTick(() => {
                if (this.$refs.editItemInput && this.$refs.editItemInput.length) {
                    this.$refs.editItemInput[0].focus();
                } else if (this.$refs.editItemInput) {
                    this.$refs.editItemInput.focus();
                }
            });
        },
        async updateItemContent(item) {
            if (!this.editingItemContent.trim()) return;
            if (this.editingItemContent === item.content) {
                this.editingItemId = null;
                return;
            }
            try {
                await Nova.request().put(`/nova-vendor/project-board/checklist-items/${item.id}`, {
                    content: this.editingItemContent
                });
                item.content = this.editingItemContent;
                this.editingItemId = null;
            } catch (e) {
                Nova.error('Failed to update item');
            }
        },
        // Method for parent to call to add a new checklist
        async createChecklist() {
            try {
                const { data } = await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/checklists`, {
                    name: 'Checklist'
                });
                this.checklists.push(data);
                Nova.success('Checklist created');
            } catch (e) {
                Nova.error('Failed to create checklist');
            }
        }
    }
}
</script>
