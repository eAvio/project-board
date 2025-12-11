<template>
  <icon :type="normalizedType" :class="classes" />
</template>

<script>
export default {
  props: {
    name: {
      type: String,
      required: true,
    },
  },
  computed: {
    normalizedType() {
      // Map common Heroicons 2 names to Heroicons 1 (Nova 4) names if needed
      // Most are similar, but some differ.
      // E.g. x-mark -> x, plus -> plus, etc.
      // This is a basic pass-through for now, can be expanded.
      const map = {
        'x-mark': 'x',
        'ellipsis-horizontal': 'dots-horizontal',
        'arrows-pointing-out': 'expand',
        'arrows-pointing-in': 'collapse',
        'magnifying-glass': 'search',
        'trash': 'trash',
        'pencil': 'pencil',
        'plus': 'plus',
        'check': 'check',
        'check-circle': 'check-circle',
        'clock': 'clock',
        'calendar': 'calendar',
        'user': 'user',
        'users': 'users',
        'user-group': 'user-group',
        'document-text': 'document-text',
        'paper-clip': 'paper-clip',
        'chat-bubble-left': 'chat',
        'archive-box': 'archive',
        'photo': 'photograph',
        'key': 'key',
        'arrow-up-tray': 'upload',
      };
      
      return map[this.name] || this.name;
    },
    classes() {
        return this.$attrs.class || '';
    }
  },
};
</script>
