<template>
  <button 
    :class="computedClasses" 
    type="button"
    v-bind="$attrs"
  >
    <div v-if="icon" class="mr-2 flex items-center">
        <!-- Use our compatibility icon if needed, or rely on global icon if we didn't import our wrapper locally in this component (we didn't). 
             Users passing 'icon' prop expect an icon. 
             Since this is a wrapper, we assume global 'icon' element is available or we slot it.
             But the prompt logic implies we might use the wrapper Icon here? 
             Let's use a dynamic component or just <icon> assuming global availability, but 'type' needs mapping. 
             Ideally passed icon is just the name.
        -->
        <component :is="'icon'" :type="mapIcon(icon)" />
    </div>
    <slot />
  </button>
</template>

<script>
export default {
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
  methods: {
    mapIcon(name) {
         const map = {
            'ellipsis-horizontal': 'dots-horizontal',
            'plus': 'plus',
            'trash': 'trash',
         };
         return map[name] || name;
    }
  },
  computed: {
    computedClasses() {
        let base = 'inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2';
        
        if (this.variant === 'ghost') {
            return 'text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-1';
        }

        if (this.variant === 'link') {
            base = 'text-sm font-bold cursor-pointer';
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
