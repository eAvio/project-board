<template>
    <div class="flex items-start space-x-4">
        <Icon name="chat-bubble-left" class="w-6 h-6 mt-1 text-gray-500" />
        <div class="flex-1">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200">Activity</h3>
                <button type="button" @click="toggleActivityDetails" class="bg-gray-100 dark:bg-gray-700 px-3 py-1.5 rounded text-sm font-medium text-gray-600 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-900 transition-colors">
                    {{ showActivityDetails ? 'Hide Details' : 'Show Details' }}
                </button>
            </div>

            <div class="flex space-x-3 mb-6">
                <img 
                    :src="currentUser.avatar_url || `https://ui-avatars.com/api/?name=${currentUser.name}`"
                    class="w-8 h-8 rounded-full flex-shrink-0 object-cover"
                />
                <div class="flex-1 relative">
                    <textarea 
                        v-model="newComment"
                        class="w-full border border-gray-200 dark:border-gray-600 rounded-lg p-3 text-sm bg-white dark:bg-gray-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 shadow-sm"
                        placeholder="Write a comment..."
                        rows="1"
                        @focus="isCommentFocused = true"
                        @paste="handleCommentPaste"
                        @drop.prevent="handleCommentDrop"
                        @input="onCommentInput"
                        style="min-height: 40px; resize: none; overflow-y: hidden;"
                        ref="commentInput"
                    ></textarea>
                        
                    <!-- Mention List -->
                    <div v-if="showMentionList" class="absolute bottom-full left-0 mb-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 max-h-48 overflow-y-auto">
                        <div 
                            v-for="user in filteredUsers" 
                            :key="user.id"
                            @click="selectMention(user)"
                            class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer flex items-center space-x-2"
                        >
                            <img :src="user.avatar_url" class="w-6 h-6 rounded-full" />
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ user.name }}</span>
                        </div>
                    </div>
                    
                    <!-- Buttons below textarea (like edit mode) -->
                    <div v-if="isCommentFocused || newComment" class="flex items-center space-x-2 mt-2">
                        <button
                            type="button"
                            @click="addComment"
                            class="shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm disabled:opacity-50"
                            :disabled="!newComment.trim()"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            @click="cancelComment"
                            class="focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring-2 rounded border-2 border-gray-200 dark:border-gray-500 hover:border-primary-500 active:border-primary-400 dark:hover:border-gray-400 dark:active:border-gray-300 bg-white dark:bg-transparent text-primary-500 dark:text-gray-400 px-3 h-9 inline-flex items-center font-bold text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Activity Feed -->
            <div class="space-y-4">
                <div v-for="item in activityFeed" :key="item.unique_id" class="flex space-x-3 group">
                    <img 
                        v-if="item.user"
                        :src="item.user.avatar_url || `https://ui-avatars.com/api/?name=${item.user.name}`"
                        class="w-8 h-8 rounded-full flex-shrink-0 object-cover"
                    />
                    <div v-else class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-500">?</div>

                    <div class="flex-1">
                        <div class="flex items-baseline space-x-2">
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ item.user ? item.user.name : 'Unknown' }}</span>
                            
                            <span v-if="item.is_activity" class="text-sm text-gray-600 dark:text-gray-400">
                                {{ item.text }}
                            </span>
                            
                            <span class="text-xs text-gray-500" :title="new Date(item.created_at).toLocaleString()">{{ formatTime(item.created_at) }}</span>
                        </div>
                        
                        <!-- Comment Content -->
                        <div v-if="!item.is_activity">
                            <div v-if="editingCommentId === item.id" class="mt-1">
                                <textarea 
                                    v-model="editingCommentContent"
                                    class="w-full border border-gray-200 dark:border-gray-600 rounded-lg p-2 text-sm bg-white dark:bg-gray-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 shadow-sm"
                                    rows="2"
                                    @paste="handleEditCommentPaste"
                                    @drop.prevent="handleEditCommentDrop"
                                ></textarea>
                                <div class="flex items-center space-x-2 mt-2">
                                    <button type="button" @click="updateComment" class="shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm disabled:opacity-50" :disabled="!editingCommentContent.trim()">Save</button>
                                    <button type="button" @click="cancelEditingComment" class="focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring-2 rounded border-2 border-gray-200 dark:border-gray-500 hover:border-primary-500 active:border-primary-400 dark:hover:border-gray-400 dark:active:border-gray-300 bg-white dark:bg-transparent text-primary-500 dark:text-gray-400 px-3 h-9 inline-flex items-center font-bold text-sm">Cancel</button>
                                </div>
                            </div>
                            <div v-else>
                                <div class="text-sm text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 p-2 rounded shadow-sm border border-gray-100 dark:border-gray-700 prose dark:prose-invert max-w-none mt-1">
                                    <div v-html="formatComment(item.content)"></div>
                                    
                                    <!-- Reactions Display -->
                                    <div v-if="item.reactions && item.reactions.length" class="flex flex-wrap gap-1 mt-2">
                                        <div 
                                            v-for="reaction in groupReactions(item.reactions)" 
                                            :key="reaction.emoji"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-xs cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600"
                                            :title="reaction.users.join(', ')"
                                            @click="addEmojiReaction(item, reaction.emoji)"
                                        >
                                            <span class="mr-1">{{ reaction.emoji }}</span>
                                            <span class="font-semibold text-gray-600 dark:text-gray-400">{{ reaction.count }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3 mt-1 text-xs text-gray-500 transition-opacity">
                                    <div class="relative">
                                        <button type="button" class="link-default text-xs" @click.stop="toggleEmojiPicker(item)">
                                            <Icon name="face-smile" class="w-3 h-3 inline" />
                                        </button>
                                        <!-- Emoji Picker -->
                                        <div v-if="showEmojiPicker === item.id" v-click-outside="closeEmojiPicker" class="absolute bottom-full left-0 mb-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-lg p-2 z-50 w-64 h-48 overflow-y-auto grid grid-cols-8 gap-1">
                                            <button v-for="emoji in commonEmojis" :key="emoji" @click="addEmojiReaction(item, emoji)" class="hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded text-lg leading-none">
                                                {{ emoji }}
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" class="link-default text-xs hover:underline" @click="replyToComment(item)">Reply</button>
                                    <button type="button" class="link-default text-xs hover:underline" @click="startEditingComment(item)">Edit</button>
                                    <button type="button" class="link-default text-xs text-red-500 hover:text-red-400 hover:underline" @click="deleteComment(item)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Comment Modal (Teleported with high z-index) -->
    <Teleport to="body">
        <template v-if="showDeleteCommentModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 100;" @click="showDeleteCommentModal = false"></div>
            <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                        <ModalHeader>Delete Comment</ModalHeader>
                        <div class="p-6">
                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                Are you sure you want to delete this comment? This action cannot be undone.
                            </p>
                        </div>
                        <ModalFooter>
                            <div class="flex items-center ml-auto">
                                <Button variant="link" state="mellow" @click.prevent="showDeleteCommentModal = false" class="mr-3">Cancel</Button>
                                <Button state="danger" @click="confirmDeleteComment">Delete</Button>
                            </div>
                        </ModalFooter>
                    </div>
                </div>
            </div>
        </template>
    </Teleport>
</template>

<script>
import { Icon, Button } from 'laravel-nova-ui'
import { marked } from 'marked'
import { formatDistanceToNow } from 'date-fns'

export default {
    components: { Icon, Button },
    props: {
        card: Object,
        currentUser: Object,
        users: Array,
    },
    emits: ['update'],
    data() {
        return {
            newComment: '',
            isCommentFocused: false,
            showActivityDetails: false,
            activities: [],
            editingCommentId: null,
            editingCommentContent: '',
            showEmojiPicker: null,
            commonEmojis: ['👍', '👎', '😄', '🎉', '😕', '❤️', '🚀', '👀'],
            showMentionList: false,
            mentionQuery: '',
            mentionIndex: -1,
            showDeleteCommentModal: false,
            commentToDelete: null,
        }
    },
    computed: {
        filteredUsers() {
            if (!this.mentionQuery) return [];
            return this.users.filter(u => u.name.toLowerCase().includes(this.mentionQuery.toLowerCase()));
        },
        activityFeed() {
            let feed = [];
            // Add comments
            if (this.card && this.card.comments) {
                feed = this.card.comments.map(c => ({ ...c, is_activity: false, unique_id: 'c_' + c.id }));
            }
            // Add activities if details shown
            if (this.showActivityDetails && this.activities) {
                const activities = this.activities.map(a => ({ ...a, is_activity: true, unique_id: 'a_' + a.id, created_at: a.created_at }));
                feed = [...feed, ...activities];
            }
            // Sort by date desc
            return feed.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
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
    methods: {
        formatTime(date) {
            try {
                return formatDistanceToNow(new Date(date), { addSuffix: true });
            } catch (e) {
                return '';
            }
        },
        formatComment(content) {
            if (!content) return '';
            let html = marked.parse(content);
            html = html.replace(/(@[\w]+)/g, '<span class="text-primary-500 font-bold">$1</span>');
            return html;
        },
        async toggleActivityDetails() {
            this.showActivityDetails = !this.showActivityDetails;
            if (this.showActivityDetails) {
                await this.fetchActivities();
            }
        },
        async fetchActivities() {
            try {
                const { data } = await Nova.request().get(`/nova-vendor/project-board/cards/${this.card.id}/activities`);
                this.activities = data;
            } catch (e) {
                console.error('Failed to fetch activities');
            }
        },
        async addComment() {
            if (!this.newComment.trim()) return;
            try {
                const { data } = await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/comments`, {
                    content: this.newComment
                });
                
                // Update local state immediately with the new comment
                if (this.card) {
                    if (!this.card.comments) this.card.comments = [];
                    // Add the returned comment (with proper user data)
                    this.card.comments.push(data);
                }

                // Clear input
                this.newComment = '';
                this.isCommentFocused = false;
                this.showMentionList = false;
                
                this.$emit('update');
                Nova.success('Comment added');
            } catch (e) {
                console.error('Failed to add comment:', e);
                Nova.error(e.response?.data?.message || 'Failed to add comment');
            }
        },
        cancelComment() {
            this.newComment = '';
            this.isCommentFocused = false;
            this.showMentionList = false;
        },
        handleCommentPaste(e) {
            this.handlePaste(e, 'new');
        },
        handleEditCommentPaste(e) {
            this.handlePaste(e, 'edit');
        },
        handlePaste(e, target) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (let index in items) {
                const item = items[index];
                if (item.kind === 'file' && item.type.indexOf('image/') !== -1) {
                    const blob = item.getAsFile();
                    this.uploadCommentImage(blob, target);
                    e.preventDefault(); 
                    return;
                }
            }
        },
        handleCommentDrop(e) {
            this.handleDrop(e, 'new');
        },
        handleEditCommentDrop(e) {
            this.handleDrop(e, 'edit');
        },
        handleDrop(e, target) {
            const files = e.dataTransfer.files;
            if (files.length && files[0].type.startsWith('image/')) {
                this.uploadCommentImage(files[0], target);
            }
        },
        async uploadCommentImage(file, target = 'new') {
            const placeholder = " [Image Uploading...] ";
            
            if (target === 'new') {
                this.newComment = (this.newComment || '') + placeholder;
            } else {
                this.editingCommentContent = (this.editingCommentContent || '') + placeholder;
            }

            try {
                const formData = new FormData();
                formData.append('image', file);
                // We use the attachments endpoint which returns the URL
                const { data } = await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/attachments`, formData);
                
                const markdown = ` ![](${data.url}) `;
                
                if (target === 'new') {
                    this.newComment = this.newComment.replace(placeholder, markdown);
                } else {
                    this.editingCommentContent = this.editingCommentContent.replace(placeholder, markdown);
                }
            } catch(e) {
                const errorMsg = " [Upload Failed] ";
                if (target === 'new') {
                    this.newComment = this.newComment.replace(placeholder, errorMsg);
                } else {
                    this.editingCommentContent = this.editingCommentContent.replace(placeholder, errorMsg);
                }
                Nova.error('Failed to upload image');
            }
        },
        onCommentInput(e) {
            const val = e.target.value;
            const cursorPosition = e.target.selectionStart;
            const textBeforeCursor = val.substring(0, cursorPosition);
            const atSymbolIndex = textBeforeCursor.lastIndexOf('@');
            
            if (atSymbolIndex !== -1) {
                this.mentionQuery = textBeforeCursor.substring(atSymbolIndex + 1);
                if (!this.mentionQuery.includes(' ')) {
                    this.showMentionList = true;
                    return;
                }
            }
            this.showMentionList = false;
        },
        selectMention(user) {
            const cursorPosition = this.$refs.commentInput.selectionStart;
            const text = this.newComment;
            const textBefore = text.substring(0, cursorPosition);
            const textAfter = text.substring(cursorPosition);
            const atIndex = textBefore.lastIndexOf('@');
            
            this.newComment = textBefore.substring(0, atIndex) + `@${user.name} ` + textAfter;
            this.showMentionList = false;
            this.$nextTick(() => {
                this.$refs.commentInput.focus();
            });
        },
        startEditingComment(item) {
            this.editingCommentId = item.id;
            this.editingCommentContent = item.content;
        },
        cancelEditingComment() {
            this.editingCommentId = null;
            this.editingCommentContent = '';
        },
        async updateComment() {
            if (!this.editingCommentContent.trim()) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/comments/${this.editingCommentId}`, {
                    content: this.editingCommentContent
                });

                // Update local state
                if (this.card && this.card.comments) {
                    const comment = this.card.comments.find(c => c.id === this.editingCommentId);
                    if (comment) {
                        comment.content = this.editingCommentContent;
                    }
                }

                this.editingCommentId = null;
                this.$emit('update');
                Nova.success('Comment updated');
            } catch(e) {
                Nova.error('Failed to update comment');
            }
        },
        async deleteComment(item) {
            this.commentToDelete = item;
            this.showDeleteCommentModal = true;
        },
        async confirmDeleteComment() {
            if (!this.commentToDelete) {
                this.showDeleteCommentModal = false;
                return;
            }
            try {
                await Nova.request().delete(`/nova-vendor/project-board/comments/${this.commentToDelete.id}`);
                if (this.card && this.card.comments) {
                    const index = this.card.comments.findIndex(c => c.id === this.commentToDelete.id);
                    if (index > -1) {
                        this.card.comments.splice(index, 1);
                    }
                }
                this.$emit('update');
                Nova.success('Comment deleted');
            } catch (e) {
                Nova.error('Failed to delete comment');
            } finally {
                this.commentToDelete = null;
                this.showDeleteCommentModal = false;
            }
        },
        replyToComment(item) {
            if (!item.user) return;
            const mention = `@${item.user.name} `;
            this.newComment = mention + this.newComment;
            this.isCommentFocused = true;
            this.$nextTick(() => {
                this.$refs.commentInput.focus();
            });
        },
        toggleEmojiPicker(item) {
            if (this.showEmojiPicker === item.id) {
                this.showEmojiPicker = null;
            } else {
                this.showEmojiPicker = item.id;
            }
        },
        closeEmojiPicker() {
            this.showEmojiPicker = null;
        },
        async addEmojiReaction(item, emoji) {
            try {
                const { data } = await Nova.request().post(`/nova-vendor/project-board/comments/${item.id}/reactions`, {
                    emoji
                });
                
                // Update local state
                if (this.card && this.card.comments) {
                    const comment = this.card.comments.find(c => c.id === item.id);
                    if (comment) {
                        comment.reactions = data.reactions;
                    }
                }

                this.showEmojiPicker = null;
                this.$emit('update');
                Nova.success('Reaction updated');
            } catch (e) {
                Nova.error('Failed to update reaction');
            }
        },
        groupReactions(reactions) {
            if (!reactions) return [];
            const groups = {};
            reactions.forEach(r => {
                if (!groups[r.emoji]) {
                    groups[r.emoji] = { emoji: r.emoji, count: 0, users: [] };
                }
                groups[r.emoji].count++;
                if (r.user) {
                    groups[r.emoji].users.push(r.user.name);
                }
            });
            return Object.values(groups);
        },
    }
}
</script>
