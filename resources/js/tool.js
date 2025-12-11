import Tool from './pages/Tool'
import ResourceToolWrapper from './components/ResourceToolWrapper'

// Global tool Inertia page
Nova.inertia('ProjectsBoard', Tool)

// Resource tool component - matches Nova 5 pattern exactly
Nova.booting((app, store) => {
  app.component('projects-board-resource-tool', ResourceToolWrapper)
})
