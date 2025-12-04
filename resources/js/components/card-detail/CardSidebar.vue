<template>
    <div class="md:col-span-1 space-y-6">
        <div>
            <h4 class="text-xs uppercase font-bold text-gray-500 mb-2">Add to card</h4>
            <div class="space-y-2 relative">
                <!-- Members Button -->
                <div class="relative">
                    <button 
                        type="button" 
                        class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" 
                        @click.stop="onPopupButtonClick('members', $event)"
                    >
                        <Icon name="user" class="w-4 h-4 mr-2" /> Members
                    </button>
                    <!-- Members Popup -->
                    <div 
                        v-if="activePopup === 'members'" 
                        v-click-outside="closePopups"
                        class="popup-container absolute left-0 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 overflow-hidden"
                        :class="popupPositionClass"
                        ref="membersPopup"
                        style="width: 18rem;"
                        @click.stop
                    >
                        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div></div>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">Members</span>
                            <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="x-mark" class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="p-3 max-h-64 overflow-y-auto">
                            <div 
                                v-for="user in users" 
                                :key="user.id"
                                @click="toggleMember(user)"
                                class="flex items-center px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer rounded"
                            >
                                <img :src="user.avatar_url" class="w-6 h-6 rounded-full mr-2" />
                                <span class="text-sm text-gray-700 dark:text-gray-200 flex-1 truncate">{{ user.name }}</span>
                                <Icon v-if="isMemberSelected(user)" name="check" class="w-4 h-4 text-green-500" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Labels Button -->
                <div class="relative">
                    <button 
                        type="button" 
                        class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" 
                        @click.stop="onPopupButtonClick('labels', $event)"
                    >
                        <Icon name="tag" class="w-4 h-4 mr-2" /> Labels
                    </button>
                    <!-- Labels Popup -->
                    <div 
                        v-if="activePopup === 'labels'" 
                        v-click-outside="closePopups"
                        class="popup-container absolute left-0 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 overflow-hidden"
                        :class="popupPositionClass"
                        ref="labelsPopup"
                        style="width: 18rem;"
                        @click.stop
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <button v-if="isCreatingLabel || editingLabel" @click="backToLabelList" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="chevron-left" class="w-4 h-4" />
                            </button>
                            <div v-else></div>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">{{ popupTitle }}</span>
                            <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="x-mark" class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Labels List -->
                        <div v-if="!isCreatingLabel && !editingLabel" class="p-3">
                            <input 
                                type="text" 
                                v-model="labelSearch"
                                placeholder="Search labels..." 
                                class="w-full form-control form-input form-control-bordered text-sm mb-3 h-9"
                            />
                            
                            <div class="space-y-1 max-h-64 overflow-y-auto mb-3">
                                <div 
                                    v-for="label in filteredLabels" 
                                    :key="label.id"
                                    class="flex items-center group"
                                >
                                    <div 
                                        @click="toggleLabel(label)"
                                       class="flex-1 h-8 rounded px-3 flex items-center gap-2 cursor-pointer hover:opacity-80 transition-all relative overflow-hidden w-0"
                                        :style="{ backgroundColor: label.color || '#e5e7eb' }"
                                    >
                                        <span class="font-bold text-white text-sm drop-shadow-sm relative z-10 truncate flex-1">{{ label.name }}</span>
                                       <Icon v-if="isLabelSelected(label)" name="check" class="w-4 h-4 text-white drop-shadow-sm relative z-10 flex-shrink-0" />
                                    </div>
                                    <button class="ml-3 p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded" @click="startEditingLabel(label)">
                                        <Icon name="pencil" class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                            
                            <button 
                                @click="isCreatingLabel = true" 
                                class="w-full py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-200 text-sm font-medium rounded transition-colors"
                            >
                                Create a new label
                            </button>
                        </div>
                        
                        <!-- Create Label Form -->
                        <div v-else-if="isCreatingLabel" class="p-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                            <input 
                                v-model="newLabelName" 
                                class="w-full form-control form-input form-control-bordered text-sm mb-3"
                                @keydown.enter="createLabel"
                                ref="createLabelInput"
                            />
                            
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select a color</label>
                            <div class="grid grid-cols-5 gap-2 mb-4" style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));">
                                <div 
                                    v-for="color in trelloColors" 
                                    :key="color"
                                    @click="newLabelColor = color"
                                    class="h-8 rounded cursor-pointer transition-all duration-200 flex items-center justify-center transform hover:scale-110 hover:shadow-md"
                                    :class="{ 'ring-2 ring-offset-2 ring-gray-300 dark:ring-gray-600 scale-110 shadow-sm': newLabelColor === color }"
                                    :style="{ backgroundColor: color }"
                                >
                                    <Icon v-if="newLabelColor === color" name="check" class="w-4 h-4 text-white drop-shadow-md" />
                                </div>
                                <div 
                                    @click="newLabelColor = null"
                                    class="h-8 rounded bg-gray-200 dark:bg-gray-600 cursor-pointer flex items-center justify-center hover:bg-gray-300 dark:hover:bg-gray-500"
                                    :class="{ 'ring-2 ring-offset-2 ring-gray-300 dark:ring-gray-600': !newLabelColor }"
                                >
                                    <Icon v-if="!newLabelColor" name="check" class="w-4 h-4 text-gray-500" />
                                </div>
                            </div>
                            
                            <div v-if="!newLabelColor" class="mb-4 text-xs text-gray-500">
                                No color. This won't show up on the front of cards.
                            </div>

                            <div class="flex items-center justify-between">
                                <button @click="createLabel" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded font-bold text-sm transition-colors" :disabled="!newLabelName">Create</button>
                            </div>
                        </div>

                         <!-- Edit Label Form -->
                        <div v-else-if="editingLabel" class="p-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                            <input 
                                v-model="newLabelName" 
                                class="w-full form-control form-input form-control-bordered text-sm mb-3"
                                @keydown.enter="updateLabel"
                                ref="editLabelInput"
                            />
                            
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select a color</label>
                            <div class="grid grid-cols-5 gap-2 mb-4" style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));">
                                <div 
                                    v-for="color in trelloColors" 
                                    :key="color"
                                    @click="newLabelColor = color"
                                    class="h-8 rounded cursor-pointer transition-all duration-200 flex items-center justify-center transform hover:scale-110 hover:shadow-md"
                                    :class="{ 'ring-2 ring-offset-2 ring-gray-300 dark:ring-gray-600 scale-110 shadow-sm': newLabelColor === color }"
                                    :style="{ backgroundColor: color }"
                                >
                                    <Icon v-if="newLabelColor === color" name="check" class="w-4 h-4 text-white drop-shadow-md" />
                                </div>
                                <div 
                                    @click="newLabelColor = null"
                                    class="h-8 rounded bg-gray-200 dark:bg-gray-600 cursor-pointer flex items-center justify-center hover:bg-gray-300 dark:hover:bg-gray-500"
                                    :class="{ 'ring-2 ring-offset-2 ring-gray-300 dark:ring-gray-600': !newLabelColor }"
                                >
                                    <Icon v-if="!newLabelColor" name="check" class="w-4 h-4 text-gray-500" />
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <button @click="updateLabel" class="bg-primary-500 hover:bg-primary-400 text-white px-4 py-2 rounded font-bold text-sm transition-colors" :disabled="!newLabelName">Save</button>
                                <button @click="deleteLabel" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-bold text-sm transition-colors">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" @click="$emit('add-checklist')">
                    <Icon name="check-circle" class="w-4 h-4 mr-2" /> Checklist
                </button>
                <div class="relative">
                    <button 
                        type="button" 
                        class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" 
                        @click.stop="onPopupButtonClick('dates', $event)"
                    >
                        <Icon name="clock" class="w-4 h-4 mr-2" /> Dates
                    </button>
                    <!-- Dates Popup -->
                    <div 
                        v-if="activePopup === 'dates'" 
                        v-click-outside="closePopups"
                        class="popup-container absolute left-0 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 overflow-hidden"
                        :class="popupPositionClass"
                        ref="datesPopup"
                        style="width: 18rem;"
                        @click.stop
                    >
                        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div></div>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">Dates</span>
                            <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="x-mark" class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="p-3">
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Due Date</label>
                            <input 
                                type="date" 
                                v-model="localDueDate" 
                                class="w-full form-control form-input form-control-bordered text-sm mb-3" 
                            />
                            
                            <div class="flex flex-col space-y-2">
                                <button @click="saveDueDate" class="bg-primary-500 hover:bg-primary-400 text-white px-4 py-2 rounded font-bold text-sm w-full">Save</button>
                                <button v-if="card.due_date" @click="removeDueDate" class="bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 px-4 py-2 rounded font-bold text-sm w-full">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" @click="triggerAttachmentUpload">
                    <Icon name="paper-clip" class="w-4 h-4 mr-2" /> Attachment
                </button>
                <input type="file" ref="attachmentInput" class="hidden" @change="handleAttachmentUpload" />

                <!-- Estimates Button -->
                <div class="relative">
                    <button 
                        type="button" 
                        class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" 
                        @click.stop="onPopupButtonClick('estimates', $event)"
                    >
                        <Icon name="calculator" class="w-4 h-4 mr-2" /> Estimates
                    </button>
                    <!-- Estimates Popup -->
                    <div 
                        v-if="activePopup === 'estimates'" 
                        v-click-outside="closePopups"
                        class="popup-container absolute left-0 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 overflow-hidden"
                        :class="popupPositionClass"
                        ref="estimatesPopup"
                        style="width: 18rem;"
                        @click.stop
                    >
                        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div></div>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">Time & Cost</span>
                            <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="x-mark" class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="p-3 space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Est. Hours</label>
                                <input 
                                    type="number" 
                                    step="0.5"
                                    min="0"
                                    v-model="localEstimatedHours" 
                                    class="w-full form-control form-input form-control-bordered text-sm" 
                                    placeholder="0"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Est. Cost (€)</label>
                                <input 
                                    type="number" 
                                    step="10"
                                    min="0"
                                    v-model="localEstimatedCost" 
                                    class="w-full form-control form-input form-control-bordered text-sm" 
                                    placeholder="0"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Actual Hours</label>
                                <input 
                                    type="number" 
                                    step="0.5"
                                    min="0"
                                    v-model="localActualHours" 
                                    class="w-full form-control form-input form-control-bordered text-sm" 
                                    placeholder="0"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Actual Cost (€)</label>
                                <input 
                                    type="number" 
                                    step="10"
                                    min="0"
                                    v-model="localActualCost" 
                                    class="w-full form-control form-input form-control-bordered text-sm" 
                                    placeholder="0"
                                />
                            </div>
                            <button @click="saveEstimates" class="bg-primary-500 hover:bg-primary-400 text-white px-4 py-2 rounded font-bold text-sm w-full">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <h4 class="text-xs uppercase font-bold text-gray-500 mb-2">Actions</h4>
            <div class="space-y-2">
                <button type="button" class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" @click.stop="openMovePopup($event)">
                    <Icon name="arrow-right" class="w-4 h-4 mr-2" /> Move
                </button>
                <button type="button" class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" @click.stop="openCopyPopup($event)">
                    <Icon name="document-duplicate" class="w-4 h-4 mr-2" /> Copy
                </button>
                <div class="relative">
                    <button type="button" class="w-full text-left inline-flex items-center px-3 py-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-500 active:text-gray-600 dark:hover:bg-gray-800 rounded-lg font-bold text-sm" @click.stop="onPopupButtonClick('mirror', $event)">
                        <Icon name="squares-2x2" class="w-4 h-4 mr-2" /> Mirror
                    </button>
                    <!-- Mirror Popup -->
                    <div 
                        v-if="activePopup === 'mirror'" 
                        v-click-outside="closePopups"
                        class="popup-container absolute left-0 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 overflow-hidden"
                        :class="popupPositionClass"
                        ref="mirrorPopup"
                        style="width: 18rem;"
                        @click.stop
                    >
                        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div></div>
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-full text-center">Mirror Card</span>
                            <button @click="closePopups" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <Icon name="x-mark" class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="p-3">
                            <!-- Current Mirrors -->
                            <div v-if="currentMirrors.length" class="mb-3">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Mirrored to</label>
                                <div class="space-y-1">
                                    <div v-for="mirror in currentMirrors" :key="mirror.column_id" class="flex items-center justify-between py-1.5 px-2 bg-purple-100 dark:bg-purple-900/30 rounded">
                                        <span class="text-purple-700 dark:text-purple-300 truncate flex-1 text-xs">{{ mirror.board_name }} → {{ mirror.column_name }}</span>
                                        <button @click="removeMirror(mirror)" class="text-purple-500 hover:text-purple-700 dark:hover:text-purple-300 ml-2">
                                            <Icon name="x-mark" class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Search -->
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Add to board</label>
                            <input 
                                type="text" 
                                v-model="mirrorSearch"
                                @input="searchMirrorBoards"
                                placeholder="Search boards..." 
                                class="w-full form-control form-input form-control-bordered text-sm mb-2" 
                            />

                            <!-- Loading -->
                            <div v-if="isMirrorLoading" class="text-center text-gray-500 dark:text-gray-400 py-2 text-xs">
                                Searching...
                            </div>

                            <!-- Search Results -->
                            <div v-else-if="mirrorSearchResults.length" class="max-h-32 overflow-y-auto space-y-1">
                                <div 
                                    v-for="result in mirrorSearchResults" 
                                    :key="`${result.board_id}-${result.column_id}`"
                                    @click="addMirrorFromSearch(result)"
                                    class="flex items-center justify-between py-1.5 px-2 rounded cursor-pointer"
                                    :class="{
                                        'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 cursor-default': result.is_home,
                                        'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 cursor-default': result.has_mirror,
                                        'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200': !result.is_home && !result.has_mirror
                                    }"
                                >
                                    <span class="truncate flex-1 text-xs">{{ result.board_name }} → {{ result.column_name }}</span>
                                    <span v-if="result.is_home" class="text-xs text-primary-500 ml-1">Home</span>
                                    <Icon v-else-if="result.has_mirror" name="check" class="w-3 h-3 text-green-500 ml-1" />
                                </div>
                            </div>

                            <!-- No results -->
                            <div v-else-if="mirrorSearch.length >= 2 && !isMirrorLoading" class="text-center text-gray-500 dark:text-gray-400 py-2 text-xs">
                                No boards found
                            </div>

                            <!-- Hint -->
                            <div v-else-if="mirrorSearch.length < 2" class="text-center text-gray-400 dark:text-gray-500 py-2 text-xs">
                                Type to search boards...
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-300 dark:border-gray-600 my-2"></div>
                <button type="button" @click="confirmDelete" class="w-full text-left inline-flex items-center px-3 py-2 text-red-500 hover:bg-red-50 hover:text-red-600 active:text-red-700 dark:hover:bg-red-900/20 rounded-lg font-bold text-sm">
                    <Icon name="trash" class="w-4 h-4 mr-2" /> Delete
                </button>
            </div>
        </div>

        <MoveCopyPopup
            :show="showMoveCopyPopup"
            :mode="moveCopyMode"
            :card="card"
            :boards="boards"
            :current-board-id="currentBoardId" 
            :trigger-rect="moveCopyTriggerRect"
            @close="() => { showMoveCopyPopup = false; moveCopyTriggerRect = null; }"
            @move="handleMove"
            @copy="handleCopy"
        />

        <!-- Delete Card Modal (Teleported with custom high z-index backdrop) -->
        <Teleport to="body">
            <template v-if="showDeleteCardModal">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 100;" @click="showDeleteCardModal = false"></div>
                <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                            <ModalHeader>Delete Card</ModalHeader>

                            <div class="p-8">
                                <p class="text-gray-500 dark:text-gray-400 text-sm">
                                    Are you sure you want to delete the card
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ card ? card.title : '' }}"</span>?
                                    This action cannot be undone.
                                </p>
                            </div>

                            <ModalFooter>
                                <div class="flex items-center ml-auto">
                                    <Button
                                        variant="link"
                                        state="mellow"
                                        @click.prevent="showDeleteCardModal = false"
                                        class="mr-3"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        state="danger"
                                        @click="confirmDeleteCard"
                                    >
                                        Delete Card
                                    </Button>
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
import MoveCopyPopup from './MoveCopyPopup.vue'

export default {
    components: { Icon, MoveCopyPopup, Button },
    props: {
        card: Object,
        users: Array,
        availableLabels: Array,
        allColumns: Array,
        boards: {
            type: Array,
            default: () => []
        },
        currentBoardId: {
            type: [Number, String],
            default: null
        },
    },
    emits: ['update', 'delete-card', 'add-checklist'],
    data() {
        return {
            activePopup: null, // 'members', 'labels', 'dates', 'estimates', 'mirror'
            popupPlacement: 'above', // 'above' | 'below'
            popupTriggerRect: null,
            showMoveCopyPopup: false,
            moveCopyMode: 'move',
            moveCopyTriggerRect: null,
            // Form data
            localEstimatedHours: '',
            localEstimatedCost: '',
            localActualHours: '',
            localActualCost: '',
            localDueDate: '',
            // Labels
            isCreatingLabel: false,
            editingLabel: null,
            newLabelName: '',
            newLabelColor: '#3b82f6',
            labelSearch: '',
            // Mirror
            mirrorSearch: '',
            mirrorSearchResults: [],
            isMirrorLoading: false,
            trelloColors: [
                '#61bd4f', // Green
                '#f2d600', // Yellow
                '#ff9f1a', // Orange
                '#eb5a46', // Red
                '#c377e0', // Purple
                '#0079bf', // Blue
                '#00c2e0', // Sky
                '#51e898', // Lime
                '#ff78cb', // Pink
                '#344563', // Black
                '#b6bbbf', // Gray
            ],
            showDeleteCardModal: false,
        }
    },
    computed: {
        filteredLabels() {
            if (!this.labelSearch) return this.availableLabels;
            return this.availableLabels.filter(l => l.name.toLowerCase().includes(this.labelSearch.toLowerCase()));
        },
        popupTitle() {
            if (this.isCreatingLabel) return 'Create Label';
            if (this.editingLabel) return 'Edit Label';
            return 'Labels';
        },
        currentMirrors() {
            return this.card?.appearances_info?.mirrors || [];
        },
        popupPositionClass() {
            // Position popup above or below the trigger depending on available viewport space
            return this.popupPlacement === 'below' ? 'top-full mt-1' : 'bottom-full mb-1';
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
        onPopupButtonClick(name, event) {
            // Toggle same popup off
            if (this.activePopup === name) {
                this.closePopups();
                return;
            }

            // Track trigger position for dynamic placement
            if (event && event.currentTarget) {
                this.popupTriggerRect = event.currentTarget.getBoundingClientRect();
            } else {
                this.popupTriggerRect = null;
            }

            this.togglePopup(name);

            // After render, measure popup and decide placement
            this.$nextTick(() => {
                this.updatePopupPlacement(name);
            });
        },
        backToLabelList() {
            this.isCreatingLabel = false;
            this.editingLabel = null;
            this.newLabelName = '';
            this.newLabelColor = '#3b82f6';
        },
        startEditingLabel(label) {
            this.editingLabel = label;
            this.newLabelName = label.name;
            this.newLabelColor = label.color;
            this.isCreatingLabel = false;
             this.$nextTick(() => {
                if (this.$refs.editLabelInput) {
                    this.$refs.editLabelInput.focus();
                }
            });
        },
        async updateLabel() {
            if (!this.editingLabel || !this.newLabelName) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/labels/${this.editingLabel.id}`, {
                    name: this.newLabelName,
                    color: this.newLabelColor
                });
                this.$emit('update');
                Nova.success('Label updated');
                this.backToLabelList();
            } catch (e) {
                Nova.error('Failed to update label');
            }
        },
        async deleteLabel() {
            if (!this.editingLabel) return;
            if (!confirm(`Delete label "${this.editingLabel.name}"? It will be removed from all cards.`)) return;
            
            try {
                await Nova.request().delete(`/nova-vendor/project-board/labels/${this.editingLabel.id}`);
                this.$emit('update');
                Nova.success('Label deleted');
                this.backToLabelList();
            } catch (e) {
                Nova.error('Failed to delete label');
            }
        },
        togglePopup(name) {
            this.activePopup = name;
            // Reset form data when opening
            if (name === 'estimates') {
                this.localEstimatedHours = this.card.estimated_hours || '';
                this.localEstimatedCost = this.card.estimated_cost || '';
                this.localActualHours = this.card.actual_hours || '';
                this.localActualCost = this.card.actual_cost || '';
            } else if (name === 'dates') {
                this.localDueDate = this.card.due_date || '';
            } else if (name === 'mirror') {
                this.mirrorSearch = '';
                this.mirrorSearchResults = [];
            } else if (name === 'labels') {
                this.isCreatingLabel = false;
                this.editingLabel = null;
                this.labelSearch = '';
            }
        },
        updatePopupPlacement(name) {
            const triggerRect = this.popupTriggerRect;
            const popupRefName = `${name}Popup`;
            const popupEl = this.$refs[popupRefName];

            if (!popupEl || !triggerRect) {
                // Default to above if we can't measure
                this.popupPlacement = 'above';
                return;
            }

            const popupRect = popupEl.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            const margin = 8;

            const spaceBelow = viewportHeight - triggerRect.bottom - margin;
            const spaceAbove = triggerRect.top - margin;
            const popupHeight = popupRect.height;

            if (popupHeight <= spaceBelow) {
                this.popupPlacement = 'below';
            } else if (popupHeight <= spaceAbove) {
                this.popupPlacement = 'above';
            } else {
                // Not enough space either way: pick side with more space inside the viewport
                this.popupPlacement = spaceBelow >= spaceAbove ? 'below' : 'above';
            }
        },
        openMovePopup(event) {
            this.activePopup = null;
            this.moveCopyMode = 'move';
            this.moveCopyTriggerRect = event && event.currentTarget
                ? event.currentTarget.getBoundingClientRect()
                : null;
            this.showMoveCopyPopup = true;
        },
        openCopyPopup(event) {
            this.activePopup = null;
            this.moveCopyMode = 'copy';
            this.moveCopyTriggerRect = event && event.currentTarget
                ? event.currentTarget.getBoundingClientRect()
                : null;
            this.showMoveCopyPopup = true;
        },
        async searchMirrorBoards() {
            if (this.mirrorSearch.length < 2) {
                this.mirrorSearchResults = [];
                return;
            }
            this.isMirrorLoading = true;
            try {
                const { data } = await Nova.request().get(`/nova-vendor/project-board/cards/${this.card.id}/search-columns-for-mirroring`, {
                    params: { q: this.mirrorSearch }
                });
                this.mirrorSearchResults = data;
            } catch (e) {
                console.error('Failed to search boards:', e);
                this.mirrorSearchResults = [];
            } finally {
                this.isMirrorLoading = false;
            }
        },
        async addMirrorFromSearch(result) {
            if (result.is_home || result.has_mirror) return;
            
            try {
                const { data } = await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/mirror`, {
                    column_id: result.column_id
                });
                
                // Update local state
                if (!this.card.appearances_info) {
                    this.card.appearances_info = { home: null, mirrors: [] };
                }
                this.card.appearances_info.mirrors.push(data.mirror);
                
                // Update search results
                result.has_mirror = true;
                
                this.$emit('update');
                Nova.success('Card mirrored');
            } catch (e) {
                Nova.error(e.response?.data?.error || 'Failed to mirror card');
            }
        },
        async removeMirror(mirror) {
            try {
                await Nova.request().delete(`/nova-vendor/project-board/cards/${this.card.id}/mirror/${mirror.column_id}`);
                
                // Update local state
                if (this.card.appearances_info) {
                    this.card.appearances_info.mirrors = this.card.appearances_info.mirrors.filter(
                        m => m.column_id !== mirror.column_id
                    );
                }
                
                // Update search results if visible
                const result = this.mirrorSearchResults.find(r => r.column_id === mirror.column_id);
                if (result) result.has_mirror = false;
                
                this.$emit('update');
                Nova.success('Mirror removed');
            } catch (e) {
                Nova.error('Failed to remove mirror');
            }
        },
        async handleMove(payload) {
            // Calculate order based on position
            let orderColumn = 1;
            const targetCol = this.allColumns.find(c => c.id === payload.board_column_id);
            if (targetCol && targetCol.cards) {
                if (payload.position === 'bottom') {
                    const max = targetCol.cards.reduce((acc, c) => Math.max(acc, c.order_column), 0);
                    orderColumn = max + 1;
                } else {
                    orderColumn = 1; // Top
                    // Ideally we shift others, but for now let's just insert at 1. 
                    // Backend might need to handle collision or we accept it.
                }
            }

            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}/move`, {
                    board_column_id: payload.board_column_id,
                    order_column: orderColumn
                });
                this.showMoveCopyPopup = false;
                this.$emit('update');
                Nova.success('Card moved');
            } catch (e) {
                Nova.error('Failed to move card');
            }
        },
        async handleCopy(payload) {
            // Calculate order
            let orderColumn = 1;
            const targetCol = this.allColumns.find(c => c.id === payload.board_column_id);
            if (targetCol && targetCol.cards) {
                if (payload.position === 'bottom') {
                    const max = targetCol.cards.reduce((acc, c) => Math.max(acc, c.order_column), 0);
                    orderColumn = max + 1;
                } else {
                    orderColumn = 1;
                }
            }

            try {
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/duplicate`, {
                    board_column_id: payload.board_column_id,
                    title: payload.title,
                    order_column: orderColumn
                });
                this.showMoveCopyPopup = false;
                this.$emit('update');
                Nova.success('Card copied');
            } catch (e) {
                Nova.error('Failed to copy card');
            }
        },
        async confirmDelete() {
            this.showDeleteCardModal = true;
        },
        async confirmDeleteCard() {
            try {
                await Nova.request().delete(`/nova-vendor/project-board/cards/${this.card.id}`);
                Nova.success('Card deleted');
                this.$emit('delete-card');
            } catch (e) {
                Nova.error('Failed to delete card');
            } finally {
                this.showDeleteCardModal = false;
            }
        },
        notImplemented(feature) {
            Nova.warning(`${feature} is not implemented yet.`);
        },
        closePopups() {
            this.activePopup = null;
            this.showMoveCopyPopup = false;
            this.moveCopyTriggerRect = null;
            this.isCreatingLabel = false;
            this.editingLabel = null;
            this.labelSearch = '';
            this.mirrorSearch = '';
        },
        // Legacy methods - kept for compatibility but should be removed
        toggleMembersPopup() { this.togglePopup('members'); },
        toggleLabelsPopup() { this.togglePopup('labels'); },
        toggleDatesPopup() { this.togglePopup('dates'); },
        toggleEstimatesPopup() { this.togglePopup('estimates'); },
        async saveEstimates() {
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}`, {
                    estimated_hours: this.localEstimatedHours || null,
                    estimated_cost: this.localEstimatedCost || null,
                    actual_hours: this.localActualHours || null,
                    actual_cost: this.localActualCost || null,
                });
                this.$emit('update');
                Nova.success('Estimates saved');
                this.closePopups();
            } catch (e) {
                Nova.error('Failed to save estimates');
            }
        },
        async saveDueDate() {
            if (!this.localDueDate) return;
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}`, {
                    due_date: this.localDueDate
                });
                this.$emit('update');
                Nova.success('Due date saved');
                this.closePopups();
            } catch (e) {
                Nova.error('Failed to save due date');
            }
        },
        async removeDueDate() {
            try {
                await Nova.request().put(`/nova-vendor/project-board/cards/${this.card.id}`, {
                    due_date: null,
                    completed_at: null
                });
                this.$emit('update');
                Nova.success('Due date removed');
                this.closePopups();
            } catch (e) {
                Nova.error('Failed to remove due date');
            }
        },
        isMemberSelected(user) {
            return this.card.assignees?.some(u => u.id === user.id);
        },
        async toggleMember(user) {
            let assignees = this.card.assignees ? [...this.card.assignees] : [];
            const index = assignees.findIndex(u => u.id === user.id);
            
            if (index >= 0) {
                assignees.splice(index, 1);
            } else {
                assignees.push(user);
            }
            
            // Optimistic update
            this.card.assignees = assignees;

            try {
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/assignees`, {
                    users: assignees.map(u => u.id)
                });
                this.$emit('update'); // Triggers refresh-board
                Nova.success('Members updated');
            } catch (e) {
                Nova.error('Failed to update members');
            }
        },
        isLabelSelected(label) {
            return this.card.labels?.some(l => l.id === label.id);
        },
        async toggleLabel(label) {
            let labels = this.card.labels ? [...this.card.labels] : [];
            const index = labels.findIndex(l => l.id === label.id);
            
            if (index >= 0) {
                labels.splice(index, 1);
            } else {
                labels.push(label);
            }
            
            // Optimistic update
            this.card.labels = labels;

            try {
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/labels`, {
                    labels: labels.map(l => l.id)
                });
                this.$emit('update');
                Nova.success('Labels updated');
            } catch (e) {
                Nova.error('Failed to update labels');
            }
        },
        async createLabel() {
            if (!this.newLabelName) return;
            try {
                const { data } = await Nova.request().post('/nova-vendor/project-board/labels', {
                    name: this.newLabelName,
                    color: this.newLabelColor
                });
                this.$emit('update');
                Nova.success('Label created');
                this.newLabelName = '';
                this.toggleLabel(data);
            } catch (e) {
                Nova.error('Failed to create label');
            }
        },
        triggerAttachmentUpload() {
            this.$refs.attachmentInput.click();
        },
        async handleAttachmentUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('file', file);

            try {
                Nova.success('Uploading attachment...');
                await Nova.request().post(`/nova-vendor/project-board/cards/${this.card.id}/attachments`, formData);
                this.$emit('update');
                Nova.success('Attachment uploaded');
            } catch (e) {
                Nova.error('Failed to upload attachment');
            }
            // Reset input
            e.target.value = null;
        }
    }
}
</script>
