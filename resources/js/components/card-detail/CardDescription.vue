<template>
    <div class="flex items-start space-x-4">
        <Icon name="bars-3-bottom-left" class="w-6 h-6 mt-1 text-gray-500" />
        <div class="flex-1">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200">Description</h3>
                <button type="button" v-if="!isEditingDescription && card.description" @click="startEditing" 
                class="bg-gray-100 dark:bg-gray-700 px-3 py-1.5 rounded text-sm font-medium text-gray-600 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-900">
                    Edit</button>
            </div>
            
            <div v-if="isEditingDescription || !card.description" class="mb-4">
                <div class="markdown-editor-wrapper">
                    <textarea ref="markdownTextarea"></textarea>
                </div>

                <div class="flex items-center space-x-2 mt-2" v-if="isEditingDescription || !card.description">
                    <button type="button" @click="saveDescription" class="shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm">Save</button>
                    <button type="button" @click="cancelDescriptionEdit" class="focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring-2 rounded border-2 border-gray-200 dark:border-gray-500 hover:border-primary-500 active:border-primary-400 dark:hover:border-gray-400 dark:active:border-gray-300 bg-white dark:bg-transparent text-primary-500 dark:text-gray-400 px-3 h-9 inline-flex items-center font-bold text-sm">Cancel</button>
                </div>
            </div>
            <div v-else @click="startEditing" class="prose dark:prose-invert max-w-none cursor-pointer -ml-2 rounded">
                <div v-if="displayDescription" v-html="renderedDescription" class="text-sm text-gray-800 dark:text-gray-200"></div>
                <p v-else class="text-sm text-gray-400 italic">Add a more detailed description...</p>
            </div>
        </div>
    </div>

    <!-- Discard Description Changes Modal (Teleported with high z-index) -->
    <Teleport to="body">
        <template v-if="showDiscardDescriptionModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 100;" @click="showDiscardDescriptionModal = false"></div>
            <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                        <ModalHeader>Discard Changes</ModalHeader>
                        <div class="p-6">
                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                Are you sure you want to discard your changes?
                            </p>
                        </div>
                        <ModalFooter>
                            <div class="flex items-center ml-auto">
                                <Button variant="link" state="mellow" @click.prevent="showDiscardDescriptionModal = false" class="mr-3">Keep Editing</Button>
                                <Button state="danger" @click="confirmDiscardDescription">Discard</Button>
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
import { marked } from 'marked'
import EasyMDE from 'easymde'
import 'easymde/dist/easymde.min.css' // Ensure styles are present

marked.setOptions({
    breaks: true,
})
export default {
    components: { Icon, Button },
    props: {
        card: Object
    },
    emits: ['update'],
    data() {
        return {
            isEditingDescription: false,
            currentDescription: '',
            localDisplayDescription: null,
            easyMDE: null,
            showDiscardDescriptionModal: false,
        }
    },
    computed: {
        displayDescription() {
            return this.localDisplayDescription !== null ? this.localDisplayDescription : this.card.description;
        },
        renderedDescription() {
            if (!this.displayDescription) return '';
            return marked.parse(this.displayDescription);
        },
    },
    watch: {
        card: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    this.currentDescription = newVal.description || '';
                    this.localDisplayDescription = newVal.description;
                    
                    // Handle editor state on card change
                    this.$nextTick(() => {
                        if (!newVal.description) {
                            this.startEditing();
                        } else {
                            // Reset to view mode if switching to a card with description
                            this.isEditingDescription = false;
                            if (this.easyMDE) {
                                this.easyMDE.toTextArea();
                                this.easyMDE = null;
                            }
                        }
                    });
                }
            }
        }
    },
    mounted() {
        if (!this.card.description) {
            this.startEditing();
        }
    },
    methods: {
        initEasyMDE() {
            this.$nextTick(() => {
                if (this.easyMDE) {
                    this.easyMDE.toTextArea();
                    this.easyMDE = null;
                }

                if (this.$refs.markdownTextarea) {
                    this.easyMDE = new EasyMDE({
                        element: this.$refs.markdownTextarea,
                        initialValue: this.currentDescription,
                        spellChecker: false,
                        status: false,
                        placeholder: 'Add a more detailed description...',
                        autoDownloadFontAwesome: true, 
                        forceSync: true,
                        toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', 'image', '|', 'preview', 'side-by-side', 'fullscreen'],
                    });

                    this.easyMDE.codemirror.on('change', () => {
                        this.currentDescription = this.easyMDE.value();
                    });
                }
            });
        },
        startEditing() {
            this.isEditingDescription = true;
            this.currentDescription = this.displayDescription || '';
            this.initEasyMDE();
        },
        async saveDescription() {
             try {
                 // Ensure we get latest value
                 if (this.easyMDE) {
                     this.currentDescription = this.easyMDE.value();
                 }

                 // Optimistic update
                 const newDescription = this.currentDescription;
                 this.localDisplayDescription = newDescription;
                 
                 // Cleanup editor
                 if (this.easyMDE) {
                     this.easyMDE.toTextArea();
                     this.easyMDE = null;
                 }
                 this.isEditingDescription = false;

                 await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}`, {
                     description: newDescription
                 });
                 
                 this.$emit('update');
                 Nova.success('Description saved');
             } catch (e) {
                 Nova.error('Failed to update description');
             }
        },
        cancelDescriptionEdit() {
            // Get current editor value
            const editorValue = this.easyMDE ? this.easyMDE.value().trim() : '';
            const originalValue = (this.card.description || '').trim();
            
            // If there's new text that differs from original, ask for confirmation
            if (editorValue && editorValue !== originalValue) {
                this.showDiscardDescriptionModal = true;
                return;
            }
            
            this.doDiscardDescription();
        },
        confirmDiscardDescription() {
            this.showDiscardDescriptionModal = false;
            this.doDiscardDescription();
        },
        doDiscardDescription() {
            // Cleanup editor
            if (this.easyMDE) {
                this.easyMDE.toTextArea();
                this.easyMDE = null;
            }
            
            // Reset to original value
            this.currentDescription = this.card.description || '';
            this.localDisplayDescription = this.card.description;
            this.isEditingDescription = false;
            
            // If card has no description, re-init the editor in empty state (cleared)
            if (!this.card.description) {
                this.$nextTick(() => {
                    this.isEditingDescription = true;
                    this.currentDescription = '';
                    this.initEasyMDE();
                });
            }
        },
    },
    beforeUnmount() {
        if (this.easyMDE) {
            this.easyMDE.toTextArea();
            this.easyMDE = null;
        }
    }
}
</script>

<style>
/* EasyMDE Overrides for Nova/Tailwind compatibility */
.editor-toolbar {
    border-color: #e5e7eb !important;
    background-color: #f9fafb !important;
    color: #374151 !important;
}
.dark .editor-toolbar {
    border-color: #374151 !important;
    background-color: #1f2937 !important;
}
.dark .editor-toolbar i {
    color: #d1d5db !important;
}
.dark .editor-toolbar button:hover, .dark .editor-toolbar button.active {
    background-color: #374151 !important;
    border-color: #4b5563 !important;
}

.CodeMirror {
    border-color: #e5e7eb !important;
    background-color: #ffffff !important;
    color: #1f2937 !important;
}
.dark .CodeMirror {
    border-color: #374151 !important;
    background-color: #111827 !important;
    color: #d1d5db !important;
}
.CodeMirror-cursor {
    border-left: 1px solid #000 !important;
}
.dark .CodeMirror-cursor {
    border-left: 1px solid #fff !important;
}
.editor-preview {
    background-color: #ffffff !important;
}
.dark .editor-preview {
    background-color: #111827 !important;
    color: #d1d5db !important;
}
.editor-statusbar {
    display: none !important; 
}
</style>
