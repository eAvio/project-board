<template>
    <div class="relative">
        <!-- Trigger Button -->
        <button 
            @click="togglePicker" 
            class="flex items-center px-3 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
            type="button"
        >
            <Icon name="photo" class="w-4 h-4 mr-2" />
            Change Background
        </button>

        <!-- Picker Popup -->
        <div 
            v-if="isOpen" 
            class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50"
            @click.stop
        >
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">Board Background</h3>
                    <button @click="closePicker" class="text-gray-400 hover:text-gray-600">
                        <Icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Search -->
                <div class="mb-4">
                    <input 
                        v-model="searchQuery"
                        @keydown.enter="searchPhotos"
                        type="text" 
                        placeholder="Search photos..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    />
                </div>

                <!-- Color Options -->
                <div class="mb-4">
                    <h4 class="text-xs font-bold text-gray-500 uppercase mb-2">Colors</h4>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            v-for="color in colors" 
                            :key="color"
                            @click="selectColor(color)"
                            class="w-10 h-8 rounded-md border-2 hover:scale-110 transition-transform"
                            :class="currentColor === color ? 'border-primary-500' : 'border-transparent'"
                            :style="{ backgroundColor: color }"
                        ></button>
                        <button 
                            @click="clearBackground"
                            class="w-10 h-8 rounded-md border-2 border-gray-300 dark:border-gray-600 hover:scale-110 transition-transform flex items-center justify-center text-gray-400"
                            title="Remove background"
                        >
                            <Icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Photos Grid -->
                <div class="mb-2">
                    <h4 class="text-xs font-bold text-gray-500 uppercase mb-2">Photos by Unsplash</h4>
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-500"></div>
                    </div>
                    <div v-else class="grid grid-cols-4 gap-2 max-h-48 overflow-y-auto">
                        <button 
                            v-for="photo in photos" 
                            :key="photo.id"
                            @click="selectPhoto(photo)"
                            class="relative aspect-video rounded-md overflow-hidden hover:ring-2 hover:ring-primary-500 transition-all group"
                        >
                            <img 
                                :src="photo.thumb" 
                                :alt="photo.alt"
                                class="w-full h-full object-cover"
                            />
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors"></div>
                        </button>
                    </div>
                </div>

                <!-- Attribution -->
                <p class="text-xs text-gray-400 mt-2">
                    Photos provided by <a href="https://unsplash.com" target="_blank" class="underline hover:text-gray-600">Unsplash</a>
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import { Icon } from 'laravel-nova-ui';

export default {
    name: 'BackgroundPicker',
    components: { Icon },
    props: {
        boardId: {
            type: [Number, String],
            required: true
        },
        currentBackground: {
            type: String,
            default: null
        },
        currentColor: {
            type: String,
            default: null
        }
    },
    emits: ['update'],
    data() {
        return {
            isOpen: false,
            searchQuery: '',
            photos: [],
            loading: false,
            colors: [
                '#0079bf', // Blue
                '#d29034', // Orange
                '#519839', // Green
                '#b04632', // Red
                '#89609e', // Purple
                '#cd5a91', // Pink
                '#4bbf6b', // Lime
                '#00aecc', // Cyan
                '#838c91', // Gray
            ]
        };
    },
    methods: {
        togglePicker() {
            this.isOpen = !this.isOpen;
            if (this.isOpen && this.photos.length === 0) {
                this.loadFeaturedPhotos();
            }
        },
        closePicker() {
            this.isOpen = false;
        },
        async loadFeaturedPhotos() {
            this.loading = true;
            try {
                const response = await Nova.request().get('/nova-vendor/project-board/unsplash/featured', {
                    params: { per_page: 16 }
                });
                this.photos = response.data.photos;
            } catch (e) {
                console.error('Failed to load photos', e);
            } finally {
                this.loading = false;
            }
        },
        async searchPhotos() {
            if (!this.searchQuery.trim()) {
                this.loadFeaturedPhotos();
                return;
            }
            this.loading = true;
            try {
                const response = await Nova.request().get('/nova-vendor/project-board/unsplash/search', {
                    params: { query: this.searchQuery, per_page: 16 }
                });
                this.photos = response.data.photos;
            } catch (e) {
                console.error('Failed to search photos', e);
            } finally {
                this.loading = false;
            }
        },
        async selectPhoto(photo) {
            try {
                // Track download per Unsplash guidelines
                await Nova.request().post('/nova-vendor/project-board/unsplash/track-download', {
                    download_location: photo.download_location
                });

                // Update board background
                await Nova.request().put(`/nova-vendor/project-board/boards/${this.boardId}`, {
                    background_url: photo.regular,
                    background_color: photo.color
                });

                this.$emit('update', { 
                    background_url: photo.regular, 
                    background_color: photo.color 
                });
                this.closePicker();
                Nova.success('Background updated');
            } catch (e) {
                Nova.error('Failed to update background');
            }
        },
        async selectColor(color) {
            try {
                await Nova.request().put(`/nova-vendor/project-board/boards/${this.boardId}`, {
                    background_url: null,
                    background_color: color
                });

                this.$emit('update', { 
                    background_url: null, 
                    background_color: color 
                });
                this.closePicker();
                Nova.success('Background updated');
            } catch (e) {
                Nova.error('Failed to update background');
            }
        },
        async clearBackground() {
            try {
                await Nova.request().put(`/nova-vendor/project-board/boards/${this.boardId}`, {
                    background_url: null,
                    background_color: null
                });

                this.$emit('update', { 
                    background_url: null, 
                    background_color: null 
                });
                this.closePicker();
                Nova.success('Background removed');
            } catch (e) {
                Nova.error('Failed to remove background');
            }
        }
    },
    mounted() {
        // Close picker when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.$el.contains(e.target)) {
                this.closePicker();
            }
        });
    }
};
</script>
