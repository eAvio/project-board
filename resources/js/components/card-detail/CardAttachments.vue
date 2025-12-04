<template>
    <div class="mb-8" v-if="attachments && attachments.length">
        <div class="flex items-center mb-4">
            <Icon name="paper-clip" class="w-5 h-5 text-gray-500 mr-2" />
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">Attachments</h3>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div 
                v-for="media in attachments" 
                :key="media.id" 
                class="group relative bg-white hover:bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200"
            >
                <!-- Image/Preview Area -->
                <div 
                    class="w-full bg-gray-100 dark:bg-gray-700 relative cursor-pointer overflow-hidden"
                    style="height: 140px;"
                    @click="isImage(media) ? openLightbox(media) : null"
                >
                    <img 
                        v-if="isImage(media)" 
                        :src="getMediaUrl(media)" 
                        class="w-full h-full object-cover block transition-transform duration-300 group-hover:scale-105" 
                        style="object-fit: cover; height: 100%; width: 100%;"
                    />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                        <Icon name="document-text" class="w-8 h-8 mb-1 opacity-50" />
                        <span class="font-bold text-[10px] uppercase tracking-wider">{{ media.extension || 'FILE' }}</span>
                    </div>

                    <!-- Cover Badge -->
                    <div v-if="isCover(media)" class="absolute top-1 right-1 bg-white text-yellow-400 rounded-full p-0.5 shadow-md z-10" title="Cover Image">
                        <Icon name="star" class="w-3 h-3" />
                    </div>
                </div>
                
                <!-- Footer Info & Actions -->
                <div class="p-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <a 
                        :href="getMediaUrl(media)" 
                        target="_blank"
                        class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate block hover:text-primary-500 transition-colors mb-1" 
                        :title="media.file_name"
                        @click.stop
                    >
                        {{ formatFileName(media.file_name) }}
                    </a>
                    <div class="text-[10px] text-gray-400 mb-2">
                        {{ formatTime(media.created_at) }}
                    </div>

                    <!-- Action Buttons (Always Visible) -->
                    <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-700 pt-2 mt-1">
                         <button 
                            @click.stop="deleteAttachment(media)" 
                            class="text-gray-400 hover:text-red-500 transition-colors"
                            title="Delete"
                        >
                            <Icon name="trash" class="w-3 h-3" />
                        </button>

                        <button 
                            @click.stop="renameAttachment(media)" 
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            title="Rename"
                        >
                            <Icon name="pencil" class="w-3 h-3" />
                        </button>
                        <!-- ADD THIS DOWNLOAD BUTTON -->
                        <a
                            :href="getMediaUrl(media)"
                            :download="media.file_name"
                            @click.stop
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            title="Download"
                        >
                            <Icon name="arrow-down-tray" class="w-3 h-3" />
                        </a>

                        <template v-if="isImage(media)">
                            <button 
                                v-if="!isCover(media)"
                                @click.stop="setCover(media)" 
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                title="Set as Cover"
                            >
                                <Icon name="photo" class="w-3 h-3" />
                            </button>
                            <button 
                                v-else
                                @click.stop="removeCover" 
                                class="text-yellow-500 hover:text-red-500 transition-colors"
                                title="Remove from Header"
                            >
                                <Icon name="x-circle" class="w-3 h-3" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal - Teleported to body to escape stacking context -->
        <Teleport to="body">
            <div 
                v-if="lightboxMedia" 
                class="fixed inset-0 flex items-center justify-center bg-black/95 p-4 backdrop-blur-sm"
                style="z-index: 99999;"
                @click="closeLightbox"
                @keydown.esc="closeLightbox"
            >
                <div class="relative max-w-full max-h-full" @click.stop>
                    <img :src="getMediaUrl(lightboxMedia)" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
                    <button class="absolute -top-12 right-0 text-white/70 hover:text-white transition-colors" @click="closeLightbox">
                        <Icon name="x-mark" class="w-8 h-8" />
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Delete Attachment Modal (Teleported with high z-index) -->
        <Teleport to="body">
            <template v-if="showDeleteAttachmentModal">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 100;" @click="showDeleteAttachmentModal = false"></div>
                <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                            <ModalHeader>Delete Attachment</ModalHeader>
                            <div class="p-6">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">
                                    Are you sure you want to delete the attachment
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">"{{ attachmentToDelete ? formatFileName(attachmentToDelete.file_name) : '' }}"</span>?
                                </p>
                            </div>
                            <ModalFooter>
                                <div class="flex items-center ml-auto">
                                    <Button variant="link" state="mellow" @click.prevent="showDeleteAttachmentModal = false" class="mr-3">Cancel</Button>
                                    <Button state="danger" @click="confirmDeleteAttachment">Delete</Button>
                                </div>
                            </ModalFooter>
                        </div>
                    </div>
                </div>
            </template>
        </Teleport>
    </div>
</template>

<script>
import { Icon, Button } from 'laravel-nova-ui'
import { formatDistanceToNow } from 'date-fns'

export default {
    components: { Icon, Button },
    props: {
        card: Object
    },
    emits: ['update'],
    data() {
        return {
            lightboxMedia: null,
            showDeleteAttachmentModal: false,
            attachmentToDelete: null,
        }
    },
    computed: {
        attachments() {
            if (!this.card || !this.card.media) return [];
            // Filter out 'featured_image' collection if you treat it as a duplicate
            // or if you only want to show 'attachments' or 'default'
            return this.card.media.filter(m => m.collection_name !== 'featured_image');
        },
        coverImage() {
            if (!this.card || !this.card.media) return null;
            return this.card.media.find(m => m.collection_name === 'featured_image');
        }
    },
    methods: {
        formatFileName(name) {
            if (!name) return '';
            // Replace dashes, triple dashes, underscores with spaces
            return name.replace(/---/g, ' ').replace(/[-_]/g, ' ');
        },
        getMediaUrl(media) {
            if (!media || !media.original_url) return '';
            try {
                const parsed = new URL(media.original_url);
                return parsed.pathname;
            } catch(e) {
                return media.original_url;
            }
        },
        formatTime(date) {
            try {
                return formatDistanceToNow(new Date(date), { addSuffix: true });
            } catch (e) {
                return '';
            }
        },
        notImplemented(feature) {
            Nova.warning(`${feature} is not implemented yet.`);
        },
        isImage(media) {
            return media.mime_type && media.mime_type.startsWith('image/');
        },
        isCover(media) {
            if (!this.coverImage) return false;
            // Compare file_name and size as a proxy for "same image"
            return this.coverImage.file_name === media.file_name && this.coverImage.size === media.size;
        },
        openLightbox(media) {
            this.lightboxMedia = media;
        },
        closeLightbox() {
            this.lightboxMedia = null;
        },
        async setCover(media) {
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}/cover`, {
                    media_id: media.id
                });
                this.$emit('update');
                Nova.success('Cover updated');
            } catch (e) {
                Nova.error('Failed to set cover');
            }
        },
        async removeCover() {
            try {
                await Nova.request().delete(`/nova-vendor/project-board/cards/${this.card.id}/cover`);
                this.$emit('update');
                Nova.success('Cover removed');
            } catch (e) {
                Nova.error('Failed to remove cover');
            }
        },
        async deleteAttachment(media) {
            this.attachmentToDelete = media;
            this.showDeleteAttachmentModal = true;
        },
        async confirmDeleteAttachment() {
            if (!this.attachmentToDelete) {
                this.showDeleteAttachmentModal = false;
                return;
            }
            try {
                await Nova.request().delete(`/nova-vendor/project-board/cards/${this.card.id}/attachments/${this.attachmentToDelete.id}`);
                this.$emit('update');
                Nova.success('Attachment deleted');
            } catch (e) {
                Nova.error('Failed to delete attachment');
            } finally {
                this.attachmentToDelete = null;
                this.showDeleteAttachmentModal = false;
            }
        },
        async renameAttachment(media) {
            const currentName = this.formatFileName(media.file_name);
            const newName = prompt('Rename attachment:', currentName);
            if (!newName || newName === currentName) return;

            // Since we don't have a backend endpoint for renaming, we'll just warn for now
            // or we can try to implement it. 
            // For now, let's show a warning as per previous behavior but at least prompt works.
             Nova.warning('Rename functionality is coming soon!');
        }
    }
}
</script>
