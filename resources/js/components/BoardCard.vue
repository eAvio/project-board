<template>
  <div 
    class="bg-white dark:bg-gray-700 rounded-lg shadow p-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900 group relative overflow-hidden max-w-full"
    :class="{ 'border-l-4 border-purple-400': card.is_mirror }"
    @click="$emit('click')"
  >
    <!-- Labels -->
    <div 
      v-if="card.labels && card.labels.length" 
      class="flex flex-wrap gap-1 mb-2"
      @click.stop="toggleLabels"
    >
       <span 
         v-for="label in card.labels" 
         :key="label.id"
         class="inline-flex items-center whitespace-nowrap rounded-full uppercase font-bold text-white transition-all duration-200 overflow-hidden"
         :class="showLabels ? 'min-h-6 px-2 text-xs' : 'h-2 w-8 p-0'"
         :style="{ backgroundColor: label.color || '#3b82f6' }"
         :title="label.name"
       >
         <span v-if="showLabels">{{ label.name }}</span>
       </span>
    </div>

    <!-- Cover Image (if any) -->
    <div v-if="featuredImage" class="mb-2 -mx-3 -mt-3 rounded-t">
       <img :src="featuredImage" class="w-full h-32 object-cover rounded-t" alt="Cover" />
    </div>

    <!-- Title with mirror indicator -->
    <h4 class="text-gray-800 dark:text-gray-200 text-sm font-medium leading-tight mb-2 break-words flex items-start gap-1">
       <Icon v-if="card.is_mirror" name="arrow-top-right-on-square" class="w-3 h-3 text-purple-400 flex-shrink-0 mt-0.5" :title="`Mirror from: ${card.home_board_name} → ${card.home_column_name}`" />
       <span>{{ card.title }}</span>
    </h4>
    
    <div class="flex items-center justify-between text-gray-400 text-xs">
       <div class="flex items-center space-x-2">
          <!-- Due Date -->
          <div v-if="card.due_date" class="flex items-center" :class="{'text-red-500': isOverdue, 'text-green-500': isComplete}">
             <Icon name="clock" class="w-3 h-3 mr-1" />
             <span>{{ formattedDate }}</span>
          </div>
          
          <!-- Description Indicator -->
          <div v-if="card.description">
             <Icon name="bars-3-bottom-left" class="w-3 h-3" />
          </div>
          
          <!-- Comments Count -->
          <div v-if="card.comments_count > 0" class="flex items-center">
             <Icon name="chat-bubble-left" class="w-3 h-3 mr-0.5" />
             {{ card.comments_count }}
          </div>

          <!-- Estimates -->
          <div v-if="hasTimeOrCost" class="flex items-center space-x-2 text-gray-500">
             <!-- Hours -->
             <span v-if="card.estimated_hours > 0 || card.actual_hours > 0">
                <span :class="hoursActualClass">{{ formatNum(card.actual_hours) }}</span><template v-if="card.estimated_hours > 0"> / {{ formatNum(card.estimated_hours) }}</template> h
             </span>
             <!-- Cost -->
             <span v-if="card.estimated_cost > 0 || card.actual_cost > 0">
                <span :class="costActualClass">{{ formatNum(card.actual_cost) }}</span><template v-if="card.estimated_cost > 0"> / {{ formatNum(card.estimated_cost) }}</template> €
             </span>
          </div>
       </div>
       
       <!-- Assignees -->
       <div class="flex -space-x-1 overflow-hidden" v-if="card.assignees && card.assignees.length">
          <img 
            v-for="user in card.assignees" 
            :key="user.id"
            class="inline-block h-5 w-5 rounded-full ring-1 ring-white dark:ring-gray-800"
            :src="user.avatar_url || `https://ui-avatars.com/api/?name=${user.name}`"
            :alt="user.name"
            :title="user.name"
          />
       </div>
    </div>
    
    <!-- Quick Edit / Actions Menu -->
    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity z-20" @click.stop>
        <Dropdown>
            <template #trigger>
                <button 
                    class="inline-flex items-center p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-900 rounded-lg"
                >
                   <Icon name="ellipsis-horizontal" class="w-4 h-4" />
                </button>
            </template>
            <template #menu>
                <DropdownMenu width="150">
                    <DropdownMenuItem as="button" @click.stop="$emit('quick-edit', $event)">
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem as="button" class="text-red-500 hover:text-red-600" @click.stop="$emit('delete')">
                        Delete
                    </DropdownMenuItem>
                </DropdownMenu>
            </template>
        </Dropdown>
    </div>
  </div>
</template>

<script>
import { format, isPast, parseISO } from 'date-fns'
import Icon from './UI/Icon.vue'

export default {
  components: {
    Icon
  },
  props: {
    card: {
      type: Object,
      required: true
    }
  },
  
  data() {
      return {
          showLabels: false
      }
  },
  
  methods: {
      toggleLabels() {
          this.showLabels = !this.showLabels;
      },
      formatNum(val) {
          const num = parseFloat(val) || 0;
          return num % 1 === 0 ? num.toFixed(0) : num.toFixed(1);
      }
  },
  
  computed: {
     hasTimeOrCost() {
        return (this.card.estimated_hours > 0 || this.card.actual_hours > 0 || this.card.estimated_cost > 0 || this.card.actual_cost > 0);
     },
     hoursActualClass() {
        const actual = parseFloat(this.card.actual_hours) || 0;
        const estimated = parseFloat(this.card.estimated_hours) || 0;
        if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
        return 'font-bold';
     },
     costActualClass() {
        const actual = parseFloat(this.card.actual_cost) || 0;
        const estimated = parseFloat(this.card.estimated_cost) || 0;
        if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
        return 'font-bold';
     },
     featuredImage() {
        // Assuming media library structure
        const url = this.card.media?.find(m => m.collection_name === 'featured_image')?.original_url;
        if (!url) return null;
        // Force relative URL to avoid localhost vs 127.0.0.1 issues
        try {
            const parsed = new URL(url);
            return parsed.pathname;
        } catch(e) {
            return url;
        }
     },
     formattedDate() {
        if (!this.card.due_date) return '';
        return format(parseISO(this.card.due_date), 'MMM d');
     },
     isOverdue() {
        if (!this.card.due_date || this.card.completed_at) return false;
        return isPast(parseISO(this.card.due_date));
     },
     isComplete() {
        return !!this.card.completed_at;
     }
  }
}
</script>
