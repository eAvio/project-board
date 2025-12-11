<template>
  <button 
    :class="computedClasses" 
    type="button"
    v-bind="$attrs"
  >
    <div v-if="icon" :class="{ 'mr-2': hasContent }" class="flex items-center">
        <Icon :name="icon" />
    </div>
    <slot />
  </button>
</template>

<script>
import Icon from './Icon.vue'
import { useSlots, computed } from 'vue'

export default {
  components: {
    Icon
  },
  props: {
    variant: {
        type: String,
        default: 'primary' // 'primary', 'secondary', 'danger', 'ghost', 'link'
    },
    state: {
        type: String,
        default: '' // 'danger', 'mellow'
    },
    icon: {
        type: String,
        default: ''
    }
  },
  setup(props, { slots }) {
     const hasContent = computed(() => !!slots.default);
     return { hasContent };
  },
  computed: {
    computedClasses() {
        let base = 'inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2';
        
        if (this.variant === 'ghost') {
            return 'text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-1 inline-flex items-center justify-center transition-colors';
        }

        if (this.variant === 'link') {
            base = 'text-sm font-bold cursor-pointer inline-flex items-center';
            if (this.state === 'mellow') return base + ' text-gray-500 hover:text-gray-800';
            return base + ' text-primary-500 hover:text-primary-600';
        }

        if (this.state === 'danger') {
             return base + ' bg-red-500 text-white hover:bg-red-600 focus:ring-red-500';
        }

        // Default Primary (Nova Blue)
        return base + ' shadow bg-primary-500 text-white hover:bg-primary-400 active:bg-primary-600 focus:ring-primary-500';
    }
  }
};
</script>
