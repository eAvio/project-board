<template>
  <div class="resource-tool-wrapper">
    <Tool
      :resourceName="effectiveResourceName"
      :resourceId="effectiveResourceId"
      :field="effectiveField"
      :initialUser="currentUser"
    />
  </div>
</template>

<script>
import Tool from '../pages/Tool.vue'

export default {
  name: 'ResourceToolWrapper',

  components: {
    Tool,
  },

  // Nova 4 resource tools receive various props depending on context
  props: {
    resourceName: String,
    resourceId: [String, Number],
    panel: Object,
    field: Object,
    // Nova may pass resource info in different ways
    resource: Object,
    viaResource: String,
    viaResourceId: [String, Number],
  },

  computed: {
    // Try multiple sources for resourceName
    effectiveResourceName() {
      // Direct prop
      if (this.resourceName) return this.resourceName;
      // From $attrs (Nova might pass it there)
      if (this.$attrs.resourceName) return this.$attrs.resourceName;
      // From panel fields
      if (this.panel?.fields?.[0]?.resourceName) return this.panel.fields[0].resourceName;
      // From viaResource (common in Nova)
      if (this.viaResource) return this.viaResource;
      // From field metadata
      if (this.field?.resourceName) return this.field.resourceName;
      
      console.warn('[ProjectBoard] Could not determine resourceName');
      return null;
    },

    // Try multiple sources for resourceId
    effectiveResourceId() {
      // Direct prop
      if (this.resourceId) return this.resourceId;
      // From $attrs
      if (this.$attrs.resourceId) return this.$attrs.resourceId;
      // From panel fields
      if (this.panel?.fields?.[0]?.resourceId) return this.panel.fields[0].resourceId;
      // From viaResourceId
      if (this.viaResourceId) return this.viaResourceId;
      // From field metadata
      if (this.field?.resourceId) return this.field.resourceId;
      
      console.warn('[ProjectBoard] Could not determine resourceId');
      return null;
    },

    effectiveField() {
      // Field could be passed directly or as first item in panel.fields
      return this.field || this.panel?.fields?.[0] || null;
    },

    currentUser() {
      // Try to get currentUser from field metadata (set via jsonSerialize)
      const field = this.effectiveField;
      if (field && field.currentUser) {
        return field.currentUser;
      }
      // Fallback to Nova's global user config
      return Nova.config('user') || null;
    },
  },

  mounted() {
    console.log('[ProjectBoard ResourceTool] Mounted with:', {
      resourceName: this.effectiveResourceName,
      resourceId: this.effectiveResourceId,
      field: this.effectiveField,
      '$props': this.$props,
      '$attrs': this.$attrs,
    });
  },
}
</script>
