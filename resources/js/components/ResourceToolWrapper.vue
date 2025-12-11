<template>
  <Tool
    :resource-name="resourceName"
    :resource-id="resourceId"
    :field="field"
  />
</template>

<script>
import Tool from '../pages/Tool.vue'

export default {
  components: {
    Tool,
  },

  props: {
    resourceName: {
        type: String,
        required: true,
    },
    resourceId: {
        type: [String, Number],
        required: true,
    },
    field: {
        type: Object,
        default: () => ({}),
    },
  },
}
</script>
