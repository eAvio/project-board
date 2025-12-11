<template>
  <div :class="[containerClass, { 'board-expanded': isFullscreen }]">
    <div v-if="loading" class="flex items-center justify-center flex-1">
      <div class="text-gray-500 animate-pulse">Loading Board...</div>
    </div>

    <div v-else-if="boards.length === 0" class="flex flex-col items-center justify-center flex-1 text-gray-500">
      <Icon type="server" class="w-16 h-16 mb-4" />
      <p class="text-lg">No boards found. Create one to get started!</p>
      <button
        @click="createBoard"
        class="mt-4 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm"
      >
        Create Board
      </button>
    </div>

    <div v-else class="flex-1 overflow-x-auto overflow-y-hidden flex flex-col">
       <!-- Board Selection Tabs -->
       <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-4 py-2 bg-white dark:bg-gray-800 flex-shrink-0 gap-2">
         <div class="flex space-x-1 overflow-x-auto no-scrollbar flex-1 mr-2">
            <div
                v-for="board in boards"
                :key="board.id"
                class="relative group flex-shrink-0"
            >
                <div 
                    v-if="isRenamingBoardId === board.id"
                    class="px-4 py-2 min-w-[120px]"
                >
                    <input
                        v-model="board.name"
                        ref="boardRenameInput"
                        @blur="finishRenamingBoard(board)"
                        @keydown.enter="finishRenamingBoard(board)"
                        class="form-control form-input form-control-bordered text-sm font-bold h-8 w-full"
                    />
                </div>
                <button
                    v-else
                    @click="handleTabClick(board)"
                    @dblclick="startRenamingBoard(board)"
                    :class="[
                    'px-4 py-2 rounded-lg font-bold text-sm transition-colors duration-200 whitespace-nowrap flex items-center',
                    currentBoard?.id === board.id
                        ? 'text-primary-500 dark:text-primary-400 bg-gray-50 dark:bg-gray-700'
                        : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 board-tab-inactive'
                    ]"
                >
                    <span>{{ board.name }}</span>
                    <span v-if="board.boardable" class="ml-2 text-xs px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 rounded text-gray-600 dark:text-gray-300 font-normal">
                        {{ board.boardable.name || board.boardable.title || 'Resource' }}
                    </span>
                </button>
            </div>
         </div>

         <!-- Board Totals -->
         <div v-if="currentBoard?.totals && hasBoardTotals" class="flex items-center text-xs space-x-3 flex-shrink-0 text-gray-500 dark:text-gray-400">
            <div class="relative board-totals-wrapper flex items-center space-x-4">
              <span v-if="currentBoard.totals.estimated_hours > 0 || currentBoard.totals.actual_hours > 0" class="cursor-help">
                <span :class="boardHoursActualClass">{{ formatNum(currentBoard.totals.actual_hours) }}</span><template v-if="currentBoard.totals.estimated_hours > 0"> / {{ formatNum(currentBoard.totals.estimated_hours) }}</template> h
              </span>
              <span v-if="currentBoard.totals.estimated_cost > 0 || currentBoard.totals.actual_cost > 0" class="cursor-help">
                <span :class="boardCostActualClass">{{ formatNum(currentBoard.totals.actual_cost) }}</span><template v-if="currentBoard.totals.estimated_cost > 0"> / {{ formatNum(currentBoard.totals.estimated_cost) }}</template> €
              </span>
              <!-- Board Breakdown Tooltip - compact panel directly below totals -->
              <div
                class="board-totals-tooltip absolute right-0 top-full mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 p-3"
                style="z-index: 60; min-width: 260px; max-width: 460px;"
              >
                <div class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-2 border-b border-gray-200 dark:border-gray-600 pb-1">Board Breakdown</div>
                <div class="max-h-64 overflow-y-auto space-y-1">
                  <div v-for="card in boardCardsWithEstimates" :key="card.id" class="flex justify-between text-xs py-1 border-b border-gray-100 dark:border-gray-700 last:border-0">
                    <span class="mr-2">{{ card.title }}</span>
                    <span class="flex space-x-2 flex-shrink-0">
                      <span v-if="card.estimated_hours > 0 || card.actual_hours > 0"><span :class="getCardHoursClass(card)">{{ formatNum(card.actual_hours) }}</span><template v-if="card.estimated_hours > 0"> / {{ formatNum(card.estimated_hours) }}</template> h</span>
                      <span v-if="card.estimated_cost > 0 || card.actual_cost > 0"><span :class="getCardCostClass(card)">{{ formatNum(card.actual_cost) }}</span><template v-if="card.estimated_cost > 0"> / {{ formatNum(card.estimated_cost) }}</template> €</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Copy & Download buttons - OUTSIDE the hover wrapper -->
            <button @click.stop="copyBreakdownToClipboard" class="p-0.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded" title="Copy breakdown to clipboard">
              <Icon name="clipboard-document" type="micro" />
            </button>
            <button @click.stop="downloadBreakdownCSV" class="p-0.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded" title="Download CSV">
              <Icon name="arrow-down-tray" type="micro" />
            </button>
         </div>
         
         <!-- Search -->
         <div class="relative flex-shrink-0" v-click-outside="closeSearch">
            <div class="relative flex items-center">
                <Icon
                    name="magnifying-glass"
                    class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
                />
                <input 
                    v-model="searchQuery" 
                    @input="onSearchInput"
                    @focus="showSearchResults = true"
                    type="search" 
                    placeholder="Search cards, comments..." 
                    class="appearance-none rounded-full h-8 pl-10 pr-8 w-64 text-sm bg-gray-100 dark:bg-gray-900 dark:focus:bg-gray-800 focus:bg-white focus:outline-none focus:ring focus:ring-primary-200 dark:focus:ring-gray-600"
                    role="search"
                    aria-label="Search"
                    spellcheck="false"
                />
                <div v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center">
                    <Loader class="w-4 h-4 text-gray-400" />
                </div>
            </div>

            <!-- Search Results Dropdown -->
            <div v-if="showSearchResults && (searchResults.length > 0 || searchQuery.length >= 2)" class="absolute left-0 top-full mt-1 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 z-50 max-h-80 overflow-y-auto">
                <div v-if="searchResults.length === 0 && searchQuery.length >= 2 && !isSearching" class="p-4 text-center text-gray-500 text-sm">
                    No results found.
                </div>
                <div v-else-if="searchResults.length > 0">
                    <div v-for="(result, index) in searchResults" :key="index" @click="openSearchResult(result)" class="p-3 border-b border-gray-100 dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5 mr-3">
                                <Icon v-if="result.type === 'card'" name="credit-card" class="w-5 h-5 text-primary-500" />
                                <Icon v-else-if="result.type === 'comment'" name="chat-bubble-left" class="w-5 h-5 text-gray-500" />
                                <Icon v-else-if="result.type === 'attachment'" name="paper-clip" class="w-5 h-5 text-gray-500" />
                                <Icon v-else-if="result.type === 'checklist'" name="check-circle" class="w-5 h-5 text-green-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ result.title }}</div>
                                <div class="text-xs text-gray-500 truncate mt-0.5">{{ result.preview }}</div>
                                <div class="text-xs text-gray-400 mt-1 flex items-center">
                                    <span>{{ result.board_name }}</span>
                                    <span class="mx-1">•</span>
                                    <span>{{ result.type }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
         </div>

         <!-- Board Members Avatars -->
         <div v-if="currentBoard && currentBoard.users && currentBoard.users.length > 0" class="flex items-center">
            <div class="flex -space-x-2">
                <button
                    v-for="(member, index) in currentBoard.users.slice(0, 5)"
                    :key="member.id"
                    @click="openMembersModal"
                    class="relative w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 hover:z-10 hover:scale-110 transition-transform"
                    :style="{ zIndex: 5 - index }"
                    :title="`${member.name} (${member.pivot?.role || 'member'})`"
                >
                    <img 
                        :src="'https://ui-avatars.com/api/?name=' + encodeURIComponent(member.name) + '&size=32&background=random'" 
                        :alt="member.name"
                        class="w-full h-full rounded-full object-cover"
                    />
                </button>
                <button
                    v-if="currentBoard.users.length > 5"
                    @click="openMembersModal"
                    class="relative w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-xs font-medium text-gray-600 dark:text-gray-300 hover:scale-110 transition-transform"
                    :title="`+${currentBoard.users.length - 5} more members`"
                >
                    +{{ currentBoard.users.length - 5 }}
                </button>
            </div>
            <button 
                @click="openMembersModal" 
                class="ml-2 p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                title="Manage Members"
            >
                <Icon name="user-plus" class="w-4 h-4" />
            </button>
         </div>

         <!-- Fullscreen Button -->
         <button @click="toggleFullscreen" class="flex-shrink-0 p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
            <Icon :name="isFullscreen ? 'arrows-pointing-in' : 'arrows-pointing-out'" class="w-5 h-5" />
         </button>

         <Dropdown class="flex-shrink-0">
            <template #trigger>
                <Button variant="ghost" icon="ellipsis-horizontal" />
            </template>
            <template #menu>
                <DropdownMenu width="200">
                    <DropdownMenuItem as="button" @click="createBoard">
                        <div class="flex items-center">
                            <Icon name="plus" class="w-4 h-4 mr-2" />
                            Create New Board
                        </div>
                    </DropdownMenuItem>
                    
                    <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>

                    <DropdownMenuItem as="button" @click="showArchivedItems = true">
                        <div class="flex items-center">
                            <Icon name="archive-box" class="w-4 h-4 mr-2" />
                            Archived Items
                        </div>
                    </DropdownMenuItem>
                    
                    <DropdownMenuItem as="button" @click="showBackgroundPicker = true">
                        <div class="flex items-center">
                            <Icon name="photo" class="w-4 h-4 mr-2" />
                            Change Background
                        </div>
                    </DropdownMenuItem>

                    <DropdownMenuItem as="button" @click="openMembersModal">
                        <div class="flex items-center">
                            <Icon name="user-group" class="w-4 h-4 mr-2" />
                            Manage Members
                        </div>
                    </DropdownMenuItem>

                    <DropdownMenuItem as="button" @click="openApiTokensModal">
                        <div class="flex items-center">
                            <Icon name="key" class="w-4 h-4 mr-2" />
                            API Tokens
                        </div>
                    </DropdownMenuItem>
                    
                    <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                    
                    <DropdownMenuItem as="button" @click="showImportTrelloModal = true">
                        <div class="flex items-center">
                            <Icon name="arrow-up-tray" class="w-4 h-4 mr-2" />
                            Import from Trello
                        </div>
                    </DropdownMenuItem>
                    
                    <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                    
                    <DropdownMenuItem as="button" @click="deleteBoard" class="text-red-500 hover:text-red-600">
                        <div class="flex items-center">
                            <Icon name="trash" class="w-4 h-4 mr-2" />
                            Delete Board
                        </div>
                    </DropdownMenuItem>
                </DropdownMenu>
            </template>
         </Dropdown>
       </div>

       <!-- Kanban Board Area -->
<div v-if="currentBoard" class="flex-1 flex items-stretch p-4 overflow-x-auto overflow-y-hidden space-x-4 bg-cover bg-center bg-no-repeat transition-all duration-300" :class="{ 'min-h-screen': isFullscreen }" :style="[boardBackgroundStyle, !isFullscreen ? { height: 'calc(100vh - 200px)' } : {}]">
            <draggable
            v-model="currentBoard.columns"
            group="columns"
            item-key="id"
            class="flex space-x-4"
            style="height: 100%;"
            ghost-class="opacity-50"
            handle=".column-drag-handle"
            @end="onColumnReorder"
          >
            <template #item="{ element: column }">
              <div 
                 class="flex-shrink-0 flex-grow-0 focus:outline-none"
                 style="width: 272px; min-width: 272px; max-width: 272px; height: 100%;"
                 @paste="onPaste($event, column)"
                 tabindex="0"
              >
                <BoardColumn
                  :column="column"
                  :board-id="currentBoard.id"
                  class="h-full"
                  @card-moved="onCardMoved"
                  @delete-column="deleteColumn"
                  @refresh-board="fetchBoards"
                  @edit-card="openCard"
/>
              </div>
            </template>
          </draggable>

          <!-- Add Column Button -->
          <div class="flex-shrink-0 flex-grow-0" style="width: 272px; min-width: 272px; max-width: 272px;">
             <button
               v-if="!isCreatingColumn"
               @click="isCreatingColumn = true"
               class="w-full py-3 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-gray-600 dark:text-gray-400 font-semibold flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600"
             >
               + Add Column
             </button>
             <div v-else class="bg-gray-100 dark:bg-gray-800 p-3 rounded-lg shadow">
                <input
                  v-model="newColumnName"
                  ref="newColumnInput"
                  @keyup.enter="createColumn"
                  @keyup.esc="isCreatingColumn = false"
                  type="text"
                  placeholder="Column name..."
                  class="w-full form-control form-input form-control-bordered mb-2"
                />
                <div class="flex space-x-2">
                  <button @click="createColumn" class="shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm">Add</button>
                  <button @click="isCreatingColumn = false" class="focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring-2 rounded border-2 border-gray-200 dark:border-gray-500 hover:border-primary-500 active:border-primary-400 dark:hover:border-gray-400 dark:active:border-gray-300 bg-white dark:bg-transparent text-primary-500 dark:text-gray-400 px-3 h-9 inline-flex items-center font-bold text-sm">Cancel</button>
                </div>
             </div>
          </div>
       </div>
    </div>

    <!-- Global Card Detail Modal -->
    <CardDetailModal 
        v-if="activeCard"
        :show="showCardModal" 
        :card="activeCard"
        :column="activeCard.column" 
        :all-columns="currentBoard?.columns || []"
        :current-user="currentUser"
        :users="users"
        :available-labels="availableLabels"
        :boards="boards"
        :current-board-id="currentBoard?.id"
        @close="closeCardModal" 
        @update="onCardUpdated"
        @refresh-board="fetchBoards"
        @delete-card="closeCardModal(); fetchBoards()"
    />

    <!-- Archived Items Modal -->
    <ArchivedItemsModal
        :show="showArchivedItems"
        :board-id="currentBoard?.id"
        @close="showArchivedItems = false"
        @refresh="fetchBoards"
    />

    <!-- Background Picker Modal -->
    <Teleport to="body">
      <template v-if="showBackgroundPicker && currentBoard">
        <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 99999;" @click="showBackgroundPicker = false"></div>
        <div class="fixed inset-0 overflow-y-auto" style="z-index: 100000;">
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-600 w-96" @click.stop>
              <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">Board Background</h3>
                    <button @click="showBackgroundPicker = false" class="text-gray-400 hover:text-gray-600">
                        <Icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <!-- Search -->
                <div class="mb-4">
                    <input 
                        v-model="bgSearchQuery"
                        @keydown.enter="searchBgPhotos"
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
                            v-for="color in bgColors" 
                            :key="color"
                            @click="selectBgColor(color)"
                            class="w-10 h-8 rounded-md border-2 hover:scale-110 transition-transform"
                            :class="currentBoard.background_color === color ? 'border-primary-500' : 'border-transparent'"
                            :style="{ backgroundColor: color }"
                        ></button>
                        <button 
                            @click="clearBgBackground"
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
                    <div v-if="bgLoading" class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-500"></div>
                    </div>
                    <div v-else class="grid grid-cols-4 gap-2 max-h-48 overflow-y-auto">
                        <button 
                            v-for="photo in bgPhotos" 
                            :key="photo.id"
                            @click="selectBgPhoto(photo)"
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
        </div>
      </template>
    </Teleport>

    <!-- Members Modal -->
    <Teleport to="body">
      <template v-if="showMembersModal && currentBoard">
        <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 99999;" @click="showMembersModal = false"></div>
        <div class="fixed inset-0 overflow-y-auto" style="z-index: 100000;">
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-600 w-[480px] max-h-[80vh] overflow-hidden flex flex-col" @click.stop>
              <div class="p-4 flex flex-col flex-1 overflow-hidden">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">Board Members</h3>
                    <button @click="showMembersModal = false" class="text-gray-400 hover:text-gray-600">
                        <Icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <!-- Add Member -->
                <div class="mb-4">
                    <div class="flex gap-2">
                        <div class="flex-1 relative">
                            <input 
                                v-model="memberSearchQuery"
                                @input="searchUsers"
                                type="text" 
                                placeholder="Search users by name or email..."
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            />
                            <!-- Search Results Dropdown -->
                            <div v-if="userSearchResults.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg z-10 max-h-48 overflow-y-auto">
                                <button 
                                    v-for="user in userSearchResults" 
                                    :key="user.id"
                                    @click="selectUserToAdd(user)"
                                    class="w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center gap-2"
                                >
                                    <img :src="user.avatar_url" class="w-6 h-6 rounded-full" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ user.name }}</div>
                                        <div class="text-xs text-gray-500">{{ user.email }}</div>
                                    </div>
                                </button>
                            </div>
                        </div>
                        <select v-model="newMemberRole" class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                            <option value="viewer">Viewer</option>
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div v-if="selectedUserToAdd" class="mt-2 flex items-center gap-2 p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <img :src="selectedUserToAdd.avatar_url" class="w-6 h-6 rounded-full" />
                        <span class="text-sm text-gray-800 dark:text-gray-200 flex-1">{{ selectedUserToAdd.name }}</span>
                        <button @click="addMember" class="px-3 py-1 text-xs bg-primary-500 text-white rounded hover:bg-primary-600">
                            Add
                        </button>
                        <button @click="selectedUserToAdd = null" class="text-gray-400 hover:text-gray-600">
                            <Icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Current Members -->
                <div>
                    <h4 class="text-xs font-bold text-gray-500 uppercase mb-2">Members with Access ({{ boardMembers.length }})</h4>
                    <div v-if="membersLoading" class="flex items-center justify-center py-4">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-500"></div>
                    </div>
                    <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                        <div v-for="member in boardMembers" :key="member.id" class="flex items-center px-3 py-2 rounded-lg" :class="member.is_global_admin ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-700'">
                            <img :src="member.avatar_url" class="w-8 h-8 rounded-full flex-shrink-0 mr-3" />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate flex items-center gap-1">
                                    {{ member.name }}
                                    <span v-if="member.is_global_admin" class="px-1.5 py-0.5 text-[10px] bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-200 rounded">Global</span>
                                </div>
                                <div class="text-xs text-gray-500 truncate">{{ member.email }}</div>
                            </div>
                            <template v-if="member.is_global_admin">
                                <span class="px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded capitalize">
                                    {{ member.role }}
                                </span>
                            </template>
                            <template v-else>
                                <select 
                                    :value="member.role" 
                                    @change="updateMemberRole(member.id, $event.target.value)"
                                    class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-800 dark:text-gray-200"
                                >
                                    <option value="viewer">Viewer</option>
                                    <option value="member">Member</option>
                                    <option value="admin">Admin</option>
                                </select>
                                <button @click="removeMember(member.id)" class="text-red-400 hover:text-red-600 p-1">
                                    <Icon name="trash" class="w-4 h-4" />
                                </button>
                            </template>
                        </div>
                        <div v-if="boardMembers.length === 0" class="text-center py-4 text-gray-500 text-sm">
                            No members found.
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mt-4 flex-shrink-0">
                    <span class="inline-block w-2 h-2 bg-blue-100 dark:bg-blue-800 rounded mr-1"></span>
                    <strong>Global</strong> users have access to all boards and cannot be removed.
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Teleport>

    <!-- Delete Board Modal -->
    <Modal :show="showDeleteBoardModal" @close="showDeleteBoardModal = false" role="dialog">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <ModalHeader>Delete Board</ModalHeader>

            <div class="p-8">
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Are you sure you want to delete the board
                    <span class="font-semibold text-gray-700 dark:text-gray-200">"{{ currentBoard ? currentBoard.name : '' }}"</span>?
                    This action cannot be undone.
                </p>
            </div>

            <ModalFooter>
                <div class="flex items-center ml-auto">
                    <Button
                        variant="link"
                        state="mellow"
                        @click.prevent="showDeleteBoardModal = false"
                        class="mr-3"
                    >
                        Cancel
                    </Button>
                    <Button
                        state="danger"
                        @click="confirmDeleteBoard"
                    >
                        Delete Board
                    </Button>
                </div>
            </ModalFooter>
        </div>
    </Modal>

    <!-- Delete Column Modal -->
    <Modal :show="showDeleteColumnModal" @close="() => { showDeleteColumnModal = false; columnIdToDelete = null; }" role="dialog">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <ModalHeader>Delete Column</ModalHeader>

            <div class="p-8">
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Are you sure you want to delete this column? All cards in it will also be deleted.
                </p>
            </div>

            <ModalFooter>
                <div class="flex items-center ml-auto">
                    <Button
                        variant="link"
                        state="mellow"
                        @click.prevent="() => { showDeleteColumnModal = false; columnIdToDelete = null; }"
                        class="mr-3"
                    >
                        Cancel
                    </Button>
                    <Button
                        state="danger"
                        @click="confirmDeleteColumn"
                    >
                        Delete Column
                    </Button>
                </div>
            </ModalFooter>
        </div>
    </Modal>

    <!-- Remove Member Modal -->
    <Modal :show="showRemoveMemberModal" @close="() => { showRemoveMemberModal = false; memberIdToRemove = null; }" role="dialog">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <ModalHeader>Remove Member</ModalHeader>

            <div class="p-8">
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Are you sure you want to remove this member from the board?
                </p>
            </div>

            <ModalFooter>
                <div class="flex items-center ml-auto">
                    <Button
                        variant="link"
                        state="mellow"
                        @click.prevent="() => { showRemoveMemberModal = false; memberIdToRemove = null; }"
                        class="mr-3"
                    >
                        Cancel
                    </Button>
                    <Button
                        state="danger"
                        @click="confirmRemoveMember"
                    >
                        Remove Member
                    </Button>
                </div>
            </ModalFooter>
        </div>
    </Modal>

    <!-- API Tokens Modal -->
    <Modal :show="showApiTokensModal" @close="showApiTokensModal = false" role="dialog" size="2xl">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" style="min-width: 600px;">
            <ModalHeader>
                <div class="flex items-center">
                    <Icon name="key" class="w-5 h-5 mr-2" />
                    API Tokens for ProjectsBoard
                </div>
            </ModalHeader>

            <div class="p-6">
                <!-- Info Box -->
                <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <Icon name="information-circle" class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" />
                        <div class="text-sm">
                            <p class="font-medium">These tokens are for ProjectsBoard API only</p>
                            <p class="mt-1">Use them to integrate with ChatGPT, Zapier, or custom apps. Tokens only grant access to boards you have permission to view.</p>
                        </div>
                    </div>
                </div>

                <!-- Create New Token -->
                <div class="mb-6">
                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">Create New Token</h4>
                    <div class="flex gap-3">
                        <input 
                            v-model="newTokenName"
                            type="text" 
                            placeholder="Token name (e.g., ChatGPT, Zapier)"
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 focus:border-transparent mr-3"
                        />
                        <Button @click="createApiToken" :disabled="!newTokenName.trim() || creatingToken">
                            <span v-if="creatingToken">Creating...</span>
                            <span v-else>Create Token</span>
                        </Button>
                    </div>
                </div>

                <!-- Newly Created Token Display -->
                <div v-if="newlyCreatedToken" class="mb-6 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <Icon name="check-circle" class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-medium text-green-700 dark:text-green-300">Token created successfully!</p>
                                <p class="text-xs text-green-600 dark:text-green-400">Copy it now - you won't be able to see it again.</p>
                            </div>
                        </div>
                        <button 
                            @click="copyToken(newlyCreatedToken)"
                            class="flex items-center gap-1 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors"
                            :title="tokenCopied ? 'Copied!' : 'Copy token'"
                        >
                            <Icon :name="tokenCopied ? 'check' : 'document-duplicate'" class="w-4 h-4" />
                            <span>{{ tokenCopied ? 'Copied!' : 'Copy' }}</span>
                        </button>
                    </div>
                    <input 
                        type="text" 
                        :value="newlyCreatedToken" 
                        readonly 
                        class="w-full px-4 py-3 bg-gray-900 text-green-400 font-mono text-sm rounded-lg border-0"
                    />
                </div>

                <!-- Existing Tokens -->
                <div>
                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">Your Tokens</h4>
                    <div v-if="apiTokensLoading" class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-500"></div>
                    </div>
                    <div v-else-if="apiTokens.length === 0" class="text-center py-8 text-gray-500 text-sm">
                        No tokens yet. Create one above to get started.
                    </div>
                    <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                        <div 
                            v-for="token in apiTokens" 
                            :key="token.id"
                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                        >
                            <div>
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ token.name }}</div>
                                <div class="text-xs text-gray-500">
                                    Created {{ formatTokenDate(token.created_at) }}
                                    <span v-if="token.last_used_at"> · Last used {{ formatTokenDate(token.last_used_at) }}</span>
                                    <span v-if="token.expires_at" class="text-yellow-600"> · Expires {{ formatTokenDate(token.expires_at) }}</span>
                                </div>
                            </div>
                            <button 
                                @click="revokeToken(token)"
                                class="text-red-500 hover:text-red-600 p-1"
                                title="Revoke token"
                            >
                                <Icon name="trash" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- API Endpoint Info -->
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">API Endpoint</h4>
                    <code class="block bg-gray-100 dark:bg-gray-900 px-3 py-2 rounded text-sm font-mono text-gray-800 dark:text-gray-200">
                        {{ apiBaseUrl }}/api/project-board/boards
                    </code>
                    <p class="text-xs text-gray-500 mt-2">
                        Add header: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">Authorization: Bearer YOUR_TOKEN</code>
                    </p>
                    <div class="mt-3 flex items-center gap-3">
                        <a 
                            :href="`${apiBaseUrl}/projects-board/openapi.json`" 
                            target="_blank"
                            class="text-xs text-primary-500 hover:text-primary-600 flex items-center gap-1"
                        >
                            <Icon name="document-text" class="w-3 h-3" />
                            OpenAPI Specification
                        </a>
                        <span class="text-gray-300">&nbsp;|&nbsp;</span>
                        <span class="text-xs text-gray-400">Use with ChatGPT Actions, Zapier, etc.</span>
                    </div>
                </div>
            </div>

            <ModalFooter>
                <div class="flex items-center ml-auto">
                    <Button variant="link" state="mellow" @click.prevent="showApiTokensModal = false">
                        Close
                    </Button>
                </div>
            </ModalFooter>
        </div>
    </Modal>

    <!-- Revoke Token Confirmation Modal -->
    <Teleport to="body">
        <template v-if="showRevokeTokenModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" style="z-index: 110;" @click="showRevokeTokenModal = false"></div>
            <div class="fixed inset-0 overflow-y-auto" style="z-index: 110;">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md" @click.stop>
                        <ModalHeader>Revoke Token</ModalHeader>
                        <div class="p-6">
                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                Are you sure you want to revoke the token <span class="font-semibold">"{{ tokenToRevoke?.name }}"</span>? 
                                Any integrations using this token will stop working.
                            </p>
                        </div>
                        <ModalFooter>
                            <div class="flex items-center ml-auto">
                                <Button variant="link" state="mellow" @click.prevent="showRevokeTokenModal = false" class="mr-3">Cancel</Button>
                                <Button state="danger" @click="confirmRevokeToken">Revoke</Button>
                            </div>
                        </ModalFooter>
                    </div>
                </div>
            </div>
        </template>
    </Teleport>

    <!-- Import Trello Modal -->
    <Modal :show="showImportTrelloModal" @close="closeImportTrelloModal" role="dialog" size="lg">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" style="width: 540px;">
            <ModalHeader>Import from Trello</ModalHeader>

            <div class="p-6 space-y-4">
                <!-- Instructions -->
                <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>How to export from Trello:</strong><br>
                        Open your board → Menu (⋮) → More → Print and export → Export as JSON
                    </p>
                </div>

                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Trello Export File (JSON)
                    </label>
                    <div
                        class="relative border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer"
                        :class="[
                            trelloImportDragging
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                        ]"
                        @dragover.prevent="trelloImportDragging = true"
                        @dragleave.prevent="trelloImportDragging = false"
                        @drop.prevent="handleTrelloDrop"
                        @click="$refs.trelloFileInput.click()"
                    >
                        <input
                            type="file"
                            ref="trelloFileInput"
                            accept=".json"
                            class="hidden"
                            @change="handleTrelloFileSelect"
                        />
                        <Icon name="arrow-up-tray" class="w-10 h-10 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-medium text-primary-600 dark:text-primary-400">Click to upload</span>
                            or drag and drop
                        </p>
                        <p class="mt-1 text-xs text-gray-500">JSON file up to 50MB</p>
                    </div>
                </div>

                <!-- Selected File Preview -->
                <div v-if="trelloImportFile" class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <Icon name="document-text" class="w-5 h-5 text-green-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ trelloImportFile.name }}</span>
                            <span class="text-xs text-gray-500">({{ formatFileSize(trelloImportFile.size) }})</span>
                        </div>
                        <button @click="clearTrelloFile" class="text-gray-400 hover:text-red-500">
                            <Icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                    <!-- Preview Stats -->
                    <div v-if="trelloImportStats" class="grid grid-cols-3 gap-2 text-xs">
                        <div class="bg-white dark:bg-gray-600 px-2 py-1.5 rounded">
                            <span class="text-gray-500 dark:text-gray-400">Board:</span>
                            <span class="ml-1 font-medium text-gray-700 dark:text-gray-200">{{ trelloImportStats.name }}</span>
                        </div>
                        <div class="bg-white dark:bg-gray-600 px-2 py-1.5 rounded">
                            <span class="text-gray-500 dark:text-gray-400">Lists:</span>
                            <span class="ml-1 font-medium text-gray-700 dark:text-gray-200">{{ trelloImportStats.lists }}</span>
                        </div>
                        <div class="bg-white dark:bg-gray-600 px-2 py-1.5 rounded">
                            <span class="text-gray-500 dark:text-gray-400">Cards:</span>
                            <span class="ml-1 font-medium text-gray-700 dark:text-gray-200">{{ trelloImportStats.cards }}</span>
                        </div>
                    </div>
                </div>

                <!-- Trello API Credentials (for attachments) -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Trello API Key <span class="text-gray-400 font-normal">(optional, for attachments)</span>
                        </label>
                        <input
                            v-model="trelloApiKey"
                            type="text"
                            placeholder="Your Trello API key from trello.com/app-key"
                            class="w-full form-control form-input form-control-bordered"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Trello API Token <span class="text-gray-400 font-normal">(optional, for attachments)</span>
                        </label>
                        <input
                            v-model="trelloApiToken"
                            type="text"
                            placeholder="Personal API token with read access to the board..."
                            class="w-full form-control form-input form-control-bordered"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Open <a href="https://trello.com/app-key" target="_blank" class="text-primary-500 hover:underline">trello.com/app-key</a>, copy your <strong>Key</strong>, then click &quot;Generate Token&quot; and copy the token here.
                        </p>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="trelloImportError" class="p-3 bg-red-50 dark:bg-red-900/30 rounded-lg border border-red-200 dark:border-red-800">
                    <p class="text-sm text-red-700 dark:text-red-300">{{ trelloImportError }}</p>
                </div>

                <!-- Success Message -->
                <div v-if="trelloImportSuccess" class="p-3 bg-green-50 dark:bg-green-900/30 rounded-lg border border-green-200 dark:border-green-800">
                    <p class="text-sm text-green-700 dark:text-green-300">{{ trelloImportSuccess }}</p>
                </div>
            </div>

            <ModalFooter>
                <div class="flex items-center ml-auto">
                    <Button
                        variant="link"
                        state="mellow"
                        @click.prevent="closeImportTrelloModal"
                        class="mr-3"
                        :disabled="trelloImporting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="startTrelloImport"
                        :disabled="!trelloImportFile || trelloImporting"
                        :loading="trelloImporting"
                    >
                        {{ trelloImporting ? 'Importing...' : 'Start Import' }}
                    </Button>
                </div>
            </ModalFooter>
        </div>
    </Modal>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import BoardColumn from '../components/BoardColumn.vue'
import CardDetailModal from '../components/CardDetailModal.vue'
import ArchivedItemsModal from '../components/ArchivedItemsModal.vue'
import BackgroundPicker from '../components/BackgroundPicker.vue'
import Button from '../components/UI/Button.vue'
import Icon from '../components/UI/Icon.vue'
import ModalHeader from '../components/UI/ModalHeader.vue'
import ModalFooter from '../components/UI/ModalFooter.vue'

export default {
  components: {
    draggable,
    BoardColumn,
    CardDetailModal,
    ArchivedItemsModal,
    BackgroundPicker,
    Icon,
    Button,
    ModalHeader,
    ModalFooter
  },

  props: {
    resourceName: String,
    resourceId: [String, Number],
    field: Object,
    initialUser: Object,
  },

  computed: {
    isResourceTool() {
        return !!this.resourceId;
    },
    containerClass() {
        return this.isResourceTool ? 'w-full h-[600px] flex flex-col' : 'w-full min-h-[800px] flex flex-col';
    },
    currentUser() {
        if (this.initialUser) return this.initialUser;
        if (this.field && this.field.currentUser) {
            return this.field.currentUser;
        }
        return Nova.config('user') || { 
            name: 'Guest User',
            avatar_url: null
        };
    },
    hasBoardTotals() {
        const t = this.currentBoard?.totals;
        if (!t) return false;
        return (t.estimated_hours > 0 || t.actual_hours > 0 || t.estimated_cost > 0 || t.actual_cost > 0);
    },
    boardHoursActualClass() {
        const t = this.currentBoard?.totals;
        if (!t) return 'font-bold';
        if (t.estimated_hours > 0 && t.actual_hours > t.estimated_hours) return 'font-bold text-red-500';
        return 'font-bold';
    },
    boardCostActualClass() {
        const t = this.currentBoard?.totals;
        if (!t) return 'font-bold';
        if (t.estimated_cost > 0 && t.actual_cost > t.estimated_cost) return 'font-bold text-red-500';
        return 'font-bold';
    },
    boardCardsWithEstimates() {
        if (!this.currentBoard?.columns) return [];
        const cards = [];
        for (const col of this.currentBoard.columns) {
            if (col.cards) {
                for (const card of col.cards) {
                    if (card.estimated_hours > 0 || card.estimated_cost > 0) {
                        cards.push(card);
                    }
                }
            }
        }
        return cards;
    },
    boardBackgroundStyle() {
        if (!this.currentBoard) return {};
        if (this.currentBoard.background_url) {
            return {
                backgroundImage: `url(${this.currentBoard.background_url})`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat',
            };
        }
        if (this.currentBoard.background_color) {
            return {
                backgroundColor: this.currentBoard.background_color,
            };
        }
        return {};
    },
    apiBaseUrl() {
        return window.location.origin;
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

  data() {
    return {
      loading: true,
      boards: [],
      currentBoard: null,
      isCreatingColumn: false,
      newColumnName: '',
      isRenamingBoardId: null,
      activeCard: null,
      showCardModal: false,
      showArchivedItems: false,
      users: [],
      availableLabels: [],
      
      // Search
      searchQuery: '',
      searchResults: [],
      isSearching: false,
      showSearchResults: false,
      searchDebounce: null,
      basePath: null,
      
      // Background picker
      showBackgroundPicker: false,
      bgSearchQuery: '',
      bgPhotos: [],
      bgLoading: false,
      bgColors: [
          '#0079bf', '#d29034', '#519839', '#b04632', '#89609e',
          '#cd5a91', '#4bbf6b', '#00aecc', '#838c91'
      ],
      
      // Fullscreen
      isFullscreen: false,

      // Members modal
      showMembersModal: false,
      boardMembers: [],
      membersLoading: false,
      memberSearchQuery: '',
      userSearchResults: [],
      selectedUserToAdd: null,
      newMemberRole: 'member',
      memberSearchDebounce: null,
      showDeleteBoardModal: false,
      showDeleteColumnModal: false,
      columnIdToDelete: null,
      showRemoveMemberModal: false,
      memberIdToRemove: null,

      // API Tokens
      showApiTokensModal: false,
      apiTokens: [],
      apiTokensLoading: false,
      newTokenName: '',
      creatingToken: false,
      newlyCreatedToken: null,
      tokenCopied: false,
      showRevokeTokenModal: false,
      tokenToRevoke: null,

      // Trello Import
      showImportTrelloModal: false,
      trelloImportFile: null,
      trelloImportStats: null,
      trelloImportDragging: false,
      trelloImporting: false,
      trelloImportError: null,
      trelloImportSuccess: null,
      trelloApiKey: '',
      trelloApiToken: '',
    }
  },

  mounted() {
    // Determine base path up to /projects-board so we can safely append segments
    const path = window.location.pathname;
    const parts = path.split('/projects-board');
    this.basePath = parts[0] + '/projects-board';

    this.fetchBoards().then(() => {
        this.checkUrlForCardOrPath();
    });
    
    window.addEventListener('popstate', this.handlePopState);
  },

  unmounted() {
    window.removeEventListener('popstate', this.handlePopState);
    document.body.style.overflow = '';
  },

  watch: {
    showBackgroundPicker(val) {
        if (val && this.bgPhotos.length === 0) {
            this.loadBgFeaturedPhotos();
        }
    }
  },

  methods: {
    formatHours(val) {
        const num = parseFloat(val) || 0;
        return num.toFixed(1) + 'h';
    },
    formatCost(val) {
        const num = parseFloat(val) || 0;
        return num.toFixed(0);
    },
    formatNum(val) {
        const num = parseFloat(val) || 0;
        return num % 1 === 0 ? num.toFixed(0) : num.toFixed(1);
    },
    getCardHoursClass(card) {
        const actual = parseFloat(card.actual_hours) || 0;
        const estimated = parseFloat(card.estimated_hours) || 0;
        if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
        return 'font-bold';
    },
    getCardCostClass(card) {
        const actual = parseFloat(card.actual_cost) || 0;
        const estimated = parseFloat(card.estimated_cost) || 0;
        if (estimated > 0 && actual > estimated) return 'font-bold text-red-500';
        return 'font-bold';
    },
    copyBreakdownToClipboard() {
        const cards = this.boardCardsWithEstimates;
        if (!cards.length) {
            Nova.warning('No estimates to copy');
            return;
        }
        // Build tab-separated text for Excel
        let text = 'Card\tActual Hours\tEstimated Hours\tActual Cost\tEstimated Cost\n';
        for (const card of cards) {
            const actH = this.formatNum(card.actual_hours);
            const estH = this.formatNum(card.estimated_hours);
            const actC = this.formatNum(card.actual_cost);
            const estC = this.formatNum(card.estimated_cost);
            text += `${card.title}\t${actH}\t${estH}\t${actC}\t${estC}\n`;
        }
        // Add totals row
        const t = this.currentBoard.totals;
        text += `TOTAL\t${this.formatNum(t.actual_hours)}\t${this.formatNum(t.estimated_hours)}\t${this.formatNum(t.actual_cost)}\t${this.formatNum(t.estimated_cost)}\n`;
        
        navigator.clipboard.writeText(text).then(() => {
            Nova.success('Breakdown copied to clipboard');
        }).catch(() => {
            Nova.error('Failed to copy');
        });
    },
    downloadBreakdownCSV() {
        const cards = this.boardCardsWithEstimates;
        if (!cards.length) {
            Nova.warning('No estimates to download');
            return;
        }
        // Build CSV
        let csv = 'Card,Actual Hours,Estimated Hours,Actual Cost,Estimated Cost\n';
        for (const card of cards) {
            const title = card.title.replace(/"/g, '""'); // Escape quotes
            const actH = this.formatNum(card.actual_hours);
            const estH = this.formatNum(card.estimated_hours);
            const actC = this.formatNum(card.actual_cost);
            const estC = this.formatNum(card.estimated_cost);
            csv += `"${title}",${actH},${estH},${actC},${estC}\n`;
        }
        // Add totals row
        const t = this.currentBoard.totals;
        csv += `"TOTAL",${this.formatNum(t.actual_hours)},${this.formatNum(t.estimated_hours)},${this.formatNum(t.actual_cost)},${this.formatNum(t.estimated_cost)}\n`;
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${this.currentBoard.name || 'board'}-breakdown.csv`;
        link.click();
        URL.revokeObjectURL(url);
        Nova.success('CSV downloaded');
    },
    onBackgroundUpdate(data) {
        if (this.currentBoard) {
            this.currentBoard.background_url = data.background_url;
            this.currentBoard.background_color = data.background_color;
        }
    },
    async loadBgFeaturedPhotos() {
        this.bgLoading = true;
        try {
            const response = await Nova.request().get('/nova-vendor/project-board/unsplash/featured', {
                params: { per_page: 16 }
            });
            this.bgPhotos = response.data.photos;
        } catch (e) {
            console.error('Failed to load photos', e);
        } finally {
            this.bgLoading = false;
        }
    },
    async searchBgPhotos() {
        if (!this.bgSearchQuery.trim()) {
            this.loadBgFeaturedPhotos();
            return;
        }
        this.bgLoading = true;
        try {
            const response = await Nova.request().get('/nova-vendor/project-board/unsplash/search', {
                params: { query: this.bgSearchQuery, per_page: 16 }
            });
            this.bgPhotos = response.data.photos;
        } catch (e) {
            console.error('Failed to search photos', e);
        } finally {
            this.bgLoading = false;
        }
    },
    async selectBgPhoto(photo) {
        try {
            await Nova.request().post('/nova-vendor/project-board/unsplash/track-download', {
                download_location: photo.download_location
            });
            await Nova.request().put(`/nova-vendor/project-board/boards/${this.currentBoard.id}`, {
                background_url: photo.regular,
                background_color: photo.color
            });
            this.currentBoard.background_url = photo.regular;
            this.currentBoard.background_color = photo.color;
            this.showBackgroundPicker = false;
            Nova.success('Background updated');
        } catch (e) {
            Nova.error('Failed to update background');
        }
    },
    async selectBgColor(color) {
        try {
            await Nova.request().put(`/nova-vendor/project-board/boards/${this.currentBoard.id}`, {
                background_url: null,
                background_color: color
            });
            this.currentBoard.background_url = null;
            this.currentBoard.background_color = color;
            this.showBackgroundPicker = false;
            Nova.success('Background updated');
        } catch (e) {
            Nova.error('Failed to update background');
        }
    },
    async clearBgBackground() {
        try {
            await Nova.request().put(`/nova-vendor/project-board/boards/${this.currentBoard.id}`, {
                background_url: null,
                background_color: null
            });
            this.currentBoard.background_url = null;
            this.currentBoard.background_color = null;
            this.showBackgroundPicker = false;
            Nova.success('Background removed');
        } catch (e) {
            Nova.error('Failed to remove background');
        }
    },
    toggleFullscreen() {
        this.isFullscreen = !this.isFullscreen;
        // Toggle body overflow to prevent scrolling when expanded
        document.body.style.overflow = this.isFullscreen ? 'hidden' : '';
    },

    // Members management
    async openMembersModal() {
        this.showMembersModal = true;
        this.membersLoading = true;
        this.memberSearchQuery = '';
        this.userSearchResults = [];
        this.selectedUserToAdd = null;
        this.newMemberRole = 'member';
        
        try {
            const response = await Nova.request().get(`/nova-vendor/project-board/boards/${this.currentBoard.id}/members`);
            this.boardMembers = response.data;
        } catch (e) {
            Nova.error('Failed to load members');
            this.boardMembers = [];
        } finally {
            this.membersLoading = false;
        }
    },

    searchUsers() {
        clearTimeout(this.memberSearchDebounce);
        this.memberSearchDebounce = setTimeout(async () => {
            if (!this.memberSearchQuery || this.memberSearchQuery.length < 2) {
                this.userSearchResults = [];
                return;
            }
            try {
                const response = await Nova.request().get('/nova-vendor/project-board/users/search', {
                    params: { q: this.memberSearchQuery }
                });
                // Filter out users already in the board
                const memberIds = this.boardMembers.map(m => m.id);
                this.userSearchResults = response.data.filter(u => !memberIds.includes(u.id));
            } catch (e) {
                this.userSearchResults = [];
            }
        }, 300);
    },

    selectUserToAdd(user) {
        this.selectedUserToAdd = user;
        this.userSearchResults = [];
        this.memberSearchQuery = '';
    },

    async addMember() {
        if (!this.selectedUserToAdd) return;
        
        try {
            await Nova.request().post(`/nova-vendor/project-board/boards/${this.currentBoard.id}/members`, {
                user_id: this.selectedUserToAdd.id,
                role: this.newMemberRole
            });
            
            const newMember = {
                ...this.selectedUserToAdd,
                role: this.newMemberRole,
                is_global_admin: false,
                pivot: { role: this.newMemberRole }
            };
            
            // Add to local modal list
            this.boardMembers.push(newMember);
            
            // Add to board.users for header avatars (reactive)
            if (this.currentBoard.users) {
                this.currentBoard.users.push(newMember);
            } else {
                this.currentBoard.users = [newMember];
            }
            
            this.selectedUserToAdd = null;
            this.newMemberRole = 'member';
            Nova.success('Member added');
        } catch (e) {
            Nova.error(e.response?.data?.error || 'Failed to add member');
        }
    },

    async updateMemberRole(userId, newRole) {
        try {
            await Nova.request().put(`/nova-vendor/project-board/boards/${this.currentBoard.id}/members/${userId}`, {
                role: newRole
            });
            
            // Update local list
            const member = this.boardMembers.find(m => m.id === userId);
            if (member) member.role = newRole;
            
            Nova.success('Role updated');
        } catch (e) {
            Nova.error('Failed to update role');
        }
    },

    removeMember(userId) {
        this.memberIdToRemove = userId;
        this.showRemoveMemberModal = true;
    },

    async confirmRemoveMember() {
        if (!this.currentBoard || !this.memberIdToRemove) {
            this.showRemoveMemberModal = false;
            this.memberIdToRemove = null;
            return;
        }

        try {
            await Nova.request().delete(`/nova-vendor/project-board/boards/${this.currentBoard.id}/members/${this.memberIdToRemove}`);
            
            // Remove from local modal list
            this.boardMembers = this.boardMembers.filter(m => m.id !== this.memberIdToRemove);
            
            // Remove from board.users for header avatars (reactive)
            if (this.currentBoard.users) {
                this.currentBoard.users = this.currentBoard.users.filter(m => m.id !== this.memberIdToRemove);
            }
            
            Nova.success('Member removed');
        } catch (e) {
            Nova.error('Failed to remove member');
        }
    },

    encodeId(id) {
        const n = Number(id);
        if (!Number.isInteger(n) || n < 0) return String(id);
        const alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (n === 0) return '0';
        let out = '';
        let value = n;
        while (value > 0) {
            out = alphabet[value % 62] + out;
            value = Math.floor(value / 62);
        }
        return out;
    },

    decodeId(hash) {
        const alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (!hash || typeof hash !== 'string') return null;
        let value = 0;
        for (let i = 0; i < hash.length; i++) {
            const idx = alphabet.indexOf(hash[i]);
            if (idx === -1) return null;
            value = value * 62 + idx;
        }
        return value;
    },

    buildCardSlug(id) {
        const hash = this.encodeId(id);
        const suffix = hash.slice(-2) || hash; // short, Trello-like
        return `${id}-${suffix}`;
    },

    parseCardIdFromPath() {
        const path = window.location.pathname;
        const parts = path.split('/projects-board');
        if (parts.length < 2) return null;
        const tail = parts[1].split('/').filter(Boolean);
        if (tail.length < 2) return null; // no card segment
        const cardSlug = tail[1];
        const numeric = parseInt(cardSlug.split('-')[0], 10);
        return Number.isNaN(numeric) ? null : numeric;
    },

    async checkUrlForCardOrPath() {
        const urlParams = new URLSearchParams(window.location.search);
        let cardId = urlParams.get('card');

        if (!cardId) {
            const fromPath = this.parseCardIdFromPath();
            if (fromPath) {
                cardId = fromPath;
            }
        }

        if (!cardId) {
            // No card specified, but maybe a board segment exists
            this.syncBoardFromPath();
            return;
        }

        try {
            const { data } = await Nova.request().get(`/nova-vendor/project-board/cards/${cardId}`);
            if (data && data.column && data.column.board) {
                // Switch to the correct board if not active
                if (!this.currentBoard || this.currentBoard.id != data.column.board.id) {
                    const foundBoard = this.boards.find(b => b.id == data.column.board.id);
                    if (foundBoard) {
                        this.currentBoard = foundBoard;
                    }
                }

                // Open the card
                this.activeCard = data;
                this.showCardModal = true;
                // Pre-fetch related data
                this.fetchUsers();
                this.fetchLabels();
            }
        } catch (e) {
            console.error('Failed to load card from URL', e);
        }
    },

    syncBoardFromPath() {
        if (!this.boards.length) return;

        const path = window.location.pathname;
        const parts = path.split('/projects-board');
        if (parts.length < 2) {
            // Fall back to first board
            if (!this.currentBoard) this.currentBoard = this.boards[0];
            return;
        }

        const tail = parts[1].split('/').filter(Boolean);
        if (!tail.length) {
            if (!this.currentBoard) this.currentBoard = this.boards[0];
            return;
        }

        // tail[0] should be the encoded board id
        const boardHash = tail[0];
        const decodedId = this.decodeId(boardHash);
        const found = this.boards.find(b => decodedId && b.id == decodedId);
        if (found) {
            this.currentBoard = found;
        } else if (!this.currentBoard) {
            this.currentBoard = this.boards[0];
        }
    },
    
    handlePopState() {
        const urlParams = new URLSearchParams(window.location.search);
        const queryCard = urlParams.get('card');
        const pathCard = this.parseCardIdFromPath();
        const hasCard = queryCard || pathCard;

        if (!hasCard && this.showCardModal) {
            this.showCardModal = false;
            this.activeCard = null;
        } else if (hasCard && !this.showCardModal) {
            this.checkUrlForCardOrPath();
        }
    },

    async fetchUsers() {
        try {
            const { data } = await Nova.request().get('/nova-vendor/project-board/users');
            this.users = data;
        } catch (e) {
            console.error('Failed to fetch users');
        }
    },

    async fetchLabels() {
        try {
            const { data } = await Nova.request().get('/nova-vendor/project-board/labels');
            this.availableLabels = data;
        } catch (e) {
            console.error('Failed to fetch labels');
        }
    },

    openCard(card, column) {
        // Ensure column is attached if passed
        if (column && !card.column) {
             card = { ...card, column: column };
        }
        this.activeCard = card;
        this.showCardModal = true;
        
        // Update URL to /projects-board/<boardHash>/<cardSlug>
        const url = new URL(window.location);
        const boardId = (this.currentBoard && this.currentBoard.id) || (card.column && card.column.board && card.column.board.id);
        if (boardId && this.basePath) {
            const boardHash = this.encodeId(boardId);
            const cardSlug = this.buildCardSlug(card.id);
            url.pathname = `${this.basePath}/${boardHash}/${cardSlug}`;
        }
        url.searchParams.delete('card');
        window.history.pushState({}, '', url);
        
        this.fetchUsers();
        this.fetchLabels();
    },

    closeCardModal() {
        this.showCardModal = false;
        this.activeCard = null;
        
        // Revert URL back to board-only path
        const url = new URL(window.location);
        url.searchParams.delete('card');
        if (this.currentBoard && this.basePath) {
            const boardHash = this.encodeId(this.currentBoard.id);
            url.pathname = `${this.basePath}/${boardHash}`;
        } else if (this.basePath) {
            url.pathname = this.basePath;
        }
        window.history.pushState({}, '', url);
    },

    onCardUpdated() {
        this.fetchBoards(); // Refresh everything to reflect changes
        this.fetchLabels(); // Refresh labels in case they were edited
        this.fetchUsers(); // Refresh users just in case
    },

    // ... rest of existing methods ...
    startRenamingBoard(board) {
        this.isRenamingBoardId = board.id;
        this.$nextTick(() => {
            if(this.$refs.boardRenameInput && this.$refs.boardRenameInput[0]) {
                this.$refs.boardRenameInput[0].focus();
            }
        });
    },

    async finishRenamingBoard(board) {
        if (this.isRenamingBoardId !== board.id) return;
        this.isRenamingBoardId = null;
        if (!board.name.trim()) return;
        
        try {
            await Nova.request().put(`/nova-vendor/project-board/boards/${board.id}`, { name: board.name });
            Nova.success('Board renamed');
        } catch (error) {
            Nova.error('Failed to rename board');
            await this.fetchBoards(); // Revert on error
        }
    },

    async fetchBoards() {
      console.log('[ProjectBoard] Fetching boards...', {
        isResourceTool: this.isResourceTool,
        resourceName: this.resourceName,
        resourceId: this.resourceId
      });
      try {
        const params = {};
        if (this.isResourceTool) {
            params.resourceName = this.resourceName;
            params.resourceId = this.resourceId;
        }
        const { data } = await Nova.request().get('/nova-vendor/project-board/boards', { params })
        console.log('[ProjectBoard] Fetched boards:', data);
        this.boards = data
        if (this.boards.length > 0 && !this.currentBoard) {
          this.currentBoard = this.boards[0]
        } else if (this.currentBoard) {
           // Refresh current board data
           const found = this.boards.find(b => b.id == this.currentBoard.id);
           this.currentBoard = found || this.boards[0]
        }
      } catch (error) {
        console.error('[ProjectBoard] Error fetching boards:', error);
        Nova.error('Failed to load boards: ' + (error.response?.data?.message || error.message))
      } finally {
        this.loading = false
      }
    },

    selectBoard(board) {
      this.currentBoard = board
      // Update URL to board-only path
      if (this.basePath) {
        const url = new URL(window.location);
        const boardHash = this.encodeId(board.id);
        url.pathname = `${this.basePath}/${boardHash}`;
        url.searchParams.delete('card');
        window.history.pushState({}, '', url);
      }
    },

    handleTabClick(board) {
        if (this.currentBoard?.id === board.id) {
            // If clicking active tab, do nothing or enable edit?
            // User requested rename on pressing tab. 
            // But double click handles it. 
            // Maybe they want single click on active to rename?
            // Let's stick to select on single click.
        }
        this.selectBoard(board);
    },

    async createBoard() {
        // Simple prompt for now, later a modal
        const name = prompt('Enter board name:');
        if (!name) return;
        
        try {
            const payload = { name };
            if (this.isResourceTool) {
                payload.resourceName = this.resourceName;
                payload.resourceId = this.resourceId;
            }
            const { data } = await Nova.request().post('/nova-vendor/project-board/boards', payload);
            this.boards.push(data);
            this.currentBoard = data;
            Nova.success('Board created');
        } catch (error) {
            Nova.error('Failed to create board');
        }
    },
    
    deleteBoard() {
        if (!this.currentBoard) return;
        this.showDeleteBoardModal = true;
    },

    async confirmDeleteBoard() {
        if (!this.currentBoard) {
            this.showDeleteBoardModal = false;
            return;
        }

        try {
            await Nova.request().delete(`/nova-vendor/project-board/boards/${this.currentBoard.id}`);
            this.boards = this.boards.filter(b => b.id !== this.currentBoard.id);
            this.currentBoard = this.boards.length > 0 ? this.boards[0] : null;
            Nova.success('Board deleted');
        } catch (error) {
            Nova.error('Failed to delete board');
        } finally {
            this.showDeleteBoardModal = false;
        }
    },
    
    async renameBoard() {
        this.startRenamingBoard();
    },

    async createColumn() {
      if (!this.newColumnName.trim()) return;
      
      try {
        await Nova.request().post(`/nova-vendor/project-board/boards/${this.currentBoard.id}/columns`, {
          name: this.newColumnName
        })
        this.newColumnName = ''
        this.isCreatingColumn = false
        await this.fetchBoards()
        Nova.success('Column created')
      } catch (error) {
        Nova.error('Failed to create column')
      }
    },
    
    async onColumnReorder() {
       // Implement persistence if needed, currently UI optimistic update
       // In a real implementation, you'd loop through columns and send their new indices to the API
    },
    
    onCardMoved(evt) {
      // evt contains info about drag event from child
      // But we need to refresh to ensure state consistency
      this.fetchBoards();
    },
    
    async onPaste(event, column) {
        const items = (event.clipboardData || event.originalEvent.clipboardData).items;
        for (let index in items) {
            const item = items[index];
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                const blob = item.getAsFile();
                
                // Create a new card with this image
                const formData = new FormData();
                formData.append('title', 'Pasted Image ' + new Date().toLocaleString());
                formData.append('image', blob);
                
                try {
                    Nova.success('Uploading pasted image...');
                    await Nova.request().post(`/nova-vendor/project-board/columns/${column.id}/cards-with-image`, formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });
                    this.fetchBoards();
                    Nova.success('Card created from image');
                } catch (e) {
                    Nova.error('Failed to upload image');
                }
            }
        }
    },

    deleteColumn(columnId) {
       this.columnIdToDelete = columnId;
       this.showDeleteColumnModal = true;
    },

    async confirmDeleteColumn() {
       if (!this.columnIdToDelete) {
         this.showDeleteColumnModal = false;
         return;
       }

       try {
         await Nova.request().delete(`/nova-vendor/project-board/columns/${this.columnIdToDelete}`)
         await this.fetchBoards()
         Nova.success('Column deleted')
       } catch(e) {
         Nova.error('Failed to delete column')
       } finally {
         this.columnIdToDelete = null;
         this.showDeleteColumnModal = false;
       }
    },

    // Search Methods
    onSearchInput() {
        if (this.searchDebounce) clearTimeout(this.searchDebounce);
        this.searchDebounce = setTimeout(() => {
            this.performSearch();
        }, 300);
    },
    async performSearch() {
        if (this.searchQuery.length < 2) {
            this.searchResults = [];
            this.isSearching = false;
            return;
        }
        
        this.isSearching = true;
        try {
            const { data } = await Nova.request().get('/nova-vendor/project-board/search', {
                params: { query: this.searchQuery }
            });
            this.searchResults = data;
        } catch (e) {
            console.error('Search failed', e);
        } finally {
            this.isSearching = false;
        }
    },
    closeSearch() {
        this.showSearchResults = false;
    },
    openSearchResult(result) {
        this.closeSearch();
        // Update URL and trigger card handler which fetches and opens the card
        const url = new URL(window.location);
        url.searchParams.set('card', result.card_id);
        window.history.pushState({}, '', url);
        this.checkUrlForCardOrPath();
    },

    // API Tokens Methods
    async openApiTokensModal() {
        this.showApiTokensModal = true;
        this.newlyCreatedToken = null;
        this.tokenCopied = false;
        await this.fetchApiTokens();
    },

    async fetchApiTokens() {
        this.apiTokensLoading = true;
        try {
            const { data } = await Nova.request().get('/nova-vendor/project-board/api-tokens');
            this.apiTokens = data.tokens || [];
        } catch (e) {
            console.error('Failed to fetch API tokens', e);
            this.apiTokens = [];
        } finally {
            this.apiTokensLoading = false;
        }
    },

    async createApiToken() {
        if (!this.newTokenName.trim()) return;
        
        this.creatingToken = true;
        try {
            const { data } = await Nova.request().post('/nova-vendor/project-board/api-tokens', {
                name: this.newTokenName.trim()
            });
            this.newlyCreatedToken = data.plain_token;
            this.newTokenName = '';
            this.tokenCopied = false;
            await this.fetchApiTokens();
            Nova.success('API token created');
        } catch (e) {
            Nova.error('Failed to create token');
        } finally {
            this.creatingToken = false;
        }
    },

    copyToken(token) {
        navigator.clipboard.writeText(token).then(() => {
            this.tokenCopied = true;
            setTimeout(() => {
                this.tokenCopied = false;
            }, 2000);
        });
    },

    revokeToken(token) {
        this.tokenToRevoke = token;
        this.showRevokeTokenModal = true;
    },

    async confirmRevokeToken() {
        if (!this.tokenToRevoke) return;
        
        try {
            await Nova.request().delete(`/nova-vendor/project-board/api-tokens/${this.tokenToRevoke.id}`);
            await this.fetchApiTokens();
            Nova.success('Token revoked');
        } catch (e) {
            Nova.error('Failed to revoke token');
        } finally {
            this.tokenToRevoke = null;
            this.showRevokeTokenModal = false;
        }
    },

    formatTokenDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        
        if (diffDays === 0) return 'today';
        if (diffDays === 1) return 'yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
        return date.toLocaleDateString();
    },

    // Trello Import Methods
    closeImportTrelloModal() {
        if (this.trelloImporting) return;
        this.showImportTrelloModal = false;
        this.trelloImportFile = null;
        this.trelloImportStats = null;
        this.trelloImportError = null;
        this.trelloImportSuccess = null;
        this.trelloApiKey = '';
        this.trelloApiToken = '';
    },

    handleTrelloFileSelect(event) {
        const file = event.target.files[0];
        if (file) this.processTrelloFile(file);
    },

    handleTrelloDrop(event) {
        this.trelloImportDragging = false;
        const file = event.dataTransfer.files[0];
        if (file) this.processTrelloFile(file);
    },

    processTrelloFile(file) {
        this.trelloImportError = null;
        
        if (!file.name.endsWith('.json')) {
            this.trelloImportError = 'Please select a JSON file';
            return;
        }
        
        if (file.size > 50 * 1024 * 1024) {
            this.trelloImportError = 'File size exceeds 50MB limit';
            return;
        }
        
        this.trelloImportFile = file;
        
        // Parse preview
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = JSON.parse(e.target.result);
                
                if (!data.name || !data.lists || !data.cards) {
                    this.trelloImportError = 'Invalid Trello export file. Missing required fields.';
                    this.trelloImportFile = null;
                    return;
                }
                
                this.trelloImportStats = {
                    name: data.name || 'Unknown',
                    lists: (data.lists || []).length,
                    cards: (data.cards || []).length,
                };
            } catch (err) {
                this.trelloImportError = 'Failed to parse JSON: ' + err.message;
                this.trelloImportFile = null;
            }
        };
        reader.readAsText(file);
    },

    clearTrelloFile() {
        this.trelloImportFile = null;
        this.trelloImportStats = null;
        if (this.$refs.trelloFileInput) {
            this.$refs.trelloFileInput.value = '';
        }
    },

    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    async startTrelloImport() {
        if (!this.trelloImportFile) return;
        
        this.trelloImporting = true;
        this.trelloImportError = null;
        this.trelloImportSuccess = null;
        
        const formData = new FormData();
        formData.append('file', this.trelloImportFile);
        
        // Pass resource context if available
        if (this.resourceName && this.resourceId) {
            const modelMap = {
                'companies': 'App\\Models\\Company',
                'projects': 'App\\Models\\Project',
                'locations': 'App\\Models\\Location',
            };
            if (modelMap[this.resourceName]) {
                formData.append('boardable_type', modelMap[this.resourceName]);
                formData.append('boardable_id', this.resourceId);
            }
        }
        
        // Pass Trello API credentials if provided (both required for Trello-hosted attachments)
        if (this.trelloApiKey && this.trelloApiToken) {
            formData.append('trello_api_key', this.trelloApiKey);
            formData.append('trello_api_token', this.trelloApiToken);
        }
        
        try {
            const response = await Nova.request().post('/nova-vendor/project-board/import-trello', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            this.trelloImportSuccess = `Import started! "${response.data.stats.board_name}" with ${response.data.stats.lists_count} lists and ${response.data.stats.cards_count} cards is being imported.`;
            Nova.success(`Import started: ${response.data.stats.board_name}`);
            
            // Refresh boards after a delay
            setTimeout(() => {
                this.closeImportTrelloModal();
                this.fetchBoards();
            }, 2000);
            
        } catch (err) {
            this.trelloImportError = err.response?.data?.error || err.message || 'Import failed';
        } finally {
            this.trelloImporting = false;
        }
    }
  }
}
</script>

<style scoped>
/* Custom scrollbar for horizontal scrolling */
::-webkit-scrollbar {
  height: 12px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 20px;
  border: 3px solid transparent;
  background-clip: content-box;
}

/* Dark mode hover for inactive board tabs - primary color */
.dark .board-tab-inactive:hover {
  color: var(--colors-primary-500) !important;
}

/* Board totals tooltip hover - shows on hover of the numbers only */
.board-totals-tooltip {
  display: none;
  pointer-events: none;
}
.board-totals-wrapper:hover .board-totals-tooltip {
  display: block;
  pointer-events: auto;
}

/* Expanded board - fills entire viewport */
.board-expanded {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  z-index: 50 !important;
  background: white;
  overflow: auto;
}
.dark .board-expanded {
  background: #1f2937;
}
</style>
