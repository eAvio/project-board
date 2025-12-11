<template>
  <div 
    class="flex flex-col bg-gray-200 dark:bg-gray-800 rounded-lg max-h-full transition-all"
    :class="{ 'ring-2 ring-primary-500 ring-offset-2 bg-primary-50 dark:bg-primary-900/20': isDraggingOver }"
    style="width: 272px; min-width: 272px; max-width: 272px;"
    @dragover.prevent="onDragOver"
    @dragleave="onDragLeave"
    @drop.prevent="onDrop"
  >
    <!-- Column Header -->
    <div class="p-3 cursor-move column-drag-handle flex-shrink-0">
      <div class="flex items-center justify-between">
        <div class="flex-1 mr-2">
          <input 
              v-if="isRenaming"
              v-model="column.name"
              ref="columnNameInput"
              @blur="finishRenaming"
              @keydown.enter="finishRenaming"
              class="w-full form-control form-input form-control-bordered px-2 py-1 text-sm font-bold"
          />
          <h3 
              v-else
                class="font-bold text-gray-700 dark:text-white text-sm uppercase cursor-pointer hover:text-primary-500"
              @click="startRenaming"
              title="Click to rename"
          >
              {{ column.name }}
          </h3>
        </div>
      <div class="relative" v-click-outside="closeMenu">
         <button 
            @click="toggleMenu"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-800 p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
         >
           <Icon name="ellipsis-horizontal" class="w-4 h-4" />
         </button>
         <!-- Dropdown Menu -->
         <div 
            v-if="showMenu"
            class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-700 rounded-lg shadow-xl z-50 border border-gray-200 dark:border-gray-600 py-1"
         >
            
                <button 
                  @click="startRenaming"
                  class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                  Rename
                </button>
                <button 
                  class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800"
                  @click="archiveAllCards"
                >
                  Archive All Cards
                </button>

                <button 
                  @click="deleteColumn"
                  class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                  Archive Column
                </button>
            
         </div>
        </div>
      </div>
      <!-- Column Totals -->
      <div v-if="hasTotals" class="mt-1 flex items-center text-xs space-x-3 relative column-totals-wrapper text-gray-500 dark:text-gray-400">
        <span v-if="column.totals?.estimated_hours > 0 || column.totals?.actual_hours > 0">
          <span :class="hoursActualClass">{{ formatNum(column.totals?.actual_hours) }}</span><template v-if="column.totals?.estimated_hours > 0"> / {{ formatNum(column.totals?.estimated_hours) }}</template> h
        </span>
        <span v-if="column.totals?.estimated_cost > 0 || column.totals?.actual_cost > 0">
          <span :class="costActualClass">{{ formatNum(column.totals?.actual_cost) }}</span><template v-if="column.totals?.estimated_cost > 0"> / {{ formatNum(column.totals?.estimated_cost) }}</template> €
        </span>
        <!-- Breakdown Tooltip -->
        <div class="column-totals-tooltip absolute left-0 top-full mt-1 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 p-2 z-50" style="width: 320px;">
          <div class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-2 border-b border-gray-200 dark:border-gray-600 pb-1">Breakdown</div>
          <div class="max-h-48 overflow-y-auto space-y-1">
            <div v-for="card in cardsWithEstimates" :key="card.id" class="flex justify-between text-xs py-1 border-b border-gray-100 dark:border-gray-700 last:border-0">
              <span class="mr-2">{{ card.title }}</span>
              <span class="flex space-x-3 flex-shrink-0">
                <span v-if="card.estimated_hours > 0 || card.actual_hours > 0"><span :class="getCardHoursClass(card)">{{ formatNum(card.actual_hours) }}</span><template v-if="card.estimated_hours > 0"> / {{ formatNum(card.estimated_hours) }}</template> h</span>
                <span v-if="card.estimated_cost > 0 || card.actual_cost > 0"><span :class="getCardCostClass(card)">{{ formatNum(card.actual_cost) }}</span><template v-if="card.estimated_cost > 0"> / {{ formatNum(card.estimated_cost) }}</template> €</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cards List -->
    <div 
        class="flex-1 overflow-y-auto overflow-x-hidden px-2 pb-2 custom-scrollbar min-h-[50px]"
        @click.self="enableAddCard"
    >
       <draggable
         v-model="column.cards"
         group="cards"
         item-key="id"
         class="flex flex-col space-y-2 min-h-full"
         ghost-class="opacity-50"
         @change="onCardChange"
       >
         <template #item="{ element: card }">
           <BoardCard 
             :card="card" 
             @click="editCard(card)" 
             @quick-edit="openQuickEdit(card, $event)"
             @delete="deleteCard(card)"
           />
         </template>
       </draggable>
       
       <!-- Add Card Input -->
       <div v-if="isAddingCard" class="mt-2">
          <textarea
            v-model="newCardTitle"
            ref="newCardInput"
            @keydown.enter.prevent="createCard"
            @keydown.esc="isAddingCard = false"
            @paste="handlePaste"
            @blur="handleCardBlur"
            class="w-full form-control form-input form-control-bordered text-sm p-2"
            placeholder="Enter title or paste image..."
            rows="3"
          ></textarea>
          <div class="flex space-x-2 mt-2">
             <button @mousedown.prevent="isClickingButton = true" @click="createCard" class="shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm">Add Card</button>
             <button @mousedown.prevent="isClickingButton = true" @click="isAddingCard = false" class="focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring-2 rounded border-2 border-gray-200 dark:border-gray-500 hover:border-primary-500 active:border-primary-400 dark:hover:border-gray-400 dark:active:border-gray-300 bg-white dark:bg-transparent text-primary-500 dark:text-gray-400 px-3 h-9 inline-flex items-center font-bold text-sm">Cancel</button>
          </div>
       </div>
    </div>

    <!-- Add Card Button (Footer) -->
    <div v-if="!isAddingCard" class="p-2 flex-shrink-0">
       <button
         @click="enableAddCard"
         class="w-full py-2 px-3 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-sm flex items-center transition-colors"
       >
         <Icon name="plus" class="w-4 h-4 mr-2" />
         Add a card
       </button>
    </div>

    <!-- Archive All Cards Modal -->
    <Modal :show="showArchiveAllCardsModal" @close="showArchiveAllCardsModal = false" role="dialog">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <ModalHeader>Archive All Cards</ModalHeader>

        <div class="p-8">
          <p class="text-gray-500 dark:text-gray-400 text-sm">
            Are you sure you want to archive all cards in the column "{{ column.name }}"?
          </p>
        </div>

        <ModalFooter>
          <div class="flex items-center ml-auto">
            <Button
              variant="link"
              state="mellow"
              @click.prevent="showArchiveAllCardsModal = false"
              class="mr-3"
            >
              Cancel
            </Button>
            <Button
              state="danger"
              @click="confirmArchiveAllCards"
            >
              Archive All Cards
            </Button>
          </div>
        </ModalFooter>
      </div>
    </Modal>

    <!-- Archive Single Card Modal -->
    <Modal :show="showArchiveCardModal" @close="() => { showArchiveCardModal = false; cardToArchive = null; }" role="dialog">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <ModalHeader>Archive Card</ModalHeader>

        <div class="p-8">
          <p class="text-gray-500 dark:text-gray-400 text-sm">
            Are you sure you want to archive the card
            <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ cardToArchive ? cardToArchive.title : '' }}"</span>
            ?
          </p>
        </div>

        <ModalFooter>
          <div class="flex items-center ml-auto">
            <Button
              variant="link"
              state="mellow"
              @click.prevent="() => { showArchiveCardModal = false; cardToArchive = null; }"
              class="mr-3"
            >
              Cancel
            </Button>
            <Button
              state="danger"
              @click="confirmArchiveCard"
            >
              Archive Card
            </Button>
          </div>
        </ModalFooter>
      </div>
    </Modal>

    <!-- Archive Column Modal -->
    <Modal :show="showArchiveColumnModal" @close="showArchiveColumnModal = false" role="dialog">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
        <ModalHeader>Archive Column</ModalHeader>

        <div class="p-8">
          <p class="text-gray-500 dark:text-gray-400 text-sm">
            Are you sure you want to archive the column
            <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ column.name }}"</span>
            ? All cards in it will also be archived.
          </p>
        </div>

        <ModalFooter>
          <div class="flex items-center ml-auto">
            <Button
              variant="link"
              state="mellow"
              @click.prevent="showArchiveColumnModal = false"
              class="mr-3"
            >
              Cancel
            </Button>
            <Button
              state="danger"
              @click="confirmArchiveColumn"
            >
              Archive Column
            </Button>
          </div>
        </ModalFooter>
      </div>
    </Modal>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import BoardCard from './BoardCard.vue'
import Icon from './UI/Icon.vue'
import Button from './UI/Button.vue'
import { formatDistanceToNow } from 'date-fns'

export default {
  components: {
    draggable,
    BoardCard,
    Icon,
    Button
  },
  props: {
    column: {
      type: Object,
      required: true
    },
    boardId: {
      type: Number,
      required: true
    }
  },
  emits: ['refresh-board', 'edit-card', 'delete-column'],
  data() {
    return {
      isAddingCard: false,
      newCardTitle: '',
      isRenaming: false,
      showMenu: false,
      isDraggingOver: false,
      isClickingButton: false,
      showArchiveAllCardsModal: false,
      showArchiveCardModal: false,
      cardToArchive: null,
      showArchiveColumnModal: false,
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
  computed: {
    currentUser() {
        return Nova.config('user') || { 
            name: 'Guest User',
            avatar_url: null
        };
    },
    hasTotals() {
        const t = this.column.totals;
        if (!t) return false;
        return (t.estimated_hours > 0 || t.actual_hours > 0 || t.estimated_cost > 0 || t.actual_cost > 0);
    },
    hoursActualClass() {
        const t = this.column.totals;
        if (!t) return 'font-bold';
        if (t.estimated_hours > 0 && t.actual_hours > t.estimated_hours) return 'font-bold text-red-500';
        return 'font-bold';
    },
    costActualClass() {
        const t = this.column.totals;
        if (!t) return 'font-bold';
        if (t.estimated_cost > 0 && t.actual_cost > t.estimated_cost) return 'font-bold text-red-500';
        return 'font-bold';
    },
    cardsWithEstimates() {
        if (!this.column.cards) return [];
        return this.column.cards.filter(c => c.estimated_hours > 0 || c.estimated_cost > 0);
    }
  },
  methods: {
    formatHours(val) {
        const num = parseFloat(val) || 0;
        return num.toFixed(1) + 'h';
    },
    formatCost(val) {
        const num = parseFloat(val) || 0;
        return num.toFixed(0);
    },
    formatNum(val) {
        const num = parseFloat(val) || 0;
        return num % 1 === 0 ? num.toFixed(0) : num.toFixed(1);
    },
    getCardHoursClass(card) {
        const actual = parseFloat(card.actual_hours) || 0;
        const estimated = parseFloat(card.estimated_hours) || 0;
        if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
        return 'font-bold';
    },
    getCardCostClass(card) {
        const actual = parseFloat(card.actual_cost) || 0;
        const estimated = parseFloat(card.estimated_cost) || 0;
        if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
        return 'font-bold';
    },
    notImplemented(feature) {
      Nova.warning(`${feature} is not implemented yet.`);
    },

  closeMenu() {
    this.showMenu = false;
  },
    toggleMenu() {
      this.showMenu = !this.showMenu;
    },
    refreshBoard() {
        this.$emit('refresh-board');
    },
    formatTime(date) {
      return formatDistanceToNow(new Date(date), { addSuffix: true });
    },
    startRenaming() {
      this.isRenaming = true;
      this.showMenu = false;
      this.$nextTick(() => {
        if(this.$refs.columnNameInput) this.$refs.columnNameInput.focus();
      });
    },
    async finishRenaming() {
      if (!this.isRenaming) return; // Already finished
      this.isRenaming = false;
      if (!this.column.name.trim()) return;
      try {
        await Nova.request().put(`/nova-vendor/project-board/columns/${this.column.id}`, {
          name: this.column.name,
          board_id: this.boardId
        });
        this.$emit('refresh-board');
        Nova.success('Column renamed');
      } catch (e) {
        Nova.error('Rename failed');
      }
    },
    handlePaste(e) {
      const items = (e.clipboardData || e.originalEvent.clipboardData).items;
      for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
          e.preventDefault();
          e.stopPropagation();
          const blob = item.getAsFile();
          this.createCardWithFile(blob);
          return;
        }
      }
    },
    onDragOver(e) {
      // Only show drop zone if dragging files
      if (e.dataTransfer.types.includes('Files')) {
        this.isDraggingOver = true;
      }
    },
    onDragLeave(e) {
      // Only reset if leaving the column entirely
      if (!e.currentTarget.contains(e.relatedTarget)) {
        this.isDraggingOver = false;
      }
    },
    onDrop(e) {
      this.isDraggingOver = false;
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        for (let i = 0; i < files.length; i++) {
          const file = files[i];
          this.createCardWithFile(file);
        }
      }
    },
    async createCardWithFile(file) {
      const formData = new FormData();
      formData.append('file', file);
      // Use filename without extension as title
      const fileName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
      formData.append('title', fileName);
      try {
        Nova.success('Uploading file...');
        await Nova.request().post(`/nova-vendor/project-board/columns/${this.column.id}/cards-with-file`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });
        this.isAddingCard = false;
        this.$emit('refresh-board');
        Nova.success('Card created with attachment');
      } catch (e) {
        Nova.error('Failed to upload file');
      }
    },
    enableAddCard() {
      this.isAddingCard = true;
      this.isClickingButton = false;
      this.$nextTick(() => {
        this.$refs.newCardInput.focus();
      });
    },
    handleCardBlur() {
      if (this.isClickingButton) {
        this.isClickingButton = false;
        return;
      }
      if (!this.isAddingCard) return;

      if (this.newCardTitle.trim()) {
        // Create a single card on blur, then close the input
        this.createCardFromBlur();
      } else {
        // Nothing typed, just cancel add mode
        this.isAddingCard = false;
      }
    },
    async createCardFromBlur() {
      if (!this.newCardTitle.trim()) return;
      try {
        await Nova.request().post(`/nova-vendor/project-board/columns/${this.column.id}/cards`, {
          title: this.newCardTitle
        });
        // Reset and close after blur-created card
        this.newCardTitle = '';
        this.isAddingCard = false;
        this.$emit('refresh-board');
      } catch (e) {
        Nova.error('Failed to create card');
      }
    },
    async archiveAllCards() {
      this.showArchiveAllCardsModal = true;
    },
    async confirmArchiveAllCards() {
      try {
        await Nova.request().post(`/nova-vendor/project-board/columns/${this.column.id}/archive-cards`);
        this.$emit('refresh-board');
        this.closeMenu();
        Nova.success('All cards in this column were archived');
      } catch (e) {
        Nova.error('Failed to archive cards');
      }
    },
    async createCard() {
      if (!this.newCardTitle.trim()) return;
      try {
        await Nova.request().post(`/nova-vendor/project-board/columns/${this.column.id}/cards`, {
          title: this.newCardTitle
        });
        this.newCardTitle = '';
        this.$nextTick(() => {
          if(this.$refs.newCardInput) this.$refs.newCardInput.focus();
        });
        this.$emit('refresh-board');
      } catch (e) {
        Nova.error('Failed to create card');
      }
    },
    async onCardChange(evt) {
      if (evt.added) {
        const { element, newIndex } = evt.added;
        await this.moveCard(element.id, this.column.id, newIndex + 1);
      } else if (evt.moved) {
        const { element, newIndex } = evt.moved;
        await this.moveCard(element.id, this.column.id, newIndex + 1);
      }
    },
    async moveCard(cardId, columnId, order) {
      try {
        await Nova.request().put(`/nova-vendor/project-board/cards/${cardId}/move`, {
          board_column_id: columnId,
          order_column: order
        });
      } catch (e) {
        Nova.error('Failed to move card');
        this.$emit('refresh-board');
      }
    },
    editCard(card) {
      this.$emit('edit-card', card, this.column);
    },
    openQuickEdit(card, event) {
      Nova.success('Quick Edit: ' + card.title);
      this.editCard(card);
    },
    async deleteCard(card) {
        this.cardToArchive = card;
        this.showArchiveCardModal = true;
    },
    async confirmArchiveCard() {
        if (!this.cardToArchive) return;
        try {
            await Nova.request().delete(`/nova-vendor/project-board/cards/${this.cardToArchive.id}`);
            this.$emit('refresh-board');
            Nova.success('Card archived');
        } catch (e) {
            Nova.error('Failed to archive card');
        } finally {
            this.cardToArchive = null;
            this.showArchiveCardModal = false;
        }
    },
    async deleteColumn() {
        this.showArchiveColumnModal = true;
    },
    async confirmArchiveColumn() {
        try {
            await Nova.request().delete(`/nova-vendor/project-board/columns/${this.column.id}`);
            this.$emit('refresh-board');
            Nova.success('Column archived');
        } catch (e) {
            Nova.error('Failed to archive column');
        } finally {
            this.showArchiveColumnModal = false;
        }
    }
  }
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: rgba(0,0,0,0.05);
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.7);
}

/* Tooltip hover */
.column-totals-tooltip {
  display: none;
}
.column-totals-wrapper:hover .column-totals-tooltip {
  display: block;
}
</style>
