import Tool from './pages/Tool'

// Global tool Inertia page
Nova.inertia('ProjectsBoard', Tool)

// Resource tool component - matches Nova 5 pattern exactly
Nova.booting((app, store) => {
  app.component('projects-board-resource-tool', Tool)
})
